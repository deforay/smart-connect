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

        $sQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                    "total" => new Expression("COUNT(*)"),
                    "initial_pcr" => new Expression("SUM(CASE WHEN (pcr_test_performed_before like 'no' OR pcr_test_performed_before is NULL OR pcr_test_performed_before like '') THEN 1 ELSE 0 END)"),
                    "second_third_pcr" => new Expression("SUM(CASE WHEN (pcr_test_performed_before is not null AND pcr_test_performed_before like 'yes') THEN 1 ELSE 0 END)")
                )
            )
            ->join(array('rs' => 'r_eid_sample_type'), 'rs.sample_id=eid.specimen_type', array('sample_name'), 'left')
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
        $queryStr = $sql->getSqlStringForSqlObject($sQuery);
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
            //->join(array('rs' => 'r_sample_type'), 'rs.sample_id=eid.sample_type', array('sample_name'))
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
        $iQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(
                array(
                    'eid_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=eid.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
            //->join(array('rs' => 'r_sample_type'), 'rs.sample_id=eid.sample_type', array('sample_name'))
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
        $mostRejectionQuery = $sql->select()->from(array('eid' => $this->table))
            ->columns(array('rejections' => new Expression('COUNT(*)')))
            ->join(array('r_r_r' => 'r_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=eid.reason_for_sample_rejection', array('rejection_reason_id'))
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
                $rejectionQuery = $sql->select()->from(array('eid' => $this->table))
                    ->columns(array('rejections' => new Expression('COUNT(*)')))
                    ->join(array('r_r_r' => 'r_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=eid.reason_for_sample_rejection', array('rejection_reason_name'))
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

        $eidOutcomesQueryStr = $sql->getSqlStringForSqlObject($eidOutcomesQuery);
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

        $eidOutcomesQueryStr = $sql->getSqlStringForSqlObject($eidOutcomesQuery);
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

        $eidOutcomesQueryStr = $sql->getSqlStringForSqlObject($eidOutcomesQuery);
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

        $eidOutcomesQueryStr = $sql->getSqlStringForSqlObject($eidOutcomesQuery);
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
        $queryStr = $sql->getSqlStringForSqlObject($query);
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
        $cQueryStr = $sql->getSqlStringForSqlObject($receivedQuery);
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
        $cQueryStr = $sql->getSqlStringForSqlObject($testedQuery);
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
        $cQueryStr = $sql->getSqlStringForSqlObject($rejectedQuery);
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
                $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            } else if (!empty($this->mappedFacilities)) {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
                $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            }


            $specimenTypes = null;
            if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                $rsQuery = $sql->select()->from(array('rs' => 'r_sample_type'))->columns(array('sample_id'));
                $rsQuery = $rsQuery->where('rs.sample_id="' . base64_decode(trim($params['sampleType'])) . '"');
                $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
                //$sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $specimenTypesResult = $common->cacheQuery($rsQueryStr, $dbAdapter);
                $specimenTypes = array_column($specimenTypesResult, 'sample_id');
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

            if ($specimenTypes != null) {
                $queryStr = $queryStr->where('eid.sample_type IN ("' . implode('", "', $specimenTypes) . '")');
            }
            if ($facilityIdList != null) {
                $queryStr = $queryStr->where('eid.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "' 
                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");

            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
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
                $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            } else if (!empty($this->mappedFacilities)) {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    //->where('f.facility_type = 2 AND f.status="active"')
                    ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
                $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            }


            $specimenTypes = null;
            if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                $rsQuery = $sql->select()->from(array('rs' => 'r_sample_type'))->columns(array('sample_id'));
                $rsQuery = $rsQuery->where('rs.sample_id="' . base64_decode(trim($params['sampleType'])) . '"');
                $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
                //$sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $specimenTypesResult = $common->cacheQuery($rsQueryStr, $dbAdapter);
                $specimenTypes = array_column($specimenTypesResult, 'sample_id');
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
                ->where(array("eid.sample_collection_date <='" . $endMonth . " 23:59:59" . "'", "eid.sample_collection_date >='" . $startMonth . " 00:00:00" . "'"))

                ->group('eid.lab_id');

            if ($specimenTypes != null) {
                $query = $query->where('eid.sample_type IN ("' . implode('", "', $specimenTypes) . '")');
            }
            if ($facilityIdList != null) {
                $query = $query->where('eid.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $queryStr = $sql->getSqlStringForSqlObject($query);
            echo $queryStr;
            die;
            $testResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $j = 0;
            foreach ($testResult as $data) {
                $result['sampleName']['Negative'][$j] = $data['Negative'];
                $result['sampleName']['Positive'][$j] = $data['Positive'];
                $result['lab'][$j] = $data['facility_name'];
                $j++;
            }
        }
        return $result;
    }

    // LABS DASHBOARD END



}
