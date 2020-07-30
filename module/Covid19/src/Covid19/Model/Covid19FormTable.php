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
            $queryStr = $queryStr->where("(sample_collection_date is not null AND sample_collection_date != '')
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
            //->join(array('rs' => 'r_sample_type'), 'rs.sample_id=covid19.sample_type', array('sample_name'))
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
            //->join(array('rs' => 'r_sample_type'), 'rs.sample_id=covid19.sample_type', array('sample_name'))
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
}
