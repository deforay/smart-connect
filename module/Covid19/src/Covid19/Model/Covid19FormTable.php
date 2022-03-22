<?php

namespace Covid19\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Expression;
use \Application\Service\CommonService;
use Zend\Debug\Debug;

/**
 * Description of Countries
 *
 * @author amit
 * Description of Countries
 */
class Covid19FormTable extends AbstractTableGateway
{

    protected $table = 'dash_form_covid19';
    public $sm = null;
    public $config = null;
    protected $translator = null;
    protected $commonService = null;
    protected $mappedFacilities = null;

    public function __construct(Adapter $adapter, $sm = null, $mappedFacilities = null, $table = null, $commonService = null)
    {
        $this->adapter = $adapter;
        $this->sm = $sm;
        if ($table != null && !empty($table)) {
            $this->table = $table;
        }
        $this->config = $this->sm->get('Config');
        $this->translator = $this->sm->get('translator');
        $this->mappedFacilities = $mappedFacilities;
        $this->commonService = $commonService;
    }

    public function getSummaryTabDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        $queryStr = $sql->select()->from(array('covid19' => $this->table))
            ->columns(array(
                "total_samples_received" => new Expression("COUNT(*)"),
                "total_samples_tested" => new Expression("(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END))"),
                "positive_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                "rejected_samples" => new Expression("SUM(CASE WHEN (covid19.reason_for_sample_rejection !='' AND covid19.reason_for_sample_rejection !='0' AND covid19.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                "tat" => new Expression("AVG((DATEDIFF(result_printed_datetime,sample_collection_date)))")
            ));

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $queryStr = $queryStr->where("(sample_collection_date is not null AND sample_collection_date not like '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $queryStr = $sql->buildSqlString($queryStr);

        // echo $queryStr;die;

        return $this->commonService->cacheQuery($queryStr, $dbAdapter);
    }

    public function fetchSamplesReceivedBarChartDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();

        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                    "total" => new Expression("COUNT(*)"),
                )
            )
            ->join(array('rs' => 'r_covid19_sample_type'), 'rs.sample_id=covid19.specimen_type', array('sample_name'), 'left')
            ->group(array(new Expression('YEAR(covid19.sample_collection_date)'), new Expression('MONTH(covid19.sample_collection_date)')));

        if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
            $sQuery = $sQuery->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'));
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $sQuery = $sQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
        }

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $sQuery = $sQuery->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }


        $sQuery = $sQuery->order(array(new Expression('DATE(sample_collection_date)')));
        $queryStr = $sql->buildSqlString($sQuery);
        // echo $queryStr;die;
        //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        $j = 0;
        foreach ($sampleResult as $row) {

            $result['sampleName']['total'][$j] = $row['total'];
            $result['date'][$j] = $row['monthDate'];
            $j++;
        }

        return $result;
    }

    public function fetchAllSamplesReceivedByFacility($parameters)
    {



        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('facility_name', 'f_d_l_dp.location_name', 'f_d_l_d.location_name');
        $orderColumns = array('facility_name', 'f_d_l_dp.location_name', 'f_d_l_d.location_name', 'total_samples_received', 'total_samples_tested', 'total_samples_pending', 'total_samples_rejected', 'initial_pcr_percentage', 'second_third_pcr_percentage');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {

                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {

                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN ((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL') OR (covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection != '' AND covid19.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_pending" => new Expression("(SUM(CASE WHEN ((covid19.result IS NULL OR covid19.result = '' OR covid19.result = 'NULL') AND (covid19.reason_for_sample_rejection IS NULL OR covid19.reason_for_sample_rejection = '' OR covid19.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('covid19.facility_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(sample_collection_date is not null)
                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        // echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(array('covid19_id'))
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array())
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array())
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array())
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('covid19.facility_id');


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }

        $iQueryStr = $sql->buildSqlString($iQuery);



        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = "<span style='white-space:nowrap !important;' >" . ucwords($aRow['facility_name']) . "</span>";
            $row[] = ucwords($aRow['province']);
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['total_samples_rejected'];

            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchAllSamplesReceivedByProvince($parameters)
    {


        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.location_name');
        $orderColumns = array('f_d_l_d.location_name', 'total_samples_received', 'total_samples_tested', 'total_samples_pending', 'total_samples_rejected', 'initial_pcr_percentage', 'second_third_pcr_percentage');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {

                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {

                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN ((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL') OR (covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection != '' AND covid19.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_pending" => new Expression("(SUM(CASE WHEN ((covid19.result IS NULL OR covid19.result = '' OR covid19.result = 'NULL') AND (covid19.reason_for_sample_rejection IS NULL OR covid19.reason_for_sample_rejection = '' OR covid19.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("SUM(CASE WHEN (covid19.reason_for_sample_rejection !='' AND covid19.reason_for_sample_rejection !='0' AND covid19.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state');
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        // echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date != '1970-01-01' AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state');

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['province'];
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['total_samples_rejected'];
            $output['aaData'][] = $row;
        }
        return $output;
    }

    /* Samples Received District*/
    public function fetchAllSamplesReceivedByDistrict($parameters)
    {


        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.location_name');
        $orderColumns = array('f_d_l_d.location_name', 'total_samples_received', 'total_samples_tested', 'total_samples_pending', 'total_samples_rejected', 'initial_pcr_percentage', 'second_third_pcr_percentage');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {

                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {

                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN ((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL') OR (covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection != '' AND covid19.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_pending" => new Expression("(SUM(CASE WHEN ((covid19.result IS NULL OR covid19.result = '' OR covid19.result = 'NULL') AND (covid19.reason_for_sample_rejection IS NULL OR covid19.reason_for_sample_rejection = '' OR covid19.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("SUM(CASE WHEN (covid19.reason_for_sample_rejection !='' AND covid19.reason_for_sample_rejection !='0' AND covid19.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))

            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))

            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['district'];
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['total_samples_rejected'];
            $output['aaData'][] = $row;
        }

        return $output;
    }

    public function fetchPositiveRateBarChartDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();

        $sQuery = $sql->select()
            ->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "monthyear" => new Expression("DATE_FORMAT(sample_collection_date, '%b %y')"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN (covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL') THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    "positive_rate" => new Expression("ROUND(((SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                )
            )

            ->group(array(new Expression('YEAR(sample_collection_date)'), new Expression('MONTH(sample_collection_date)')));

        if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
            $sQuery = $sQuery->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'));
        }

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }

        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $sQuery = $sQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $sQuery = $sQuery->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $sQuery = $sQuery->order(array(new Expression('DATE(sample_collection_date)')));
        $queryStr = $sql->buildSqlString($sQuery);
        // echo $queryStr;die;
        //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        $j = 0;
        foreach ($sampleResult as $row) {
            $result['valid_results'][$j]  = $valid = (!empty($row["total_samples_tested"])) ? $row["total_samples_tested"] - $row["total_samples_rejected"] : 0;
            $result['positive_rate'][$j] = ($row["total_positive_samples"] > 0 && $valid > 0) ? round((($row["total_positive_samples"] / $valid) * 100), 2) : null;
            $result['date'][$j] = $row['monthyear'];
            $j++;
        }
        return $result;
    }

    public function fetchAllPositiveRateByProvince($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.location_name');
        $orderColumns = array('f_d_l_d.location_name', 'total_samples_valid', 'total_positive_samples', 'total_negative_samples', 'total_samples_rejected', 'positive_rate');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {

                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {

                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result like 'Negative')) THEN 1 ELSE 0 END)"),
                    //"total_positive_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (covid19.result < 1000 or covid19.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                    "positive_rate" => new Expression("ROUND(((SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = ucwords($aRow['province']);
            $row[] = $aRow['total_samples_valid'];
            $row[] = $aRow['total_positive_samples'];
            $row[] = $aRow['total_negative_samples'];
            $row[] = ($aRow['total_samples_rejected'] > 0 && $aRow['total_samples_received'] > 0) ? round((($aRow['total_samples_rejected'] / $aRow['total_samples_received']) * 100), 2) . '%' : '';
            $row[] = ($aRow['total_samples_valid'] > 0 && $aRow['total_positive_samples'] > 0) ? round((($aRow['total_positive_samples'] / $aRow['total_samples_valid']) * 100), 2) . '%' : '';

            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchAllPositiveRateByDistrict($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.location_name');
        $orderColumns = array('f_d_l_d.location_name', 'total_samples_valid', 'total_positive_samples', 'total_negative_samples', 'total_samples_rejected', 'positive_rate');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {

                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {

                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result like 'Negative')) THEN 1 ELSE 0 END)"),
                    //"total_positive_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (covid19.result < 1000 or covid19.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                    "positive_rate" => new Expression("ROUND(((SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['total_samples_valid'];
            $row[] = $aRow['total_positive_samples'];
            $row[] = $aRow['total_negative_samples'];
            $row[] = ($aRow['total_samples_rejected'] > 0 && $aRow['total_samples_received'] > 0) ? round((($aRow['total_samples_rejected'] / $aRow['total_samples_received']) * 100), 2) . '%' : '';
            $row[] = ($aRow['total_samples_valid'] > 0 && $aRow['total_positive_samples'] > 0) ? round((($aRow['total_positive_samples'] / $aRow['total_samples_valid']) * 100), 2) . '%' : '';

            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchAllPositiveRateByFacility($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */

        $queryContainer = new Container('query');

        $aColumns = array('facility_name', 'f_d_l_dp.location_name', 'f_d_l_d.location_name');
        $orderColumns = array('f_d_l_d.location_name', 'f_d_l_dp.location_name', 'f_d_l_d.location_name', 'total_samples_valid', 'total_positive_samples', 'total_negative_samples', 'total_samples_rejected', 'positive_rate');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {

                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {

                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result like 'Negative')) THEN 1 ELSE 0 END)"),
                    //"total_positive_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (covid19.result < 1000 or covid19.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                    "positive_rate" => new Expression("ROUND(((SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('covid19.facility_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        $queryContainer->fetchAllPositiveRateByFacility = $sQuery;

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'covid19_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('covid19.facility_id');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = "<span style='white-space:nowrap !important;' >" . ucwords($aRow['facility_name']) . "</span>";
            $row[] = ucwords($aRow['province']);
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['total_samples_valid'];
            $row[] = $aRow['total_positive_samples'];
            $row[] = $aRow['total_negative_samples'];
            $row[] = ($aRow['total_samples_rejected'] > 0 && $aRow['total_samples_received'] > 0) ? round((($aRow['total_samples_rejected'] / $aRow['total_samples_received']) * 100), 2) . '%' : '';
            $row[] = ($aRow['total_samples_valid'] > 0 && $aRow['total_positive_samples'] > 0) ? round((($aRow['total_positive_samples'] / $aRow['total_samples_valid']) * 100), 2) . '%' : '';

            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchSamplesRejectedBarChartDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $mostRejectionReasons = array();
        $mostRejectionQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(array('rejections' => new Expression('COUNT(*)')))
            ->join(array('r_r_r' => 'r_vl_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=covid19.reason_for_sample_rejection', array('rejection_reason_id'))
            ->group('covid19.reason_for_sample_rejection')
            ->order('rejections DESC')
            ->limit(4);

        if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'));
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $mostRejectionQuery = $mostRejectionQuery->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $mostRejectionQueryStr = $sql->buildSqlString($mostRejectionQuery);
        $mostRejectionResult = $dbAdapter->query($mostRejectionQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if (isset($mostRejectionResult) && count($mostRejectionResult) > 0) {
            foreach ($mostRejectionResult as $rejectionReason) {
                $mostRejectionReasons[] = $rejectionReason['rejection_reason_id'];
            }
            $mostRejectionReasons[] = 0;
        }
        $result = array();
        $start = strtotime($params['fromDate']);
        $end = strtotime($params['toDate']);

        $j = 0;
        while ($start <= $end) {
            $month = date('m', $start);
            $year = date('Y', $start);
            $monthYearFormat = date("M-Y", $start);
            for ($m = 0; $m < count($mostRejectionReasons); $m++) {
                $rejectionQuery = $sql->select()->from(array('covid19' => $this->table))
                    ->columns(array('rejections' => new Expression('COUNT(*)')))
                    ->join(array('r_r_r' => 'r_vl_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=covid19.reason_for_sample_rejection', array('rejection_reason_name'))
                    ->where("MONTH(sample_collection_date)='" . $month . "' AND Year(sample_collection_date)='" . $year . "'");


                if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
                    $rejectionQuery = $rejectionQuery->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'));
                }
                if (isset($params['provinces']) && trim($params['provinces']) != '') {
                    $rejectionQuery = $rejectionQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
                }
                if (isset($params['districts']) && trim($params['districts']) != '') {
                    $rejectionQuery = $rejectionQuery->where('f.facility_district IN (' . $params['districts'] . ')');
                }
                if (isset($params['clinics']) && trim($params['clinics']) != '') {
                    $rejectionQuery = $rejectionQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
                }
                if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                    $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
                    $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
                    $rejectionQuery = $rejectionQuery->where("(sample_collection_date is not null)
                                                AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                                AND DATE(sample_collection_date) <= '" . $endMonth . "'");
                }
                if ($mostRejectionReasons[$m] == 0) {
                    $rejectionQuery = $rejectionQuery->where('covid19.reason_for_sample_rejection is not null and covid19.reason_for_sample_rejection!= "" and covid19.reason_for_sample_rejection NOT IN("' . implode('", "', $mostRejectionReasons) . '")');
                } else {
                    $rejectionQuery = $rejectionQuery->where('covid19.reason_for_sample_rejection = "' . $mostRejectionReasons[$m] . '"');
                }
                $rejectionQuery = $rejectionQuery->order(array(new Expression('DATE(sample_collection_date)')));
                $rejectionQueryStr = $sql->buildSqlString($rejectionQuery);
                $rejectionResult = $this->commonService->cacheQuery($rejectionQueryStr, $dbAdapter);
                $rejectionReasonName = ($mostRejectionReasons[$m] == 0) ? 'Others' : ucwords($rejectionResult[0]['rejection_reason_name']);
                $result['rejection'][$rejectionReasonName][$j] = (isset($rejectionResult[0]['rejections'])) ? $rejectionResult[0]['rejections'] : 0;
                $result['date'][$j] = $monthYearFormat;
            }
            $start = strtotime("+1 month", $start);
            $j++;
        }
        return $result;
    }

    public function fetchAllSamplesRejectedByDistrict($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.location_name');
        $orderColumns = array('f_d_l_d.location_name', 'total_samples_received', 'total_samples_rejected', 'rejection_rate');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {

                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {

                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)'),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "rejection_rate" => new Expression("ROUND(((SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))/(COUNT(*)))*100,2)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)')
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_rejected'];
            $row[] = ($aRow['rejection_rate']);
            $output['aaData'][] = $row;
        }
        return $output;
    }
    public function fecthAllSamplesRejectedByProvince($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.location_name');
        $orderColumns = array('f_d_l_d.location_name', 'total_samples_received', 'total_samples_rejected', 'rejection_rate');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {

                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {

                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)'),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "rejection_rate" => new Expression("ROUND(((SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))/(COUNT(*)))*100,2)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '' AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)')
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '' AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = ucwords($aRow['province']);
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_rejected'];
            $row[] = ($aRow['rejection_rate']);
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fecthAllSamplesRejectedByFacility($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f.facility_name', 'f_d_l_dp.location_name', 'f_d_l_d.location_name');
        $orderColumns = array('f_d_l_dp.location_name', 'f_d_l_d.location_name', 'total_samples_received', 'total_samples_rejected', 'rejection_rate');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {

                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {

                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)'),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "rejection_rate" => new Expression("ROUND(((SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))/(COUNT(*)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '' AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)')
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '' AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_id');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = "<span style='white-space:nowrap !important;' >" . ucwords($aRow['facility_name']) . "</span>";
            $row[] = ucwords($aRow['province']);
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['total_samples_received'];
            $row[] = ($aRow['rejection_rate']);
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchKeySummaryIndicatorsDetails($params)
    {
        $queryContainer = new Container('query');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $summaryResult = array();

        $samplesReceivedSummaryQuery = $sql->select()
            ->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "monthyear" => new Expression("DATE_FORMAT(sample_collection_date, '%b %y')"),
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN (covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL') THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result like 'Negative' )) THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = covid19.facility_id')
            //->where("sample_collection_date <= NOW()")
            //->where("sample_collection_date >= DATE_ADD(Now(),interval - 12 month)")
            ->group(array(new Expression('YEAR(sample_collection_date)'), new Expression('MONTH(sample_collection_date)')));

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
        }

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery->order(array(new Expression('DATE(sample_collection_date)')));

        $queryContainer->indicatorSummaryQuery = $samplesReceivedSummaryQuery;
        $samplesReceivedSummaryCacheQuery = $sql->buildSqlString($samplesReceivedSummaryQuery);
        $samplesReceivedSummaryResult = $this->commonService->cacheQuery($samplesReceivedSummaryCacheQuery, $dbAdapter);
        //var_dump($samplesReceivedSummaryResult);die;
        $j = 0;
        foreach ($samplesReceivedSummaryResult as $row) {
            $summaryResult['sample'][$this->translator->translate('Samples Received')]['month'][$j] = (isset($row["total_samples_received"])) ? $row["total_samples_received"] : 0;
            $summaryResult['sample'][$this->translator->translate('Samples Tested')]['month'][$j] = (isset($row["total_samples_tested"])) ? $row["total_samples_tested"] : 0;
            $summaryResult['sample'][$this->translator->translate('Samples Rejected')]['month'][$j] = (isset($row["total_samples_rejected"])) ? $row["total_samples_rejected"] : 0;
            $summaryResult['sample'][$this->translator->translate('Valid Tested')]['month'][$j]  = $valid = (isset($row["total_samples_tested"])) ? $row["total_samples_tested"] - $row["total_samples_rejected"] : 0;;
            $summaryResult['sample'][$this->translator->translate('No. of Positive')]['month'][$j] = (isset($row["total_positive_samples"])) ? $row["total_positive_samples"] : 0;
            //$summaryResult['sample'][$this->translator->translate('No. of Negative')]['month'][$j] = (isset($row["total_negative_samples"])) ? $row["total_negative_samples"] : 0;
            $summaryResult['sample'][$this->translator->translate('Positive %')]['month'][$j] = ($valid > 0) ? round((($row["total_positive_samples"] / $valid) * 100), 2) . ' %' : '0';
            $summaryResult['sample'][$this->translator->translate('Rejection %')]['month'][$j] = (isset($row["total_samples_rejected"]) && $row["total_samples_rejected"] > 0 && $row["total_samples_received"] > 0) ? round((($row["total_samples_rejected"] / ($row["total_samples_tested"] + $row["total_samples_rejected"])) * 100), 2) . ' %' : '0';
            $summaryResult['month'][$j] = $row['monthyear'];
            $j++;
        }
        return $summaryResult;
    }

    public function fetchCovid19OutcomesDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $covid19OutcomesQuery = $sql->select()
            ->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "total_samples" => new Expression("SUM(CASE WHEN ((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL')) THEN 1 ELSE 0 END)"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' )) THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = covid19.facility_id', array());

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $covid19OutcomesQuery = $covid19OutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $covid19OutcomesQueryStr = $sql->buildSqlString($covid19OutcomesQuery);
        $result = $this->commonService->cacheQuery($covid19OutcomesQueryStr, $dbAdapter);
        return $result[0];
    }

    public function fetchCovid19OutcomesByAgeDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        /* Dynamic year range */
        $ageGroup = array('2', '2-5', '6-14', '15-49', '50');

        $ageGroupArray['noDatan'] = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND (covid19.patient_dob IS NULL OR covid19.patient_dob = '0000-00-00'))THEN 1 ELSE 0 END)");
        $ageGroupArray['noDatap'] = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND (covid19.patient_dob IS NULL OR covid19.patient_dob = '0000-00-00'))THEN 1 ELSE 0 END)");
        foreach ($ageGroup as $key => $age) {
            if ($key == 0) {
                $ageGroupArray[$age . 'n']   = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-" . $age . ' YEARS')) . "')THEN 1 ELSE 0 END)");
                $ageGroupArray[$age . 'p']   = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-" . $age . ' YEARS')) . "')THEN 1 ELSE 0 END)");
            } elseif ($key == 4) {
                $ageGroupArray[$age . 'n']   = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-" . $age . ' YEARS')) . "')THEN 1 ELSE 0 END)");
                $ageGroupArray[$age . 'p']   = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-" . $age . ' YEARS')) . "')THEN 1 ELSE 0 END)");
            } else {
                $keyIndex = explode('-', $age);
                $ageGroupArray[$age . 'n']   = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-" . $keyIndex[0] . ' YEARS')) . "' AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-" . $keyIndex[1] . ' YEARS')) . "')THEN 1 ELSE 0 END)");
                $ageGroupArray[$age . 'p']   = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-" . $keyIndex[0] . ' YEARS')) . "' AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-" . $keyIndex[1] . ' YEARS')) . "')THEN 1 ELSE 0 END)");
            }
        }
        $covid19OutcomesQuery = $sql->select()
            ->from(array('covid19' => $this->table))
            ->columns(
                $ageGroupArray
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = covid19.facility_id', array());

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $covid19OutcomesQuery = $covid19OutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $covid19OutcomesQueryStr = $sql->buildSqlString($covid19OutcomesQuery);
        // echo $covid19OutcomesQueryStr;die;
        $result = $this->commonService->cacheQuery($covid19OutcomesQueryStr, $dbAdapter);
        return $result[0];
    }

    public function fetchCovid19OutcomesByProvinceDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $covid19OutcomesQuery = $sql->select()
            ->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "total_samples" => new Expression("SUM(CASE WHEN ((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL')) THEN 1 ELSE 0 END)"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' )) THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = covid19.facility_id', array())
            ->join(array('l' => 'location_details'), 'l.location_id = f.facility_state', array('location_name'))
            ->group('f.facility_state');

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
        }

        $covid19OutcomesQueryStr = $sql->buildSqlString($covid19OutcomesQuery);
        $result = $this->commonService->cacheQuery($covid19OutcomesQueryStr, $dbAdapter);
        return $result;
    }

    public function fetchTATDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $covid19OutcomesQuery = $sql->select()
            ->from(array('covid19' => $this->table))
            ->columns(
                array(
                    'sec1' => new Expression("AVG(DATEDIFF(sample_received_at_vl_lab_datetime, sample_collection_date))"),
                    'sec2' => new Expression("AVG(DATEDIFF(sample_tested_datetime, sample_received_at_vl_lab_datetime))"),
                    'sec3' => new Expression("AVG(DATEDIFF(result_printed_datetime, sample_tested_datetime))"),
                    'total' => new Expression("AVG(DATEDIFF(result_printed_datetime, sample_collection_date))"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = covid19.facility_id', array());

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $covid19OutcomesQuery = $covid19OutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $covid19OutcomesQueryStr = $sql->buildSqlString($covid19OutcomesQuery);
        // echo $covid19OutcomesQueryStr;die;
        $result = $dbAdapter->query($covid19OutcomesQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        // $result = $this->commonService->cacheQuery($covid19OutcomesQueryStr, $dbAdapter);
        return $result[0];
    }
    // SUMMARY DASHBOARD END

    // LAB DASHBOARD START
    public function getStats($params)
    {
        $logincontainer = new Container('credo');
        $quickStats = $this->fetchQuickStats($params);
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $testedTotal = 0;
        $receivedResult = array();
        $tResult = array();
        $rejectedResult = array();
        if (trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        } else {
            $timestamp = time();
            $qDates = array();
            for ($i = 0; $i < 28; $i++) {
                $qDates[] = "'" . date('Y-m-d', $timestamp) . "'";
                $timestamp -= 24 * 3600;
            }
            $qDates = implode(",", $qDates);
        }

        //get received data
        $receivedQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(array('total' => new Expression('COUNT(*)'), 'receivedDate' => new Expression('DATE(sample_collection_date)')))
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
            ->group(array("receivedDate"));
        if ($logincontainer->role != 1) {
            $receivedQuery = $receivedQuery->where('covid19.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        if (trim($params['daterange']) != '') {
            if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                $receivedQuery = $receivedQuery->where(array("DATE(covid19.sample_collection_date) <='$splitDate[1]'", "DATE(covid19.sample_collection_date) >='$splitDate[0]'"));
            }
        } else {
            $receivedQuery = $receivedQuery->where("DATE(sample_collection_date) IN ($qDates)");
        }
        $cQueryStr = $sql->buildSqlString($receivedQuery);
        // echo $cQueryStr;die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $this->commonService->cacheQuery($cQueryStr, $dbAdapter);

        //var_dump($receivedResult);die;
        $recTotal = 0;
        foreach ($rResult as $rRow) {
            $displayDate = $this->commonService->humanDateFormat($rRow['receivedDate']);
            $receivedResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'receivedDate' => $displayDate, 'receivedTotal' => $recTotal += $rRow['total']);
        }

        //tested data
        $testedQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(array('total' => new Expression('COUNT(*)'), 'testedDate' => new Expression('DATE(sample_tested_datetime)')))
            ->where("((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL') OR (covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection != '' AND covid19.reason_for_sample_rejection != 0))")
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
            ->group(array("testedDate"));
        if ($logincontainer->role != 1) {
            $testedQuery = $testedQuery->where('covid19.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        if (trim($params['daterange']) != '') {
            if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                $testedQuery = $testedQuery->where(array("DATE(covid19.sample_tested_datetime) <='$splitDate[1]'", "DATE(covid19.sample_tested_datetime) >='$splitDate[0]'"));
            }
        } else {
            $testedQuery = $testedQuery->where("DATE(sample_tested_datetime) IN ($qDates)");
        }
        $cQueryStr = $sql->buildSqlString($testedQuery);
        // echo $cQueryStr;//die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $this->commonService->cacheQuery($cQueryStr, $dbAdapter);

        //var_dump($receivedResult);die;
        $testedTotal = 0;
        foreach ($rResult as $rRow) {
            $displayDate = $this->commonService->humanDateFormat($rRow['testedDate']);
            $tResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'testedDate' => $displayDate, 'testedTotal' => $testedTotal += $rRow['total']);
        }

        //get rejected data
        $rejectedQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(array('total' => new Expression('COUNT(*)'), 'rejectDate' => new Expression('DATE(sample_collection_date)')))
            ->where("covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection !='' AND covid19.reason_for_sample_rejection!= 0")
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
            ->group(array("rejectDate"));
        if ($logincontainer->role != 1) {
            $rejectedQuery = $rejectedQuery->where('covid19.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        if (trim($params['daterange']) != '') {
            if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                $rejectedQuery = $rejectedQuery->where(array("DATE(covid19.sample_collection_date) <='$splitDate[1]'", "DATE(covid19.sample_collection_date) >='$splitDate[0]'"));
            }
        } else {
            $rejectedQuery = $rejectedQuery->where("DATE(sample_collection_date) IN ($qDates)");
        }
        $cQueryStr = $sql->buildSqlString($rejectedQuery);
        // echo $cQueryStr;die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $this->commonService->cacheQuery($cQueryStr, $dbAdapter);
        $rejTotal = 0;
        foreach ($rResult as $rRow) {
            $displayDate = $this->commonService->humanDateFormat($rRow['rejectDate']);
            $rejectedResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'rejectDate' => $displayDate, 'rejectTotal' => $rejTotal += $rRow['total']);
        }
        return array('quickStats' => $quickStats, 'scResult' => $receivedResult, 'stResult' => $tResult, 'srResult' => $rejectedResult);
    }

    public function fetchQuickStats($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');

        $query = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    $this->translator->translate("Total Samples") => new Expression('COUNT(*)'),
                    $this->translator->translate("Samples Tested") => new Expression("SUM(CASE 
                                                                                WHEN (((covid19.result is NOT NULL AND covid19.result !='') OR (covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection != '' AND covid19.reason_for_sample_rejection != 0))) THEN 1
                                                                                ELSE 0
                                                                                END)"),
                    $this->translator->translate("Gender Missing") => new Expression("SUM(CASE 
                                                                                    WHEN ((patient_gender IS NULL OR patient_gender ='' OR patient_gender ='unreported' OR patient_gender ='Unreported')) THEN 1
                                                                                    ELSE 0
                                                                                    END)"),
                    $this->translator->translate("Age Missing") => new Expression("SUM(CASE 
                                                                                WHEN ((patient_age IS NULL OR patient_age ='' OR patient_age ='Unreported'  OR patient_age ='unreported')) THEN 1
                                                                                ELSE 0
                                                                                END)"),
                    $this->translator->translate("Results Not Available (< 6 months)") => new Expression("SUM(CASE
                                                                                                                                WHEN ((covid19.result is NULL OR covid19.result ='') AND (sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH)) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or reason_for_sample_rejection = 0)) THEN 1
                                                                                                                                ELSE 0
                                                                                                                                END)"),
                    $this->translator->translate("Results Not Available (> 6 months)") => new Expression("SUM(CASE
                                                                                                                                WHEN ((covid19.result is NULL OR covid19.result ='') AND (sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH)) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or reason_for_sample_rejection = 0)) THEN 1
                                                                                                                                ELSE 0
                                                                                                                                END)")
                )
            );
        if ($logincontainer->role != 1) {
            $query = $query->where('covid19.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        $queryStr = $sql->buildSqlString($query);
        //echo $queryStr;die;
        //$result = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $result = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        return $result[0];
    }

    public function getMonthlySampleCount($params)
    {

        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));

            $facilityIdList = null;

            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_type = 2 AND f.status="active"');
                $fQuery = $fQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            } else if (!empty($this->mappedFacilities)) {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            }


            $queryStr = $sql->select()->from(array('covid19' => $this->table))
                ->columns(
                    array(
                        "total" => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                        "negative" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative%' OR covid19.result like 'Negative%')) THEN 1 ELSE 0 END)"),
                        "positive" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive%' OR covid19.result like 'Positive%' )) THEN 1 ELSE 0 END)"),
                        "total_samples_valid" => new Expression("(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END))")
                    )
                );

            if ($facilityIdList != null) {
                $queryStr = $queryStr->where('covid19.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date not like '')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");

            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));
            $queryStr = $sql->buildSqlString($queryStr);
            // echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["monthDate"] == null) continue;
                $result['covid19Result']['Positive'][$j] = (isset($sRow["positive"])) ? $sRow["positive"] : 0;
                $result['covid19Result']['Negative'][$j] = (isset($sRow["negative"])) ? $sRow["negative"] : 0;
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    public function getMonthlySampleCountByLabs($params)
    {
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));

            $facilityIdList = null;

            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_type = 2 AND f.status="active"');
                $fQuery = $fQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            } else if (!empty($this->mappedFacilities)) {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    //->where('f.facility_type = 2 AND f.status="active"')
                    ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            }


            $query = $sql->select()->from(array('covid19' => $this->table))

                ->columns(
                    array(
                        "total" => new Expression("SUM(CASE WHEN (
                                                        covid19.result in ('negative', 'Negative','positive', 'Positive')) 
                                                        THEN 1 ELSE 0 END)"),
                        "negative" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result like 'Negative')) THEN 1 ELSE 0 END)"),
                        "positive" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.lab_id', array('facility_name'))
                ->where(array("covid19.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "covid19.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"))

                ->group('covid19.lab_id')
                ->order('total DESC');

            if ($facilityIdList != null) {
                $query = $query->where('covid19.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            $testResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $j = 0;
            foreach ($testResult as $data) {

                $result['sampleName']['Positive'][$j] = !empty($data['positive']) ? $data['positive'] : 0;
                $result['sampleName']['Negative'][$j] = !empty($data['negative']) ? $data['negative'] : 0;
                $result['lab'][$j] = $data['facility_name'];
                $j++;
            }
        }

        return $result;
    }

    public function fetchLabTurnAroundTime($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $skipDays = isset($this->config['defaults']['tat-skipdays']) ? $this->config['defaults']['tat-skipdays'] : 120;
        $facilityIdList = null;

        // FILTER :: Checking if the facility filter is set
        // else if the user is mapped to one or more facilities

        if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                ->where('f.facility_type = 2 AND f.status="active"');
            $fQuery = $fQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $facilityIdList = array_column($facilityResult, 'facility_id');
        } else if (!empty($this->mappedFacilities)) {
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                //->where('f.facility_type = 2 AND f.status="active"')
                ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $facilityIdList = array_column($facilityResult, 'facility_id');
        }

        // FILTER :: Checking if the date range filter is set (which should be always set)

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $monthyear = date("Y-m");
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));

            if (strtotime($startMonth) >= strtotime($monthyear)) {
                $startMonth = $endMonth = date("Y-m-01", strtotime("-2 months"));
            } else if (strtotime($endMonth) >= strtotime($monthyear)) {
                $endMonth = date("Y-m-t", strtotime("-2 months"));
            }

            $query = $sql->select()->from(array('covid19' => $this->table))
                ->columns(
                    array(
                        // "month" => new Expression("MONTH(result_approved_datetime)"),
                        // "year" => new Expression("YEAR(result_approved_datetime)"),
                        // "AvgDiff" => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                        // "monthDate" => new Expression("DATE_FORMAT(DATE(result_approved_datetime), '%b-%Y')"),
                        // "total_samples_collected" => new Expression('COUNT(*)'),
                        // "total_samples_pending" => new Expression("(SUM(CASE WHEN ((covid19.result IS NULL OR covid19.result like '' OR covid19.result like 'NULL') AND (covid19.reason_for_sample_rejection IS NULL OR covid19.reason_for_sample_rejection = '' OR covid19.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))")

                        "totalSamples" => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(covid19.sample_tested_datetime), '%b-%Y')"),
                        "daydiff" => new Expression('ABS(TIMESTAMPDIFF(DAY,sample_tested_datetime,sample_collection_date))'),
                        "AvgTestedDiff" => new Expression('CAST(ABS(AVG(TIMESTAMPDIFF(DAY,covid19.sample_tested_datetime,covid19.sample_collection_date))) AS DECIMAL (10,2))'),
                        "AvgReceivedDiff" => new Expression('CAST(ABS(AVG(TIMESTAMPDIFF(DAY,covid19.sample_received_at_vl_lab_datetime,covid19.sample_collection_date))) AS DECIMAL (10,2))'),
                        "AvgReceivedTested" => new Expression('CAST(ABS(AVG(TIMESTAMPDIFF(DAY,covid19.sample_tested_datetime,covid19.sample_received_at_vl_lab_datetime))) AS DECIMAL (10,2))'),
                    )
                );
            $query = $query->where("
                    (covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '' AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')
                    AND (covid19.result_approved_datetime is not null AND covid19.result_approved_datetime not like '' AND DATE(covid19.result_approved_datetime) !='1970-01-01' AND DATE(covid19.result_approved_datetime) !='0000-00-00')");
            $query = $query->where("
                        DATE(covid19.result_approved_datetime) >= '" . $startMonth . "'
                        AND DATE(covid19.result_approved_datetime) <= '" . $endMonth . "' ");


            $skipDays = (isset($skipDays) && $skipDays > 0) ? $skipDays : 120;
            $query = $query->where('
                (DATEDIFF(result_approved_datetime,sample_collection_date) < ' . $skipDays . ' AND 
                DATEDIFF(result_approved_datetime,sample_collection_date) >= 0)');

            if ($facilityIdList != null) {
                $query = $query->where('covid19.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }
            $query = $query->group('monthDate');
            // $query = $query->group(array(new Expression('YEAR(covid19.result_approved_datetime)')));
            // $query = $query->group(array(new Expression('MONTH(covid19.result_approved_datetime)')));
            // $query = $query->order(array(new Expression('DATE(covid19.result_approved_datetime) ASC')));
            $query = $query->order('covid19.sample_tested_datetime ASC');
            $queryStr = $sql->buildSqlString($query);
            // echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $key => $sRow) {
                /* $result['all'][$key] = (isset($sRow["AvgDiff"]) && $sRow["AvgDiff"] != NULL && $sRow["AvgDiff"] > 0) ? round($sRow["AvgDiff"], 2) : null;
                //$result['lab'][$key] = (isset($labsubQueryResult[0]["labCount"]) && $labsubQueryResult[0]["labCount"] != NULL && $labsubQueryResult[0]["labCount"] > 0) ? round($labsubQueryResult[0]["labCount"],2) : 0;
                $result['data']['Samples Collected'][$key] = (isset($sRow['total_samples_collected']) && $sRow['total_samples_collected'] != NULL) ? $sRow['total_samples_collected'] : null;
                $result['data']['Results Not Available'][$key] = (isset($sRow['total_samples_pending']) && $sRow['total_samples_pending'] != NULL) ? $sRow['total_samples_pending'] : null;
                $result['dates'][$key] = $sRow["monthDate"]; */

                if ($sRow["monthDate"] == null) {
                    continue;
                }

                $result['totalSamples'][$j] = (isset($sRow["totalSamples"]) && $sRow["totalSamples"] > 0 && $sRow["totalSamples"] != null) ? $sRow["totalSamples"] : 'null';
                $result['sampleTestedDiff'][$j] = (isset($sRow["AvgTestedDiff"]) && $sRow["AvgTestedDiff"] > 0 && $sRow["AvgTestedDiff"] != null) ? round($sRow["AvgTestedDiff"], 2) : 'null';
                $result['sampleReceivedDiff'][$j] = (isset($sRow["AvgReceivedDiff"]) && $sRow["AvgReceivedDiff"] > 0 && $sRow["AvgReceivedDiff"] != null) ? round($sRow["AvgReceivedDiff"], 2) : 'null';
                $result['sampleReceivedTested'][$j] = (isset($sRow["AvgReceivedTested"]) && $sRow["AvgReceivedTested"] > 0 && $sRow["AvgReceivedTested"] != null) ? round($sRow["AvgReceivedTested"], 2) : 'null';
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    public function fetchLabPerformance($params)
    {
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));

            $facilityIdList = null;

            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_type = 2 AND f.status="active"');
                $fQuery = $fQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            } else if (!empty($this->mappedFacilities)) {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    //->where('f.facility_type = 2 AND f.status="active"')
                    ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            }


            $query = $sql->select()->from(array('covid19' => $this->table))

                ->columns(
                    array(
                        "total" => new Expression("COUNT(*)"),
                        "rejected" => new Expression("SUM(CASE WHEN ((covid19.is_sample_rejected like 'yes')) THEN 1 ELSE 0 END)"),
                        "total_valid_tests" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result like 'Negative') OR (covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                        "negative" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result like 'Negative')) THEN 1 ELSE 0 END)"),
                        "positive" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('total_facilities' => new Expression("COUNT(DISTINCT f.facility_id)")))
                ->join(array('lab' => 'facility_details'), 'lab.facility_id=covid19.lab_id', array('lab_name' => 'facility_name'))
                ->where(array("covid19.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "covid19.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"))

                ->group('covid19.lab_id')
                ->order('total DESC');


            if ($facilityIdList != null) {
                $query = $query->where('covid19.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $queryStr = $sql->buildSqlString($query);
            // echo $queryStr;die;
            $result = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }

        return $result;
    }

    public function fetchCovid19OutcomesByAgeInLabsDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $ageGroup = array('2', '2-5', '6-14', '15-49', '50');

        $ageGroupArray['noDatan'] = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND (covid19.patient_dob IS NULL OR covid19.patient_dob = '0000-00-00'))THEN 1 ELSE 0 END)");
        $ageGroupArray['noDatap'] = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND (covid19.patient_dob IS NULL OR covid19.patient_dob = '0000-00-00'))THEN 1 ELSE 0 END)");
        foreach ($ageGroup as $key => $age) {
            if ($key == 0) {
                $ageGroupArray[$age . 'n']   = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-" . $age . ' YEARS')) . "')THEN 1 ELSE 0 END)");
                $ageGroupArray[$age . 'p']   = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-" . $age . ' YEARS')) . "')THEN 1 ELSE 0 END)");
            } elseif ($key == 4) {
                $ageGroupArray[$age . 'n']   = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-" . $age . ' YEARS')) . "')THEN 1 ELSE 0 END)");
                $ageGroupArray[$age . 'p']   = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-" . $age . ' YEARS')) . "')THEN 1 ELSE 0 END)");
            } else {
                $keyIndex = explode('-', $age);
                $ageGroupArray[$age . 'n']   = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-" . $keyIndex[0] . ' YEARS')) . "' AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-" . $keyIndex[1] . ' YEARS')) . "')THEN 1 ELSE 0 END)");
                $ageGroupArray[$age . 'p']   = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-" . $keyIndex[0] . ' YEARS')) . "' AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-" . $keyIndex[1] . ' YEARS')) . "')THEN 1 ELSE 0 END)");
            }
        }
        $covid19OutcomesQuery = $sql->select()
            ->from(array('covid19' => $this->table))
            ->columns(
                $ageGroupArray
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = covid19.facility_id', array());

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $covid19OutcomesQuery = $covid19OutcomesQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $covid19OutcomesQuery = $covid19OutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $facilityIdList = array();
        if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                ->where('f.facility_type = 2 AND f.status="active"');
            $fQuery = $fQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $facilityIdList = array_column($facilityResult, 'facility_id');
        } else if (!empty($this->mappedFacilities)) {
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $facilityIdList = array_column($facilityResult, 'facility_id');
        }

        if ($facilityIdList != null) {
            $queryStr = $queryStr->where('covid19.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
        }

        $covid19OutcomesQueryStr = $sql->buildSqlString($covid19OutcomesQuery);
        $result = $this->commonService->cacheQuery($covid19OutcomesQueryStr, $dbAdapter);
        return $result[0];
    }

    public function fetchCovid19PositivityRateDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        
        $startMonth = "";
        $endMonth = "";
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = date('Y-m-01', strtotime(str_replace(' ', '-', $params['fromDate'])));
            $endMonth = date('Y-m-t', strtotime(str_replace(' ', '-', $params['toDate'])));

            $monthList = $this->commonService->getMonthsInRange($startMonth, $endMonth);
            $sQuery = $sql->select()->from(array('covid19' => 'dash_form_covid19'))->columns(array(
                'monthYear' => new Expression("DATE_FORMAT(sample_collection_date, '%b-%Y')"),
                'positive_rate' => new Expression("ROUND(((SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
            ))
                ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.lab_id', array('facility_name'))
                ->where("(sample_collection_date is not null)
                                    AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                    AND DATE(sample_collection_date) <= '" . $endMonth . "'")
                ->group(array("lab_id", new Expression("DATE_FORMAT(sample_collection_date, '%m-%Y')")))
                ->order(array("lab_id", new Expression("DATE_FORMAT(sample_collection_date, '%m-%Y')")));

            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
            }
            if (isset($params['clinics']) && trim($params['clinics']) != '') {
                $sQuery = $sQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
            }

            $facilityIdList = array();
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $mQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_type = 2 AND f.status="active"');
                $mQuery = $mQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
                $mQueryStr = $sql->buildSqlString($mQuery);
                $facilityResult = $dbAdapter->query($mQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            } else if (!empty($this->mappedFacilities)) {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            }

            if ($facilityIdList != null) {
                $sQuery = $sQuery->where('covid19.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $sQueryStr = $sql->buildSqlString($sQuery);
            // echo $sQueryStr;die;
            $result = $this->commonService->cacheQuery($sQueryStr, $dbAdapter);
            return array('result' => $result, 'month' => $monthList);
        } else {
            return 0;
        }
    }
    // LABS DASHBOARD END

    ////////////////////////////////////////////
    /////////*** Turnaround Time Page ***///////
    ///////////////////////////////////////////

    public function getTATbyProvince($labs, $startDate, $endDate)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $skipDays = isset($this->config['defaults']['tat-skipdays']) ? $this->config['defaults']['tat-skipdays'] : 120;
        $squery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "Collection_Receive"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_received_at_vl_lab_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                    "Receive_Register"    => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_received_at_vl_lab_datetime))) AS DECIMAL (10,2))"),
                    "Register_Analysis"   => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "Analysis_Authorise"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "total"               => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                )
            )
            ->join('facility_details', 'facility_details.facility_id = covid19.facility_id')
            ->join('location_details', 'facility_details.facility_state = location_details.location_id')
            ->where(
                array(
                    "sample_tested_datetime >= '$startDate' AND sample_tested_datetime <= '$endDate'",
                    "(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '' AND DATE(covid19.sample_collection_date) not like '1970-01-01' AND DATE(covid19.sample_collection_date) not like '0000-00-00')",
                    // "facility_details.facility_state = '$provinceID'"
                )
            );
        if ($skipDays > 0) {
            $squery = $squery->where('
                DATEDIFF(sample_received_at_vl_lab_datetime,sample_collection_date) < ' . $skipDays . ' AND 
                DATEDIFF(sample_received_at_vl_lab_datetime,sample_collection_date) >= 0 AND 

                DATEDIFF(sample_registered_at_lab,sample_received_at_vl_lab_datetime) < ' . $skipDays . ' AND 
                DATEDIFF(sample_registered_at_lab,sample_received_at_vl_lab_datetime) >= 0 AND 

                DATEDIFF(sample_tested_datetime,sample_received_at_vl_lab_datetime) < ' . $skipDays . ' AND 
                DATEDIFF(sample_tested_datetime,sample_registered_at_lab)>=0 AND 

                DATEDIFF(result_approved_datetime,sample_tested_datetime) < ' . $skipDays . ' AND 
                DATEDIFF(result_approved_datetime,sample_tested_datetime) >= 0');
        }

        if (isset($labs) && !empty($labs)) {
            $squery = $squery->where('covid19.lab_id IN (' . implode(',', $labs) . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $squery = $squery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        $squery = $squery->group(array('location_id'));
        $sQueryStr = $sql->buildSqlString($squery);
        // die($sQueryStr);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }

    public function getTATbyDistrict($labs, $startDate, $endDate)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $skipDays = isset($this->config['defaults']['tat-skipdays']) ? $this->config['defaults']['tat-skipdays'] : 120;
        $squery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "Collection_Receive"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_received_at_vl_lab_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                    "Receive_Register"    => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_received_at_vl_lab_datetime))) AS DECIMAL (10,2))"),
                    "Register_Analysis"   => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "Analysis_Authorise"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "total"               => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                )
            )
            ->join('facility_details', 'facility_details.facility_id = covid19.facility_id')
            ->join('location_details', 'facility_details.facility_state = location_details.location_id')
            ->where(
                array(
                    "sample_tested_datetime >= '$startDate' AND sample_tested_datetime <= '$endDate'",
                    "(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '' AND DATE(covid19.sample_collection_date) not like '1970-01-01' AND DATE(covid19.sample_collection_date) not like '0000-00-00')",
                    // "facility_details.facility_district = '$districtID'"
                )
            );
        if ($skipDays > 0) {
            $squery = $squery->where('
                DATEDIFF(sample_received_at_vl_lab_datetime,sample_collection_date)<120 AND 
                DATEDIFF(sample_received_at_vl_lab_datetime,sample_collection_date)>=0 AND 

                DATEDIFF(sample_registered_at_lab,sample_received_at_vl_lab_datetime)<120 AND 
                DATEDIFF(sample_registered_at_lab,sample_received_at_vl_lab_datetime)>=0 AND 

                DATEDIFF(sample_tested_datetime,sample_received_at_vl_lab_datetime)<120 AND 
                DATEDIFF(sample_tested_datetime,sample_registered_at_lab)>=0 AND 

                DATEDIFF(result_approved_datetime,sample_tested_datetime)<120 AND 
                DATEDIFF(result_approved_datetime,sample_tested_datetime)>=0');
        }

        if (isset($labs) && !empty($labs)) {
            $squery = $squery->where('covid19.lab_id IN (' . implode(',', $labs) . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $squery = $squery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        $squery = $squery->group(array('location_id'));
        $sQueryStr = $sql->buildSqlString($squery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }

    public function getTATbyClinic($labs, $startDate, $endDate)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $skipDays = isset($this->config['defaults']['tat-skipdays']) ? $this->config['defaults']['tat-skipdays'] : 120;
        $squery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "Collection_Receive"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_received_at_vl_lab_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                    "Receive_Register"    => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_received_at_vl_lab_datetime))) AS DECIMAL (10,2))"),
                    "Register_Analysis"   => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "Analysis_Authorise"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "total"               => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                )
            )
            ->join('facility_details', 'facility_details.facility_id = covid19.facility_id')
            ->join('location_details', 'facility_details.facility_state = location_details.location_id')
            ->where(
                array(
                    "sample_tested_datetime >= '$startDate' AND sample_tested_datetime <= '$endDate'",
                    "(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '' AND DATE(covid19.sample_collection_date) not like '1970-01-01' AND DATE(covid19.sample_collection_date) not like '0000-00-00')",
                    // "covid19.facility_id = '$clinicID'"
                )
            );
        if ($skipDays > 0) {
            $squery = $squery->where('
                DATEDIFF(sample_received_at_vl_lab_datetime,sample_collection_date)<120 AND 
                DATEDIFF(sample_received_at_vl_lab_datetime,sample_collection_date)>=0 AND 

                DATEDIFF(sample_registered_at_lab,sample_received_at_vl_lab_datetime)<120 AND 
                DATEDIFF(sample_registered_at_lab,sample_received_at_vl_lab_datetime)>=0 AND 

                DATEDIFF(sample_tested_datetime,sample_received_at_vl_lab_datetime)<120 AND 
                DATEDIFF(sample_tested_datetime,sample_registered_at_lab)>=0 AND 

                DATEDIFF(result_approved_datetime,sample_tested_datetime)<120 AND 
                DATEDIFF(result_approved_datetime,sample_tested_datetime)>=0');
        }

        if (isset($labs) && !empty($labs)) {
            $squery = $squery->where('covid19.lab_id IN (' . implode(',', $labs) . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $squery = $squery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        $squery = $squery->group(array('location_id'));
        $sQueryStr = $sql->buildSqlString($squery);
        die($sQueryStr);
        $sResult   = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }

    /////////////////////////////////////////////
    /////////*** Turnaround Time Page ***////////
    ////////////////////////////////////////////

    public function fetchProvinceWiseResultAwaitedDrillDown($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }

        $p = 0;

        $countQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((covid19.is_sample_rejected is NULL OR covid19.is_sample_rejected = '' OR covid19.is_sample_rejected = 'no') 
                                                    AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or covid19.reason_for_sample_rejection = 0))) THEN 1
                                                            ELSE 0
                                                        END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.lab_id', array())
            ->join(array('p' => 'location_details'), 'p.location_id=f.facility_state', array('province_name' => 'location_name', 'location_id'), 'left')
            ->group('p.location_id');
        if (isset($params['lab']) && trim($params['lab']) != '') {
            $countQuery = $countQuery->where('covid19.lab_id IN (' . $params['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $countQuery = $countQuery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $countQuery = $countQuery->where('p.location_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $countQuery = $countQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
            $countQuery = $countQuery->where('covid19.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("covid19.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "covid19.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } else {
            if (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
                $countQuery = $countQuery->where("(covid19.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            } else if (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
                $countQuery = $countQuery->where("(covid19.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            }
        }

        //print_r($params['age']);die;
        if (isset($params['age']) && trim($params['age']) != '') {
            $age = explode(',', $params['age']);
            $where = '';
            for ($a = 0; $a < count($age); $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                } else if ($age[$a] == '2to5') {
                    $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                } else if ($age[$a] == '6to14') {
                    $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                } else if ($age[$a] == '15to49') {
                    $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                } else if ($age[$a] == '>=50') {
                    $where .= "(covid19.patient_age >= 50)";
                } else if ($age[$a] == 'unknown') {
                    $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown' OR covid19.patient_age = 'unreported' OR covid19.patient_age = 'Unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('covid19.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded' OR covid19.patient_gender = 'Unreported' OR covid19.patient_gender = 'unreported')");
        }


        $countQueryStr = $sql->buildSqlString($countQuery);
        // echo $countQueryStr;die;
        $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($countResult as $row) {
            $result['province'][$p] = ($row['province_name'] != null && trim($row['province_name']) != '') ? ($row['province_name']) : 'Not Specified';
            $result['sample']['Results Not Available'][$p] = (isset($row['total'])) ? $row['total'] : 0;
            $p++;
        }
        return $result;
    }
    public function fetchDistrictWiseResultAwaitedDrillDown($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }

        $p = 0;

        $countQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((covid19.is_sample_rejected is NULL OR covid19.is_sample_rejected = '' OR covid19.is_sample_rejected = 'no') 
                                                    AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or covid19.reason_for_sample_rejection = 0))) THEN 1
                                                            ELSE 0
                                                        END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.lab_id', array())
            ->join(array('d' => 'location_details'), 'd.location_id=f.facility_district', array('district_name' => 'location_name', 'location_id'), 'left')
            ->order('total DESC')
            ->group('d.location_id');
        if (isset($params['lab']) && trim($params['lab']) != '') {
            $countQuery = $countQuery->where('covid19.lab_id IN (' . $params['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $countQuery = $countQuery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $countQuery = $countQuery->where('p.location_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $countQuery = $countQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
            $countQuery = $countQuery->where('covid19.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("covid19.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "covid19.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } else {
            if (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
                $countQuery = $countQuery->where("(covid19.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            } else if (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
                $countQuery = $countQuery->where("(covid19.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            }
        }

        //print_r($params['age']);die;
        if (isset($params['age']) && trim($params['age']) != '') {
            $age = explode(',', $params['age']);
            $where = '';
            for ($a = 0; $a < count($age); $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                } else if ($age[$a] == '2to5') {
                    $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                } else if ($age[$a] == '6to14') {
                    $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                } else if ($age[$a] == '15to49') {
                    $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                } else if ($age[$a] == '>=50') {
                    $where .= "(covid19.patient_age >= 50)";
                } else if ($age[$a] == 'unknown') {
                    $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown' OR covid19.patient_age = 'unreported' OR covid19.patient_age = 'Unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('covid19.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded' OR covid19.patient_gender = 'Unreported' OR covid19.patient_gender = 'unreported')");
        }


        $countQueryStr = $sql->buildSqlString($countQuery);
        // echo $countQueryStr;die;
        $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($countResult as $row) {
            $result['district'][$p] = ($row['district_name'] != null && trim($row['district_name']) != '') ? ($row['district_name']) : 'Not Specified';
            $result['sample']['Results Not Available'][$p] = (isset($row['total'])) ? $row['total'] : 0;
            $p++;
        }
        return $result;
    }

    public function fetchLabWiseResultAwaitedDrillDown($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }

        $l = 0;

        $countQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((covid19.is_sample_rejected is NULL OR covid19.is_sample_rejected = '' OR covid19.is_sample_rejected = 'no') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or covid19.reason_for_sample_rejection = 0))) THEN 1
                                                                                 ELSE 0
                                                                                 END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.lab_id', array('lab_name' => 'facility_name'))
            ->order('total DESC')
            ->group(array('covid19.lab_id'));

        if (isset($params['lab']) && trim($params['lab']) != '') {
            $countQuery = $countQuery->where('f.facility_id IN (' . $params['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $countQuery = $countQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $countQuery = $countQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $countQuery = $countQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
            $countQuery = $countQuery->where('covid19.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("covid19.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "covid19.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } else {
            if (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
                $countQuery = $countQuery->where("(covid19.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            } else if (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
                $countQuery = $countQuery->where("(covid19.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            }
        }

        //print_r($params['age']);die;
        if (isset($params['age']) && trim($params['age']) != '') {
            $age = explode(',', $params['age']);
            $where = '';
            for ($a = 0; $a < count($age); $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                } else if ($age[$a] == '2to5') {
                    $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                } else if ($age[$a] == '6to14') {
                    $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                } else if ($age[$a] == '15to49') {
                    $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                } else if ($age[$a] == '>=50') {
                    $where .= "(covid19.patient_age >= 50)";
                } else if ($age[$a] == 'unknown') {
                    $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown' OR covid19.patient_age = 'Unreported' OR covid19.patient_age = 'unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('covid19.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded' OR covid19.patient_gender = 'unreported' OR covid19.patient_gender = 'Unreported')");
        }


        $countQueryStr = $sql->buildSqlString($countQuery);
        // echo $countQueryStr;die;
        $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($countResult as $lab) {
            $result['lab'][$l] = ucwords($lab['lab_name']);
            $result['sample']['Results Not Available'][$l] = (isset($lab['total'])) ? $lab['total'] : 0;
            $l++;
        }

        return $result;
    }

    public function fetchClinicWiseResultAwaitedDrillDown($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }

        $l = 0;

        $countQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((covid19.is_sample_rejected is NULL OR covid19.is_sample_rejected = '' OR covid19.is_sample_rejected = 'no') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or covid19.reason_for_sample_rejection = 0))) THEN 1
                                                                                 ELSE 0
                                                                                 END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('clinic_name' => 'facility_name'))
            ->order('total DESC')
            ->group(array('covid19.facility_id'));

        if (isset($params['lab']) && trim($params['lab']) != '') {
            $countQuery = $countQuery->where('f.facility_id IN (' . $params['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $countQuery = $countQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $countQuery = $countQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $countQuery = $countQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
            $countQuery = $countQuery->where('covid19.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("covid19.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "covid19.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } else {
            if (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
                $countQuery = $countQuery->where("(covid19.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            } else if (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
                $countQuery = $countQuery->where("(covid19.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            }
        }

        //print_r($params['age']);die;
        if (isset($params['age']) && trim($params['age']) != '') {
            $age = explode(',', $params['age']);
            $where = '';
            for ($a = 0; $a < count($age); $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                } else if ($age[$a] == '2to5') {
                    $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                } else if ($age[$a] == '6to14') {
                    $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                } else if ($age[$a] == '15to49') {
                    $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                } else if ($age[$a] == '>=50') {
                    $where .= "(covid19.patient_age >= 50)";
                } else if ($age[$a] == 'unknown') {
                    $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown' OR covid19.patient_age = 'Unreported' OR covid19.patient_age = 'unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('covid19.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded' OR covid19.patient_gender = 'unreported' OR covid19.patient_gender = 'Unreported')");
        }


        $countQueryStr = $sql->buildSqlString($countQuery);
        // echo $countQueryStr;die;
        $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($countResult as $facility) {
            $result['clinic'][$l] = ucwords($facility['clinic_name']);
            $result['sample']['Results Not Available'][$l] = (isset($facility['total'])) ? $facility['total'] : null;
            $l++;
        }

        return $result;
    }


    public function fetchFilterSampleResultAwaitedDetails($parameters)
    {
        $logincontainer = new Container('credo');
        $queryContainer = new Container('query');
        
        
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('sample_code', "DATE_FORMAT(sample_collection_date,'%d-%b-%Y')", 'f.facility_code', 'f.facility_name', 'specimen_type', 'l.facility_code', 'l.facility_name', "DATE_FORMAT(sample_received_at_vl_lab_datetime,'%d-%b-%Y')");
        $orderColumns = array('sample_code', 'sample_collection_date', 'f.facility_code', 'specimen_type', 'l.facility_name', 'sample_received_at_vl_lab_datetime');
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */
        if (isset($parameters['daterange']) && trim($parameters['daterange']) != '') {
            $splitDate = explode('to', $parameters['daterange']);
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(array('sample_code', 'collectionDate' => new Expression('DATE(sample_collection_date)'), 'receivedDate' => new Expression('DATE(sample_received_at_vl_lab_datetime)')))
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facilityName' => 'facility_name', 'facilityCode' => 'facility_code'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=covid19.lab_id', array('labName' => 'facility_name'), 'left')
            ->where("(covid19.is_sample_rejected is NULL OR covid19.is_sample_rejected = '' OR covid19.is_sample_rejected = 'no') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or covid19.reason_for_sample_rejection = 0)");
        if (isset($parameters['daterange']) && trim($parameters['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $sQuery = $sQuery->where(array("covid19.sample_collection_date >='" . $splitDate[0] . " 00:00:00" . "'", "covid19.sample_collection_date <='" . $splitDate[1] . " 23:59:59" . "'"));
        } else {
            if (isset($parameters['frmSource']) && trim($parameters['frmSource']) == '<') {
                $sQuery = $sQuery->where("(covid19.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            } else if (isset($parameters['frmSource']) && trim($parameters['frmSource']) == '>') {
                $sQuery = $sQuery->where("(covid19.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            }
        }
        if (isset($parameters['provinces']) && trim($parameters['provinces']) != '') {
            $sQuery = $sQuery->where('l.facility_state IN (' . $parameters['provinces'] . ')');
        }
        if (isset($parameters['districts']) && trim($parameters['districts']) != '') {
            $sQuery = $sQuery->where('l.facility_district IN (' . $parameters['districts'] . ')');
        }
        if (isset($parameters['lab']) && trim($parameters['lab']) != '') {
            $sQuery = $sQuery->where('covid19.lab_id IN (' . $parameters['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $sQuery = $sQuery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('covid19.facility_id IN (' . $parameters['clinicId'] . ')');
        }

        //print_r($parameters['age']);die;
        if (isset($parameters['age']) && trim($parameters['age']) != '') {
            $where = '';
            $parameters['age'] = explode(',', $parameters['age']);
            for ($a = 0; $a < count($parameters['age']); $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($parameters['age'][$a] == '<2') {
                    $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                } else if ($parameters['age'][$a] == '2to5') {
                    $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                } else if ($parameters['age'][$a] == '6to14') {
                    $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                } else if ($parameters['age'][$a] == '15to49') {
                    $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                } else if ($parameters['age'][$a] == '>=50') {
                    $where .= "(covid19.patient_age >= 50)";
                } else if ($parameters['age'][$a] == 'unknown') {
                    $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown' OR covid19.patient_age = 'Unreported' OR covid19.patient_age = 'unreported')";
                }
            }
            $where = '(' . $where . ')';
            $sQuery = $sQuery->where($where);
        }


        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded' OR covid19.patient_gender = 'unreported' OR covid19.patient_gender = 'Unreported')");
        }



        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $queryContainer->resultsAwaitedQuery = $sQuery;
        $queryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $queryStr;die;
        $rResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(array('sample_code', 'collectionDate' => new Expression('DATE(sample_collection_date)'), 'receivedDate' => new Expression('DATE(sample_received_at_vl_lab_datetime)')))
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facilityName' => 'facility_name', 'facilityCode' => 'facility_code'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=covid19.lab_id', array('labName' => 'facility_name'), 'left')
            ->where("(covid19.is_sample_rejected is NULL OR covid19.is_sample_rejected = '' OR covid19.is_sample_rejected = 'no') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or covid19.reason_for_sample_rejection = 0)");
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
            $iQuery = $iQuery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        // echo($iQueryStr);die;
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $displayCollectionDate = $this->commonService->humanDateFormat($aRow['collectionDate']);
            $displayReceivedDate = $this->commonService->humanDateFormat($aRow['receivedDate']);
            $row = array();
            $row[] = $aRow['sample_code'];
            $row[] = $displayCollectionDate;
            $row[] = $aRow['facilityCode'] . ' - ' . ucwords($aRow['facilityName']);
            $row[] = (isset($aRow['specimen_type'])) ? ucwords($aRow['specimen_type']) : '';
            $row[] = (isset($aRow['labName'])) ? ucwords($aRow['labName']) : '';
            $row[] = $displayReceivedDate;
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function getMonthsByQuarter($quarter)
    {
        switch ($quarter) {
            case 1:
                return array('01', '02', '03');
            case 2:
                return array('04', '05', '06');
            case 3:
                return array('07', '08', '09');
            case 4:
                return array(10, 11, 12);
        }
    }

    public function fetchSampleDetails($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $facilityQuery = $sql->select()->from(array('f' => 'facility_details'))
                ->where(array('f.facility_type' => 2));
            if (isset($params['lab']) && trim($params['lab']) != '') {
                $facilityQuery = $facilityQuery->where('f.facility_id IN (' . $params['lab'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $facilityQuery = $facilityQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $facilityQueryStr = $sql->buildSqlString($facilityQuery);
            $facilityResult = $dbAdapter->query($facilityQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($facilityResult) && count($facilityResult) > 0) {

                $facilityIdList = array_column($facilityResult, 'facility_id');

                $countQuery = $sql->select()->from(array('covid19' => $this->table))->columns(array('total' => new Expression('COUNT(*)')))
                    ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.lab_id', array('facility_name', 'facility_code'))
                    ->where('covid19.lab_id IN ("' . implode('", "', $facilityIdList) . '")')
                    ->group('covid19.lab_id');

                /* if (!isset($params['fromSrc'])) {
                    $countQuery = $countQuery->where('(covid19.is_sample_rejected IS NOT NULL AND covid19.is_sample_rejected!= "")');
                } */
                if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                    $countQuery = $countQuery->where(array("covid19.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "covid19.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
                }
                if (isset($params['provinces']) && trim($params['provinces']) != '') {
                    $countQuery = $countQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
                }
                if (isset($params['districts']) && trim($params['districts']) != '') {
                    $countQuery = $countQuery->where('f.facility_district IN (' . $params['districts'] . ')');
                }
                if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                    $countQuery = $countQuery->where('covid19.facility_id IN (' . $params['clinicId'] . ')');
                }


                //print_r($params['age']);die;
                if (isset($params['age']) && trim($params['age']) != '') {
                    $age = explode(',', $params['age']);
                    $where = '';
                    for ($a = 0; $a < count($age); $a++) {
                        if (trim($where) != '') {
                            $where .= ' OR ';
                        }
                        if ($age[$a] == '<2') {
                            $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                        } else if ($age[$a] == '2to5') {
                            $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                        } else if ($age[$a] == '6to14') {
                            $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                        } else if ($age[$a] == '15to49') {
                            $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                        } else if ($age[$a] == '>=50') {
                            $where .= "(covid19.patient_age >= 50)";
                        } else if ($age[$a] == 'unknown') {
                            $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown')";
                        }
                    }
                    $where = '(' . $where . ')';
                    $countQuery = $countQuery->where($where);
                }

                if (isset($params['gender']) && $params['gender'] == 'F') {
                    $countQuery = $countQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
                } else if (isset($params['gender']) && $params['gender'] == 'M') {
                    $countQuery = $countQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
                } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                    $countQuery = $countQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded')");
                }



                $cQueryStr = $sql->buildSqlString($countQuery);
                // echo $cQueryStr;die;
                $countResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $i = 0;
                foreach ($countResult as $data) {
                    $result[$i][0] = $data['total'];
                    $result[$i][1] = ucwords($data['facility_name']);
                    $result[$i][2] = $data['facility_code'];
                    $i++;
                }
            }
        }
        return $result;
    }

    public function fetchBarSampleDetails($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))
                ->where(array('f.facility_type' => 2));
            if (isset($params['lab']) && trim($params['lab']) != '') {
                $fQuery = $fQuery->where('f.facility_id IN (' . $params['lab'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($facilityResult) && count($facilityResult) > 0) {

                $facilityIdList = array_column($facilityResult, 'facility_id');

                $countQuery = $sql->select()->from(array('covid19' => $this->table))
                    ->columns(
                        array(
                            'total' => new Expression('COUNT(*)'),
                            "positive" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive%' OR covid19.is_sample_rejected like 'Positive%') AND covid19.result not like '') THEN 1 ELSE 0 END)"),
                            "negative" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative%' OR covid19.is_sample_rejected like 'Negative%') AND covid19.result not like '') THEN 1 ELSE 0 END)"),
                            "rejected" => new Expression("SUM(CASE WHEN ((covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection != '' AND covid19.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END)"),
                        )
                    )
                    ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.lab_id', array('facility_name'))
                    ->where('covid19.lab_id IN ("' . implode('", "', $facilityIdList) . '")')
                    ->group('covid19.lab_id');

                if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                    $countQuery = $countQuery->where(array("covid19.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "covid19.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
                }
                if (isset($params['provinces']) && trim($params['provinces']) != '') {
                    $countQuery = $countQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
                }
                if (isset($params['districts']) && trim($params['districts']) != '') {
                    $countQuery = $countQuery->where('f.facility_district IN (' . $params['districts'] . ')');
                }
                if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                    $countQuery = $countQuery->where('covid19.facility_id IN (' . $params['clinicId'] . ')');
                }


                //print_r($params['age']);die;
                if (isset($params['age']) && trim($params['age']) != '') {
                    $age = explode(',', $params['age']);
                    $where = '';
                    for ($a = 0; $a < count($age); $a++) {
                        if (trim($where) != '') {
                            $where .= ' OR ';
                        }
                        if ($age[$a] == '<2') {
                            $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                        } else if ($age[$a] == '2to5') {
                            $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                        } else if ($age[$a] == '6to14') {
                            $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                        } else if ($age[$a] == '15to49') {
                            $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                        } else if ($age[$a] == '>=50') {
                            $where .= "(covid19.patient_age >= 50)";
                        } else if ($age[$a] == 'unknown') {
                            $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown')";
                        }
                    }
                    $where = '(' . $where . ')';
                    $countQuery = $countQuery->where($where);
                }

                if (isset($params['gender']) && $params['gender'] == 'F') {
                    $countQuery = $countQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
                } else if (isset($params['gender']) && $params['gender'] == 'M') {
                    $countQuery = $countQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
                } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                    $countQuery = $countQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded')");
                }

                $cQueryStr = $sql->buildSqlString($countQuery);
                // echo $cQueryStr;die;
                $barChartResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $j = 0;
                foreach ($barChartResult as $data) {
                    $result['sample']['Positive'][$j] = $data['positive'];
                    $result['sample']['Negative'][$j] = $data['negative'];
                    $result['sample']['Rejected'][$j] = $data['rejected'];
                    $result['lab'][$j] = ucwords($data['facility_name']);
                    $j++;
                }
            }
        }
        return $result;
    }

    public function fetchLabBarSampleDetails($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));

            $sQuery = $sql->select()->from(array('covid19' => $this->table))
                ->columns(
                    array(
                        'samples' => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                        "positive" => new Expression("SUM(CASE WHEN (((covid19.result = 'positive' or covid19.result = 'Positive' or covid19.result not like '') AND covid19.result IS NOT NULL AND covid19.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1 ELSE 0 END)"),
                        "negative" => new Expression("SUM(CASE WHEN (( covid19.result IS NOT NULL AND covid19.result!= '' AND covid19.result ='negative' AND covid19.result ='Negative' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.lab_id', array(), 'left')
                //->where("Month(sample_collection_date)='".$month."' AND Year(sample_collection_date)='".$year."'")
            ;

            $sQuery = $sQuery->where(
                "
                                        (sample_collection_date is not null AND sample_collection_date not like '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'"
            );

            if (isset($params['lab']) && trim($params['lab']) != '') {
                $sQuery = $sQuery->where('covid19.lab_id IN (' . $params['lab'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $sQuery = $sQuery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
            }
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $sQuery = $sQuery->where('covid19.facility_id IN (' . $params['clinicId'] . ')');
            }


            //print_r($params['age']);die;
            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $where = '';
                for ($a = 0; $a < count($age); $a++) {
                    if (trim($where) != '') {
                        $where .= ' OR ';
                    }
                    if ($age[$a] == '<2') {
                        $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                    } else if ($age[$a] == '2to5') {
                        $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                    } else if ($age[$a] == '6to14') {
                        $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                    } else if ($age[$a] == '15to49') {
                        $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                    } else if ($age[$a] == '>=50') {
                        $where .= "(covid19.patient_age >= 50)";
                    } else if ($age[$a] == 'unknown') {
                        $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown')";
                    }
                }
                $where = '(' . $where . ')';
                $sQuery = $sQuery->where($where);
            }
            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $sQuery = $sQuery->where("(covid19.result < 1000 or covid19.result = 'Target Not Detected' or covid19.result = 'TND' or covid19.result = 'tnd' or covid19.result= 'Below Detection Level' or covid19.result='BDL' or covid19.result='bdl' or covid19.result= 'Low Detection Level' or covid19.result='LDL' or covid19.result='ldl') AND covid19.result IS NOT NULL AND covid19.result!= '' AND covid19.result!='Failed' AND covid19.result!='failed' AND covid19.result!='Fail' AND covid19.result!='fail' AND covid19.result!='No Sample' AND covid19.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            } else if (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $sQuery = $sQuery->where("covid19.result IS NOT NULL AND covid19.result!= '' AND covid19.result >= 1000 AND covid19.result!='Failed' AND covid19.result!='failed' AND covid19.result!='Fail' AND covid19.result!='fail' AND covid19.result!='No Sample' AND covid19.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            }
            if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                $sQuery = $sQuery->where('covid19.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
            }
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $sQuery = $sQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $sQuery = $sQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $sQuery = $sQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded')");
            }


            $sQuery = $sQuery->group(array(new Expression('MONTH(sample_collection_date)')));
            $sQuery = $sQuery->order(array(new Expression('DATE(sample_collection_date)')));
            $sQueryStr = $sql->buildSqlString($sQuery);
            // echo $sQueryStr;die;
            $barChartResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $j = 0;
            foreach ($barChartResult as $data) {
                $result['rslt']['POSTIVE'][$j] = $data['positive'];
                $result['rslt']['NEGATIVE'][$j] = $data['negative'];
                $result['date'][$j] = $data['monthDate'];
                $j++;
            }
        }
        return $result;
    }

    public function fetchLabFilterSampleDetails($parameters)
    {
        $logincontainer = new Container('credo');
        $queryContainer = new Container('query');
        
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('DATE_FORMAT(sample_collection_date,"%d-%b-%Y")', 'specimen_type', 'facility_name');
        $orderColumns = array('sample_collection_date', 'sample_code', 'sample_code', 'sample_code', 'sample_code', 'sample_code', 'sample_code', 'specimen_type', 'facility_name');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(array(
                'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                "total_samples_received" => new Expression("(COUNT(*))"),
                "total_samples_tested" => new Expression("(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) OR (covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection != '' AND covid19.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                "total_samples_pending" => new Expression("(SUM(CASE WHEN ((covid19.result IS NULL OR covid19.result = '' OR covid19.result = 'NULL' OR sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00') AND (covid19.reason_for_sample_rejection IS NULL OR covid19.reason_for_sample_rejection = '' OR covid19.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                "rejected_samples" => new Expression("SUM(CASE WHEN (covid19.reason_for_sample_rejection !='' AND covid19.reason_for_sample_rejection !='0' AND covid19.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)")
            ))
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=covid19.lab_id', array(), 'left')
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND f.facility_type = 1")
            ->group(new Expression('DATE(sample_collection_date)'))
            ->group('covid19.specimen_type')
            ->group('covid19.facility_id');
        //filter start
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $sQuery = $sQuery->where(array("covid19.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "covid19.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
        }
        if (isset($parameters['lab']) && trim($parameters['lab']) != '') {
            $sQuery = $sQuery->where('covid19.lab_id IN (' . $parameters['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $sQuery = $sQuery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (isset($parameters['provinces']) && trim($parameters['provinces']) != '') {
            $sQuery = $sQuery->where('l.facility_state IN (' . $parameters['provinces'] . ')');
        }
        if (isset($parameters['districts']) && trim($parameters['districts']) != '') {
            $sQuery = $sQuery->where('l.facility_district IN (' . $parameters['districts'] . ')');
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('covid19.facility_id IN (' . $params['clinicId'] . ')');
        }


        if (isset($parameters['age']) && trim($parameters['age']) != '') {
            $age = explode(',', $parameters['age']);
            $where = '';
            for ($a = 0; $a < count($age); $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                } else if ($age[$a] == '2to5') {
                    $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                } else if ($age[$a] == '6to14') {
                    $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                } else if ($age[$a] == '15to49') {
                    $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                } else if ($age[$a] == '>=50') {
                    $where .= "(covid19.patient_age >= 50)";
                } else if ($age[$a] == 'unknown') {
                    $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown')";
                }
            }
            $where = '(' . $where . ')';
            $sQuery = $sQuery->where($where);
        }


        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded')");
        }


        //filter end
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }
        $queryContainer->labTestedSampleQuery = $sQuery;
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance
        // echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        // die($fQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(array(
                "total_samples_received" => new Expression("(COUNT(*))")
            ))
            ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=covid19.lab_id', array(), 'left')
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND f.facility_type = 1")
            ->group(new Expression('DATE(sample_collection_date)'))
            ->group('covid19.specimen_type')
            ->group('covid19.facility_id');
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
            $iQuery = $iQuery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = array();
            $sampleCollectionDate = '';
            if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != null && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                $sampleCollectionDate = $this->commonService->humanDateFormat($aRow['sampleCollectionDate']);
            }
            $row[] = $sampleCollectionDate;
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['rejected_samples'];
            $row[] = ucwords($aRow['specimen_type']);
            $row[] = ucwords($aRow['facility_name']);
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchFilterSampleDetails($parameters)
    {
        $logincontainer = new Container('credo');
        $queryContainer = new Container('query');
        
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('facility_name', 'sample_code', 'sample_code', 'sample_code', 'sample_code', 'sample_code', 'sample_code');
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
        */
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
        }
        $sQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('covid19' => $this->table), 'covid19.lab_id=f.facility_id', array(
                "total_samples_received" => new Expression("(COUNT(*))"),
                "total_samples_tested" => new Expression("(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) OR (covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection != '' AND covid19.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                "total_samples_pending" => new Expression("(SUM(CASE WHEN ((covid19.result IS NULL OR covid19.result = '' OR covid19.result = 'NULL' OR sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00') AND (covid19.reason_for_sample_rejection IS NULL OR covid19.reason_for_sample_rejection = '' OR covid19.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                "rejected_samples" => new Expression("SUM(CASE WHEN (covid19.reason_for_sample_rejection !='' AND covid19.reason_for_sample_rejection !='0' AND covid19.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)")
            ))
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND covid19.lab_id !=0")
            ->group('covid19.lab_id');
        if (isset($parameters['provinces']) && trim($parameters['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state IN (' . $parameters['provinces'] . ')');
        }
        if (isset($parameters['districts']) && trim($parameters['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district IN (' . $parameters['districts'] . ')');
        }
        if (isset($parameters['lab']) && trim($parameters['lab']) != '') {
            $sQuery = $sQuery->where('covid19.lab_id IN (' . $parameters['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $sQuery = $sQuery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $sQuery = $sQuery->where(array("covid19.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "covid19.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('covid19.facility_id IN (' . $parameters['clinicId'] . ')');
        }


        if (isset($parameters['age']) && trim($parameters['age']) != '') {
            $where = '';
            $parameters['age'] = explode(',', $parameters['age']);
            for ($a = 0; $a < count($parameters['age']); $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($parameters['age'][$a] == '<2') {
                    $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                } else if ($parameters['age'][$a] == '2to5') {
                    $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                } else if ($parameters['age'][$a] == '6to14') {
                    $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                } else if ($parameters['age'][$a] == '15to49') {
                    $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                } else if ($parameters['age'][$a] == '>=50') {
                    $where .= "(covid19.patient_age >= 50)";
                } else if ($parameters['age'][$a] == 'unknown') {
                    $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown')";
                }
            }
            $where = '(' . $where . ')';
            $sQuery = $sQuery->where($where);
        }


        if (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'sample_tested') {
            $sQuery = $sQuery->where("((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') OR (covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection != '' AND covid19.reason_for_sample_rejection != 0))");
        } else if (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'samples_not_tested') {
            $sQuery = $sQuery->where("(covid19.result IS NULL OR covid19.result = '' OR covid19.result = 'NULL' OR sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00') AND (covid19.reason_for_sample_rejection IS NULL OR covid19.reason_for_sample_rejection = '' OR covid19.reason_for_sample_rejection = 0)");
        } else if (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'sample_rejected') {
            $sQuery = $sQuery->where("covid19.reason_for_sample_rejection IS NOT NULL AND covid19.reason_for_sample_rejection != '' AND covid19.reason_for_sample_rejection != 0");
        }
        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded')");
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }
        $queryContainer->sampleResultQuery = $sQuery;
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance
        // echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('covid19' => $this->table), 'covid19.lab_id=f.facility_id', array(
                "total_samples_received" => new Expression("(COUNT(*))")
            ))
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND covid19.lab_id !=0")
            ->group('covid19.lab_id');
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
            $iQuery = $iQuery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        //print_r($parameters);die;
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = ucwords($aRow['facility_name']);
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['rejected_samples'];
            $output['aaData'][] = $row;
        }
        return $output;
    }

    //get eid out comes result
    public function fetchCovid19OutComes($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $vlOutComeResult = array();
        
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $sQuery = $sql->select()->from(array('covid19' => $this->table))
                ->columns(
                    array(
                        "positive" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive%' OR covid19.result like 'Positive%' ) AND covid19.result not like '') THEN 1 ELSE 0 END)"),
                        "negative" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative%' OR covid19.result like 'Negative%' ) AND covid19.result not like '') THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.lab_id', array());
            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
            }
            if (isset($params['lab']) && trim($params['lab']) != '') {
                $sQuery = $sQuery->where('covid19.lab_id IN (' . $params['lab'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $sQuery = $sQuery->where('covid19.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                $sQuery = $sQuery->where(array("covid19.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "covid19.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
            }
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $sQuery = $sQuery->where('covid19.facility_id IN (' . $params['clinicId'] . ')');
            }


            if (isset($params['age']) && is_array($params['age'])) {
                $params['age'] = implode(',', $params['age']);
            }
            if (isset($params['age']) && trim($params['age']) != '') {
                $where = '';
                $params['age'] = explode(',', $params['age']);
                for ($a = 0; $a < count($params['age']); $a++) {
                    if (trim($where) != '') {
                        $where .= ' OR ';
                    }
                    if ($params['age'][$a] == '<2') {
                        $where .= "(covid19.patient_age > 0 AND covid19.patient_age < 2)";
                    } else if ($params['age'][$a] == '2to5') {
                        $where .= "(covid19.patient_age >= 2 AND covid19.patient_age <= 5)";
                    } else if ($params['age'][$a] == '6to14') {
                        $where .= "(covid19.patient_age >= 6 AND covid19.patient_age <= 14)";
                    } else if ($params['age'][$a] == '15to49') {
                        $where .= "(covid19.patient_age >= 15 AND covid19.patient_age <= 49)";
                    } else if ($params['age'][$a] == '>=50') {
                        $where .= "(covid19.patient_age >= 50)";
                    } else if ($params['age'][$a] == 'unknown') {
                        $where .= "(covid19.patient_age IS NULL OR covid19.patient_age = '' OR covid19.patient_age = 'Unknown' OR covid19.patient_age = 'unknown')";
                    }
                }
                $where = '(' . $where . ')';
                $sQuery = $sQuery->where($where);
            }

            if (isset($params['gender']) && $params['gender'] == 'F') {
                $sQuery = $sQuery->where("covid19.patient_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $sQuery = $sQuery->where("covid19.patient_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $sQuery = $sQuery->where("(covid19.patient_gender IS NULL OR covid19.patient_gender = '' OR covid19.patient_gender ='Not Recorded' OR covid19.patient_gender = 'not recorded')");
            }

            $queryStr = $sql->buildSqlString($sQuery);
            // die($queryStr);
            $vlOutComeResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        }
        return $vlOutComeResult;
    }
    //end lab dashboard details

    public function fetchEidOutcomesByAgeInLabsDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        
        $eidOutcomesQuery = $sql->select()
            ->from(array('covid19' => 'dash_form_covid19'))
            ->columns(
                array(
                    'noDatan' => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND (covid19.patient_dob IS NULL OR covid19.patient_dob = '0000-00-00'))THEN 1 ELSE 0 END)"),

                    'noDatap' => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND (covid19.patient_dob IS NULL OR covid19.patient_dob ='0000-00-00'))THEN 1 ELSE 0 END)"),

                    'less2n' => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "')THEN 1 ELSE 0 END)"),

                    'less2p' => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "')THEN 1 ELSE 0 END)"),

                    '2to9n' => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND (covid19.patient_dob >= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "' AND covid19.patient_dob <= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '2to9p' => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND (covid19.patient_dob >= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "' AND covid19.patient_dob <= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '9to12n' => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND (covid19.patient_dob >= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "' AND covid19.patient_dob <= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '9to12p' => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND (covid19.patient_dob >= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "' AND covid19.patient_dob <= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '12to24n' => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND (covid19.patient_dob >= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "' AND covid19.patient_dob <= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '12to24p' => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND (covid19.patient_dob >= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "' AND covid19.patient_dob <= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    'above24n' => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob >= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "')THEN 1 ELSE 0 END)"),

                    'above24p' => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob >= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "')THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = covid19.facility_id', array());

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $eidOutcomesQuery = $eidOutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $facilityIdList = array();
        if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                ->where('f.facility_type = 2 AND f.status="active"');
            $fQuery = $fQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $facilityIdList = array_column($facilityResult, 'facility_id');
        } else if (!empty($this->mappedFacilities)) {
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $facilityIdList = array_column($facilityResult, 'facility_id');
        }

        if ($facilityIdList != null) {
            $eidOutcomesQuery = $eidOutcomesQuery->where('covid19.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
        }

        $eidOutcomesQueryStr = $sql->buildSqlString($eidOutcomesQuery);
        $result = $this->commonService->cacheQuery($eidOutcomesQueryStr, $dbAdapter);
        return $result[0];
    }

    public function fetchEidPositivityRateDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        
        $startMonth = "";
        $endMonth = "";
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = date('Y-m-01', strtotime(str_replace(' ', '-', $params['fromDate'])));
            $endMonth = date('Y-m-t', strtotime(str_replace(' ', '-', $params['toDate'])));

            $monthList = $this->commonService->getMonthsInRange($startMonth, $endMonth);
            /* foreach($monthList as $key=>$list){
                $searchVal[$key] =  new Expression("AVG(CASE WHEN (covid19.result like 'positive%' AND covid19.result not like '' AND sample_collection_date LIKE '%".$list."%') THEN 1 ELSE 0 END)");
            } */
            $sQuery = $sql->select()->from(array('covid19' => 'dash_form_covid19'))->columns(array(
                'monthYear' => new Expression("DATE_FORMAT(sample_collection_date, '%b-%Y')"),
                'positive_rate' => new Expression("ROUND(((SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
            ))
                ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.lab_id', array('facility_name'))
                ->where("(sample_collection_date is not null)
                                    AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                    AND DATE(sample_collection_date) <= '" . $endMonth . "'")
                ->group(array("lab_id", new Expression("DATE_FORMAT(sample_collection_date, '%m-%Y')")))
                ->order(array("lab_id", new Expression("DATE_FORMAT(sample_collection_date, '%m-%Y')")));

            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
            }
            if (isset($params['clinics']) && trim($params['clinics']) != '') {
                $sQuery = $sQuery->where('covid19.facility_id IN (' . $params['clinics'] . ')');
            }

            $facilityIdList = array();
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $mQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_type = 2 AND f.status="active"');
                $mQuery = $mQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
                $mQueryStr = $sql->buildSqlString($mQuery);
                $facilityResult = $dbAdapter->query($mQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            } else if (!empty($this->mappedFacilities)) {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            }

            if ($facilityIdList != null) {
                $sQuery = $sQuery->where('covid19.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $sQueryStr = $sql->buildSqlString($sQuery);
            // echo $sQueryStr;die;
            $result = $this->commonService->cacheQuery($sQueryStr, $dbAdapter);
            return array('result' => $result, 'month' => $monthList);
        } else {
            return 0;
        }
    }
    public function insertOrUpdate($arrayData)
    {
        $query = 'INSERT INTO `' . $this->table . '` (' . implode(',', array_keys($arrayData)) . ') VALUES (' . implode(',', array_fill(1, count($arrayData), '?')) . ') ON DUPLICATE KEY UPDATE ' . implode(' = ?,', array_keys($arrayData)) . ' = ?';
        $result =  $this->adapter->query($query, array_merge(array_values($arrayData), array_values($arrayData)));
        return $result->getGeneratedValue();
    }
}
