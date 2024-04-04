<?php

namespace Application\Service;

use Exception;
use JsonMachine\Items;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Application\Model\SampleTable;
use Application\Model\FacilityTable;
use Application\Service\CommonService;
use Laminas\Cache\Pattern\ObjectCache;
use Application\Model\LocationDetailsTable;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Application\Model\DashApiReceiverStatsTable;

class SnapShotService
{

    public $sm;
    public CommonService $commonService;
    public Adapter $adapter;
    protected $translator = null;
    public function __construct($sm, $commonService, $dbAdapter)
    {
        $this->sm = $sm;
        $this->commonService = $commonService;
        $this->adapter = $dbAdapter;
        $this->translator = $this->sm->get('translator');
    }
    public function getSnapshotData($params){
        $testTypeQuery = [];$where = [];
        $common = new CommonService();
        if(isset($params['collectionDate']) && !empty($params['collectionDate'])){
            $date = explode(" to ", $params['collectionDate']);
            $where[] = " DATE(sample_collection_date) >= '" . $common->isoDateFormat($date[0]) . "' AND DATE(sample_collection_date) <= '" . $common->isoDateFormat($date[1]) . "' ";
        }
        if(isset($params['testedDate']) && !empty($params['testedDate'])){
            $date = explode(" to ", $params['testedDate']);
            $where[] = " DATE(sample_tested_datetime) >= '" . $common->isoDateFormat($date[0]) . "' AND DATE(sample_tested_datetime) <= '" . $common->isoDateFormat($date[1]) . "' ";
        }
        if(isset($params['provinceName']) && !empty($params['provinceName'])){
            $where[] = " facility_state_id IN(".implode(",", $params['provinceName']).") ";
        }
        if(isset($params['districtName']) && !empty($params['districtName'])){
            $where[] = " facility_district_id IN(".implode(",", $params['districtName']).") ";
        }
        if(isset($params['clinicId']) && !empty($params['clinicId'])){
            $where[] = " facility_id IN(".implode(",", $params['clinicId']).") ";
        }
        if(isset($where) && !empty($where)){
            $whereQuery = " WHERE " . implode(" AND ", $where);
        }
        if(isset($params['testType']) && !empty($params['testType'])){
            foreach($params['testType'] as $type){
                $testTypeQuery[] = " SELECT count(*) AS reg, 
                SUM(CASE WHEN (sample_collection_date IS NOT NULL AND sample_collection_date NOT LIKE '' AND DATE(sample_collection_date) NOT LIKE '0000:00:00') THEN 1 ELSE 0 END) AS 'totalReceived',
                SUM(CASE WHEN (sample_tested_datetime IS NOT NULL AND sample_tested_datetime NOT LIKE '' AND DATE(sample_tested_datetime) NOT LIKE '0000:00:00') THEN 1 ELSE 0 END) AS 'totalTested',
                SUM(CASE WHEN ((reason_for_sample_rejection IS NOT NULL AND reason_for_sample_rejection NOT LIKE '') OR is_sample_rejected like 'yes') THEN 1 ELSE 0 END) AS 'totalRejected',
                SUM(CASE WHEN ((sample_collection_date IS NOT NULL AND sample_collection_date NOT LIKE '' AND DATE(sample_collection_date) NOT LIKE '0000:00:00') AND (is_sample_rejected like 'yes' OR result IS NULL OR result LIKE '' OR result_status IN(2,4,5,10))) THEN 1 ELSE 0 END) AS 'totalPending',
                facility_name FROM dash_form_".$type." AS ".$type." INNER JOIN facility_details as f ON ".$type.".lab_id = f.facility_id ".$whereQuery." GROUP BY f.facility_id ";
            }
        }else{
            foreach(["vl", "eid", "tb", "covid19", "hepatitis"] as $type){
                $testTypeQuery[] = " SELECT count(*) AS reg, 
                SUM(CASE WHEN (sample_collection_date IS NOT NULL AND sample_collection_date NOT LIKE '' AND DATE(sample_collection_date) NOT LIKE '0000:00:00') THEN 1 ELSE 0 END) AS 'totalReceived',
                SUM(CASE WHEN (sample_tested_datetime IS NOT NULL AND sample_tested_datetime NOT LIKE '' AND DATE(sample_tested_datetime) NOT LIKE '0000:00:00') THEN 1 ELSE 0 END) AS 'totalTested',
                SUM(CASE WHEN ((reason_for_sample_rejection IS NOT NULL AND reason_for_sample_rejection NOT LIKE '') OR is_sample_rejected like 'yes') THEN 1 ELSE 0 END) AS 'totalRejected',
                SUM(CASE WHEN ((sample_collection_date IS NOT NULL AND sample_collection_date NOT LIKE '' AND DATE(sample_collection_date) NOT LIKE '0000:00:00') AND (is_sample_rejected like 'yes' OR result IS NULL OR result LIKE '' OR result_status IN(2,4,5,10))) THEN 1 ELSE 0 END) AS 'totalPending',
                facility_name FROM dash_form_".$type." AS ".$type." INNER JOIN facility_details as f ON ".$type.".lab_id = f.facility_id ".$whereQuery." GROUP BY f.facility_id ";
            }
        }
        $db = $this->adapter;

        $sql = "SELECT t.facility_name AS clinicName, SUM(t.reg) AS total, sum(t.totalTested) AS totalTested, sum(t.totalRejected) AS totalRejected, sum(t.totalPending) AS totalPending  
        FROM (
                ".implode(" UNION ALL " , $testTypeQuery)."
             ) t GROUP BY clinicName ORDER BY total DESC";
        return $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE)->toArray();
    }
    public function getSnapshotQuickStatsDetails($params)
    {
            $loginContainer = new Container('credo');
            $quickStats = $this->fetchQuickStats($params);
            $dbAdapter = $this->adapter;
            $sql = new Sql($dbAdapter);
    
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
            $receivedQuery = $sql->select()->from(array('vl' => "dash_form_vl"))
                ->columns(array('total' => new Expression('COUNT(*)'), 'receivedDate' => new Expression('DATE(sample_collection_date)')))
                ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
                ->group(array("receivedDate"));
            if (trim($params['daterange']) != '') {
                if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                    $receivedQuery = $receivedQuery->where(array("DATE(vl.sample_collection_date) <='$splitDate[1]'", "DATE(vl.sample_collection_date) >='$splitDate[0]'"));
                }
            } else {
                $receivedQuery = $receivedQuery->where("DATE(sample_collection_date) IN ($qDates)");
            }
            $cQueryStr = $sql->buildSqlString($receivedQuery);
            //echo $cQueryStr;die;
            //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $rResult = $this->commonService->cacheQuery($cQueryStr, $dbAdapter);
    
            //var_dump($receivedResult);die;
            $recTotal = 0;
            foreach ($rResult as $rRow) {
                $displayDate = \Application\Service\CommonService::humanReadableDateFormat($rRow['receivedDate']);
                $receivedResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'receivedDate' => $displayDate, 'receivedTotal' => $recTotal += $rRow['total']);
            }
    
            //tested data
            $testedQuery = $sql->select()->from(array('vl' => 'dash_form_vl'))
                ->columns(array('total' => new Expression('COUNT(*)'), 'testedDate' => new Expression('DATE(sample_tested_datetime)')))
                ->where("((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))")
                ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
                ->group(array("testedDate"));
            if (trim($params['daterange']) != '') {
                if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                    $testedQuery = $testedQuery->where(array("DATE(vl.sample_tested_datetime) <='$splitDate[1]'", "DATE(vl.sample_tested_datetime) >='$splitDate[0]'"));
                }
            } else {
                $testedQuery = $testedQuery->where("DATE(sample_tested_datetime) IN ($qDates)");
            }
            $cQueryStr = $sql->buildSqlString($testedQuery);
            //echo $cQueryStr;//die;
            //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $rResult = $this->commonService->cacheQuery($cQueryStr, $dbAdapter);
    
            //var_dump($receivedResult);die;
            $testedTotal = 0;
            foreach ($rResult as $rRow) {
                $displayDate = \Application\Service\CommonService::humanReadableDateFormat($rRow['testedDate']);
                $tResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'testedDate' => $displayDate, 'testedTotal' => $testedTotal += $rRow['total']);
            }
    
            //get rejected data
            $rejectedQuery = $sql->select()->from(array('vl' => 'dash_form_vl'))
                ->columns(array('total' => new Expression('COUNT(*)'), 'rejectDate' => new Expression('DATE(sample_collection_date)')))
                ->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection!= 0")
                ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
                ->group(array("rejectDate"));
            if (trim($params['daterange']) != '') {
                if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                    $rejectedQuery = $rejectedQuery->where(array("DATE(vl.sample_collection_date) <='$splitDate[1]'", "DATE(vl.sample_collection_date) >='$splitDate[0]'"));
                }
            } else {
                $rejectedQuery = $rejectedQuery->where("DATE(sample_collection_date) IN ($qDates)");
            }
            $cQueryStr = $sql->buildSqlString($rejectedQuery);
            //echo $cQueryStr;die;
            //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $rResult = $this->commonService->cacheQuery($cQueryStr, $dbAdapter);
            $rejTotal = 0;
            foreach ($rResult as $rRow) {
                $displayDate = CommonService::humanReadableDateFormat($rRow['rejectDate']);
                $rejectedResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'rejectDate' => $displayDate, 'rejectTotal' => $rejTotal += $rRow['total']);
            }
            return array('quickStats' => $quickStats, 'scResult' => $receivedResult, 'stResult' => $tResult, 'srResult' => $rejectedResult);
    }
    public function fetchQuickStats($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');

        $query = $sql->select()->from(array('vl' => 'dash_form_vl'))
            ->columns(
                array(
                    $this->translator->translate("Total Samples") => new Expression('COUNT(*)'),
                    $this->translator->translate("Samples Tested") => new Expression("SUM(CASE
                                                                                WHEN (((vl.vl_result_category is NOT NULL AND vl.vl_result_category !='') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))) THEN 1
                                                                                ELSE 0
                                                                                END)"),
                    $this->translator->translate("Gender Missing") => new Expression("SUM(CASE
                                                                                    WHEN ((patient_gender IS NULL OR patient_gender ='' OR patient_gender ='unreported' OR patient_gender ='Unreported')) THEN 1
                                                                                    ELSE 0
                                                                                    END)"),
                    $this->translator->translate("Age Missing") => new Expression("SUM(CASE
                                                                                WHEN ((patient_age_in_years IS NULL OR patient_age_in_years ='' OR patient_age_in_years ='Unreported'  OR patient_age_in_years ='unreported')) THEN 1
                                                                                ELSE 0
                                                                                END)"),
                    $this->translator->translate("Results Not Available (< 6 months)") => new Expression("SUM(CASE
                                                                                                                                WHEN ((vl.vl_result_category is NULL OR vl.vl_result_category ='') AND (sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH)) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or reason_for_sample_rejection = 0)) THEN 1
                                                                                                                                ELSE 0
                                                                                                                                END)"),
                    $this->translator->translate("Results Not Available (> 6 months)") => new Expression("SUM(CASE
                                                                                                                                WHEN ((vl.vl_result_category is NULL OR vl.vl_result_category ='') AND (sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH)) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or reason_for_sample_rejection = 0)) THEN 1
                                                                                                                                ELSE 0
                                                                                                                                END)")
                )
            );
        $queryStr = $sql->buildSqlString($query);
        $result = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        return $result[0];
    }
}