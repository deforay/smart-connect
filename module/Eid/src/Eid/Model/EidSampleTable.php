<?php

namespace Eid\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Expression;
//use Laminas\Db\Sql\Where;
use \Application\Service\CommonService;
use Zend\Debug\Debug;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Countries
 *
 * @author amit
 */
class EidSampleTable extends AbstractTableGateway
{

    protected $table = 'dash_eid_form';
    public $sm = null;
    public $config = null;
    protected $dbsId = null;
    protected $plasmaId = null;
    protected $mappedFacilities = null;
    protected $translator = null;

    public function __construct(Adapter $adapter, $sm = null, $mappedFacilities = null, $table = null)
    {
        $this->adapter = $adapter;
        $this->sm = $sm;
        if ($table != null && !empty($table)) {
            $this->table = $table;
        }
        $this->config = $this->sm->get('Config');
        $this->translator = $this->sm->get('translator');
        $this->dbsId = $this->config['defaults']['dbsId'];
        $this->plasmaId = $this->config['defaults']['plasmaId'];
        $this->mappedFacilities = $mappedFacilities;
    }



    public function getSummaryTabDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);

        $queryStr = $sql->select()->from(array('eid' => $this->table))
            ->columns(array(
                "total_samples_received" => new Expression("COUNT(*)"),
                "total_samples_tested" => new Expression("(SUM(CASE WHEN (((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL'))) THEN 1 ELSE 0 END))"),
                "positive_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                "rejected_samples" => new Expression("SUM(CASE WHEN (eid.reason_for_sample_rejection !='' AND eid.reason_for_sample_rejection !='0' AND eid.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                "tat" => new Expression("AVG((DATEDIFF(result_printed_datetime,sample_collection_date)))")
            ));

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $queryStr = $queryStr->where("(sample_collection_date is not null AND sample_collection_date not like '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $queryStr = $sql->buildSqlString($queryStr);

        // echo $queryStr;die;

        return $common->cacheQuery($queryStr, $dbAdapter);
    }

    public function fetchSamplesReceivedBarChartDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);

        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                    "total" => new Expression("COUNT(*)"),
                    "initial_pcr" => new Expression("SUM(CASE WHEN (pcr_test_performed_before like 'no' OR pcr_test_performed_before is NULL OR pcr_test_performed_before like '') THEN 1 ELSE 0 END)"),
                    "second_third_pcr" => new Expression("SUM(CASE WHEN (pcr_test_performed_before is not null AND pcr_test_performed_before like 'yes') THEN 1 ELSE 0 END)")
                )
            )
            ->group(array(new Expression('YEAR(eid.sample_collection_date)'), new Expression('MONTH(eid.sample_collection_date)')));

        if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
            $sQuery = $sQuery->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'));
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $sQuery = $sQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
        }

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $sQuery = $sQuery->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $queryStr = $sql->buildSqlString($sQuery);
        //echo $queryStr;die;
        //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sampleResult = $common->cacheQuery($queryStr, $dbAdapter);
        $j = 0;
        foreach ($sampleResult as $row) {

            if (isset($params['sampleResgisteredEidTestType']) && ($params['sampleResgisteredEidTestType'] == 'initialPcr' || $params['sampleResgisteredEidTestType'] == '')) {
                $result['sampleName']['Initial PCR'][$j] = (isset($row["initial_pcr"])) ? $row["initial_pcr"] : 0;
            }

            if (isset($params['sampleResgisteredEidTestType']) && (trim($params['sampleResgisteredEidTestType']) == 'secondThirdPcr' || $params['sampleResgisteredEidTestType'] == '')) {
                $result['sampleName']['Second/Third PCR'][$j] = (isset($row["second_third_pcr"])) ? $row["second_third_pcr"] : 0;
            }



            $result['date'][$j] = $row['monthDate'];
            $j++;
        }

        return $result;
    }

    /* Samples Received Province*/
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
        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN ((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL') OR (eid.reason_for_sample_rejection IS NOT NULL AND eid.reason_for_sample_rejection != '' AND eid.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_pending" => new Expression("(SUM(CASE WHEN ((eid.result IS NULL OR eid.result = '' OR eid.result = 'NULL') AND (eid.reason_for_sample_rejection IS NULL OR eid.reason_for_sample_rejection = '' OR eid.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("SUM(CASE WHEN (eid.reason_for_sample_rejection !='' AND eid.reason_for_sample_rejection !='0' AND eid.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                    "initial_pcr_percentage" => new Expression("TRUNCATE(((SUM(pcr_test_performed_before like 'no' OR pcr_test_performed_before is NULL OR pcr_test_performed_before like '')/COUNT(*))*100),2)"),
                    "second_third_pcr_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (pcr_test_performed_before like 'yes') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state');
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $sQuery = $sQuery
                ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '')
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $iQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '' AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state');

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '')
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
            $row[] = (round($aRow['initial_pcr_percentage']) > 0) ? $aRow['initial_pcr_percentage'] . '%' : '';
            $row[] = (round($aRow['second_third_pcr_percentage']) > 0) ? $aRow['second_third_pcr_percentage'] . '%' : '';
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
        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN ((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL') OR (eid.reason_for_sample_rejection IS NOT NULL AND eid.reason_for_sample_rejection != '' AND eid.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_pending" => new Expression("(SUM(CASE WHEN ((eid.result IS NULL OR eid.result = '' OR eid.result = 'NULL') AND (eid.reason_for_sample_rejection IS NULL OR eid.reason_for_sample_rejection = '' OR eid.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("SUM(CASE WHEN (eid.reason_for_sample_rejection !='' AND eid.reason_for_sample_rejection !='0' AND eid.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                    "initial_pcr_percentage" => new Expression("TRUNCATE(((SUM(pcr_test_performed_before like 'no' OR pcr_test_performed_before is NULL OR pcr_test_performed_before like '')/COUNT(*))*100),2)"),
                    "second_third_pcr_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (pcr_test_performed_before like 'yes') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))

            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $sQuery = $sQuery
                ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '')
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $iQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))

            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '')
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
            $row[] = (round($aRow['initial_pcr_percentage']) > 0) ? $aRow['initial_pcr_percentage'] . '%' : '';
            $row[] = (round($aRow['second_third_pcr_percentage']) > 0) ? $aRow['second_third_pcr_percentage'] . '%' : '';
            $output['aaData'][] = $row;
        }

        return $output;
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
        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN ((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL') OR (eid.reason_for_sample_rejection IS NOT NULL AND eid.reason_for_sample_rejection != '' AND eid.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_pending" => new Expression("(SUM(CASE WHEN ((eid.result IS NULL OR eid.result = '' OR eid.result = 'NULL') AND (eid.reason_for_sample_rejection IS NULL OR eid.reason_for_sample_rejection = '' OR eid.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "initial_pcr_percentage" => new Expression("TRUNCATE(((SUM(pcr_test_performed_before like 'no' OR pcr_test_performed_before is NULL OR pcr_test_performed_before like '')/COUNT(*))*100),2)"),
                    "second_third_pcr_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (pcr_test_performed_before like 'yes') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('eid.facility_id');

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
        $iQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(array('eid_id'))
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array())
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array())
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array())
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('eid.facility_id');


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(eid.sample_collection_date is not null)
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
            $row[] = (round($aRow['initial_pcr_percentage']) > 0) ? $aRow['initial_pcr_percentage'] . '%' : '';
            $row[] = (round($aRow['second_third_pcr_percentage']) > 0) ? $aRow['second_third_pcr_percentage'] . '%' : '';

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
            ->from(array('eid' => $this->table))
            ->columns(
                array(
                    "monthyear" => new Expression("DATE_FORMAT(sample_collection_date, '%b %y')"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN (eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL') THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END)")
                )
            )

            ->group(array(new Expression('YEAR(sample_collection_date)'), new Expression('MONTH(sample_collection_date)')));

        if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
            $sQuery = $sQuery->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'));
        }

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }

        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $sQuery = $sQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $sQuery = $sQuery->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $queryStr = $sql->buildSqlString($sQuery);
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
        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL'))) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result like 'Negative')) THEN 1 ELSE 0 END)"),
                    //"total_positive_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (eid.result < 1000 or eid.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                    "positive_rate" => new Expression("ROUND(((SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $sQuery = $sQuery
                ->where("(eid.sample_collection_date is not null)
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $iQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(eid.sample_collection_date is not null)
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL'))) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result like 'Negative')) THEN 1 ELSE 0 END)"),
                    //"total_positive_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (eid.result < 1000 or eid.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                    "positive_rate" => new Expression("ROUND(((SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $sQuery = $sQuery
                ->where("(eid.sample_collection_date is not null)
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $iQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(eid.sample_collection_date is not null)
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL'))) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result like 'Negative')) THEN 1 ELSE 0 END)"),
                    //"total_positive_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (eid.result < 1000 or eid.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                    "positive_rate" => new Expression("ROUND(((SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('eid.facility_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $sQuery = $sQuery
                ->where("(eid.sample_collection_date is not null)
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $iQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('eid.facility_id');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(eid.sample_collection_date is not null)
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $mostRejectionQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(array('rejections' => new Expression('COUNT(*)')))
            ->join(array('r_r_r' => 'r_eid_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=eid.reason_for_sample_rejection', array('rejection_reason_id'))
            ->group('eid.reason_for_sample_rejection')
            ->order('rejections DESC')
            ->limit(4);

        if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'));
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
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
        $common = new CommonService($this->sm);
        $start = strtotime($params['fromDate']);
        $end = strtotime($params['toDate']);

        $j = 0;
        while ($start <= $end) {
            $month = date('m', $start);
            $year = date('Y', $start);
            $monthYearFormat = date("M-Y", $start);
            for ($m = 0; $m < count($mostRejectionReasons); $m++) {
                $rejectionQuery = $sql->select()->from(array('eid' => $this->table))
                    ->columns(array('rejections' => new Expression('COUNT(*)')))
                    ->join(array('r_r_r' => 'r_eid_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=eid.reason_for_sample_rejection', array('rejection_reason_name'))
                    ->where("MONTH(sample_collection_date)='" . $month . "' AND Year(sample_collection_date)='" . $year . "'");


                if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
                    $rejectionQuery = $rejectionQuery->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'));
                }
                if (isset($params['provinces']) && trim($params['provinces']) != '') {
                    $rejectionQuery = $rejectionQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
                }
                if (isset($params['districts']) && trim($params['districts']) != '') {
                    $rejectionQuery = $rejectionQuery->where('f.facility_district IN (' . $params['districts'] . ')');
                }
                if (isset($params['clinics']) && trim($params['clinics']) != '') {
                    $rejectionQuery = $rejectionQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
                }
                if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                    $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
                    $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
                    $rejectionQuery = $rejectionQuery->where("(sample_collection_date is not null)
                                                AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                                AND DATE(sample_collection_date) <= '" . $endMonth . "'");
                }
                if ($mostRejectionReasons[$m] == 0) {
                    $rejectionQuery = $rejectionQuery->where('eid.reason_for_sample_rejection is not null and eid.reason_for_sample_rejection!= "" and eid.reason_for_sample_rejection NOT IN("' . implode('", "', $mostRejectionReasons) . '")');
                } else {
                    $rejectionQuery = $rejectionQuery->where('eid.reason_for_sample_rejection = "' . $mostRejectionReasons[$m] . '"');
                }
                $rejectionQueryStr = $sql->buildSqlString($rejectionQuery);
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
        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)'),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "rejection_rate" => new Expression("ROUND(((SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))/(COUNT(*)))*100,2)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $sQuery = $sQuery
                ->where("(eid.sample_collection_date is not null)
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $iQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)')
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(eid.sample_collection_date is not null)
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)'),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "rejection_rate" => new Expression("ROUND(((SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))/(COUNT(*)))*100,2)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '' AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $sQuery = $sQuery
                ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '')
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $iQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)')
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_state', array('province' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '' AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '')
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)'),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "rejection_rate" => new Expression("ROUND(((SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))/(COUNT(*)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '' AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $sQuery = $sQuery
                ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '')
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $iQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)')
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '' AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_id');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
            $iQuery = $iQuery
                ->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '')
                        AND DATE(eid.sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(eid.sample_collection_date) <= '" . $endMonth . "'");
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
        $common = new CommonService($this->sm);

        $samplesReceivedSummaryQuery = $sql->select()
            ->from(array('eid' => $this->table))
            ->columns(
                array(
                    "monthyear" => new Expression("DATE_FORMAT(sample_collection_date, '%b %y')"),
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN (eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL') THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result like 'Negative' )) THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = eid.facility_id')
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
            $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
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
        $samplesReceivedSummaryCacheQuery = $sql->buildSqlString($samplesReceivedSummaryQuery);
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

    public function fetchEidOutcomesDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $eidOutcomesQuery = $sql->select()
            ->from(array('eid' => 'dash_eid_form'))
            ->columns(
                array(
                    "total_samples" => new Expression("SUM(CASE WHEN ((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL')) THEN 1 ELSE 0 END)"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' )) THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = eid.facility_id', array());

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $eidOutcomesQuery = $eidOutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $eidOutcomesQueryStr = $sql->buildSqlString($eidOutcomesQuery);
        $result = $common->cacheQuery($eidOutcomesQueryStr, $dbAdapter);
        return $result[0];
    }

    public function fetchEidOutcomesByAgeDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $eidOutcomesQuery = $sql->select()
            ->from(array('eid' => 'dash_eid_form'))
            ->columns(
                array(
                    'noDatan' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND (eid.child_dob IS NULL OR eid.child_dob = '0000-00-00'))THEN 1 ELSE 0 END)"),

                    'noDatap' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND (eid.child_dob IS NULL OR eid.child_dob ='0000-00-00'))THEN 1 ELSE 0 END)"),

                    'less2n' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND eid.child_dob <= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "')THEN 1 ELSE 0 END)"),

                    'less2p' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND eid.child_dob <= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "')THEN 1 ELSE 0 END)"),

                    '2to9n' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '2to9p' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '9to12n' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '9to12p' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '12to24n' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '12to24p' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    'above24n' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND eid.child_dob >= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "')THEN 1 ELSE 0 END)"),

                    'above24p' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND eid.child_dob >= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "')THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = eid.facility_id', array());

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $eidOutcomesQuery = $eidOutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $eidOutcomesQueryStr = $sql->buildSqlString($eidOutcomesQuery);
        $result = $common->cacheQuery($eidOutcomesQueryStr, $dbAdapter);
        return $result[0];
    }

    public function fetchEidOutcomesByProvinceDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $eidOutcomesQuery = $sql->select()
            ->from(array('eid' => 'dash_eid_form'))
            ->columns(
                array(
                    "total_samples" => new Expression("SUM(CASE WHEN ((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL')) THEN 1 ELSE 0 END)"),
                    "total_positive_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' )) THEN 1 ELSE 0 END)"),
                    "total_negative_samples" => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' )) THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = eid.facility_id', array())
            ->join(array('l' => 'location_details'), 'l.location_id = f.facility_state', array('location_name'))
            ->group('f.facility_state');

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
        }

        $eidOutcomesQueryStr = $sql->buildSqlString($eidOutcomesQuery);
        $result = $common->cacheQuery($eidOutcomesQueryStr, $dbAdapter);
        return $result;
    }

    public function fetchTATDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $eidOutcomesQuery = $sql->select()
            ->from(array('eid' => 'dash_eid_form'))
            ->columns(
                array(
                    'sec1' => new Expression("AVG(DATEDIFF(sample_received_at_vl_lab_datetime, sample_collection_date))"),
                    'sec2' => new Expression("AVG(DATEDIFF(sample_tested_datetime, sample_received_at_vl_lab_datetime))"),
                    'sec3' => new Expression("AVG(DATEDIFF(result_printed_datetime, sample_tested_datetime))"),
                    'total' => new Expression("AVG(DATEDIFF(result_printed_datetime, sample_collection_date))"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = eid.facility_id', array());

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $eidOutcomesQuery = $eidOutcomesQuery
                ->where("(sample_collection_date is not null)
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $eidOutcomesQueryStr = $sql->buildSqlString($eidOutcomesQuery);
        $result = $common->cacheQuery($eidOutcomesQueryStr, $dbAdapter);
        return $result[0];
    }


    // SUMMARY DASHBOARD END

    // LABS DASHBOARD START

    public function fetchQuickStats($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');

        $query = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    $this->translator->translate("Total Samples") => new Expression('COUNT(*)'),
                    $this->translator->translate("Samples Tested") => new Expression("SUM(CASE 
                                                                                WHEN (((eid.result is NOT NULL AND eid.result !='') OR (eid.reason_for_sample_rejection IS NOT NULL AND eid.reason_for_sample_rejection != '' AND eid.reason_for_sample_rejection != 0))) THEN 1
                                                                                ELSE 0
                                                                                END)"),
                    $this->translator->translate("Gender Missing") => new Expression("SUM(CASE 
                                                                                    WHEN ((child_gender IS NULL OR child_gender ='' OR child_gender ='unreported' OR child_gender ='Unreported')) THEN 1
                                                                                    ELSE 0
                                                                                    END)"),
                    $this->translator->translate("Age Missing") => new Expression("SUM(CASE 
                                                                                WHEN ((child_age IS NULL OR child_age ='' OR child_age ='Unreported'  OR child_age ='unreported')) THEN 1
                                                                                ELSE 0
                                                                                END)"),
                    $this->translator->translate("Results Not Available (< 6 months)") => new Expression("SUM(CASE
                                                                                                                                WHEN ((eid.result is NULL OR eid.result ='') AND (sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH)) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or reason_for_sample_rejection = 0)) THEN 1
                                                                                                                                ELSE 0
                                                                                                                                END)"),
                    $this->translator->translate("Results Not Available (> 6 months)") => new Expression("SUM(CASE
                                                                                                                                WHEN ((eid.result is NULL OR eid.result ='') AND (sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH)) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or reason_for_sample_rejection = 0)) THEN 1
                                                                                                                                ELSE 0
                                                                                                                                END)")
                )
            );
        //$query = $query->where("(eid.sample_collection_date is not null AND eid.sample_collection_date != '' AND DATE(eid.sample_collection_date) !='1970-01-01' AND DATE(eid.sample_collection_date) !='0000-00-00')");
        if ($logincontainer->role != 1) {
            $query = $query->where('eid.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        $queryStr = $sql->buildSqlString($query);
        //echo $queryStr;die;
        //$result = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $result = $common->cacheQuery($queryStr, $dbAdapter);
        return $result[0];
    }


    public function getStats($params)
    {
        $logincontainer = new Container('credo');
        $quickStats = $this->fetchQuickStats($params);
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $waitingTotal = 0;
        $receivedTotal = 0;
        $testedTotal = 0;
        $rejectedTotal = 0;
        $waitingResult = array();
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
        $receivedQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(array('total' => new Expression('COUNT(*)'), 'receivedDate' => new Expression('DATE(sample_collection_date)')))
            ->where("sample_collection_date is not null AND sample_collection_date != '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
            ->group(array("receivedDate"));
        if ($logincontainer->role != 1) {
            $receivedQuery = $receivedQuery->where('eid.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        if (trim($params['daterange']) != '') {
            if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                $receivedQuery = $receivedQuery->where(array("DATE(eid.sample_collection_date) <='$splitDate[1]'", "DATE(eid.sample_collection_date) >='$splitDate[0]'"));
            }
        } else {
            $receivedQuery = $receivedQuery->where("DATE(sample_collection_date) IN ($qDates)");
        }
        $cQueryStr = $sql->buildSqlString($receivedQuery);
        //echo $cQueryStr;die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $common->cacheQuery($cQueryStr, $dbAdapter);

        //var_dump($receivedResult);die;
        $recTotal = 0;
        foreach ($rResult as $rRow) {
            $displayDate = $common->humanDateFormat($rRow['receivedDate']);
            $receivedResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'receivedDate' => $displayDate, 'receivedTotal' => $recTotal += $rRow['total']);
        }

        //tested data
        $testedQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(array('total' => new Expression('COUNT(*)'), 'testedDate' => new Expression('DATE(sample_tested_datetime)')))
            ->where("((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL') OR (eid.reason_for_sample_rejection IS NOT NULL AND eid.reason_for_sample_rejection != '' AND eid.reason_for_sample_rejection != 0))")
            ->where("sample_collection_date is not null AND sample_collection_date != '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
            ->group(array("testedDate"));
        if ($logincontainer->role != 1) {
            $testedQuery = $testedQuery->where('eid.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        if (trim($params['daterange']) != '') {
            if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                $testedQuery = $testedQuery->where(array("DATE(eid.sample_tested_datetime) <='$splitDate[1]'", "DATE(eid.sample_tested_datetime) >='$splitDate[0]'"));
            }
        } else {
            $testedQuery = $testedQuery->where("DATE(sample_tested_datetime) IN ($qDates)");
        }
        $cQueryStr = $sql->buildSqlString($testedQuery);
        //echo $cQueryStr;//die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $common->cacheQuery($cQueryStr, $dbAdapter);

        //var_dump($receivedResult);die;
        $testedTotal = 0;
        foreach ($rResult as $rRow) {
            $displayDate = $common->humanDateFormat($rRow['testedDate']);
            $tResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'testedDate' => $displayDate, 'testedTotal' => $testedTotal += $rRow['total']);
        }

        //get rejected data
        $rejectedQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(array('total' => new Expression('COUNT(*)'), 'rejectDate' => new Expression('DATE(sample_collection_date)')))
            ->where("eid.reason_for_sample_rejection IS NOT NULL AND eid.reason_for_sample_rejection !='' AND eid.reason_for_sample_rejection!= 0")
            ->where("sample_collection_date is not null AND sample_collection_date != '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
            ->group(array("rejectDate"));
        if ($logincontainer->role != 1) {
            $rejectedQuery = $rejectedQuery->where('eid.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        if (trim($params['daterange']) != '') {
            if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                $rejectedQuery = $rejectedQuery->where(array("DATE(eid.sample_collection_date) <='$splitDate[1]'", "DATE(eid.sample_collection_date) >='$splitDate[0]'"));
            }
        } else {
            $rejectedQuery = $rejectedQuery->where("DATE(sample_collection_date) IN ($qDates)");
        }
        $cQueryStr = $sql->buildSqlString($rejectedQuery);
        //echo $cQueryStr;die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $common->cacheQuery($cQueryStr, $dbAdapter);
        $rejTotal = 0;
        foreach ($rResult as $rRow) {
            $displayDate = $common->humanDateFormat($rRow['rejectDate']);
            $rejectedResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'rejectDate' => $displayDate, 'rejectTotal' => $rejTotal += $rRow['total']);
        }
        return array('quickStats' => $quickStats, 'scResult' => $receivedResult, 'stResult' => $tResult, 'srResult' => $rejectedResult);
    }

    public function getMonthlySampleCount($params)
    {

        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";

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


            $queryStr = $sql->select()->from(array('eid' => $this->table))
                ->columns(
                    array(
                        "total" => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                        "negative" => new Expression("SUM(CASE WHEN ((eid.result like 'negative%' OR eid.result like 'Negative%')) THEN 1 ELSE 0 END)"),
                        "positive" => new Expression("SUM(CASE WHEN ((eid.result like 'positive%' OR eid.result like 'Positive%' )) THEN 1 ELSE 0 END)"),
                        "total_samples_valid" => new Expression("(SUM(CASE WHEN (((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL'))) THEN 1 ELSE 0 END))")
                    )
                );

            if ($facilityIdList != null) {
                $queryStr = $queryStr->where('eid.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");

            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));
            $queryStr = $sql->buildSqlString($queryStr);
            // echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["monthDate"] == null) continue;
                $result['eidResult']['Positive'][$j] = (isset($sRow["positive"])) ? $sRow["positive"] : 0;
                $result['eidResult']['Negative'][$j] = (isset($sRow["negative"])) ? $sRow["negative"] : 0;
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    public function getMonthlySampleCountByLabs($params)
    {
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";

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


            $query = $sql->select()->from(array('eid' => $this->table))

                ->columns(
                    array(
                        "total" => new Expression("SUM(CASE WHEN (
                                                        eid.result in ('negative', 'Negative','positive', 'Positive')) 
                                                        THEN 1 ELSE 0 END)"),
                        "negative" => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result like 'Negative')) THEN 1 ELSE 0 END)"),
                        "positive" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=eid.lab_id', array('facility_name'))
                ->where(array("eid.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "eid.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"))

                ->group('eid.lab_id')
                ->order('total DESC');

            if ($facilityIdList != null) {
                $query = $query->where('eid.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
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
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $skipDays = isset($this->config['defaults']['tat-skipdays']) ? $this->config['defaults']['tat-skipdays'] : 120;
        $common = new CommonService($this->sm);

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
            $startMonth = $params['fromDate'];
            $endMonth = $params['toDate'];

            if (strtotime($startMonth) >= strtotime($monthyear)) {
                $startMonth = $endMonth = date("Y-m", strtotime("-2 months"));
            } else if (strtotime($endMonth) >= strtotime($monthyear)) {
                $endMonth = date("Y-m", strtotime("-2 months"));
            }


            $startMonth = date("Y-m", strtotime(trim($startMonth))) . "-01";
            $endMonth = date("Y-m", strtotime(trim($endMonth))) . "-31";

            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "month" => new Expression("MONTH(result_approved_datetime)"),
                        "year" => new Expression("YEAR(result_approved_datetime)"),
                        "AvgDiff" => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(result_approved_datetime), '%b-%Y')"),
                        "total_samples_collected" => new Expression('COUNT(*)'),
                        "total_samples_pending" => new Expression("(SUM(CASE WHEN ((vl.result IS NULL OR vl.result like '' OR vl.result like 'NULL') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))")
                    )
                );
            $query = $query->where("
                    (vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')
                    AND (vl.result_approved_datetime is not null AND vl.result_approved_datetime not like '' AND DATE(vl.result_approved_datetime) !='1970-01-01' AND DATE(vl.result_approved_datetime) !='0000-00-00')");
            $query = $query->where("
                        DATE(vl.result_approved_datetime) >= '" . $startMonth . "'
                        AND DATE(vl.result_approved_datetime) <= '" . $endMonth . "' ");


            $skipDays = (isset($skipDays) && $skipDays > 0) ? $skipDays : 120;
            $query = $query->where('
                (DATEDIFF(result_approved_datetime,sample_collection_date) < ' . $skipDays . ' AND 
                DATEDIFF(result_approved_datetime,sample_collection_date) >= 0)');

            if ($facilityIdList != null) {
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }
            $query = $query->group(array(new Expression('YEAR(vl.result_approved_datetime)')));
            $query = $query->group(array(new Expression('MONTH(vl.result_approved_datetime)')));
            $query = $query->order(array(new Expression('DATE(vl.result_approved_datetime) ASC')));
            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr, $dbAdapter);
            foreach ($sampleResult as $key=>$sRow) {
                $result['all'][$key] = (isset($sRow["AvgDiff"]) && $sRow["AvgDiff"] != NULL && $sRow["AvgDiff"] > 0) ? round($sRow["AvgDiff"], 2) : null;
                //$result['lab'][$key] = (isset($labsubQueryResult[0]["labCount"]) && $labsubQueryResult[0]["labCount"] != NULL && $labsubQueryResult[0]["labCount"] > 0) ? round($labsubQueryResult[0]["labCount"],2) : 0;
                $result['data']['Samples Collected'][$key] = (isset($sRow['total_samples_collected']) && $sRow['total_samples_collected'] != NULL) ? $sRow['total_samples_collected'] : null;
                $result['data']['Results Not Available'][$key] = (isset($sRow['total_samples_pending']) && $sRow['total_samples_pending'] != NULL) ? $sRow['total_samples_pending'] : null;
                $result['dates'][$key] = $sRow["monthDate"];
            }
        }
        return $result;
    }

    public function fetchLabPerformance($params)
    {
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";

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


            $query = $sql->select()->from(array('eid' => $this->table))

                ->columns(
                    array(
                        "total" => new Expression("COUNT(*)"),
                        "initial_pcr" => new Expression("SUM(CASE WHEN (pcr_test_performed_before like 'no' OR pcr_test_performed_before is NULL OR pcr_test_performed_before like '') THEN 1 ELSE 0 END)"),
                        "initial_pcr_positives" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' ) AND (pcr_test_performed_before like 'no' OR pcr_test_performed_before is NULL OR pcr_test_performed_before like '')) THEN 1 ELSE 0 END)"),
                        "second_third_pcr" => new Expression("SUM(CASE WHEN (pcr_test_performed_before is not null AND pcr_test_performed_before like 'yes') THEN 1 ELSE 0 END)"),
                        "second_third_pcr_positives" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' ) AND (pcr_test_performed_before is not null AND pcr_test_performed_before like 'yes')) THEN 1 ELSE 0 END)"),
                        "rejected" => new Expression("SUM(CASE WHEN ((eid.is_sample_rejected like 'yes')) THEN 1 ELSE 0 END)"),
                        "total_valid_tests" => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result like 'Negative') OR (eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                        "negative" => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result like 'Negative')) THEN 1 ELSE 0 END)"),
                        "positive" => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('total_facilities' => new Expression("COUNT(f.facility_id)")))
                ->join(array('lab' => 'facility_details'), 'lab.facility_id=eid.lab_id', array('lab_name' => 'facility_name'))
                ->where(array("eid.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "eid.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"))

                ->group('eid.lab_id')
                ->order('total DESC');


            if ($facilityIdList != null) {
                $query = $query->where('eid.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $queryStr = $sql->buildSqlString($query);
            // echo $queryStr;
            // die;
            $result = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }

        return $result;
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
        $squery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "Collection_Receive"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_received_at_vl_lab_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                    "Receive_Register"    => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_received_at_vl_lab_datetime))) AS DECIMAL (10,2))"),
                    "Register_Analysis"   => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "Analysis_Authorise"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "total"               => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                )
            )
            ->join('facility_details', 'facility_details.facility_id = vl.facility_id')
            ->join('location_details', 'facility_details.facility_state = location_details.location_id')
            ->where(
                array(
                    "sample_tested_datetime >= '$startDate' AND sample_tested_datetime <= '$endDate'",
                    "(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) not like '1970-01-01' AND DATE(vl.sample_collection_date) not like '0000-00-00')",
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
            $squery = $squery->where('vl.lab_id IN (' . implode(',', $labs) . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $squery = $squery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        $squery = $squery->group(array('location_id'));
        $sQueryStr = $sql->buildSqlString($squery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }

    public function getTATbyDistrict($labs, $startDate, $endDate)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $skipDays = isset($this->config['defaults']['tat-skipdays']) ? $this->config['defaults']['tat-skipdays'] : 120;
        $squery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "Collection_Receive"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_received_at_vl_lab_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                    "Receive_Register"    => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_received_at_vl_lab_datetime))) AS DECIMAL (10,2))"),
                    "Register_Analysis"   => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "Analysis_Authorise"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "total"               => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                )
            )
            ->join('facility_details', 'facility_details.facility_id = vl.facility_id')
            ->join('location_details', 'facility_details.facility_state = location_details.location_id')
            ->where(
                array(
                    "sample_tested_datetime >= '$startDate' AND sample_tested_datetime <= '$endDate'",
                    "(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) not like '1970-01-01' AND DATE(vl.sample_collection_date) not like '0000-00-00')",
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
            $squery = $squery->where('vl.lab_id IN (' . implode(',', $labs) . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $squery = $squery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
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
        $squery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "Collection_Receive"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_received_at_vl_lab_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                    "Receive_Register"    => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_received_at_vl_lab_datetime))) AS DECIMAL (10,2))"),
                    "Register_Analysis"   => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "Analysis_Authorise"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "total"               => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                )
            )
            ->join('facility_details', 'facility_details.facility_id = vl.facility_id')
            ->join('location_details', 'facility_details.facility_state = location_details.location_id')
            ->where(
                array(
                    "sample_tested_datetime >= '$startDate' AND sample_tested_datetime <= '$endDate'",
                    "(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) not like '1970-01-01' AND DATE(vl.sample_collection_date) not like '0000-00-00')",
                    // "vl.facility_id = '$clinicID'"
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
            $squery = $squery->where('vl.lab_id IN (' . implode(',', $labs) . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $squery = $squery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        $squery = $squery->group(array('location_id'));
        $sQueryStr = $sql->buildSqlString($squery);
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
        //$globalDb = new \Application\Model\GlobalTable($this->adapter);
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }

        $p = 0;

        $countQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((vl.is_sample_rejected is NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') 
                                                    AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0))) THEN 1
                                                            ELSE 0
                                                        END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array())
            ->join(array('p' => 'location_details'), 'p.location_id=f.facility_state', array('province_name' => 'location_name', 'location_id'), 'left')
            ->group('p.location_id');
        if (isset($params['lab']) && trim($params['lab']) != '') {
            $countQuery = $countQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $countQuery = $countQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $countQuery = $countQuery->where('p.location_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $countQuery = $countQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
            $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "vl.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } else {
            if (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
                $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            } else if (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
                $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
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
                    $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                } else if ($age[$a] == '2to5') {
                    $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                } else if ($age[$a] == '6to14') {
                    $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                } else if ($age[$a] == '15to49') {
                    $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                } else if ($age[$a] == '>=50') {
                    $where .= "(vl.child_age >= 50)";
                } else if ($age[$a] == 'unknown') {
                    $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown' OR vl.child_age = 'unreported' OR vl.child_age = 'Unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("vl.child_gender IN ('m','male','M','MALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded' OR vl.child_gender = 'Unreported' OR vl.child_gender = 'unreported')");
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
        //$globalDb = new \Application\Model\GlobalTable($this->adapter);
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }

        $p = 0;

        $countQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((vl.is_sample_rejected is NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') 
                                                    AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0))) THEN 1
                                                            ELSE 0
                                                        END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array())
            ->join(array('d' => 'location_details'), 'd.location_id=f.facility_district', array('district_name' => 'location_name', 'location_id'), 'left')
            ->order('total DESC')
            ->group('d.location_id');
        if (isset($params['lab']) && trim($params['lab']) != '') {
            $countQuery = $countQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $countQuery = $countQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $countQuery = $countQuery->where('p.location_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $countQuery = $countQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
            $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "vl.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } else {
            if (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
                $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            } else if (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
                $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
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
                    $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                } else if ($age[$a] == '2to5') {
                    $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                } else if ($age[$a] == '6to14') {
                    $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                } else if ($age[$a] == '15to49') {
                    $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                } else if ($age[$a] == '>=50') {
                    $where .= "(vl.child_age >= 50)";
                } else if ($age[$a] == 'unknown') {
                    $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown' OR vl.child_age = 'unreported' OR vl.child_age = 'Unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("vl.child_gender IN ('m','male','M','MALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded' OR vl.child_gender = 'Unreported' OR vl.child_gender = 'unreported')");
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
        //$globalDb = new \Application\Model\GlobalTable($this->adapter);
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }
        
        $l = 0;

        $countQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((vl.is_sample_rejected is NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0))) THEN 1
                                                                                 ELSE 0
                                                                                 END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array('lab_name' => 'facility_name'))
            ->order('total DESC')
            ->group(array('vl.lab_id'));

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
            $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "vl.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } else {
            if (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
                $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            } else if (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
                $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
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
                    $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                } else if ($age[$a] == '2to5') {
                    $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                } else if ($age[$a] == '6to14') {
                    $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                } else if ($age[$a] == '15to49') {
                    $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                } else if ($age[$a] == '>=50') {
                    $where .= "(vl.child_age >= 50)";
                } else if ($age[$a] == 'unknown') {
                    $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown' OR vl.child_age = 'Unreported' OR vl.child_age = 'unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("vl.child_gender IN ('m','male','M','MALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded' OR vl.child_gender = 'unreported' OR vl.child_gender = 'Unreported')");
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
        //$globalDb = new \Application\Model\GlobalTable($this->adapter);
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }
        
        $l = 0;

        $countQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((vl.is_sample_rejected is NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0))) THEN 1
                                                                                 ELSE 0
                                                                                 END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('clinic_name' => 'facility_name'))
            ->order('total DESC')
            ->group(array('vl.facility_id'))
            
            ;

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
            $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "vl.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } else {
            if (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
                $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            } else if (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
                $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
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
                    $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                } else if ($age[$a] == '2to5') {
                    $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                } else if ($age[$a] == '6to14') {
                    $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                } else if ($age[$a] == '15to49') {
                    $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                } else if ($age[$a] == '>=50') {
                    $where .= "(vl.child_age >= 50)";
                } else if ($age[$a] == 'unknown') {
                    $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown' OR vl.child_age = 'Unreported' OR vl.child_age = 'unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("vl.child_gender IN ('m','male','M','MALE')");
        } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded' OR vl.child_gender = 'unreported' OR vl.child_gender = 'Unreported')");
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
        $common = new CommonService($this->sm);
        //$globalDb = new \Application\Model\GlobalTable($this->adapter);
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
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('sample_code', 'collectionDate' => new Expression('DATE(sample_collection_date)'), 'receivedDate' => new Expression('DATE(sample_received_at_vl_lab_datetime)')))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facilityName' => 'facility_name', 'facilityCode' => 'facility_code'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array('labName' => 'facility_name'), 'left')
            ->where("(vl.is_sample_rejected is NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0)");
        if (isset($parameters['daterange']) && trim($parameters['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $splitDate[0] . " 00:00:00" . "'", "vl.sample_collection_date <='" . $splitDate[1] . " 23:59:59" . "'"));
        } else {
            if (isset($parameters['frmSource']) && trim($parameters['frmSource']) == '<') {
                $sQuery = $sQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            } else if (isset($parameters['frmSource']) && trim($parameters['frmSource']) == '>') {
                $sQuery = $sQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            }
        }
        if (isset($parameters['provinces']) && trim($parameters['provinces']) != '') {
            $sQuery = $sQuery->where('l.facility_state IN (' . $parameters['provinces'] . ')');
        }
        if (isset($parameters['districts']) && trim($parameters['districts']) != '') {
            $sQuery = $sQuery->where('l.facility_district IN (' . $parameters['districts'] . ')');
        }
        if (isset($parameters['lab']) && trim($parameters['lab']) != '') {
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
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
                    $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                } else if ($parameters['age'][$a] == '2to5') {
                    $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                } else if ($parameters['age'][$a] == '6to14') {
                    $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                } else if ($parameters['age'][$a] == '15to49') {
                    $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                } else if ($parameters['age'][$a] == '>=50') {
                    $where .= "(vl.child_age >= 50)";
                } else if ($parameters['age'][$a] == 'unknown') {
                    $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown' OR vl.child_age = 'Unreported' OR vl.child_age = 'unreported')";
                }
            }
            $where = '(' . $where . ')';
            $sQuery = $sQuery->where($where);
        }
        

        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("vl.child_gender IN ('m','male','M','MALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded' OR vl.child_gender = 'unreported' OR vl.child_gender = 'Unreported')");
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
        $rResult = $common->cacheQuery($queryStr, $dbAdapter);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('sample_code', 'collectionDate' => new Expression('DATE(sample_collection_date)'), 'receivedDate' => new Expression('DATE(sample_received_at_vl_lab_datetime)')))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facilityName' => 'facility_name', 'facilityCode' => 'facility_code'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array('labName' => 'facility_name'), 'left')
            ->where("(vl.is_sample_rejected is NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0)");
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
            $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
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
            $displayCollectionDate = $common->humanDateFormat($aRow['collectionDate']);
            $displayReceivedDate = $common->humanDateFormat($aRow['receivedDate']);
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
        $common = new CommonService($this->sm);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
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

                $countQuery = $sql->select()->from(array('vl' => $this->table))->columns(array('total' => new Expression('COUNT(*)')))
                    ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array('facility_name', 'facility_code'))
                    ->where('vl.lab_id IN ("' . implode('", "', $facilityIdList) . '")')
                    ->group('vl.lab_id');

                /* if (!isset($params['fromSrc'])) {
                    $countQuery = $countQuery->where('(vl.is_sample_rejected IS NOT NULL AND vl.is_sample_rejected!= "")');
                } */
                if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                    $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
                }
                if (isset($params['provinces']) && trim($params['provinces']) != '') {
                    $countQuery = $countQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
                }
                if (isset($params['districts']) && trim($params['districts']) != '') {
                    $countQuery = $countQuery->where('f.facility_district IN (' . $params['districts'] . ')');
                }
                if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                    $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
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
                            $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                        } else if ($age[$a] == '2to5') {
                            $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                        } else if ($age[$a] == '6to14') {
                            $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                        } else if ($age[$a] == '15to49') {
                            $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                        } else if ($age[$a] == '>=50') {
                            $where .= "(vl.child_age >= 50)";
                        } else if ($age[$a] == 'unknown') {
                            $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown')";
                        }
                    }
                    $where = '(' . $where . ')';
                    $countQuery = $countQuery->where($where);
                }
                
                if (isset($params['gender']) && $params['gender'] == 'F') {
                    $countQuery = $countQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
                } else if (isset($params['gender']) && $params['gender'] == 'M') {
                    $countQuery = $countQuery->where("vl.child_gender IN ('m','male','M','MALE')");
                } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                    $countQuery = $countQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded')");
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
        $common = new CommonService($this->sm);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
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

                $countQuery = $sql->select()->from(array('vl' => $this->table))
                    ->columns(
                        array(
                            'total' => new Expression('COUNT(*)'),
                            "positive" => new Expression("SUM(CASE WHEN ((vl.result like 'positive%' OR vl.is_sample_rejected like 'Positive%') AND vl.result not like '') THEN 1 ELSE 0 END)"),
                            "negative" => new Expression("SUM(CASE WHEN ((vl.result like 'negative%' OR vl.is_sample_rejected like 'Negative%') AND vl.result not like '') THEN 1 ELSE 0 END)"),
                            "rejected" => new Expression("SUM(CASE WHEN ((vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END)"),
                        )
                    )
                    ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array('facility_name'))
                    ->where('vl.lab_id IN ("' . implode('", "', $facilityIdList) . '")')
                    ->group('vl.lab_id');

                if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                    $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
                }
                if (isset($params['provinces']) && trim($params['provinces']) != '') {
                    $countQuery = $countQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
                }
                if (isset($params['districts']) && trim($params['districts']) != '') {
                    $countQuery = $countQuery->where('f.facility_district IN (' . $params['districts'] . ')');
                }
                if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                    $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
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
                            $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                        } else if ($age[$a] == '2to5') {
                            $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                        } else if ($age[$a] == '6to14') {
                            $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                        } else if ($age[$a] == '15to49') {
                            $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                        } else if ($age[$a] == '>=50') {
                            $where .= "(vl.child_age >= 50)";
                        } else if ($age[$a] == 'unknown') {
                            $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown')";
                        }
                    }
                    $where = '(' . $where . ')';
                    $countQuery = $countQuery->where($where);
                }
               
                if (isset($params['gender']) && $params['gender'] == 'F') {
                    $countQuery = $countQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
                } else if (isset($params['gender']) && $params['gender'] == 'M') {
                    $countQuery = $countQuery->where("vl.child_gender IN ('m','male','M','MALE')");
                } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                    $countQuery = $countQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded')");
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

    public function fetchLabSampleDetails($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();

        $common = new CommonService($this->sm);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = date("Y-m", strtotime(trim($params['fromDate']))) . "-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate']))) . "-31";
            $sQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "DBS" => new Expression("SUM(CASE WHEN ((vl.specimen_type=$this->dbsId AND (vl.is_sample_rejected IS NOT NULL AND vl.is_sample_rejected != '' AND vl.is_sample_rejected != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))) THEN 1 ELSE 0 END)"),
                        "Others" => new Expression("SUM(CASE WHEN ((vl.specimen_type!=$this->dbsId AND (vl.is_sample_rejected IS NOT NULL AND vl.is_sample_rejected != '' AND vl.is_sample_rejected != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array(), 'left')
                ->where(array("DATE(vl.sample_collection_date) <='$endMonth'", "DATE(vl.sample_collection_date) >='$startMonth'"));
            if (isset($params['lab']) && trim($params['lab']) != '') {
                $sQuery = $sQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
            }
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            }

            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $where = '';
                for ($a = 0; $a < count($age); $a++) {
                    if (trim($where) != '') {
                        $where .= ' OR ';
                    }
                    if ($age[$a] == '<2') {
                        $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                    } else if ($age[$a] == '2to5') {
                        $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                    } else if ($age[$a] == '6to14') {
                        $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                    } else if ($age[$a] == '15to49') {
                        $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                    } else if ($age[$a] == '>=50') {
                        $where .= "(vl.child_age >= 50)";
                    } else if ($age[$a] == 'unknown') {
                        $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown')";
                    }
                }
                $where = '(' . $where . ')';
                $sQuery = $sQuery->where($where);
            }
            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $sQuery = $sQuery->where("(vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            } else if (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $sQuery = $sQuery->where("vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            }
            if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                $sQuery = $sQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
            }
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $sQuery = $sQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $sQuery = $sQuery->where("vl.child_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $sQuery = $sQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded')");
            }
            
            
            $sQuery = $sQuery->group(array(new Expression('DATE(sample_collection_date)')));
            $sQuery = $sQuery->order(array(new Expression('DATE(sample_collection_date)')));

            $sQuery = $sql->buildSqlString($sQuery);
            // echo $sQuery;die;
            $sampleResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $result['DBS'] = 0;
            $result['Others'] = 0;
            foreach ($sampleResult as $count) {
                $result['DBS'] += (isset($count['DBS'])) ? $count['DBS'] : 0;
                $result['Others'] += (isset($count['Others'])) ? $count['Others'] : 0;
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
        $common = new CommonService($this->sm);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";

            $sQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        'samples' => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                        "positive" => new Expression("SUM(CASE WHEN (((vl.result = 'positive' or vl.result = 'Positive' or vl.result not like '') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1 ELSE 0 END)"),
                        "negative" => new Expression("SUM(CASE WHEN (( vl.result IS NOT NULL AND vl.result!= '' AND vl.result ='negative' AND vl.result ='Negative' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array(), 'left')
                //->where("Month(sample_collection_date)='".$month."' AND Year(sample_collection_date)='".$year."'")
            ;

            $sQuery = $sQuery->where(
                "
                                        (sample_collection_date is not null AND sample_collection_date != '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'"
            );

            if (isset($params['lab']) && trim($params['lab']) != '') {
                $sQuery = $sQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
            }
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
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
                        $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                    } else if ($age[$a] == '2to5') {
                        $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                    } else if ($age[$a] == '6to14') {
                        $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                    } else if ($age[$a] == '15to49') {
                        $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                    } else if ($age[$a] == '>=50') {
                        $where .= "(vl.child_age >= 50)";
                    } else if ($age[$a] == 'unknown') {
                        $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown')";
                    }
                }
                $where = '(' . $where . ')';
                $sQuery = $sQuery->where($where);
            }
            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $sQuery = $sQuery->where("(vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            } else if (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $sQuery = $sQuery->where("vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            }
            if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                $sQuery = $sQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
            }
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $sQuery = $sQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $sQuery = $sQuery->where("vl.child_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $sQuery = $sQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded')");
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
        $common = new CommonService($this->sm);
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('DATE_FORMAT(sample_collection_date,"%d-%b-%Y")', 'specimen_type', 'facility_name');
        $orderColumns = array('sample_collection_date', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'specimen_type', 'facility_name');

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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array(
                'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                "total_samples_received" => new Expression("(COUNT(*))"),
                "total_samples_tested" => new Expression("(SUM(CASE WHEN (((vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                "total_samples_pending" => new Expression("(SUM(CASE WHEN ((vl.result IS NULL OR vl.result = '' OR vl.result = 'NULL' OR sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)")
            ))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array(), 'left')
            ->where("sample_collection_date is not null AND sample_collection_date != '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND f.facility_type = 1")
            ->group(new Expression('DATE(sample_collection_date)'))
            ->group('vl.specimen_type')
            ->group('vl.facility_id');
        //filter start
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
        }
        if (isset($parameters['lab']) && trim($parameters['lab']) != '') {
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (isset($parameters['provinces']) && trim($parameters['provinces']) != '') {
            $sQuery = $sQuery->where('l.facility_state IN (' . $parameters['provinces'] . ')');
        }
        if (isset($parameters['districts']) && trim($parameters['districts']) != '') {
            $sQuery = $sQuery->where('l.facility_district IN (' . $parameters['districts'] . ')');
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
        }
        
        
        if (isset($parameters['age']) && trim($parameters['age']) != '') {
            $age = explode(',', $parameters['age']);
            $where = '';
            for ($a = 0; $a < count($age); $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                } else if ($age[$a] == '2to5') {
                    $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                } else if ($age[$a] == '6to14') {
                    $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                } else if ($age[$a] == '15to49') {
                    $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                } else if ($age[$a] == '>=50') {
                    $where .= "(vl.child_age >= 50)";
                } else if ($age[$a] == 'unknown') {
                    $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown')";
                }
            }
            $where = '(' . $where . ')';
            $sQuery = $sQuery->where($where);
        }
        
        
        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("vl.child_gender IN ('m','male','M','MALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded')");
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
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array(

                "total_samples_received" => new Expression("(COUNT(*))")
            ))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array(), 'left')
            ->where("sample_collection_date is not null AND sample_collection_date != '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND f.facility_type = 1")
            ->group(new Expression('DATE(sample_collection_date)'))
            ->group('vl.specimen_type')
            ->group('vl.facility_id');
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
            $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
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
                $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
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
        $common = new CommonService($this->sm);
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('facility_name', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id');
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
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . "-31";
        }
        $sQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('vl' => $this->table), 'vl.lab_id=f.facility_id', array(
                "total_samples_received" => new Expression("(COUNT(*))"),
                "total_samples_tested" => new Expression("(SUM(CASE WHEN (((vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                "total_samples_pending" => new Expression("(SUM(CASE WHEN ((vl.result IS NULL OR vl.result = '' OR vl.result = 'NULL' OR sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)")
            ))
            ->where("sample_collection_date is not null AND sample_collection_date != '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND vl.lab_id !=0")
            ->group('vl.lab_id');
        if (isset($parameters['provinces']) && trim($parameters['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state IN (' . $parameters['provinces'] . ')');
        }
        if (isset($parameters['districts']) && trim($parameters['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district IN (' . $parameters['districts'] . ')');
        }
        if (isset($parameters['lab']) && trim($parameters['lab']) != '') {
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['lab'] . ')');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
        }
        
        
        if (isset($parameters['age']) && trim($parameters['age']) != '') {
            $where = '';
            $parameters['age'] = explode(',', $parameters['age']);
            for ($a = 0; $a < count($parameters['age']); $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($parameters['age'][$a] == '<2') {
                    $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                } else if ($parameters['age'][$a] == '2to5') {
                    $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                } else if ($parameters['age'][$a] == '6to14') {
                    $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                } else if ($parameters['age'][$a] == '15to49') {
                    $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                } else if ($parameters['age'][$a] == '>=50') {
                    $where .= "(vl.child_age >= 50)";
                } else if ($parameters['age'][$a] == 'unknown') {
                    $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown')";
                }
            }
            $where = '(' . $where . ')';
            $sQuery = $sQuery->where($where);
        }
        
        
        if (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'sample_tested') {
            $sQuery = $sQuery->where("((vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))");
        } else if (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'samples_not_tested') {
            $sQuery = $sQuery->where("(vl.result IS NULL OR vl.result = '' OR vl.result = 'NULL' OR sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)");
        } else if (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'sample_rejected') {
            $sQuery = $sQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
        }
        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("vl.child_gender IN ('m','male','M','MALE')");
        } else if (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded')");
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
            ->join(array('vl' => $this->table), 'vl.lab_id=f.facility_id', array(
                "total_samples_received" => new Expression("(COUNT(*))")
            ))
            ->where("sample_collection_date is not null AND sample_collection_date != '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND vl.lab_id !=0")
            ->group('vl.lab_id');
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
            $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
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
    public function getVlOutComes($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $vlOutComeResult = array();
        $common = new CommonService($this->sm);
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
            $sQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "positive" => new Expression("SUM(CASE WHEN ((vl.result like 'positive%' OR vl.result like 'Positive%' ) AND vl.result not like '') THEN 1 ELSE 0 END)"),
                        "negative" => new Expression("SUM(CASE WHEN ((vl.result like 'negative%' OR vl.result like 'Negative%' ) AND vl.result not like '') THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array());
            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
            }
            if (isset($params['lab']) && trim($params['lab']) != '') {
                $sQuery = $sQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
            }
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
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
                        $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                    } else if ($params['age'][$a] == '2to5') {
                        $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                    } else if ($params['age'][$a] == '6to14') {
                        $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                    } else if ($params['age'][$a] == '15to49') {
                        $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                    } else if ($params['age'][$a] == '>=50') {
                        $where .= "(vl.child_age >= 50)";
                    } else if ($params['age'][$a] == 'unknown') {
                        $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown')";
                    }
                }
                $where = '(' . $where . ')';
                $sQuery = $sQuery->where($where);
            }
            
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $sQuery = $sQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $sQuery = $sQuery->where("vl.child_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $sQuery = $sQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded')");
            }
            
            $queryStr = $sql->buildSqlString($sQuery);
            $vlOutComeResult = $common->cacheQuery($queryStr, $dbAdapter);
        }
        return $vlOutComeResult;
    }
    //end lab dashboard details

    public function fetchEidOutcomesByAgeInLabsDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $eidOutcomesQuery = $sql->select()
            ->from(array('eid' => 'dash_eid_form'))
            ->columns(
                array(
                    'noDatan' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND (eid.child_dob IS NULL OR eid.child_dob = '0000-00-00'))THEN 1 ELSE 0 END)"),

                    'noDatap' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND (eid.child_dob IS NULL OR eid.child_dob ='0000-00-00'))THEN 1 ELSE 0 END)"),

                    'less2n' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND eid.child_dob <= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "')THEN 1 ELSE 0 END)"),

                    'less2p' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND eid.child_dob <= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "')THEN 1 ELSE 0 END)"),

                    '2to9n' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '2to9p' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-2 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '9to12n' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '9to12p' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-9 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '12to24n' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    '12to24p' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND (eid.child_dob >= '" . date('Y-m-d', strtotime('-12 MONTHS')) . "' AND eid.child_dob <= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "'))THEN 1 ELSE 0 END)"),

                    'above24n' => new Expression("SUM(CASE WHEN ((eid.result like 'negative' OR eid.result = 'Negative' ) AND eid.child_dob >= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "')THEN 1 ELSE 0 END)"),

                    'above24p' => new Expression("SUM(CASE WHEN ((eid.result like 'positive' OR eid.result = 'Positive' ) AND eid.child_dob >= '" . date('Y-m-d', strtotime('-24 MONTHS')) . "')THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = eid.facility_id', array());

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('f.facility_district IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $eidOutcomesQuery = $eidOutcomesQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . "-31";
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
            $queryStr = $queryStr->where('eid.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
        }

        $eidOutcomesQueryStr = $sql->buildSqlString($eidOutcomesQuery);
        $result = $common->cacheQuery($eidOutcomesQueryStr, $dbAdapter);
        return $result[0];
    }

    public function fetchEidPositivityRateDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $startMonth = "";
        $endMonth = "";
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = date('Y-m-01',strtotime(str_replace(' ', '-', $params['fromDate'])));
            $endMonth = date('Y-m-t',strtotime(str_replace(' ', '-', $params['toDate'])));
            
            $monthList = $common->getMonthsInRange($startMonth, $endMonth);
            /* foreach($monthList as $key=>$list){
                $searchVal[$key] =  new Expression("AVG(CASE WHEN (eid.result like 'positive%' AND eid.result not like '' AND sample_collection_date LIKE '%".$list."%') THEN 1 ELSE 0 END)");
            } */
            $sQuery = $sql->select()->from(array('eid' => 'dash_eid_form'))->columns(array(
                'monthYear' => new Expression("DATE_FORMAT(sample_collection_date, '%b-%Y')"),
                'positive_rate' => new Expression("ROUND(((SUM(CASE WHEN ((eid.result like 'positive' OR eid.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((eid.result IS NOT NULL AND eid.result != '' AND eid.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
            ))
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.lab_id', array('facility_name'))
            ->where("(sample_collection_date is not null)
                                    AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                                    AND DATE(sample_collection_date) <= '" . $endMonth . "'")
            ->group(array("lab_id",new Expression("DATE_FORMAT(sample_collection_date, '%m-%Y')")))
            ->order(array("lab_id",new Expression("DATE_FORMAT(sample_collection_date, '%m-%Y')")));

            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
            }
            if (isset($params['clinics']) && trim($params['clinics']) != '') {
                $sQuery = $sQuery->where('eid.facility_id IN (' . $params['clinics'] . ')');
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
                $sQuery = $sQuery->where('eid.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $sQueryStr = $sql->buildSqlString($sQuery);
            // echo $sQueryStr;die;
            $result = $common->cacheQuery($sQueryStr, $dbAdapter);
            return array('result' => $result, 'month' => $monthList);
        } else{
            return 0;
        }
    }

    // CLINIC DASHBOARD STUFF
    public function fetchOverallEidResult($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sResult = array();
        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }

            $squery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "totalCollected" => new Expression("count(*)"),
                        "testedTotal" => new Expression("SUM(CASE WHEN ((vl.result is NOT NULL OR vl.result != '')) THEN 1 ELSE 0 END)"),
                        "notTestedTotal" => new Expression("SUM(CASE WHEN ((vl.result is NULL OR vl.result = '')) THEN 1 ELSE 0 END)"),
                        "positive" => new Expression("SUM(CASE WHEN ((vl.result like 'positive%' OR vl.result like 'Positive%' )) THEN 1 ELSE 0 END)"),
                        "negative" => new Expression("SUM(CASE WHEN ((vl.result like 'negative%' OR vl.result like 'Negative%')) THEN 1 ELSE 0 END)"),
                    )
                );
            $squery = $squery->where(array("DATE(vl.sample_collection_date) BETWEEN '$startDate' AND '$endDate'"));

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $squery = $squery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $squery = $squery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }

            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $squery = $squery->where('vl.specimen_type="' . base64_decode(trim($params['sampleTypeId'])) . '"');
            }
            
            //print_r($params['age']);die;
            $ageWhere = '';
            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                for ($a = 0; $a < count($age); $a++) {
                    if (trim($ageWhere) != '') {
                        $ageWhere .= ' OR ';
                    }
                    if ($age[$a] == '<2') {
                        $ageWhere .= "(vl.child_age > 0 AND vl.child_age < 2)";
                    } else if ($age[$a] == '2to5') {
                        $ageWhere .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                    } else if ($age[$a] == '6to14') {
                        $ageWhere .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                    } else if ($age[$a] == '15to49') {
                        $ageWhere .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                    } else if ($age[$a] == '>=50') {
                        $ageWhere .= "(vl.child_age >= 50)";
                    } else if ($age[$a] == 'unknown') {
                        $ageWhere .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown')";
                    }
                }
                $ageWhere = '(' . $ageWhere . ')';
                $squery = $squery->where($ageWhere);
            }

            if (isset($params['gender']) && $params['gender'] == 'F') {
                $squery = $squery->where("vl.child_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $squery = $squery->where("vl.child_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $squery = $squery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded')");
            }
            
            $sQueryStr = $sql->buildSqlString($squery);
            // echo $sQueryStr;die;
            $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        //var_dump($sResult);die;
        return $sResult;
    }

    public function fetchViralLoadStatusBasedOnGender($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "mTotal" => new Expression("SUM(CASE WHEN (vl.child_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),
                        "mpositive" => new Expression("SUM(CASE WHEN (vl.child_gender in('m','Male','M','MALE') and (vl.result like 'positive%' OR vl.result like 'Positive%')) THEN 1 ELSE 0 END)"),
                        "mnegative" => new Expression("SUM(CASE WHEN (vl.child_gender in('m','Male','M','MALE') and (vl.result like 'negative%' OR vl.result like 'Negative%' )) THEN 1 ELSE 0 END)"),

                        "fTotal" => new Expression("SUM(CASE WHEN (vl.child_gender in('f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                        "fpositive" => new Expression("SUM(CASE WHEN (vl.child_gender in('f','Female','F','FEMALE') and (vl.result like 'positive%' OR vl.result like 'Positive%')) THEN 1 ELSE 0 END)"),
                        "fnegative" => new Expression("SUM(CASE WHEN (vl.child_gender in('f','Female','F','FEMALE') and (vl.result like 'negative%' OR vl.result like 'Negative%' )) THEN 1 ELSE 0 END)"),

                        "nsTotal" => new Expression("SUM(CASE WHEN ((vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded')) THEN 1 ELSE 0 END)"),
                        "nspositive" => new Expression("SUM(CASE WHEN ((vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded' OR vl.child_gender = 'Unreported' OR vl.child_gender = 'unreported') and (vl.result like 'positive%' OR vl.result like 'Positive%')) THEN 1 ELSE 0 END)"),
                        "nsnegative" => new Expression("SUM(CASE WHEN ((vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded' OR vl.child_gender = 'Unreported' OR vl.child_gender = 'unreported') and (vl.result like 'negative%' OR vl.result like 'Negative%' )) THEN 1 ELSE 0 END)"),
                    )
                )
                ->where(array("DATE(vl.sample_collection_date) BETWEEN '$startDate' AND '$endDate'"));

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $query = $query->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $query = $query->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }

            if (isset($params['testResult']) && $params['testResult'] != '') {
                $query = $query->where("(vl.result like '".$params['testResult']."%' OR vl.result like '".ucwords($params['testResult'])."%' )");
            }
            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $query = $query->where('vl.specimen_type="' . base64_decode(trim($params['sampleTypeId'])) . '"');
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
                        $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                    } else if ($age[$a] == '2to5') {
                        $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                    } else if ($age[$a] == '6to14') {
                        $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                    } else if ($age[$a] == '15to49') {
                        $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                    } else if ($age[$a] == '>=50') {
                        $where .= "(vl.child_age >= 50)";
                    } else if ($age[$a] == 'unknown') {
                        $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown' OR vl.child_age = 'Unreported' OR vl.child_age = 'unreported')";
                    }
                }
                $where = '(' . $where . ')';
                $query = $query->where($where);
            }
            
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $query = $query->where("vl.child_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $query = $query->where("vl.child_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $query = $query->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded OR vl.child_age = 'Unreported' OR vl.child_age = 'unreported')");
            }

            $queryStr = $sql->buildSqlString($query);
            // die($queryStr);
            $sampleResult = $common->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sample) {
                $result['Total']['Male'][$j] = (isset($sample["mTotal"])) ? $sample["mTotal"] : 0;
                $result['Total']['Female'][$j] = (isset($sample["fTotal"])) ? $sample["fTotal"] : 0;
                $result['Total']['Not Specified'][$j] = (isset($sample["nsTotal"])) ? $sample["nsTotal"] : 0;
                $result['Positive']['Male'][$j] = (isset($sample["mpositive"])) ? $sample["mpositive"] : 0;
                $result['Positive']['Female'][$j] = (isset($sample["fpositive"])) ? $sample["fpositive"] : 0;
                $result['Positive']['Not Specified'][$j] = (isset($sample["nspositive"])) ? $sample["nspositive"] : 0;
                $result['Negative']['Male'][$j] = (isset($sample["mnegative"])) ? $sample["mnegative"] : 0;
                $result['Negative']['Female'][$j] = (isset($sample["fnegative"])) ? $sample["fnegative"] : 0;
                $result['Negative']['Not Specified'][$j] = (isset($sample["nsnegative"])) ? $sample["nsnegative"] : 0;
                $j++;
            }
        }
        return $result;
    }

    public function fetchClinicSampleTestedResultAgeGroupDetails($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            if ($params['age']['from'] == 'unknown') {
                $caseQuery1 = new Expression("SUM(CASE WHEN ((vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown' OR vl.child_age = 'Unreported' OR vl.child_age = 'unreported') and (vl.result like 'negative%' OR vl.result like 'Negative%')) THEN 1 ELSE 0 END)");
                $caseQuery2 = new Expression("SUM(CASE WHEN ((vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown' OR vl.child_age = 'Unreported' OR vl.child_age = 'unreported') and (vl.result like 'positive%' OR vl.result like 'Positive%' )) THEN 1 ELSE 0 END)");
            } else {
                $from = $params['age']['from'];
                $to = $params['age']['to'];
                $caseQuery1 = new Expression("SUM(CASE WHEN ((vl.child_age $from AND vl.child_age  $to) and (vl.result like 'negative%' OR vl.result like 'Negative%')) THEN 1 ELSE 0 END)");
                $caseQuery2 = new Expression("SUM(CASE WHEN ((vl.child_age $from AND vl.child_age  $to) and (vl.result like 'positive%' OR vl.result like 'Positive%' )) THEN 1 ELSE 0 END)");
            }
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%b-%Y')"),

                        "negative" => $caseQuery1,
                        "positive" => $caseQuery2,
                    )
                )
                ->where(array("DATE(vl.sample_collection_date) <='$endDate'", "DATE(vl.sample_collection_date) >='$startDate'"));
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $query = $query->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $query = $query->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            if (isset($params['testResult']) && $params['testResult'] != '') {
                $query = $query->where("(vl.result like '".$params['testResult']."%' OR vl.result like '".ucwords($params['testResult'])."%' )");
            }
            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $query = $query->where('vl.specimen_type="' . base64_decode(trim($params['sampleTypeId'])) . '"');
            }

            if (isset($params['gender']) && $params['gender'] == 'F') {
                $query = $query->where("vl.child_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $query = $query->where("vl.child_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $query = $query->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded OR vl.child_age = 'Unreported' OR vl.child_age = 'unreported')");
            }
            
            $query = $query->group(array(new Expression('WEEK(sample_collection_date)')));
            $query = $query->order(array(new Expression('WEEK(sample_collection_date)')));
            $queryStr = $sql->buildSqlString($query);
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["monthDate"] == null) continue;
                $result[$params['age']['ageName']]['negative'][$j] = (isset($sRow["negative"])) ? $sRow["negative"] : 0;

                $result[$params['age']['ageName']]['positive'][$j] = (isset($sRow["positive"])) ? $sRow["positive"] : 0;

                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    public function fetchClinicRequisitionFormsTested($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }

            $incompleteQuery = "(vl.patient_art_no IS NULL OR vl.patient_art_no='' OR vl.child_age IS NULL OR vl.child_age ='' OR vl.child_gender IS NULL OR vl.child_gender='' OR vl.current_regimen IS NOT NULL OR vl.current_regimen !='')";
            $completeQuery = "vl.patient_art_no IS NOT NULL AND vl.patient_art_no !='' AND vl.child_age IS NOT NULL AND vl.child_age !='' AND vl.child_gender IS NOT NULL AND vl.child_gender !='' AND vl.current_regimen IS NOT NULL AND vl.current_regimen !=''";
            if (isset($params['formFields']) && trim($params['formFields']) != '') {
                $formFields = explode(',', $params['formFields']);
                $incompleteQuery = '';
                $completeQuery = '';
                for ($f = 0; $f < count($formFields); $f++) {
                    if (trim($formFields[$f]) != '') {
                        $incompleteQuery .= 'vl.' . $formFields[$f] . ' IS NULL OR vl.' . $formFields[$f] . '=""';
                        $completeQuery .= 'vl.' . $formFields[$f] . ' IS NOT NULL AND vl.' . $formFields[$f] . '!=""';
                        if ((count($formFields) - $f) > 1) {
                            $incompleteQuery .= ' OR ';
                            $completeQuery .= ' AND ';
                        }
                    }
                }
            }
            $i = 0;
            $completeResultCount = 0;
            $inCompleteResultCount = 0;
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "total" => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%b-%Y')"),

                        "CompletedForms" => new Expression("SUM(CASE WHEN ($completeQuery) THEN 1 ELSE 0 END)"),
                        "IncompleteForms" => new Expression("SUM(CASE WHEN ($incompleteQuery) THEN 1 ELSE 0 END)"),

                    )
                )
                ->where(array("DATE(vl.sample_collection_date) <='$endDate'", "DATE(vl.sample_collection_date) >='$startDate'"));
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $query = $query->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $query = $query->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            if (isset($params['testResult']) && $params['testResult'] != '') {
                $queryStr = $queryStr->where("(vl.result like '".$params['testResult']."%' OR vl.result like '".ucwords($params['testResult'])."%' )");
            }
            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $query = $query->where('vl.specimen_type="' . base64_decode(trim($params['sampleTypeId'])) . '"');
            }

           
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $query = $query->where("vl.child_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $query = $query->where("vl.child_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $query = $query->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded OR vl.child_age = 'Unreported' OR vl.child_age = 'unreported')");
            }
            
            $query = $query->group(array(new Expression('WEEK(sample_collection_date)')));
            $query = $query->order(array(new Expression('WEEK(sample_collection_date)')));
            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            if (isset($sampleResult) && count($sampleResult) > 0) {
                foreach ($sampleResult as $sRow) {
                    if ($sRow["monthDate"] == null) continue;
                    $result['Complete'][$j] = (isset($sRow["CompletedForms"])) ? (int) $sRow["CompletedForms"] : null;
                    $result['Incomplete'][$j] = (isset($sRow["IncompleteForms"])) ? (int) $sRow["IncompleteForms"] : null;
                    $completionRate = 100 * ($result['Complete'][$j] / ($result['Complete'][$j] + $result['Incomplete'][$j]));
                    $result['CompletionRate'][$j] = ($completionRate > 0) ? round($completionRate, 2) : 0;
                    $result['date'][$j] = $sRow["monthDate"];
                    $j++;
                }
            }
        }

        return $result;
    }

    public function fetchSampleTestedReason($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rResult = array();
        $common = new CommonService($this->sm);
        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            $rQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(array('total' => new Expression('COUNT(*)'), 'monthDate' => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%M-%Y')")))
                ->join(array('tr' => 'r_eid_test_reasons'), 'tr.test_reason_id=vl.reason_for_eid_test', array('test_reason_name'))
                ->where(array("DATE(vl.sample_collection_date) >='$startDate'", "DATE(vl.sample_collection_date) <='$endDate'"))
                //->where('vl.facility_id !=0')
                //->where('vl.reason_for_eid_test="'.$reason['test_reason_id'].'"');
                ->group('tr.test_reason_id');
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $rQuery = $rQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $rQuery = $rQuery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            if (isset($params['testResult']) && $params['testResult'] != '') {
                $rQuery = $rQuery->where("(vl.result like '".$params['testResult']."%' OR vl.result like '".ucwords($params['testResult'])."%' )");
            }
            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $rQuery = $rQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleTypeId'])) . '"');
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
                        $where .= "(vl.child_age > 0 AND vl.child_age < 2)";
                    } else if ($age[$a] == '2to5') {
                        $where .= "(vl.child_age >= 2 AND vl.child_age <= 5)";
                    } else if ($age[$a] == '6to14') {
                        $where .= "(vl.child_age >= 6 AND vl.child_age <= 14)";
                    } else if ($age[$a] == '15to49') {
                        $where .= "(vl.child_age >= 15 AND vl.child_age <= 49)";
                    } else if ($age[$a] == '>=50') {
                        $where .= "(vl.child_age >= 50)";
                    } else if ($age[$a] == 'unknown') {
                        $where .= "(vl.child_age IS NULL OR vl.child_age = '' OR vl.child_age = 'Unknown' OR vl.child_age = 'unknown' OR vl.child_age = 'Unreported' OR vl.child_age = 'unreported')";
                    }
                }
                $where = '(' . $where . ')';
                $rQuery = $rQuery->where($where);
            }
            
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $rQuery = $rQuery->where("vl.child_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $rQuery = $rQuery->where("vl.child_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $rQuery = $rQuery->where("(vl.child_gender IS NULL OR vl.child_gender = '' OR vl.child_gender ='Not Recorded' OR vl.child_gender = 'not recorded' OR vl.child_gender = 'Unreported' OR vl.child_gender = 'unreported')");
            }
            
            if (isset($params['testReason']) && trim($params['testReason']) != '') {
                $rQuery = $rQuery->where(array("vl.reason_for_vl_testing ='" . base64_decode($params['testReason']) . "'"));
            }
            $rQueryStr = $sql->buildSqlString($rQuery);
            // echo $rQueryStr;die;
            //$qResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $qResult = $common->cacheQuery($rQueryStr, $dbAdapter);
            $j = 0;
            foreach ($qResult as $r) {
                $rResult[$r['test_reason_name']][$j]['total'] = (isset($r['total'])) ? (int) $r['total'] : 0;
                $rResult['date'][$j] = $r['monthDate'];
                $j++;
            }
        }
        return $rResult;
    }

    //get sample tested result details
    public function fetchClinicSampleTestedResults($params, $sampleType)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        if(isset($sampleType) && count($sampleType) > 0){
            foreach($sampleType as $type){
                $sampleList[] = $type['sample_id'];
            }
            $samples = implode(",", $sampleList);
        }
        if(isset($params['sampleType']) && $params['sampleType'] != ''){
            $samples = base64_decode($params['sampleType']);
            $params['sampleTypeId'] = $params['sampleType'];
        }


        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            $queryStr = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        //"total" => new Expression('COUNT(*)'),
                        "day" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%b-%Y')"),

                        "positive" => new Expression("SUM(CASE WHEN (vl.specimen_type IN($samples) AND (vl.result like 'not%' OR vl.result like 'Not%')) THEN 1 ELSE 0 END)"),
                        "negative" => new Expression("SUM(CASE WHEN (vl.specimen_type IN($samples) AND (vl.result like 'suppressed%' OR vl.result like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                    )
                )->join(array('st'=>'r_eid_sample_type'),'vl.specimen_type=st.sample_id',array('sample_name'))
                ->where(array("DATE(vl.sample_collection_date) <='$endDate'", "DATE(vl.sample_collection_date) >='$startDate'"));
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $queryStr = $queryStr->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            } else {
                if ($logincontainer->role != 1) {
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                    $queryStr = $queryStr->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            if (isset($params['testResult']) && $params['testResult'] != '') {
                $queryStr = $queryStr->where("(vl.result like '".$params['testResult']."%' OR vl.result like '".ucwords($params['testResult'])."%' )");
            }
            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $queryStr = $queryStr->where('vl.specimen_type="' . base64_decode(trim($params['sampleTypeId'])) . '"');
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
                        $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                    } else if ($age[$a] == '2to5') {
                        $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    } else if ($age[$a] == '6to14') {
                        $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    } else if ($age[$a] == '15to49') {
                        $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    } else if ($age[$a] == '>=50') {
                        $where .= "(vl.patient_age_in_years >= 50)";
                    } else if ($age[$a] == 'unknown') {
                        $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                    }
                }
                $where = '(' . $where . ')';
                $queryStr = $queryStr->where($where);
            }
           
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $queryStr = $queryStr->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'M') {
                $queryStr = $queryStr->where("vl.patient_gender IN ('m','male','M','MALE')");
            } else if (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $queryStr = $queryStr->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
            }
            $queryStr = $queryStr->group(array('specimen_type'));
            $queryStr = $queryStr->group(array(new Expression('WEEK(sample_collection_date)')));
            $queryStr = $queryStr->order(array(new Expression('WEEK(sample_collection_date)')));
            $queryStr = $sql->buildSqlString($queryStr);
            // echo $queryStr;die;
            $sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["day"] == null) continue;
                $result[$sRow['sample_name']]['positive'][$j] = (isset($sRow["positive"])) ? $sRow["positive"] : 0;
                $result[$sRow['sample_name']]['negative'][$j] = (isset($sRow["negative"])) ? $sRow["negative"] : 0;
                $result['date'][$j] = $sRow["day"];
                $j++;
            }
        }
        return $result;
    }
    //end clinic details
}
