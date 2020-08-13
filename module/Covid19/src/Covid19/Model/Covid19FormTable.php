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

    protected $table = 'dash_covid19_form';
    public $sm = null;
    public $config = null;
    protected $translator = null;

    public function __construct(Adapter $adapter, $sm = null)
    {
        $this->adapter = $adapter;
        $this->sm = $sm;
        $this->config = $this->sm->get('Config');
        $this->translator = $this->sm->get('translator');
    }

    public function getSummaryTabDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);

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
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $queryStr = $queryStr->where("(sample_collection_date is not null AND sample_collection_date not like '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $queryStr = $sql->getSqlStringForSqlObject($queryStr);

        // echo $queryStr;die;

        return $common->cacheQuery($queryStr, $dbAdapter);
    }

    public function fetchSamplesReceivedBarChartDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);

        $sQuery = $sql->select()->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                    "total" => new Expression("COUNT(*)"),
                )
            )
            ->join(array('rs' => 'r_eid_sample_type'), 'rs.sample_id=covid19.specimen_type', array('sample_name'), 'left')
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
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $sQuery = $sQuery->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $queryStr = $sql->getSqlStringForSqlObject($sQuery);
        // echo $queryStr;die;
        //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sampleResult = $common->cacheQuery($queryStr, $dbAdapter);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        // echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }

        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);



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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        // echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
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
        $common = new CommonService($this->sm);

        $sQuery = $sql->select()
            ->from(array('covid19' => $this->table))
            ->columns(
                array(
                    "monthyear" => new Expression("DATE_FORMAT(sample_collection_date, '%b %y')"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN (covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL') THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)")
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
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $sQuery = $sQuery->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $queryStr = $sql->getSqlStringForSqlObject($sQuery);
        //echo $queryStr;die;
        //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sampleResult = $common->cacheQuery($queryStr, $dbAdapter);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
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
            ->join(array('r_r_r' => 'r_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=covid19.reason_for_sample_rejection', array('rejection_reason_id'))
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
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $mostRejectionQuery = $mostRejectionQuery->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $mostRejectionQueryStr = $sql->getSqlStringForSqlObject($mostRejectionQuery);
        $mostRejectionResult = $dbAdapter->query($mostRejectionQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if (isset($mostRejectionResult) && count($mostRejectionResult) > 0) {
            foreach ($mostRejectionResult as $rejectionReason) {
                $mostRejectionReasons[] = $rejectionReason['rejection_reason_id'];
            }
            $mostRejectionReasons[] = 0;
        }
        $result = array();
        $common = new CommonService($this->sm);
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
                    ->join(array('r_r_r' => 'r_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=covid19.reason_for_sample_rejection', array('rejection_reason_name'))
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
                    $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
                    $rejectionQuery = $rejectionQuery->where("(sample_collection_date is not null)
                                                AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                                AND DATE(sample_collection_date) <= '" . $endMonth . "'");
                }
                if ($mostRejectionReasons[$m] == 0) {
                    $rejectionQuery = $rejectionQuery->where('covid19.reason_for_sample_rejection is not null and covid19.reason_for_sample_rejection!= "" and covid19.reason_for_sample_rejection NOT IN("' . implode('", "', $mostRejectionReasons) . '")');
                } else {
                    $rejectionQuery = $rejectionQuery->where('covid19.reason_for_sample_rejection = "' . $mostRejectionReasons[$m] . '"');
                }
                $rejectionQueryStr = $sql->getSqlStringForSqlObject($rejectionQuery);
                $rejectionResult = $common->cacheQuery($rejectionQueryStr, $dbAdapter);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null)
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
            ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date not like '')
                        AND DATE(covid19.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(covid19.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
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
        $common = new CommonService($this->sm);

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
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $queryContainer->indicatorSummaryQuery = $samplesReceivedSummaryQuery;
        $samplesReceivedSummaryCacheQuery = $sql->getSqlStringForSqlObject($samplesReceivedSummaryQuery);
        $samplesReceivedSummaryResult = $common->cacheQuery($samplesReceivedSummaryCacheQuery, $dbAdapter);
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
        $common = new CommonService($this->sm);
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
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $covid19OutcomesQuery = $covid19OutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $covid19OutcomesQueryStr = $sql->getSqlStringForSqlObject($covid19OutcomesQuery);
        $result = $common->cacheQuery($covid19OutcomesQueryStr, $dbAdapter);
        return $result[0];
    }

    public function fetchCovid19OutcomesByAgeDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        /* Dynamic year range */
        $ageGroup = array('2', '2-5', '6-14', '15-49', '50');

        $ageGroupArray['noDatan'] = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND (covid19.patient_dob IS NULL OR covid19.patient_dob = '0000-00-00'))THEN 1 ELSE 0 END)");
        $ageGroupArray['noDatap'] = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND (covid19.patient_dob IS NULL OR covid19.patient_dob = '0000-00-00'))THEN 1 ELSE 0 END)");
        foreach($ageGroup as $key=>$age){
            if($key == 0){
                $ageGroupArray[$age.'n']   = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-".$age.' YEARS')) . "')THEN 1 ELSE 0 END)");
                $ageGroupArray[$age.'p']   = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-".$age.' YEARS')) . "')THEN 1 ELSE 0 END)");
            } elseif($key == 4){
                $ageGroupArray[$age.'n']   = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-".$age.' YEARS')) . "')THEN 1 ELSE 0 END)");
                $ageGroupArray[$age.'p']   = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-".$age.' YEARS')) . "')THEN 1 ELSE 0 END)");
            } else{
                $keyIndex = explode('-',$age);
                $ageGroupArray[$age.'n']   = new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result = 'Negative' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-".$keyIndex[0].' YEARS')) . "' AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-".$keyIndex[1].' YEARS')) . "')THEN 1 ELSE 0 END)");
                $ageGroupArray[$age.'p']   = new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result = 'Positive' ) AND covid19.patient_dob <= '" . date('Y-m-d', strtotime("-".$keyIndex[0].' YEARS')) . "' AND covid19.patient_dob >= '" . date('Y-m-d', strtotime("-".$keyIndex[1].' YEARS')) . "')THEN 1 ELSE 0 END)");
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
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $covid19OutcomesQuery = $covid19OutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $covid19OutcomesQueryStr = $sql->getSqlStringForSqlObject($covid19OutcomesQuery);
        // echo $covid19OutcomesQueryStr;die;
        $result = $common->cacheQuery($covid19OutcomesQueryStr, $dbAdapter);
        return $result[0];
    }

    public function fetchCovid19OutcomesByProvinceDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
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

        $covid19OutcomesQueryStr = $sql->getSqlStringForSqlObject($covid19OutcomesQuery);
        $result = $common->cacheQuery($covid19OutcomesQueryStr, $dbAdapter);
        return $result;
    }

    public function fetchTATDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
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
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $covid19OutcomesQuery = $covid19OutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $covid19OutcomesQueryStr = $sql->getSqlStringForSqlObject($covid19OutcomesQuery);
        // echo $covid19OutcomesQueryStr;die;
        $result = $dbAdapter->query($covid19OutcomesQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        // $result = $common->cacheQuery($covid19OutcomesQueryStr, $dbAdapter);
        return $result[0];
    }
    // SUMMARY DASHBOARD END
}
