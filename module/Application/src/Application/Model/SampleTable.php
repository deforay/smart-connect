<?php

namespace Application\Model;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Debug\Debug;
use Zend\Db\Sql\Expression;
//use Zend\Db\Sql\Where;
use \Application\Service\CommonService;
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
class SampleTable extends AbstractTableGateway {

    protected $table = 'dash_vl_request_form';
    public $sm = null;

    public function __construct(Adapter $adapter, $sm=null) {
        $this->adapter = $adapter;
        $this->sm = $sm;
        
    }
    
    public function fetchQuickStats($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
//        $query = "SELECT count(*) as 'Total', 
//		SUM(CASE 
//            WHEN patient_gender IS NULL OR patient_gender ='' THEN 0
//            ELSE 1
//            END) as GenderMissing, 
//		SUM(CASE 
//            WHEN patient_age_in_years IS NULL OR patient_age_in_years ='' THEN 0
//            ELSE 1
//            END) as AgeMissing,
//        SUM(CASE
//            WHEN (result is NULL OR result ='') AND (sample_collection_date > DATE_SUB(NOW(), INTERVAL 6 MONTH) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='')) THEN 1
//            ELSE 0
//            END) as ResultWaiting
//           FROM `dash_vl_request_form` as vl";
           
        $globalDb = new \Application\Model\GlobalTable($this->adapter);
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        
        $query = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                          "Total Samples" => new Expression('COUNT(*)'),
                                          "Samples Tested" => new Expression("SUM(CASE 
                                                                                WHEN ((vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL') AND (sample_tested_datetime IS NOT NULL AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
                                                                                ELSE 0
                                                                                END)"),
                                          "Gender Missing" => new Expression("SUM(CASE 
                                                                                    WHEN (patient_gender IS NULL OR patient_gender ='' OR patient_gender ='unreported') THEN 1
                                                                                    ELSE 0
                                                                                    END)"),
                                          "Age Missing" => new Expression("SUM(CASE 
                                                                                WHEN patient_age_in_years IS NULL OR patient_age_in_years ='' THEN 1
                                                                                ELSE 0
                                                                                END)"),
                                          "Results Awaited <br>(< $samplesWaitingFromLastXMonths months)" => new Expression("SUM(CASE
                                                                                                                                WHEN (result is NULL OR result ='') AND (sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='')) THEN 1
                                                                                                                                ELSE 0
                                                                                                                                END)"),
                                          "Results Awaited <br>(> $samplesWaitingFromLastXMonths months)" => new Expression("SUM(CASE
                                                                                                                                WHEN (result is NULL OR result ='') AND (sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='')) THEN 1
                                                                                                                                ELSE 0
                                                                                                                                END)")
                                          )
                                        );
        //$query = $query->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')");
        if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
            $query = $query->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $query = $query->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        $queryStr = $sql->getSqlStringForSqlObject($query);
        //echo $queryStr;die;
        //$result = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $result = $common->cacheQuery($queryStr,$dbAdapter);
        return $result[0];
    }
    
    //start lab dashboard details 
    public function fetchSampleResultDetails($params){
        $logincontainer = new Container('credo');
        $quickStats = $this->fetchQuickStats($params);
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $waitingTotal = 0;$receivedTotal = 0;$testedTotal = 0;$rejectedTotal = 0;
        $waitingResult = array();$receivedResult = array();$tResult = array();$rejectedResult = array();
        if(trim($params['daterange'])!= ''){
            $splitDate = explode('to',$params['daterange']);
        }else{
            $timestamp = time();
            $qDates = array();
            for($i = 0 ;$i < 28;$i++) {
                $qDates[] = "'".date('Y-m-d', $timestamp)."'";
                $timestamp -= 24 * 3600;
            }
            $qDates = implode(",",$qDates);
        }
        
        //get received data
        $receivedQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                       ->columns(array('total' => new Expression('COUNT(*)'), 'receivedDate' => new Expression('DATE(sample_collection_date)')))
                                       //->where("vl.result!='' AND vl.result is NOT NULL");
                                       ->group(array("receivedDate"));
        if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
            $receivedQuery = $receivedQuery->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $receivedQuery = $receivedQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        if(trim($params['daterange'])!= ''){
            if(trim($splitDate[0])!= '' && trim($splitDate[1])!= ''){
                $receivedQuery = $receivedQuery->where(array("DATE(vl.sample_collection_date) <='$splitDate[1]'", "DATE(vl.sample_collection_date) >='$splitDate[0]'"));
            }
        }else{
            $receivedQuery = $receivedQuery->where("DATE(sample_collection_date) IN ($qDates)");
        }
        $cQueryStr = $sql->getSqlStringForSqlObject($receivedQuery);
        //echo $cQueryStr;die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $common->cacheQuery($cQueryStr,$dbAdapter);
        
        //var_dump($receivedResult);die;
        $recTotal = 0;
        foreach($rResult as $rRow){
            $displayDate = $common->humanDateFormat($rRow['receivedDate']);
            $receivedResult[] = array(array('total' => $rRow['total']),'date'=>$displayDate,'receivedDate'=>$displayDate, 'receivedTotal' => $recTotal+=$rRow['total']);
        }
        
        
        //get rejected data
        $rejectedQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                       ->columns(array('total' => new Expression('COUNT(*)'), 'rejectDate' => new Expression('DATE(sample_collection_date)')))
                                       ->where("vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection IS NOT NULL")
                                       ->group(array("rejectDate"));
        if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
            $rejectedQuery = $rejectedQuery->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $rejectedQuery = $rejectedQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        if(trim($params['daterange'])!= ''){
            if(trim($splitDate[0])!= '' && trim($splitDate[1])!= ''){
                $rejectedQuery = $rejectedQuery->where(array("DATE(vl.sample_collection_date) <='$splitDate[1]'", "DATE(vl.sample_collection_date) >='$splitDate[0]'"));
            }
        }else{
            $rejectedQuery = $rejectedQuery->where("DATE(sample_collection_date) IN ($qDates)");
        }
        $cQueryStr = $sql->getSqlStringForSqlObject($rejectedQuery);
        //echo $cQueryStr;die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $common->cacheQuery($cQueryStr,$dbAdapter);
        //var_dump($receivedResult);die;
        $rejTotal = 0;
        foreach($rResult as $rRow){
            $displayDate = $common->humanDateFormat($rRow['rejectDate']);
            $rejectedResult[] = array(array('total' => $rRow['total']),'date'=>$displayDate,'rejectDate'=>$displayDate, 'rejectTotal' => $rejTotal+=$rRow['total']);
        }
        
        //tested data
        $testedQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                     ->columns(array('total' => new Expression('COUNT(*)'), 'testedDate' => new Expression('DATE(sample_tested_datetime)')))
                                     ->group(array("testedDate"));
        if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
            $testedQuery = $testedQuery->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $testedQuery = $testedQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        if(trim($params['daterange'])!= ''){
            if(trim($splitDate[0])!= '' && trim($splitDate[1])!= ''){
                $testedQuery = $testedQuery->where(array("DATE(vl.sample_tested_datetime) <='$splitDate[1]'", "DATE(vl.sample_tested_datetime) >='$splitDate[0]'"));
            }
        }else{
            $testedQuery = $testedQuery->where("DATE(sample_tested_datetime) IN ($qDates)");
        }
        $cQueryStr = $sql->getSqlStringForSqlObject($testedQuery);
        //echo $cQueryStr;//die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $common->cacheQuery($cQueryStr,$dbAdapter);
        
        //var_dump($receivedResult);die;
        $testedTotal = 0;
        foreach($rResult as $rRow){
            $displayDate = $common->humanDateFormat($rRow['testedDate']);
            $tResult[] = array(array('total' => $rRow['total']),'date'=>$displayDate,'testedDate'=>$displayDate, 'testedTotal' => $testedTotal+=$rRow['total']);
        }
        
        return array('quickStats'=>$quickStats,'scResult'=>$receivedResult,'stResult'=>$tResult,'srResult'=>$rejectedResult);
    }
    
    //get sample tested result details
    public function fetchSampleTestedResultDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $rsQuery = $sql->select()->from(array('rs'=>'r_sample_type'));
            if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                $rsQuery = $rsQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
            }
            $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
            //$sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleTypeResult = $common->cacheQuery($rsQueryStr,$dbAdapter);
            
            $sampleId = array();
            foreach($sampleTypeResult as $samples){
                $sampleId[] = "'".$samples['sample_id']."'";
            }
            $sampleTypes = implode(',', $sampleId);
            
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                                ->columns(array(
                                                    "total" => new Expression('COUNT(*)'),
                                                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                    "GreaterThan1000" => new Expression("SUM(CASE WHEN vl.result >= 1000 THEN 1 ELSE 0 END)"),
                                                    "LesserThan1000" => new Expression("SUM(CASE WHEN (vl.result < 1000 or vl.result = 'Target Not Detected') THEN 1 ELSE 0 END)"),
                                                    //"TND" => new Expression("SUM(CASE WHEN vl.result='Target Not Detected' THEN 1 ELSE 0 END)"),
                                             )
                                            );
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."' 
                        AND DATE(sample_collection_date) <= '".$endMonth."'
                        AND vl.sample_type IN ($sampleTypes)");
                
            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));   
            
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            $j=0;
            foreach($sampleResult as $sRow){
                if($sRow["monthDate"] == null) continue;
                $result['sampleName']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["GreaterThan1000"]))?$sRow["GreaterThan1000"]:0;
                //$result['sampleName']['VL Not Detected'][$j] = $sRow["TND"];
                $result['sampleName']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["LesserThan1000"]))?$sRow["LesserThan1000"]:0;
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            } 
        }
      return $result;
    }
    //get sample tested result details
    public function fetchSampleTestedResultGenderDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $j = 0;
            $lessTotal = 0;
            $greaterTotal = 0;
            $notTargetTotal = 0;
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "total" => new Expression('COUNT(*)'),
                                                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                    
                                                    "MGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender !='unreported' and vl.result>=1000 and vl.patient_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),
                                                    "MLesserThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender !='unreported'  and (vl.result<1000 or vl.result='Target Not Detected') and vl.patient_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),
                                                    //"MTND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' and vl.patient_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),
                                             
                                                    "FGreaterThan1000" => new Expression("SUM(CASE WHEN vl.patient_gender is not null and vl.patient_gender !='unreported'  and vl.result>=1000 and vl.patient_gender in('f','Female','F','FEMALE') THEN 1 ELSE 0 END)"),
                                                    "FLesserThan1000" => new Expression("SUM(CASE WHEN vl.patient_gender is not null and vl.patient_gender !='unreported'  and (vl.result<1000 or vl.result='Target Not Detected') and vl.patient_gender in('f','Female','F','FEMALE') THEN 1 ELSE 0 END)"),
                                                    //"FTND" => new Expression("SUM(CASE WHEN vl.result='Target Not Detected' and vl.patient_gender in('f','Female','F','FEMALE') THEN 1 ELSE 0 END)"),

                                                    "OGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender !='unreported'  and vl.result>=1000 and vl.patient_gender NOT in('m','Male','M','MALE','f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                                                    "OLesserThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender !='unreported'  and (vl.result<1000 or vl.result='Target Not Detected') and vl.patient_gender NOT in('m','Male','M','MALE','f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                                                    //"OTND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' and vl.patient_gender NOT in('m','Male','M','MALE','f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                                             
                                              )
                                            );
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")'); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')
                        AND DATE(sample_collection_date) >= '".$startMonth."'
                        AND DATE(sample_collection_date) <= '".$endMonth."' ");
                
            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));               
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //echo $queryStr;die;
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            $j=0;
            foreach($sampleResult as $sRow){
                if($sRow["monthDate"] == null) continue;
                $result['M']['VL (>= 1000 cp/ml)'][$j] = $sRow["MGreaterThan1000"];
                //$result['M']['VL Not Detected'][$j] = $sRow["MTND"];
                $result['M']['VL (< 1000 cp/ml)'][$j] = $sRow["MLesserThan1000"];
 
                $result['F']['VL (>= 1000 cp/ml)'][$j] = $sRow["FGreaterThan1000"];
                //$result['F']['VL Not Detected'][$j] = $sRow["FTND"];
                $result['F']['VL (< 1000 cp/ml)'][$j] = $sRow["FLesserThan1000"];

                $result['Not Specified']['VL (>= 1000 cp/ml)'][$j] = $sRow["OGreaterThan1000"];
                //$result['Not Specified']['VL Not Detected'][$j] = $sRow["OTND"];
                $result['Not Specified']['VL (< 1000 cp/ml)'][$j] = $sRow["OLesserThan1000"];
                
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
            
        }
      return $result;
    }
    
    public function fetchSampleTestedResultAgeGroupDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $j = 0;
            $lessTotal = 0;
            $greaterTotal = 0;
            $notTargetTotal = 0;
            $query = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "total" => new Expression('COUNT(*)'),
                                                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                    
                                                    "AgeLt2VLGt1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and patient_age_in_years < 2) THEN 1 ELSE 0 END)"),
                                                    "AgeLt2VLLt1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and patient_age_in_years < 2) THEN 1 ELSE 0 END)"),
                                             
                                                    "AgeGte2Lte5VLGt1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and (patient_age_in_years >= 2 and patient_age_in_years <= 5)) THEN 1 ELSE 0 END)"),
                                                    "AgeGte2Lte5VLLt1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and (patient_age_in_years >= 2 and patient_age_in_years <= 5)) THEN 1 ELSE 0 END)"),
                                                    
                                                    "AgeGte6Lte14VLGt1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and (patient_age_in_years >= 6 and patient_age_in_years <= 14)) THEN 1 ELSE 0 END)"),
                                                    "AgeGte6Lte14VLLt1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and (patient_age_in_years >= 6 and patient_age_in_years <= 14)) THEN 1 ELSE 0 END)"),
                                                    
                                                    "AgeGte15Lte49VLGt1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and (patient_age_in_years >= 15 and patient_age_in_years <= 49)) THEN 1 ELSE 0 END)"),
                                                    "AgeGte15Lte49VLLt1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and (patient_age_in_years >= 15 and patient_age_in_years <= 49)) THEN 1 ELSE 0 END)"),
                                                    
                                                    "AgeGt50VLGt1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and patient_age_in_years > 50) THEN 1 ELSE 0 END)"),
                                                    "AgeGt50VLLt1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and patient_age_in_years > 50) THEN 1 ELSE 0 END)")
                                              )
                                            );
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $query = $query->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $query = $query->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."'
                        AND DATE(sample_collection_date) <= '".$endMonth."' ");
            if(isset($params['age']) && count($params['age']) > 0){
                $where = '';
                for($a=0;$a<count($params['age']);$a++){
                    if(trim($where)!= ''){ $where.= ' OR '; }
                    if($params['age'][$a] == '<2'){
                      $where.= "(vl.patient_age_in_years < 2)";
                    }else if($params['age'][$a] == '2to5') {
                      $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    }else if($params['age'][$a] == '6to14') {
                      $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    }else if($params['age'][$a] == '15to49') {
                      $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    }else if($params['age'][$a] == '>=50'){
                      $where.= "(vl.patient_age_in_years >= 50)";
                    }else if($params['age'][$a] == 'unknown'){
                      $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                    }
                }
              $where = '('.$where.')';
              $query = $query->where($where);
            }
            $query = $query->group(array(new Expression('MONTH(sample_collection_date)')));   
            $query = $query->order(array(new Expression('DATE(sample_collection_date)')));               
            $queryStr = $sql->getSqlStringForSqlObject($query);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //echo $queryStr;die;
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);   
            $j=0;
            foreach($sampleResult as $sRow){
                if($sRow["monthDate"] == null) continue;
                
                $result['Age < 2']['VL (>= 1000 cp/ml)'][$j] = $sRow["AgeLt2VLGt1000"];
                $result['Age 2-5']['VL (>= 1000 cp/ml)'][$j] = $sRow["AgeGte2Lte5VLGt1000"];
                $result['Age 6-14']['VL (>= 1000 cp/ml)'][$j] = $sRow["AgeGte6Lte14VLGt1000"];
                $result['Age 15-49']['VL (>= 1000 cp/ml)'][$j] = $sRow["AgeGte15Lte49VLGt1000"];
                $result['Age > 50']['VL (>= 1000 cp/ml)'][$j] = $sRow["AgeGt50VLGt1000"];
                
                $result['Age < 2']['VL (< 1000 cp/ml)'][$j] = $sRow["AgeLt2VLLt1000"];
                $result['Age 2-5']['VL (< 1000 cp/ml)'][$j] = $sRow["AgeGte2Lte5VLLt1000"];
                $result['Age 6-14']['VL (< 1000 cp/ml)'][$j] = $sRow["AgeGte6Lte14VLLt1000"];
                $result['Age 15-49']['VL (< 1000 cp/ml)'][$j] = $sRow["AgeGte15Lte49VLLt1000"];
                $result['Age > 50']['VL (< 1000 cp/ml)'][$j] = $sRow["AgeGt50VLLt1000"];
                
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }           
        }
       return $result;
    }
    
    public function fetchSampleTestedResultBasedVolumeDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            
            $fQuery = $sql->select()->from(array('f'=>'facility_details'))->columns(array('facility_id','facility_name'))
                        ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type'))
                        //->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                        ->where(array("DATE(vl.sample_collection_date) <='$endMonth'", "DATE(vl.sample_collection_date) >='$startMonth'"))
                        ->where('vl.lab_id !=0')
                        ->group('vl.lab_id');
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $fQuery = $fQuery->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $fQuery = $fQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                $fQuery = $fQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
            }
            $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            
            $rsQuery = $sql->select()->from(array('rs'=>'r_sample_type'))->columns(array('sample_id'));
            if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                $rsQuery = $rsQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
            }
            $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
            //$sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            
            $sampleTypeResult = $common->cacheQuery($rsQueryStr,$dbAdapter);
            
            if(count($facilityResult) >0 && count($sampleTypeResult) >0){
                $sampleId = array();
                foreach($sampleTypeResult as $samples){
                   $sampleId[] = $samples['sample_id'];
                }
                $j = 0;
                $lessTotal = 0;
                $greaterTotal = 0;
                $notTargetTotal = 0;
                foreach($facilityResult as $facility){
                    $lessThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                         ->where(array("vl.sample_collection_date <='" . $endMonth ." 23:59:59". "'", "vl.sample_collection_date >='" . $startMonth." 00:00:00". "'"))
                                         //->where('vl.sample_type="'.$sample['sample_id'].'"')
                                         ->where('vl.sample_type IN ("' . implode('", "', $sampleId) . '")')
                                         ->where(array('vl.lab_id'=>$facility['facility_id']));
                    $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                    
                    $greaterResult = $dbAdapter->query($lQueryStr." AND vl.result>=1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result['sampleName']['VL (>= 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult['total'];
                    
                    //$notTargetResult = $dbAdapter->query($lQueryStr." AND 'vl.result' ='Target Not Detected'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    //$result['sampleName']['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult['total'];
                    
                    $lessResult = $dbAdapter->query($lQueryStr." AND (vl.result<1000 or vl.result ='Target Not Detected') ", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result['sampleName']['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult['total'];
                        
                    $result['lab'][$j] = $facility['facility_name'];
                    $j++;
                }
            }
        }
        return $result;
    }
    
    public function getRequisitionFormsTested($params) {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $incompleteQuery = "vl.patient_art_no IS NULL OR vl.patient_art_no='' OR vl.patient_age_in_years IS NULL OR vl.patient_age_in_years ='' OR vl.patient_gender IS NULL OR vl.patient_gender='' OR vl.current_regimen IS NOT NULL OR vl.current_regimen !=''";
            $completeQuery = "vl.patient_art_no IS NOT NULL AND vl.patient_art_no !='' AND vl.patient_age_in_years IS NOT NULL AND vl.patient_age_in_years !='' AND vl.patient_gender IS NOT NULL AND vl.patient_gender !='' AND vl.current_regimen IS NOT NULL AND vl.current_regimen !=''";
            if(isset($params['formFields']) && count($params['formFields']) >0){
                $incompleteQuery = '';
                $completeQuery = '';
                for($f=0;$f<count($params['formFields']);$f++){
                    if(trim($params['formFields'][$f])!= ''){
                       $incompleteQuery.= 'vl.'.$params['formFields'][$f].' IS NULL OR vl.'.$params['formFields'][$f].'=""';
                       $completeQuery.= 'vl.'.$params['formFields'][$f].' IS NOT NULL AND vl.'.$params['formFields'][$f].'!=""';
                       if((count($params['formFields']) - $f) > 1){ $incompleteQuery.= ' OR '; $completeQuery.=' AND '; }
                    }  
                }
            }
            $i = 0;
            $completeResultCount = 0;
            $inCompleteResultCount = 0;
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "total" => new Expression('COUNT(*)'),
                                                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                    
                                                    "CompletedForms" => new Expression("SUM(CASE WHEN ($completeQuery) THEN 1 ELSE 0 END)"),
                                                    "IncompleteForms" => new Expression("SUM(CASE WHEN ($incompleteQuery) THEN 1 ELSE 0 END)"),
                                             
                                              )
                                            );
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."'
                        AND DATE(sample_collection_date) <= '".$endMonth."' ");
                
            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));               
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            $j=0;
            if(isset($sampleResult) && count($sampleResult) > 0){
                foreach($sampleResult as $sRow){
                    if($sRow["monthDate"] == null) continue;
                    $result['Complete'][$j] = (isset($sRow["CompletedForms"]))?(int)$sRow["CompletedForms"]:0;
                    $result['Incomplete'][$j] = (isset($sRow["IncompleteForms"]))?(int)$sRow["IncompleteForms"]:0;
                    $result['date'][$j] = $sRow["monthDate"];
                    $j++;                
                }
            }
        }
       return $result;
    }
    
    public function fetchIncompleteSampleDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        $i =0;$j =1;$k =2;$l =3;
        $result[$i]['field'] = 'Patient ART Number';
        $result[$j]['field'] = 'Current Regimen';
        $result[$k]['field'] = 'Patient Age in Years';
        $result[$l]['field'] = 'Patient Gender';
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        }
    
        $inCompleteQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                               ->join(array('f'=>'facility_details'),'f.facility_id=vl.lab_id',array(),'left');
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $inCompleteQuery = $inCompleteQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"));
        }
        if(isset($params['provinces']) && is_array($params['provinces']) && count($params['provinces']) >0){
            $inCompleteQuery = $inCompleteQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
        }
        if(isset($params['districts']) && is_array($params['districts']) && count($params['districts']) >0){
            $inCompleteQuery = $inCompleteQuery->where('f.facility_district IN ("' . implode('", "', $params['districts']) . '")');
        }
        if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
            $inCompleteQuery = $inCompleteQuery->where('vl.lab_id IN ("' . implode('", "', $params['lab']) . '")');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $inCompleteQuery = $inCompleteQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        if(isset($params['gender']) && $params['gender']=='F'){
            $inCompleteQuery = $inCompleteQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        }else if(isset($params['gender']) && $params['gender']=='M'){
            $inCompleteQuery = $inCompleteQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        }else if(isset($params['gender']) && $params['gender']=='not_specified'){
            $inCompleteQuery = $inCompleteQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
        }
        if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
            $inCompleteQuery = $inCompleteQuery->where("vl.is_patient_pregnant = 'yes'");
        }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
            $inCompleteQuery = $inCompleteQuery->where("vl.is_patient_pregnant = 'no'"); 
        }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
            $inCompleteQuery = $inCompleteQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
        }
        if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
            $inCompleteQuery = $inCompleteQuery->where("vl.is_patient_breastfeeding = 'yes'");
        }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
            $inCompleteQuery = $inCompleteQuery->where("vl.is_patient_breastfeeding = 'no'"); 
        }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
            $inCompleteQuery = $inCompleteQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
        }
        $incQueryStr = $sql->getSqlStringForSqlObject($inCompleteQuery);
        //echo $incQueryStr;die;
        $artInCompleteResult = $dbAdapter->query($incQueryStr." AND (vl.patient_art_no IS NULL OR vl.patient_art_no ='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $currentRegimenInCompleteResult = $dbAdapter->query($incQueryStr." AND (vl.current_regimen IS NULL OR vl.current_regimen ='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $ageInYearsInCompleteResult = $dbAdapter->query($incQueryStr." AND (vl.patient_age_in_years IS NULL OR vl.patient_age_in_years ='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $patientGenderInCompleteResult = $dbAdapter->query($incQueryStr." AND (vl.patient_gender IS NULL OR vl.patient_gender ='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $result[$i]['total'] = (isset($artInCompleteResult->total))?$artInCompleteResult->total:0;
        $result[$j]['total'] = (isset($currentRegimenInCompleteResult->total))?$currentRegimenInCompleteResult->total:0;
        $result[$k]['total'] = (isset($ageInYearsInCompleteResult->total))?$ageInYearsInCompleteResult->total:0;
        $result[$l]['total'] = (isset($patientGenderInCompleteResult->total))?$patientGenderInCompleteResult->total:0;
       return $result;
    }
    
    public function fetchIncompleteBarSampleDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        }
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                      ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                      ->where('vl.lab_id !=0')
                      ->group('f.facility_id');
        if(isset($params['provinces']) && is_array($params['provinces']) && count($params['provinces']) >0){
            $fQuery = $fQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
        }
        if(isset($params['districts']) && is_array($params['districts']) && count($params['districts']) >0){
            $fQuery = $fQuery->where('f.facility_district IN ("' . implode('", "', $params['districts']) . '")');
        }
        if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
            $fQuery = $fQuery->where('vl.lab_id IN ("' . implode('", "', $params['lab']) . '")');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $fQuery = $fQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        //echo $fQueryStr;die;
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(isset($facilityResult) && count($facilityResult) >0){
            $j = 0;
            foreach($facilityResult as $facility){
                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                  ->where('vl.lab_id="'.$facility['facility_id'].'"');
                if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                    $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"));
                }
                if(isset($params['gender']) && $params['gender']=='F'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                }else if(isset($params['gender']) && $params['gender']=='M'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                    $countQuery = $countQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
                }
                if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'"); 
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
                }
                if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'"); 
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                }
                $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                $completeResult = $dbAdapter->query($cQueryStr." AND vl.patient_art_no IS NOT NULL AND vl.patient_art_no !='' AND vl.current_regimen IS NOT NULL AND vl.current_regimen !='' AND vl.patient_age_in_years IS NOT NULL AND vl.patient_age_in_years !=''  AND vl.patient_gender IS NOT NULL AND vl.patient_gender != ''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['form']['Complete'][$j] = $completeResult->total;
                $inCompleteResult = $dbAdapter->query($cQueryStr." AND (vl.patient_art_no IS NULL OR vl.patient_art_no='' OR vl.current_regimen IS NULL OR vl.current_regimen='' OR vl.patient_age_in_years IS NULL OR vl.patient_age_in_years ='' OR vl.patient_gender IS NULL OR vl.patient_gender='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['form']['Incomplete'][$j] = $inCompleteResult->total;
                $result['lab'][$j] = $facility['facility_name'];
                $j++;
            }
        }
       return $result;
    }
    
    public function getSampleVolume($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";

            $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                          ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type'))
                          ->where(array("vl.sample_collection_date <='" . $endMonth ." 23:59:59". "'", "vl.sample_collection_date >='" . $startMonth." 00:00:00". "'"))
                          ->where('vl.lab_id !=0')
                          ->group('vl.lab_id');
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $fQuery = $fQuery->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $fQuery = $fQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(isset($facilityResult) && count($facilityResult) >0){
                $i = 0;
                foreach($facilityResult as $facility){
                    $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                      ->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"))
                                      ->where('vl.lab_id="'.$facility['facility_id'].'"');
                    if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested'){
                        $countQuery = $countQuery->where("(vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')");
                    }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                        $countQuery = $countQuery->where("(vl.result IS NULL OR vl.result = 'NULL' OR vl.result = '')");
                    }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                        $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
                    }
                    $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                    $countResult[$i] = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result[$i][0] = $countResult[$i]['total'];
                    $result[$i][1] = $facility['facility_name'];
                    $result[$i][2] = $facility['facility_code'];
                    $i++;
                }
            }
        }
       return $result;
    }
    
    //get female result
    public function getFemalePatientResult($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $femaleTestResult = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $query = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    //"total" => new Expression("SUM(CASE WHEN (patient_gender != '' AND patient_gender IS NOT NULL AND (patient_gender ='f' || patient_gender ='female' || patient_gender='F' || patient_gender='FEMALE')) THEN 1 ELSE 0 END)"),
                                                    "Breastfeeding" => new Expression("SUM(CASE WHEN (is_patient_breastfeeding ='yes' || is_patient_breastfeeding ='Yes' ||  is_patient_breastfeeding ='YES') THEN 1 ELSE 0 END)"),
                                                    "Not_Breastfeeding" => new Expression("SUM(CASE WHEN (is_patient_breastfeeding ='no' || is_patient_breastfeeding ='No' ||  is_patient_breastfeeding ='NO') THEN 1 ELSE 0 END)"),
                                                    "Breastfeeding_Unknown" => new Expression("SUM(CASE WHEN (is_patient_breastfeeding !='' AND is_patient_breastfeeding IS NOT NULL AND (is_patient_breastfeeding !='no' AND is_patient_breastfeeding !='No' AND  is_patient_breastfeeding !='NO') AND (is_patient_breastfeeding !='yes' AND is_patient_breastfeeding !='Yes' AND  is_patient_breastfeeding !='YES')) THEN 1 ELSE 0 END)"),
                                                    "Pregnant" => new Expression("SUM(CASE WHEN (is_patient_pregnant ='yes' || is_patient_pregnant ='Yes' ||  is_patient_pregnant ='YES') THEN 1 ELSE 0 END)"),
                                                    "Not_Pregnant" => new Expression("SUM(CASE WHEN (is_patient_pregnant ='no' || is_patient_pregnant ='No' ||  is_patient_pregnant ='NO') THEN 1 ELSE 0 END)"),
                                                    "Pregnant_Unknown" => new Expression("SUM(CASE WHEN (is_patient_pregnant !='' AND is_patient_pregnant IS NOT NULL AND (is_patient_pregnant !='no' AND is_patient_pregnant !='No' AND  is_patient_pregnant !='NO') AND (is_patient_pregnant !='yes' AND is_patient_pregnant !='Yes' AND  is_patient_pregnant !='YES')) THEN 1 ELSE 0 END)"),
                                              )
                                            );
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $query = $query->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $query = $query->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."' 
                        AND DATE(sample_collection_date) <= '".$endMonth."' AND (patient_gender='f' || patient_gender='F' || patient_gender='Female' || patient_gender='FEMALE')");
                
            $queryStr = $sql->getSqlStringForSqlObject($query);
            $femaleTestResult = $common->cacheQuery($queryStr,$dbAdapter);
        }
       return $femaleTestResult;
    }
    
    //get Line Of tratment result
    public function getLineOfTreatment($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $lineOfTreatmentResult = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $query = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "Line_Of_Treatment_1" => new Expression("SUM(CASE WHEN (line_of_treatment = 1) THEN 1 ELSE 0 END)"),
                                                    "Line_Of_Treatment_2" => new Expression("SUM(CASE WHEN (line_of_treatment = 2) THEN 1 ELSE 0 END)"),
                                                    "Line_Of_Treatment_3" => new Expression("SUM(CASE WHEN (line_of_treatment = 3) THEN 1 ELSE 0 END)"),
                                                    "Not_Specified" => new Expression("SUM(CASE WHEN ((line_of_treatment NOT IN(1,2,3)) OR (line_of_treatment IS NULL) OR (line_of_treatment= '')) THEN 1 ELSE 0 END)"),
                                              )
                                            );
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $query = $query->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $query = $query->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."'
                        AND DATE(sample_collection_date) <= '".$endMonth."'");
                         
            $queryStr = $sql->getSqlStringForSqlObject($query);
            $lineOfTreatmentResult = $common->cacheQuery($queryStr,$dbAdapter);
        }
       return $lineOfTreatmentResult;
    }
    
    //get vl out comes result
    public function getVlOutComes($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $vlOutComeResult = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "Suppressed" => new Expression("SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)"),
                                                    "Not_Suppressed" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                                              )
                                            );
            if(isset($params['provinces']) && trim($params['provinces'])!= ''){
                $sQuery = $sQuery->where('f.facility_state IN (' . $params['provinces'] . ')');
            } if(isset($params['districts']) && trim($params['districts'])!= ''){
                $sQuery = $sQuery->where('f.facility_district IN (' . $params['districts'] . ')');
            } if(isset($params['lab']) && trim($params['lab'])!= ''){
                $sQuery = $sQuery->where('f.facility_id IN (' . $params['lab'] . ')');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $sQuery = $sQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            } if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"));
            } if(isset($params['clinicId']) && trim($params['clinicId'])!= ''){
               $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')'); 
            } if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
            } if(isset($params['adherence']) && trim($params['adherence'])!=''){
                $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'"));
            } if(isset($params['age']) && is_array($params['age'])){
               $params['age'] = implode(',',$params['age']); 
            } if(isset($params['age']) && trim($params['age'])!= ''){
                $where = '';
                $params['age'] = explode(',',$params['age']);
                for($a=0;$a<count($params['age']);$a++){
                    if(trim($where)!= ''){ $where.= ' OR '; }
                    if($params['age'][$a] == '<2'){
                      $where.= "(vl.patient_age_in_years < 2)";
                    }else if($params['age'][$a] == '2to5') {
                      $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    }else if($params['age'][$a] == '6to14') {
                      $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    }else if($params['age'][$a] == '15to49') {
                      $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    }else if($params['age'][$a] == '>=50'){
                      $where.= "(vl.patient_age_in_years >= 50)";
                    }else if($params['age'][$a] == 'unknown'){
                      $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                    }
                }
              $where = '('.$where.')';
              $sQuery = $sQuery->where($where);
            } if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
            } if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested'){
                $sQuery = $sQuery->where("(vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')");
            }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                $sQuery = $sQuery->where("(sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0')");
            }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                $sQuery = $sQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
            } if(isset($params['gender']) && $params['gender']=='F'){
                $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            }else if(isset($params['gender']) && $params['gender']=='M'){
                $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
            }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                $sQuery = $sQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
            } if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'"); 
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
            } if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'"); 
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
            } if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='1'){
                $sQuery = $sQuery->where("vl.line_of_treatment = '1'");
            }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='2'){
                $sQuery = $sQuery->where("vl.line_of_treatment = '2'"); 
            }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='3'){
                $sQuery = $sQuery->where("vl.line_of_treatment = '3'"); 
            }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='not_specified'){
                $sQuery = $sQuery->where("(vl.line_of_treatment IS NULL OR vl.line_of_treatment = '' OR vl.line_of_treatment = '0')");
            }
            $queryStr = $sql->getSqlStringForSqlObject($sQuery);
            $vlOutComeResult = $common->cacheQuery($queryStr,$dbAdapter);
        }
       return $vlOutComeResult;
    }
    
    public function fetchLabTurnAroundTime($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $monthyear = date("Y-m");
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            if(strtotime($startMonth) >= strtotime($monthyear)){
                $startMonth = $endMonth = date("Y-m", strtotime("-2 months"));
            }else if(strtotime($endMonth) >= strtotime($monthyear)){
               $endMonth = date("Y-m", strtotime("-2 months")); 
            }
            $startMonth = date("Y-m", strtotime(trim($startMonth)))."-01";
            $endMonth = date("Y-m", strtotime(trim($endMonth)))."-31";
            $query = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array(
                                            "month" => new Expression("MONTH(sample_collection_date)"),
                                            "year" => new Expression("YEAR(sample_collection_date)"),
                                            "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                            "AvgDiff" => new Expression("CAST(ABS(AVG(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                                      )
                                    );
            $query = $query->where("
                        (vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')
                        AND (vl.result_approved_datetime is not null AND vl.result_approved_datetime != '' AND DATE(vl.result_approved_datetime) !='1970-01-01' AND DATE(vl.result_approved_datetime) !='0000-00-00')
                        AND vl.result is not null
                        AND vl.result != ''
                        AND DATE(vl.result_approved_datetime) >= '".$startMonth."'
                        AND DATE(vl.result_approved_datetime) <= '".$endMonth."' ");
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $query = $query->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $query = $query->group(array(new Expression('YEAR(vl.result_approved_datetime)')));
            $query = $query->group(array(new Expression('MONTH(vl.result_approved_datetime)')));
            $query = $query->order(array(new Expression('DATE(vl.sample_collection_date) ASC')));
            $queryStr = $sql->getSqlStringForSqlObject($query);
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            $j=0;
            $monthDateArray = array();
            foreach($sampleResult as $sRow){
                if($sRow["monthDate"] == null || in_array($sRow["monthDate"],$monthDateArray)) continue;
                $monthDateArray[] = $sRow["monthDate"];
                $subQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array(
                                                "total_samples_collected" => new Expression('COUNT(*)'),
                                                "total_samples_pending" => new Expression("(SUM(CASE WHEN (result is null OR result = '') AND (reason_for_sample_rejection is null OR reason_for_sample_rejection = '') THEN 1 ELSE 0 END))"),
                                              )
                                            );
                $subQuery = $subQuery->where("
                        (vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')
                        AND MONTH(vl.sample_collection_date) = '".$sRow['month']."'
                        AND YEAR(vl.sample_collection_date) = '".$sRow['year']."' ");
                if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                    $subQuery = $subQuery->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
                }else{
                    if($logincontainer->role!= 1){
                        $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                        $subQuery = $subQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                    }
                }
                $subQueryStr = $sql->getSqlStringForSqlObject($subQuery);
                $subQueryResult = $common->cacheQuery($subQueryStr,$dbAdapter);
                $result['all'][$j] = (isset($sRow["AvgDiff"]) && $sRow["AvgDiff"] != NULL && $sRow["AvgDiff"] > 0) ? round($sRow["AvgDiff"],2) : 0;
                $result['sample']['Samples Collected'][$j] = (isset($subQueryResult[0]['total_samples_collected']) && $subQueryResult[0]['total_samples_collected'] != NULL) ? $subQueryResult[0]['total_samples_collected'] : 0;
                $result['sample']['Samples Pending'][$j] = (isset($subQueryResult[0]['total_samples_pending']) && $subQueryResult[0]['total_samples_pending'] != NULL) ? $subQueryResult[0]['total_samples_pending'] : 0;
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
      return $result;
    }
    
    public function fetchFacilites($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $lResult = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $lQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('lab_id','labCount' => new \Zend\Db\Sql\Expression("COUNT(vl.lab_id)")))
                                    ->join(array('f'=>'facility_details'),'f.facility_id=vl.lab_id',array('facility_name','latitude','longitude'))
                                    ->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"))
                                    ->group('vl.lab_id');
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $lQuery = $lQuery->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else {
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $lQuery = $lQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $lQueryStr = $sql->getSqlStringForSqlObject($lQuery);
            $lResult = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(isset($lResult) && count($lResult)>0){
                $i = 0;
                foreach($lResult as $lab){
                    if($lab['lab_id']!=NULL && trim($lab['lab_id'])!='' && $lab['lab_id']!=0){
                        $lcQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                                 ->columns(array('facility_id','clinicCount' => new \Zend\Db\Sql\Expression("COUNT(vl.facility_id)")))
                                                 ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name','latitude','longitude'))
                                                 ->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"))
                                                 ->where(array("vl.lab_id"=>$lab['lab_id'],'f.facility_type'=>'1'))
                                                 ->group('vl.facility_id');
                        $lcQueryStr = $sql->getSqlStringForSqlObject($lcQuery);
                        $lResult[$i]['clinic'] = $dbAdapter->query($lcQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $i++;
                    }
                }
            }
        }
       return $lResult;
    }
    //end lab dashboard details
    
    //start clinic details
    public function fetchOverallViralLoadStatus($params){
        $testedTotal = 0;
        $notTestedTotal = 0;
        $lessTotal = 0;
        $gTotal = 0;
        $overAllTotal = 0;
        //total samples
        $where = '';
        $overAllTotal = $this->fetchOverallViralLoadResult($params,$where);
        //total tested samples
        $where = '((vl.result !="" AND vl.result IS NOT NULL) OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection!= "" AND vl.reason_for_sample_rejection != 0))';
        $testedTotal = $this->fetchOverallViralLoadResult($params,$where);
        //total not tested samples
        $where = '((vl.result = "" OR vl.result IS NULL) AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = "" OR vl.reason_for_sample_rejection = 0))';
        $notTestedTotal = $this->fetchOverallViralLoadResult($params,$where);
        //total VL <1000
        $where = '(vl.result < 1000 OR vl.result="Target Not Detected")';
        $lessTotal = $this->fetchOverallViralLoadResult($params,$where);
        //total VL >=1000
        $where = 'vl.result >= 1000';
        $gTotal = $this->fetchOverallViralLoadResult($params,$where);
      return array($testedTotal,$notTestedTotal,$lessTotal,$gTotal,$overAllTotal);
    }
    
    public function fetchViralLoadStatusBasedOnAge($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $endDate = trim($s_c_date[1]);
            }
            $query = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                "mTotal" => new Expression("SUM(CASE WHEN (vl.patient_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),
                                                "mGreaterThanEqual1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and vl.patient_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),
                                                "mLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and vl.patient_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),
                                                "fTotal" => new Expression("SUM(CASE WHEN (vl.patient_gender in('f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                                                "fGreaterThanEqual1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and vl.patient_gender in('f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                                                "fLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and vl.patient_gender in('f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                                                "nsTotal" => new Expression("SUM(CASE WHEN (vl.patient_gender NOT in('m','Male','M','MALE','f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                                                "nsGreaterThanEqual1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and vl.patient_gender NOT in('m','Male','M','MALE','f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                                                "nsLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and vl.patient_gender NOT in('m','Male','M','MALE','f','Female','F','FEMALE')) THEN 1 ELSE 0 END)")
                                              )
                                            )
                                    ->where(array("DATE(vl.sample_collection_date) <='$endDate'","DATE(vl.sample_collection_date) >='$startDate'"));
            if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
                $query = $query->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $query = $query->where('vl.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            //if(isset($params['testResult']) && trim($params['testResult']) == '<1000'){
            //  $squery = $squery->where("vl.result < 1000 or vl.result='Target Not Detected'");
            //}else if(isset($params['testResult']) && trim($params['testResult']) == '>=1000') {
            //  $squery = $squery->where("vl.result >= 1000");
            //}
            if(isset($params['sampleTypeId']) && $params['sampleTypeId']!=''){
                $query = $query->where('vl.sample_type="'.base64_decode(trim($params['sampleTypeId'])).'"');
            }
            //print_r($params['age']);die;
            if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                $where = '';
                for($a=0;$a<count($params['age']);$a++){
                    if(trim($where)!= ''){ $where.= ' OR '; }
                    if($params['age'][$a] == '<2'){
                      $where.= "(vl.patient_age_in_years < 2)";
                    }else if($params['age'][$a] == '2to5') {
                      $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    }else if($params['age'][$a] == '6to14') {
                      $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    }else if($params['age'][$a] == '15to49') {
                      $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    }else if($params['age'][$a] == '>=50'){
                      $where.= "(vl.patient_age_in_years >= 50)";
                    }else if($params['age'][$a] == 'unknown'){
                      $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                    }
                }
              $where = '('.$where.')';
              $query = $query->where($where);
            }
            if(isset($params['adherence']) && trim($params['adherence'])!=''){
                $query = $query->where(array("vl.arv_adherance_percentage = '".$params['adherence']."'")); 
            }
            if(isset($params['gender']) && $params['gender']=='F'){
                $query = $query->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            }else if(isset($params['gender']) && $params['gender']=='M'){
                $query = $query->where("vl.patient_gender IN ('m','male','M','MALE')");
            }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                $query = $query->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
            }
            if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                $query = $query->where("vl.is_patient_pregnant = 'yes'");
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                $query = $query->where("vl.is_patient_pregnant = 'no'"); 
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                $query = $query->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
            }
            if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                $query = $query->where("vl.is_patient_breastfeeding = 'yes'");
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                $query = $query->where("vl.is_patient_breastfeeding = 'no'"); 
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                $query = $query->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
            }
            $queryStr = $sql->getSqlStringForSqlObject($query);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            //set display data
            $j = 0;
            foreach($sampleResult as $sample){
                $result['Total']['Male'][$j] = (isset($sample["mTotal"]))?$sample["mTotal"]:0;
                $result['Total']['Female'][$j] = (isset($sample["fTotal"]))?$sample["fTotal"]:0;
                $result['Total']['Not Specified'][$j] = (isset($sample["nsTotal"]))?$sample["nsTotal"]:0;
                $result['Suppressed']['Male'][$j] = (isset($sample["mLesserThan1000"]))?$sample["mLesserThan1000"]:0;
                $result['Suppressed']['Female'][$j] = (isset($sample["fLesserThan1000"]))?$sample["fLesserThan1000"]:0;
                $result['Supressed']['Not Specified'][$j] = (isset($sample["nsLesserThan1000"]))?$sample["nsLesserThan1000"]:0;
                $result['Not Suppressed']['Male'][$j] = (isset($sample["mGreaterThanEqual1000"]))?$sample["mGreaterThanEqual1000"]:0;
                $result['Not Suppressed']['Female'][$j] = (isset($sample["fGreaterThanEqual1000"]))?$sample["fGreaterThanEqual1000"]:0;
                $result['Not Suppressed']['Not Specified'][$j] = (isset($sample["nsGreaterThanEqual1000"]))?$sample["nsGreaterThanEqual1000"]:0;
              $j++;
            }
        }
      return $result;
    }
    
    public function fetchSampleTestedReason($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rResult = array();
        $common = new CommonService($this->sm);
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $endDate = trim($s_c_date[1]);
            }
            $rQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array('total' => new Expression('COUNT(*)'), 'monthDate' => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%M-%Y')")))
                            ->join(array('tr'=>'r_vl_test_reasons'),'tr.test_reason_id=vl.reason_for_vl_testing', array('test_reason_name'))
                            ->where(array("DATE(vl.sample_collection_date) >='$startDate'", "DATE(vl.sample_collection_date) <='$endDate'"))
                            //->where('vl.facility_id !=0')
                            //->where('vl.reason_for_vl_testing="'.$reason['test_reason_id'].'"');
                            ->group('tr.test_reason_id');
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $rQuery = $rQuery->where('vl.facility_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $rQuery = $rQuery->where('vl.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            if(isset($params['testResult']) && trim($params['testResult']) == '<1000'){
              $rQuery = $rQuery->where("vl.result < 1000 or vl.result='Target Not Detected'");
            }else if(isset($params['testResult']) && trim($params['testResult']) == '>=1000') {
              $rQuery = $rQuery->where("vl.result >= 1000");
            }
            if(isset($params['sampleTypeId']) && $params['sampleTypeId']!=''){
                $rQuery = $rQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleTypeId'])).'"');
            }
            //print_r($params['age']);die;
            if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                $where = '';
                for($a=0;$a<count($params['age']);$a++){
                    if(trim($where)!= ''){ $where.= ' OR '; }
                    if($params['age'][$a] == '<2'){
                      $where.= "(vl.patient_age_in_years < 2)";
                    }else if($params['age'][$a] == '2to5') {
                      $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    }else if($params['age'][$a] == '6to14') {
                      $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    }else if($params['age'][$a] == '15to49') {
                      $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    }else if($params['age'][$a] == '>=50'){
                      $where.= "(vl.patient_age_in_years >= 50)";
                    }else if($params['age'][$a] == 'unknown'){
                      $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                    }
                }
              $where = '('.$where.')';
              $rQuery = $rQuery->where($where);
            }
            if(isset($params['adherence']) && trim($params['adherence'])!=''){
                $rQuery = $rQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
            }
            if(isset($params['gender']) && $params['gender']=='F'){
                $rQuery = $rQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            }else if(isset($params['gender']) && $params['gender']=='M'){
                $rQuery = $rQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
            }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                $rQuery = $rQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
            }
            if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                $rQuery = $rQuery->where("vl.is_patient_pregnant = 'yes'");
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                $rQuery = $rQuery->where("vl.is_patient_pregnant = 'no'"); 
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                $rQuery = $rQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
            }
            if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                $rQuery = $rQuery->where("vl.is_patient_breastfeeding = 'yes'");
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                $rQuery = $rQuery->where("vl.is_patient_breastfeeding = 'no'"); 
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                $rQuery = $rQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
            }
            if(isset($params['testReason'] ) && trim($params['testReason'])!=''){
                $rQuery = $rQuery->where(array("vl.reason_for_vl_testing ='".base64_decode($params['testReason'])."'")); 
            }
            $rQueryStr = $sql->getSqlStringForSqlObject($rQuery);
            //echo $rQueryStr;die;
            //$qResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $qResult = $common->cacheQuery($rQueryStr,$dbAdapter);
            $j=0;
            foreach($qResult as $r){
                $rResult[$r['test_reason_name']][$j]['total'] = (int)$r['total'];
                $rResult['date'][$j] = $r['monthDate'];
                $j++;
            }
        }
        return $rResult;
    }
    
    public function fetchOverallViralLoadResult($params,$where){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sResult = array();
        $common = new CommonService($this->sm);
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $endDate = trim($s_c_date[1]);
            }
            $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array('total' => new Expression('COUNT(*)')))
                            //->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type')
                            ->where(array("DATE(vl.sample_collection_date) <='$endDate'",
                                          "DATE(vl.sample_collection_date) >='$startDate'"));
            if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
                $squery = $squery->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $squery = $squery->where('vl.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            //if(isset($params['testResult']) && trim($params['testResult']) == '<1000'){
            //  $squery = $squery->where("vl.result < 1000 or vl.result='Target Not Detected'");
            //}else if(isset($params['testResult']) && trim($params['testResult']) == '>=1000') {
            //  $squery = $squery->where("vl.result >= 1000");
            //}
            if(isset($params['sampleTypeId']) && $params['sampleTypeId']!=''){
                $squery = $squery->where('vl.sample_type="'.base64_decode(trim($params['sampleTypeId'])).'"');
            }
            if(isset($params['adherence']) && trim($params['adherence'])!=''){
                $squery = $squery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
            }
            //print_r($params['age']);die;
            if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                $ageWhere = '';
                for($a=0;$a<count($params['age']);$a++){
                    if(trim($ageWhere)!= ''){ $ageWhere.= ' OR '; }
                    if($params['age'][$a] == '<2'){
                      $ageWhere.= "(vl.patient_age_in_years < 2)";
                    }else if($params['age'][$a] == '2to5') {
                      $ageWhere.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    }else if($params['age'][$a] == '6to14') {
                      $ageWhere.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    }else if($params['age'][$a] == '15to49') {
                      $ageWhere.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    }else if($params['age'][$a] == '>=50'){
                      $ageWhere.= "(vl.patient_age_in_years >= 50)";
                    }else if($params['age'][$a] == 'unknown'){
                      $ageWhere.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                    }
                }
              $ageWhere = '('.$ageWhere.')';
              $squery = $squery->where($ageWhere);
            }
            if(isset($params['gender']) && $params['gender']=='F'){
                $squery = $squery->where("(patient_gender ='f' OR patient_gender ='female' OR patient_gender='F' OR patient_gender='FEMALE')");
            }else if(isset($params['gender']) && $params['gender']=='M'){
                $squery = $squery->where("(patient_gender ='m' OR patient_gender ='male' OR patient_gender='M' OR patient_gender='MALE')");
            }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                $squery = $squery->where("(patient_gender !='m' AND patient_gender !='male' AND patient_gender!='M' AND patient_gender!='MALE') AND (patient_gender !='f' AND patient_gender !='female' AND patient_gender!='F' AND patient_gender!='FEMALE')");
            }
            if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                $squery = $squery->where("vl.is_patient_pregnant = 'yes'");
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                $squery = $squery->where("vl.is_patient_pregnant = 'no'"); 
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                $squery = $squery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
            }
            if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                $squery = $squery->where("vl.is_patient_breastfeeding = 'yes'");
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                $squery = $squery->where("vl.is_patient_breastfeeding = 'no'"); 
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                $squery = $squery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
            }
            if(trim($where)!=''){
              $squery = $squery->where($where);  
            }
            $sQueryStr = $sql->getSqlStringForSqlObject($squery);
            //echo $sQueryStr;die;
            $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        }
       return $sResult;
    }
    //end clinic details
    
    //get distinict date
    public function getDistinctDate($endDate,$startDate){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array(new Expression('DISTINCT YEAR(sample_collection_date) as year,MONTH(sample_collection_date) as month,DAY(sample_collection_date) as day')))
                            //->where('vl.lab_id !=0')
                            ->order('month ASC')->order('day ASC');
        if(isset($startDate) && trim($endDate)!= ''){
            if(trim($startDate) != trim($endDate)){
                $squery = $squery->where(array("vl.sample_collection_date <='" . $endDate ." 23:59:59". "'", "vl.sample_collection_date >='" . $startDate." 00:00:00". "'"));
            }else{
               $fromMonth = date("Y-m", strtotime(trim($startDate)));
               $month = strtotime($fromMonth);
               $m = date('m', $month);
               $year = date('Y', $month);
               $squery = $squery->where("Month(sample_collection_date)='".$m."' AND Year(sample_collection_date)='".$year."'"); 
            }
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($squery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }
    
    public function fetchAllTestResults($parameters) {
        $logincontainer = new Container('credo');
        $queryContainer = new Container('query');
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('sample_code','facility_name','DATE_FORMAT(sample_collection_date,"%d-%b-%Y")','rejection_reason_name','DATE_FORMAT(sample_testing_date,"%d-%b-%Y")','result');
        $orderColumns = array('sample_code','facility_name','sample_collection_date','rejection_reason_name','sample_testing_date','result');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        $startDate = '';
        $endDate = '';
	if(isset($parameters['sampleCollectionDate']) && trim($parameters['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $parameters['sampleCollectionDate']);
            if(isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $startDate = trim($s_c_date[0]);
            }
            if(isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $endDate = trim($s_c_date[1]);
            }
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array('vl_sample_id','sample_code','sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),'sample_type','sampleTestingDate'=>new Expression('DATE(sample_testing_date)'),'result_value_log','result_value_absolute','result_value_text','result'))
				->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
				->join(array('r_r_r'=>'r_sample_rejection_reasons'),'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection',array('rejection_reason_name'),'left')
				->where(array('f.facility_type'=>'1'));
        if(isset($parameters['sampleCollectionDate']) && trim($parameters['sampleCollectionDate'])!= ''){
            $sQuery = $sQuery->where(array("vl.sample_collection_date <='" . $endDate ." 23:59:59". "'", "vl.sample_collection_date >='" . $startDate." 00:00:00". "'"));
        }
        if(isset($parameters['clinicId']) && trim($parameters['clinicId']) !=''){
            $sQuery = $sQuery->where('vl.facility_id IN (' .$parameters['clinicId'] . ')');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $sQuery = $sQuery->where('vl.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        if(isset($parameters['testResult']) && trim($parameters['testResult']) == '<1000'){
            $sQuery = $sQuery->where("vl.result < 1000 or vl.result='Target Not Detected'");
        }else if(isset($parameters['testResult']) && trim($parameters['testResult']) == '>=1000') {
            $sQuery = $sQuery->where("vl.result >= 1000");
        }
        if(isset($parameters['sampleTypeId'] ) && trim($parameters['sampleTypeId'])!=''){
            $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleTypeId'])).'"');
        }
        //print_r($parameters['age']);die;
        if(isset($parameters['age']) && trim($parameters['age'])!= ''){
            $where = '';
            $parameters['age'] = explode(',',$parameters['age']);
            for($a=0;$a<count($parameters['age']);$a++){
                if(trim($where)!= ''){ $where.= ' OR '; }
                if($parameters['age'][$a] == '<2'){
                  $where.= "(vl.patient_age_in_years < 2)";
                }else if($parameters['age'][$a] == '2to5') {
                  $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                }else if($parameters['age'][$a] == '6to14') {
                  $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                }else if($parameters['age'][$a] == '15to49') {
                  $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                }else if($parameters['age'][$a] == '>=50'){
                  $where.= "(vl.patient_age_in_years >= 50)";
                }else if($parameters['age'][$a] == 'unknown'){
                  $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                }
            }
          $where = '('.$where.')';
          $sQuery = $sQuery->where($where);
        }
        if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'")); 
        }
        if(isset($parameters['gender']) && $parameters['gender']=='F'){
            $sQuery = $sQuery->where("(patient_gender ='f' OR patient_gender ='female' OR patient_gender='F' OR patient_gender='FEMALE')");
        }else if(isset($parameters['gender']) && $parameters['gender']=='M'){
            $sQuery = $sQuery->where("(patient_gender ='m' OR patient_gender ='male' OR patient_gender='M' OR patient_gender='MALE')");
        }else if(isset($parameters['gender']) && $parameters['gender']=='not_specified'){
            $sQuery = $sQuery->where("(patient_gender !='m' AND patient_gender !='male' AND patient_gender!='M' AND patient_gender!='MALE') AND (patient_gender !='f' AND patient_gender !='female' AND patient_gender!='F' AND patient_gender!='FEMALE')");
        }
        if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='yes'){
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
        }else if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='no'){
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'"); 
        }else if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='unreported'){
            $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
        }
        if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='yes'){
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
        }else if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='no'){
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'"); 
        }else if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='unreported'){
            $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
        }
        if(isset($parameters['result']) && trim($parameters['result'])=='result'){
            $sQuery = $sQuery->where("vl.result !='' AND vl.result IS NOT NULL"); 
        }else if(isset($parameters['result']) && trim($parameters['result'])=='noresult'){
            $sQuery = $sQuery->where("(vl.result ='' OR vl.result IS NULL)");
        }else if(isset($parameters['result']) && trim($parameters['result'])=='rejected'){
            $sQuery = $sQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection!= '' AND vl.reason_for_sample_rejection != 0");
        }
        
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }
        $queryContainer->resultQuery = $sQuery;
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array('vl_sample_id','sample_code','sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),'sample_type','sampleTestingDate'=>new Expression('DATE(sample_testing_date)'),'result_value_log','result_value_absolute','result_value_text','result'))
				->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('r_r_r'=>'r_sample_rejection_reasons'),'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection',array('rejection_reason_name'),'left')
				->where(array('f.facility_type'=>'1'));
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
            $iQuery = $iQuery->where('vl.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
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
        
	$common = new CommonService($this->sm);
        $viewText = $common->translate('View');
        $pdfText = $common->translate('PDF');
        foreach ($rResult as $aRow) {
            $row = array();
            $sampleCollectionDate = '';
	    if(isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate']!= NULL && trim($aRow['sampleCollectionDate'])!="" && $aRow['sampleCollectionDate']!= '0000-00-00'){
                $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
            }
            $sampleTestedDate = '';
            if(isset($aRow['sampleTestingDate']) && $aRow['sampleTestingDate']!= NULL && trim($aRow['sampleTestingDate'])!="" && $aRow['sampleTestingDate']!= '0000-00-00'){
                $sampleTestedDate = $common->humanDateFormat($aRow['sampleTestingDate']);
            }
            $pdfButtCss = ($aRow['result'] == null || trim($aRow['result']) == "")?'display:none':'';
            $row[] = $aRow['sample_code'];
            $row[] = ucwords($aRow['facility_name']);
            $row[] = $sampleCollectionDate;
            $row[] = (isset($aRow['rejection_reason_name']))?ucwords($aRow['rejection_reason_name']):'';
            $row[] = $sampleTestedDate;
	    $row[] = $aRow['result'];
	    $row[]='<a href="/clinics/test-result-view/'.base64_encode($aRow['vl_sample_id']).'" class="btn btn-primary btn-xs" target="_blank">'.$viewText.'</a>&nbsp;&nbsp;<a href="javascript:void(0);" class="btn btn-danger btn-xs" style="'.$pdfButtCss.'" onclick="generateResultPDF('.$aRow['vl_sample_id'].');">'.$pdfText.'</a>';
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    //get sample tested result details
    public function fetchClinicSampleTestedResults($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $endDate = trim($s_c_date[1]);
            }
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                      ->columns(array(
                                                    //"total" => new Expression('COUNT(*)'),
                                                    "day" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%b-%Y')"),
                                                    
                                                    "DBSGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.result >= 1000 and vl.sample_type=2) THEN 1 ELSE 0 END)"),
                                                    "DBSLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') and vl.sample_type=2) THEN 1 ELSE 0 END)"),
                                                   
                                                    "OGreaterThan1000" => new Expression("SUM(CASE WHEN vl.result>=1000 and vl.sample_type!=2 THEN 1 ELSE 0 END)"),
                                                    "OLesserThan1000" => new Expression("SUM(CASE WHEN (vl.result<1000 or vl.result='Target Not Detected') and vl.sample_type!=2 THEN 1 ELSE 0 END)"),
                                                    
                                              )
                                            )
                                      ->where(array("DATE(vl.sample_collection_date) <='$endDate'", "DATE(vl.sample_collection_date) >='$startDate'"));
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $queryStr = $queryStr->where('vl.facility_id IN ("' . implode('", "', $params['facilityId']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $queryStr = $queryStr->where('vl.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            if(isset($params['testResult']) && trim($params['testResult']) == '<1000'){
              $queryStr = $queryStr->where("vl.result < 1000 or vl.result='Target Not Detected'");
            }else if(isset($params['testResult']) && trim($params['testResult']) == '>=1000') {
              $queryStr = $queryStr->where("vl.result >= 1000");
            }
            if(isset($params['frmSrc']) && $params['frmSrc'] =='change'){
                if(isset($params['sampleType']) && $params['sampleType'] == 'dbs'){
                    $queryStr = $queryStr->where('vl.sample_type IN(2)');
                }else if(isset($params['sampleType']) && $params['sampleType'] == 'others'){
                    $queryStr = $queryStr->where('vl.sample_type NOT IN(2)');
                }
            }else if(isset($params['sampleTypeId']) && $params['sampleTypeId']!=''){
                $queryStr = $queryStr->where('vl.sample_type="'.base64_decode(trim($params['sampleTypeId'])).'"');
            }
            //print_r($params['age']);die;
            if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                $where = '';
                for($a=0;$a<count($params['age']);$a++){
                    if(trim($where)!= ''){ $where.= ' OR '; }
                    if($params['age'][$a] == '<2'){
                      $where.= "(vl.patient_age_in_years < 2)";
                    }else if($params['age'][$a] == '2to5') {
                      $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    }else if($params['age'][$a] == '6to14') {
                      $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    }else if($params['age'][$a] == '15to49') {
                      $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    }else if($params['age'][$a] == '>=50'){
                      $where.= "(vl.patient_age_in_years >= 50)";
                    }else if($params['age'][$a] == 'unknown'){
                      $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                    }
                }
              $where = '('.$where.')';
              $queryStr = $queryStr->where($where);
            }
            if(isset($params['adherence']) && trim($params['adherence'])!=''){
                $queryStr = $queryStr->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
            }
            if(isset($params['gender']) && $params['gender']=='F'){
                $queryStr = $queryStr->where("(patient_gender ='f' OR patient_gender ='female' OR patient_gender='F' OR patient_gender='FEMALE')");
            }else if(isset($params['gender']) && $params['gender']=='M'){
                $queryStr = $queryStr->where("(patient_gender ='m' OR patient_gender ='male' OR patient_gender='M' OR patient_gender='MALE')");
            }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                $queryStr = $queryStr->where("(patient_gender !='m' AND patient_gender !='male' AND patient_gender!='M' AND patient_gender!='MALE') AND (patient_gender !='f' AND patient_gender !='female' AND patient_gender!='F' AND patient_gender!='FEMALE')");
            }
            if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                $queryStr = $queryStr->where("vl.is_patient_pregnant = 'yes'");
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                $queryStr = $queryStr->where("vl.is_patient_pregnant = 'no'"); 
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                $queryStr = $queryStr->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
            }
            if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                $queryStr = $queryStr->where("vl.is_patient_breastfeeding = 'yes'");
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                $queryStr = $queryStr->where("vl.is_patient_breastfeeding = 'no'"); 
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                $queryStr = $queryStr->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
            }
            
            $queryStr = $queryStr->group(array(new Expression('DATE(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));            
            
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            $sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            
            $j=0;
            foreach($sampleResult as $sRow){
                if($sRow["day"] == null) continue;
                $result['DBS']['VL (>= 1000 cp/ml)'][$j] = $sRow["DBSGreaterThan1000"];
                $result['DBS']['VL (< 1000 cp/ml)'][$j] = $sRow["DBSLesserThan1000"];
                $result['Others']['VL (>= 1000 cp/ml)'][$j] = $sRow["OGreaterThan1000"];
                $result['Others']['VL (< 1000 cp/ml)'][$j] = $sRow["OLesserThan1000"];
                $result['date'][$j] = $sRow["day"];
                $j++;
            }
        }
       return $result;
    }
    
    
    public function fetchSampleDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        }
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                                ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                                ->where('vl.lab_id !=0')
                                ->group('vl.lab_id');
        if(isset($params['provinces']) && is_array($params['provinces']) && count($params['provinces']) >0){
            $fQuery = $fQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
        }
        if(isset($params['districts']) && is_array($params['districts']) && count($params['districts']) >0){
            $fQuery = $fQuery->where('f.facility_district IN ("' . implode('", "', $params['districts']) . '")');
        }
        if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', $params['lab']) . '")');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        //echo $fQueryStr;die;
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(isset($facilityResult) && count($facilityResult) > 0){
            $i = 0;
            foreach($facilityResult as $facility){
                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                            //->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array(),'left')
                                            ->where('vl.lab_id="'.$facility['facility_id'].'"');
                if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                    $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"));
                }
                if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
                    $countQuery = $countQuery->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
                }
                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                    $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                }
                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                    $countQuery = $countQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                }
                //print_r($params['age']);die;
                if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                    $where = '';
                    for($a=0;$a<count($params['age']);$a++){
                        if(trim($where)!= ''){ $where.= ' OR '; }
                        if($params['age'][$a] == '<2'){
                          $where.= "(vl.patient_age_in_years < 2)";
                        }else if($params['age'][$a] == '2to5') {
                          $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                        }else if($params['age'][$a] == '6to14') {
                          $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                        }else if($params['age'][$a] == '15to49') {
                          $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                        }else if($params['age'][$a] == '>=50'){
                          $where.= "(vl.patient_age_in_years >= 50)";
                        }else if($params['age'][$a] == 'unknown'){
                          $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                        }
                    }
                  $where = '('.$where.')';
                  $countQuery = $countQuery->where($where);
                }
                if(isset($params['testResult']) && $params['testResult'] == '<1000'){
                  $countQuery = $countQuery->where("vl.result < 1000 or vl.result='Target Not Detected'");
                }else if(isset($params['testResult']) && $params['testResult'] == '>=1000') {
                  $countQuery = $countQuery->where("vl.result >= 1000");
                }
                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                    $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                }
                if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested'){
                    $countQuery = $countQuery->where("(vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')");
                }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                    $countQuery = $countQuery->where("(vl.result IS NULL OR vl.result = 'NULL' OR vl.result = '')");
                }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                    $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
                }
                if(isset($params['gender']) && $params['gender']=='F'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                }else if(isset($params['gender']) && $params['gender']=='M'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                    $countQuery = $countQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
                }
                if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'"); 
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                }
                if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'"); 
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                }
                if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='1'){
                    $countQuery = $countQuery->where("vl.line_of_treatment = '1'");
                }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='2'){
                    $countQuery = $countQuery->where("vl.line_of_treatment = '2'"); 
                }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='3'){
                    $countQuery = $countQuery->where("vl.line_of_treatment = '3'"); 
                }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='not_specified'){
                    $countQuery = $countQuery->where("(vl.line_of_treatment IS NULL OR vl.line_of_treatment = '' OR vl.line_of_treatment = '0')");
                }
                $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                //echo $cQueryStr;die;
                $countResult[$i] = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result[$i][0] = $countResult[$i]['total'];
                $result[$i][1] = $facility['facility_name'];
                $result[$i][2] = $facility['facility_code'];
                $i++;
            }
        }
       return $result;
    }
    
    public function fetchBarSampleDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        }
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                                ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                                ->where('vl.lab_id !=0')
                                ->group('vl.lab_id');
        if(isset($params['provinces']) && is_array($params['provinces']) && count($params['provinces']) >0){
            $fQuery = $fQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
        }
        if(isset($params['districts']) && is_array($params['districts']) && count($params['districts']) >0){
            $fQuery = $fQuery->where('f.facility_district IN ("' . implode('", "', $params['districts']) . '")');
        }
        if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', $params['lab']) . '")');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(isset($facilityResult) && count($facilityResult) >0){
            $j = 0;
            foreach($facilityResult as $facility){
                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                    //->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                                    //->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array(),'left')
                                    ->where('vl.lab_id="'.$facility['facility_id'].'"');
                if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                    $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"));
                }
                if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
                    $countQuery = $countQuery->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
                }
                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                    $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                }
                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                    $countQuery = $countQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                }
                //print_r($params['age']);die;
                if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                    $where = '';
                    for($a=0;$a<count($params['age']);$a++){
                        if(trim($where)!= ''){ $where.= ' OR '; }
                        if($params['age'][$a] == '<2'){
                          $where.= "(vl.patient_age_in_years < 2)";
                        }else if($params['age'][$a] == '2to5') {
                          $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                        }else if($params['age'][$a] == '6to14') {
                          $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                        }else if($params['age'][$a] == '15to49') {
                          $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                        }else if($params['age'][$a] == '>=50'){
                          $where.= "(vl.patient_age_in_years >= 50)";
                        }else if($params['age'][$a] == 'unknown'){
                          $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                        }
                    }
                  $where = '('.$where.')';
                  $countQuery = $countQuery->where($where);
                }
                if(isset($params['testResult']) && $params['testResult'] == '<1000'){
                   $countQuery = $countQuery->where("vl.result < 1000 or vl.result='Target Not Detected'");
                }else if(isset($params['testResult']) && $params['testResult'] == '>=1000') {
                   $countQuery = $countQuery->where("vl.result >= 1000");
                }
                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                    $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                }
                if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested'){
                    $countQuery = $countQuery->where("(vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')");
                }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                    $countQuery = $countQuery->where("(vl.result IS NULL OR vl.result = 'NULL' OR vl.result = '')");
                }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                    $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
                }
                if(isset($params['gender']) && $params['gender']=='F'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                }else if(isset($params['gender']) && $params['gender']=='M'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                    $countQuery = $countQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
                }
                if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
                }
                if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'"); 
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                }
                if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='1'){
                    $countQuery = $countQuery->where("vl.line_of_treatment = '1'");
                }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='2'){
                    $countQuery = $countQuery->where("vl.line_of_treatment = '2'"); 
                }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='3'){
                    $countQuery = $countQuery->where("vl.line_of_treatment = '3'"); 
                }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='not_specified'){
                    $countQuery = $countQuery->where("(vl.line_of_treatment IS NULL OR vl.line_of_treatment = '' OR vl.line_of_treatment = '0')");
                }
                $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                //echo $cQueryStr;die;
                $lessResult = $dbAdapter->query($cQueryStr." AND (vl.result < 1000 or vl.result='Target Not Detected')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['sample']['Suppressed'][$j] = $lessResult->total;
                $greaterResult = $dbAdapter->query($cQueryStr." AND vl.result >= 1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['sample']['Not Suppressed'][$j] = $greaterResult->total;
                $rejectionResult = $dbAdapter->query($cQueryStr." AND vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['sample']['Rejected'][$j] = $rejectionResult->total;
                $result['lab'][$j] = $facility['facility_name'];
                $j++;
            }
        }
       return $result;
    }
    
    public function fetchLabSampleDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                        ->columns(array(
                                                        "DBS" => new Expression("SUM(CASE WHEN (vl.sample_type=2) THEN 1 ELSE 0 END)"),
                                                        "Others" => new Expression("SUM(CASE WHEN vl.result>=1000 and vl.sample_type!=2 THEN 1 ELSE 0 END)"),
                                                  )
                                                )
                                        ->join(array('f'=>'facility_details'),'f.facility_id=vl.lab_id',array(),'left')
                                        ->where(array("DATE(vl.sample_collection_date) <='$endMonth'", "DATE(vl.sample_collection_date) >='$startMonth'"));
            if(isset($params['provinces']) && is_array($params['provinces']) && count($params['provinces']) >0){
                $sQuery = $sQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
            }
            if(isset($params['districts']) && is_array($params['districts']) && count($params['districts']) >0){
                $sQuery = $sQuery->where('f.facility_district IN ("' . implode('", "', $params['districts']) . '")');
            }
            if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $params['lab']) . '")');
            }else {
                if($logincontainer->role!= 1){
                   $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                   $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
                $sQuery = $sQuery->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
            }
            if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
            }
            
            if(isset($params['adherence']) && trim($params['adherence'])!=''){
                $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
            }
            //print_r($params['age']);die;
            if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                $where = '';
                for($a=0;$a<count($params['age']);$a++){
                    if(trim($where)!= ''){ $where.= ' OR '; }
                    if($params['age'][$a] == '<2'){
                      $where.= "(vl.patient_age_in_years < 2)";
                    }else if($params['age'][$a] == '2to5') {
                      $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    }else if($params['age'][$a] == '6to14') {
                      $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    }else if($params['age'][$a] == '15to49') {
                      $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    }else if($params['age'][$a] == '>=50'){
                      $where.= "(vl.patient_age_in_years >= 50)";
                    }else if($params['age'][$a] == 'unknown'){
                      $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                    }
                }
              $where = '('.$where.')';
              $sQuery = $sQuery->where($where);
            }
            if(isset($params['testResult']) && $params['testResult'] == '<1000'){
              $sQuery = $sQuery->where("vl.result < 1000 or vl.result='Target Not Detected'");
            }else if(isset($params['testResult']) && $params['testResult'] == '>=1000') {
              $sQuery = $sQuery->where("vl.result >= 1000");
            }
            if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
            }
            if(isset($params['gender']) && $params['gender']=='F'){
                $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            }else if(isset($params['gender']) && $params['gender']=='M'){
                $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
            }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                $sQuery = $sQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
            }
            if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'"); 
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
            }
            if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'"); 
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
            }
            $sQuery = $sQuery->group(array(new Expression('DATE(sample_collection_date)')));   
            $sQuery = $sQuery->order(array(new Expression('DATE(sample_collection_date)')));          
    
            $sQuery = $sql->getSqlStringForSqlObject($sQuery);
            $sampleResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($sampleResult as $count){
                $result['DBS'] += $count['DBS'];
                $result['Others'] += $count['Others'];
            }
        }
       return $result;
    }
    
    public function fetchLabBarSampleDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $start = strtotime(date("Y-m", strtotime(trim($params['fromDate']))));
            $end = strtotime(date("Y-m", strtotime(trim($params['toDate']))));
            $j = 0;
            while($start <= $end){
                $month = date('m', $start);$year = date('Y', $start);$monthYearFormat = date("M-Y", $start);
                $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('samples' => new Expression('COUNT(*)')))
                              ->join(array('f'=>'facility_details'),'f.facility_id=vl.lab_id',array(),'left')
                              ->where("Month(sample_collection_date)='".$month."' AND Year(sample_collection_date)='".$year."'");
                if(isset($params['provinces']) && is_array($params['provinces']) && count($params['provinces']) >0){
                    $sQuery = $sQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
                }
                if(isset($params['districts']) && is_array($params['districts']) && count($params['districts']) >0){
                    $sQuery = $sQuery->where('f.facility_district IN ("' . implode('", "', $params['districts']) . '")');
                }
                if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
                    $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $params['lab']) . '")');
                }else {
                    if($logincontainer->role!= 1){
                       $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                       $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                    }
                }
                if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
                    $sQuery = $sQuery->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
                }
                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                    $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                }
                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                    $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                }
                //print_r($params['age']);die;
                if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                    $where = '';
                    for($a=0;$a<count($params['age']);$a++){
                        if(trim($where)!= ''){ $where.= ' OR '; }
                        if($params['age'][$a] == '<2'){
                          $where.= "(vl.patient_age_in_years < 2)";
                        }else if($params['age'][$a] == '2to5') {
                          $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                        }else if($params['age'][$a] == '6to14') {
                          $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                        }else if($params['age'][$a] == '15to49') {
                          $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                        }else if($params['age'][$a] == '>=50'){
                          $where.= "(vl.patient_age_in_years >= 50)";
                        }else if($params['age'][$a] == 'unknown'){
                          $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                        }
                    }
                  $where = '('.$where.')';
                  $sQuery = $sQuery->where($where);
                }
                if(isset($params['testResult']) && $params['testResult'] == '<1000'){
                    $sQuery = $sQuery->where("vl.result < 1000 or vl.result='Target Not Detected'");
                }else if(isset($params['testResult']) && $params['testResult'] == '>=1000') {
                    $sQuery = $sQuery->where("vl.result >= 1000");
                }
                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                    $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                }
                if(isset($params['gender']) && $params['gender']=='F'){
                    $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                }else if(isset($params['gender']) && $params['gender']=='M'){
                    $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                    $sQuery = $sQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
                }
                if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                    $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                    $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'"); 
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                    $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
                }
                if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                    $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                    $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'"); 
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                    $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                }
                $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
                //echo $sQueryStr;die;
                $lessResult = $dbAdapter->query($sQueryStr." AND (vl.result<1000 or vl.result = 'Target Not Detected' or vl.result='tnd')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['rslt']['VL (< 1000 cp/ml)'][$j] = $lessResult->samples;
                
                $greaterResult = $dbAdapter->query($sQueryStr." AND vl.result>=1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['rslt']['VL (>= 1000 cp/ml)'][$j] = $greaterResult->samples;
                
                //$notTargetResult = $dbAdapter->query($sQueryStr." AND 'vl.result'='Target Not Detected'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                //$result['rslt']['VL Not Detected'][$j] = $notTargetResult->samples;
                $result['date'][$j] = $monthYearFormat;
                $start = strtotime("+1 month", $start);
              $j++;
            }
        }
       return $result;
    }
    
    public function fetchLabFilterSampleDetails($parameters){
        $logincontainer = new Container('credo');
        $queryContainer = new Container('query');
        $common = new CommonService($this->sm);
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('DATE_FORMAT(sample_collection_date,"%d-%b-%Y")','sample_name','facility_name');
        $orderColumns = array('sample_collection_date','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id','sample_name','facility_name');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($parameters['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($parameters['toDate'])))."-31";
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array(
                                                'sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),
                                                'total_samples_received' => new Expression('COUNT(*)'),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                "suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)"),
                                                "not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                                                "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)")
                                ))
				->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
				->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
				->where('f.facility_type = "1" AND vl.sample_collection_date!= "" AND vl.sample_collection_date IS NOT NULL AND vl.sample_collection_date!= "0000-00-00 00:00:00"')
                                ->group(new Expression('DATE(sample_collection_date)'))
                                ->group('vl.sample_type')
                                ->group('vl.facility_id');
        //filter start
        if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
            $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"));
        } if(isset($parameters['provinces']) && trim($parameters['provinces'])!= ''){
            $sQuery = $sQuery->where('f.facility_state IN (' . $parameters['provinces'] . ')');
        } if(isset($parameters['districts']) && trim($parameters['districts'])!= ''){
            $sQuery = $sQuery->where('f.facility_district IN (' . $parameters['districts'] . ')');
        } if(isset($parameters['lab']) && trim($parameters['lab'])!= ''){
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['lab'] . ')');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        } if(isset($parameters['clinicId']) && trim($parameters['clinicId'])!= ''){
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
        } if(isset($parameters['currentRegimen']) && trim($parameters['currentRegimen'])!=''){
            $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($parameters['currentRegimen'])).'"');
        } if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'")); 
        } if(isset($parameters['age']) && trim($parameters['age'])!= ''){
            $where = '';
            $parameters['age'] = explode(',',$parameters['age']);
            for($a=0;$a<count($parameters['age']);$a++){
                if(trim($where)!= ''){ $where.= ' OR '; }
                if($parameters['age'][$a] == '<2'){
                  $where.= "(vl.patient_age_in_years < 2)";
                }else if($parameters['age'][$a] == '2to5') {
                  $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                }else if($parameters['age'][$a] == '6to14') {
                  $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                }else if($parameters['age'][$a] == '15to49') {
                  $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                }else if($parameters['age'][$a] == '>=50'){
                  $where.= "(vl.patient_age_in_years >= 50)";
                }else if($parameters['age'][$a] == 'unknown'){
                  $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                }
            }
          $where = '('.$where.')';
          $sQuery = $sQuery->where($where);
        } if(isset($parameters['testResult']) && $parameters['testResult'] == '<1000'){
          $sQuery = $sQuery->where("vl.result < 1000 or vl.result='Target Not Detected'");
        }else if(isset($parameters['testResult']) && $parameters['testResult'] == '>=1000') {
          $sQuery = $sQuery->where("vl.result >= 1000");
        } if(isset($parameters['sampleType']) && trim($parameters['sampleType'])!=''){
            $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleType'])).'"');
        } if(isset($parameters['gender']) && $parameters['gender']=='F'){
            $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        }else if(isset($parameters['gender']) && $parameters['gender']=='M'){
            $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        }else if(isset($parameters['gender']) && $parameters['gender']=='not_specified'){
            $sQuery = $sQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
        } if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='yes'){
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
        }else if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='no'){
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'"); 
        }else if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='unreported'){
            $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
        } if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='yes'){
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
        }else if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='no'){
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'"); 
        }else if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='unreported'){
            $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array(
                                                'sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),
                                                'total_samples_received' => new Expression('COUNT(*)'),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                "suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)"),
                                                "not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                                                "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)")
                                ))
				->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
				->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
				->where('f.facility_type = "1" AND vl.sample_collection_date!= "" AND vl.sample_collection_date IS NOT NULL AND vl.sample_collection_date!= "0000-00-00 00:00:00"')
                                ->group(new Expression('DATE(sample_collection_date)'))
                                ->group('vl.sample_type')
                                ->group('vl.facility_id');
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
            $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
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
            $sampleCollectionDate = '';
	    if(isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate']!= null && trim($aRow['sampleCollectionDate'])!="" && $aRow['sampleCollectionDate']!= '0000-00-00'){
                $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
            }
            $row[] = $sampleCollectionDate;
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['suppressed_samples'];
            $row[] = $aRow['not_suppressed_samples'];
            $row[] = $aRow['rejected_samples'];
            $row[] = ucwords($aRow['sample_name']);
            $row[] = ucwords($aRow['facility_name']);
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchFilterSampleDetails($parameters){
        $logincontainer = new Container('credo');
        $queryContainer = new Container('query');
        $common = new CommonService($this->sm);
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('facility_name','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id');
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
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($parameters['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($parameters['toDate'])))."-31";
        }
        $sQuery = $sql->select()->from(array('f'=>'facility_details'))
                                ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array(
                                                                                                            "total_samples_received" => new Expression("(COUNT(*))"),
                                                                                                            "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                                                                            "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                                                                            "suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)"),
                                                                                                            "not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                                                                                                            "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)")
                                                                                                            ))
                                ->where('vl.lab_id !=0')
                                ->group('vl.lab_id');
        if(isset($parameters['provinces']) && trim($parameters['provinces'])!= ''){
            $sQuery = $sQuery->where('f.facility_state IN (' . $parameters['provinces'] . ')');
        } if(isset($parameters['districts']) && trim($parameters['districts'])!= ''){
            $sQuery = $sQuery->where('f.facility_district IN (' . $parameters['districts'] . ')');
        } if(isset($parameters['lab']) && trim($parameters['lab'])!= ''){
            $sQuery = $sQuery->where('f.facility_id IN (' . $parameters['lab'] . ')');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $sQuery = $sQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        } if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
            $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"));
        } if(isset($parameters['clinicId']) && trim($parameters['clinicId'])!= ''){
           $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')'); 
        } if(isset($parameters['currentRegimen']) && trim($parameters['currentRegimen'])!=''){
            $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($parameters['currentRegimen'])).'"');
        } if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'"));
        } if(isset($parameters['age']) && is_array($parameters['age'])){
           $parameters['age'] = implode(',',$parameters['age']); 
        } if(isset($parameters['age']) && trim($parameters['age'])!= ''){
            $where = '';
            $parameters['age'] = explode(',',$parameters['age']);
            for($a=0;$a<count($parameters['age']);$a++){
                if(trim($where)!= ''){ $where.= ' OR '; }
                if($parameters['age'][$a] == '<2'){
                  $where.= "(vl.patient_age_in_years < 2)";
                }else if($parameters['age'][$a] == '2to5') {
                  $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                }else if($parameters['age'][$a] == '6to14') {
                  $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                }else if($parameters['age'][$a] == '15to49') {
                  $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                }else if($parameters['age'][$a] == '>=50'){
                  $where.= "(vl.patient_age_in_years >= 50)";
                }else if($parameters['age'][$a] == 'unknown'){
                  $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                }
            }
          $where = '('.$where.')';
          $sQuery = $sQuery->where($where);
        } if(isset($parameters['sampleType']) && trim($parameters['sampleType'])!=''){
            $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleType'])).'"');
        } if(isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'sample_tested'){
            $sQuery = $sQuery->where("(vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')");
        }else if(isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'samples_not_tested') {
            $sQuery = $sQuery->where("(sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0')");
        }else if(isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'sample_rejected') {
            $sQuery = $sQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
        } if(isset($parameters['gender']) && $parameters['gender']=='F'){
            $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        }else if(isset($parameters['gender']) && $parameters['gender']=='M'){
            $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        }else if(isset($parameters['gender']) && $parameters['gender']=='not_specified'){
            $sQuery = $sQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
        } if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='yes'){
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
        }else if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='no'){
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'"); 
        }else if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='unreported'){
            $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
        } if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='yes'){
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
        }else if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='no'){
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'"); 
        }else if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='unreported'){
            $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
        } if(isset($parameters['lineOfTreatment']) && $parameters['lineOfTreatment']=='1'){
            $sQuery = $sQuery->where("vl.line_of_treatment = '1'");
        }else if(isset($parameters['lineOfTreatment']) && $parameters['lineOfTreatment']=='2'){
            $sQuery = $sQuery->where("vl.line_of_treatment = '2'"); 
        }else if(isset($parameters['lineOfTreatment']) && $parameters['lineOfTreatment']=='3'){
            $sQuery = $sQuery->where("vl.line_of_treatment = '3'"); 
        }else if(isset($parameters['lineOfTreatment']) && $parameters['lineOfTreatment']=='not_specified'){
            $sQuery = $sQuery->where("(vl.line_of_treatment IS NULL OR vl.line_of_treatment = '' OR vl.line_of_treatment = '0')");
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('f'=>'facility_details'))
                                ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array(
                                                                                                            "total_samples_received" => new Expression("(COUNT(*))"),
                                                                                                            "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                                                                            "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                                                                            "suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)"),
                                                                                                            "not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                                                                                                            "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)")
                                                                                                            ))
                                ->where('vl.lab_id !=0')
                                ->group('vl.lab_id');
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
            $iQuery = $iQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
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
        //print_r($parameters);die;
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = ucwords($aRow['facility_name']);
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['suppressed_samples'];
            $row[] = $aRow['not_suppressed_samples'];
            $row[] = $aRow['rejected_samples'];
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchFilterSampleTatDetails($parameters){
        $logincontainer = new Container('credo');
        $queryContainer = new Container('query');
        $common = new CommonService($this->sm);
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array("DATE_FORMAT(sample_collection_date,'%b-%Y')");
        $orderColumns = array('sample_collection_date','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id');
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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($parameters['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($parameters['toDate'])));
        }
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array(
                                                "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                "suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)"),
                                                "not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                                                "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                                                "AvgDiff" => new Expression("CAST(ABS(AVG(TIMESTAMPDIFF(DAY,sample_tested_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                                              )
                                            )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.lab_id',array(),'left');
        if(isset($parameters['provinces']) && trim($parameters['provinces'])!= ''){
            $sQuery = $sQuery->where('f.facility_state IN (' . $parameters['provinces'] . ')');
        } if(isset($parameters['districts']) && trim($parameters['districts'])!= ''){
            $sQuery = $sQuery->where('f.facility_district IN (' . $parameters['districts'] . ')');
        } if(isset($parameters['lab']) && trim($parameters['lab'])!= ''){
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['lab'] . ')');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        } if(isset($parameters['clinicId']) && trim($parameters['clinicId'])!= ''){
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
        } if(isset($parameters['sampleType']) && trim($parameters['sampleType'])!=''){
            $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleType'])).'"');
        } if(isset($parameters['gender']) && $parameters['gender']=='F'){
            $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        }else if(isset($parameters['gender']) && $parameters['gender']=='M'){
            $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        }else if(isset($parameters['gender']) && $parameters['gender']=='not_specified'){
            $sQuery = $sQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
        } if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='yes'){
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
        }else if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='no'){
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'"); 
        }else if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='unreported'){
            $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
        } if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='yes'){
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
        }else if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='no'){
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'"); 
        }else if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='unreported'){
            $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
        }
        $sQuery = $sQuery->where("
                                            (sample_collection_date is not null AND sample_collection_date != '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00')
                        AND (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')
                    AND result is not null
                    AND result != '' 
                    AND DATE(sample_collection_date) >= '".$startMonth."-01' 
                    AND DATE(sample_collection_date) <= '".$endMonth."-31' AND vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL'");
                
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
        $sQuery = $sQuery->group(array(new Expression('MONTH(sample_collection_date)')));
        $sQuery = $sQuery->order(array(new Expression('DATE(sample_collection_date)')));  
        
        $queryContainer->sampleResultTestedTATQuery = $sQuery;
         $queryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $queryStr;die;
        $rResult = $common->cacheQuery($queryStr,$dbAdapter);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array(
                                                "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                "suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)"),
                                                "not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                                                "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                                                "AvgDiff" => new Expression("CAST(ABS(AVG(TIMESTAMPDIFF(DAY,sample_tested_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                                              )
                                            )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.lab_id',array(),'left');
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
            $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $iQuery = $iQuery->group(array(new Expression('MONTH(sample_collection_date)')));
        $iQuery = $iQuery->order(array(new Expression('DATE(sample_collection_date)')));
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
        //error_log($iQueryStr);die;
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
            $row[] = $aRow['monthDate'];
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['suppressed_samples'];
            $row[] = $aRow['not_suppressed_samples'];
            $row[] = $aRow['rejected_samples'];
            $row[] = (isset($aRow['AvgDiff']))?round($aRow['AvgDiff'],2):0;
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchProvinceBarSampleResultAwaitedDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $globalDb = new \Application\Model\GlobalTable($this->adapter);
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if(isset($params['daterange']) && trim($params['daterange'])!= ''){
            $splitDate = explode('to',$params['daterange']);
        }
        $pQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                      ->columns(array())
                      ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array())
                      ->join(array('l_d'=>'location_details'),'l_d.location_id=f.facility_state',array('location_id','location_name'),'left')
                      ->where('vl.facility_id !=0')
                      ->group('f.facility_state');
        if(isset($params['provinces']) && is_array($params['provinces']) && count($params['provinces']) >0){
            $pQuery = $pQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
        }
        if(isset($params['districts']) && is_array($params['districts']) && count($params['districts']) >0){
            $pQuery = $pQuery->where('f.facility_district IN ("' . implode('", "', $params['districts']) . '")');
        }
        if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
            $pQuery = $pQuery->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
        }
        $pQueryStr = $sql->getSqlStringForSqlObject($pQuery);
        $pResult  = $dbAdapter->query($pQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(isset($pResult) && count($pResult) >0){
            $p = 0;
            foreach($pResult as $province){
                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                  ->columns(
                                            array("total" => new Expression("SUM(CASE WHEN (result is NULL OR result ='') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='') THEN 1
                                                                                     ELSE 0
                                                                                     END)")))
                                  ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array())
                                  ->where(array('f.facility_state'=>$province['location_id']));
                if(isset($params['daterange']) && trim($params['daterange'])!= ''){
                    if(trim($splitDate[0])!= '' && trim($splitDate[1])!= ''){
                        $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) ." 00:00:00". "'", "vl.sample_collection_date <='" .trim($splitDate[1])." 23:59:59". "'"));
                    }
                }else{
                    if(isset($params['frmSource']) && trim($params['frmSource']) == '<'){
                       $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
                    }else if(isset($params['frmSource']) && trim($params['frmSource']) == '>'){
                       $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");  
                    }
                }
                if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
                    $countQuery = $countQuery->where('vl.lab_id IN ("' . implode('", "', $params['lab']) . '")');
                }else{
                    if($logincontainer->role!= 1){
                        $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                        $countQuery = $countQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                    }
                }
                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                    $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                }
                //print_r($params['age']);die;
                if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                    $where = '';
                    for($a=0;$a<count($params['age']);$a++){
                        if(trim($where)!= ''){ $where.= ' OR '; }
                        if($params['age'][$a] == '<2'){
                          $where.= "(vl.patient_age_in_years < 2)";
                        }else if($params['age'][$a] == '2to5') {
                          $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                        }else if($params['age'][$a] == '6to14') {
                          $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                        }else if($params['age'][$a] == '15to49') {
                          $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                        }else if($params['age'][$a] == '>=50'){
                          $where.= "(vl.patient_age_in_years >= 50)";
                        }else if($params['age'][$a] == 'unknown'){
                          $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                        }
                    }
                  $where = '('.$where.')';
                  $countQuery = $countQuery->where($where);
                }
                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                    $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                }
                if(isset($params['gender']) && $params['gender']=='F'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                }else if(isset($params['gender']) && $params['gender']=='M'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                    $countQuery = $countQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
                }
                if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
                }
                if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'"); 
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                }
                $countQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                //echo $countQueryStr;die;
                $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['province'][$p] = ($province['location_name']!= null && $province['location_name']!= '')?$province['location_name']:'Not Specified';
                $result['sample']['Results Awaited'][$p] = (isset($countResult->total))?$countResult->total:0;
              $p++;
            }
        }
      return $result;
    }
    
    public function fetchDistrictBarSampleResultAwaitedDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $globalDb = new \Application\Model\GlobalTable($this->adapter);
        $facilityDb = new \Application\Model\FacilityTable($this->adapter);
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if(isset($params['daterange']) && trim($params['daterange'])!= ''){
            $splitDate = explode('to',$params['daterange']);
        }
        $dQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                      ->columns(array())
                      ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array())
                      ->join(array('f_p_l_d'=>'location_details'),'f_p_l_d.location_id=f.facility_state',array(),'left')
                      ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('location_id','location_name'),'left')
                      ->where('vl.facility_id !=0')
                      ->group('f.facility_district');
        if(isset($params['srcVal']) && trim($params['srcVal'])!= '' && $params['src'] == 'province'){
            if($params['srcVal'] == 'Not Specified'){
                $dQuery = $dQuery->where('f.facility_state IS NULL OR f.facility_state = ""');
            }else{
                $locationInfo = $facilityDb->fatchLocationInfoByName($params['srcVal']);
                $dQuery = $dQuery->where(array('f.facility_state'=>$locationInfo->location_id));
            }
        }else if(isset($params['provinces']) && is_array($params['provinces']) && count($params['provinces']) >0){
            $dQuery = $dQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
        }
        if(isset($params['districts']) && is_array($params['districts']) && count($params['districts']) >0){
            $dQuery = $dQuery->where('f.facility_district IN ("' . implode('", "', $params['districts']) . '")');
        }
        if(isset($params['srcVal']) && trim($params['srcVal'])!= '' && $params['src'] == 'lab'){
            $facilityInfo = $facilityDb->fatchFacilityInfoByName($params['srcVal']);
            $dQuery = $dQuery->where(array('vl.lab_id'=>$facilityInfo->facility_id));
        }else{
            if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
                $dQuery = $dQuery->where('vl.lab_id IN ("' . implode('", "', $params['lab']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $dQuery = $dQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
        }
        if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
            $dQuery = $dQuery->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
        }
        $dQueryStr = $sql->getSqlStringForSqlObject($dQuery);
        //echo $dQueryStr;die;
        $dResult  = $dbAdapter->query($dQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(isset($dResult) && count($dResult) >0){
            $d = 0;
            foreach($dResult as $district){
                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                             ->columns(
                                          array("total" => new Expression("SUM(CASE WHEN (result is NULL OR result ='') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='') THEN 1
                                                                              ELSE 0
                                                                              END)")))
                             ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array())
                             ->where(array('f.facility_district'=>$district['location_id']));
                if(isset($params['daterange']) && trim($params['daterange'])!= ''){
                    if(trim($splitDate[0])!= '' && trim($splitDate[1])!= ''){
                        $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) ." 00:00:00". "'", "vl.sample_collection_date <='" .trim($splitDate[1])." 23:59:59". "'"));
                    }
                }else{
                    if(isset($params['frmSource']) && trim($params['frmSource']) == '<'){
                       $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
                    }else if(isset($params['frmSource']) && trim($params['frmSource']) == '>'){
                       $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");  
                    }
                }
                if(isset($params['srcVal']) && trim($params['srcVal'])!= '' && $params['src'] == 'lab'){
                    $facilityInfo = $facilityDb->fatchFacilityInfoByName($params['srcVal']);
                    $countQuery = $countQuery->where(array('vl.lab_id'=>$facilityInfo->facility_id));
                }else{
                    if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
                        $countQuery = $countQuery->where('vl.lab_id IN ("' . implode('", "', $params['lab']) . '")');
                    }else{
                        if($logincontainer->role!= 1){
                            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                            $countQuery = $countQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                        }
                    }
                }
                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                    $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                }
                //print_r($params['age']);die;
                if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                    $where = '';
                    for($a=0;$a<count($params['age']);$a++){
                        if(trim($where)!= ''){ $where.= ' OR '; }
                        if($params['age'][$a] == '<2'){
                          $where.= "(vl.patient_age_in_years < 2)";
                        }else if($params['age'][$a] == '2to5') {
                          $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                        }else if($params['age'][$a] == '6to14') {
                          $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                        }else if($params['age'][$a] == '15to49') {
                          $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                        }else if($params['age'][$a] == '>=50'){
                          $where.= "(vl.patient_age_in_years >= 50)";
                        }else if($params['age'][$a] == 'unknown'){
                          $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                        }
                    }
                  $where = '('.$where.')';
                  $countQuery = $countQuery->where($where);
                }
                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                    $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                }
                if(isset($params['gender']) && $params['gender']=='F'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                }else if(isset($params['gender']) && $params['gender']=='M'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                    $countQuery = $countQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
                }
                if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
                }
                if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'"); 
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                }
                $countQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['district'][$d] = ($district['location_name']!= null && $district['location_name']!= '')?$district['location_name']:'Not Specified';
                $result['sample']['Results Awaited'][$d] = (isset($countResult->total))?$countResult->total:0;
              $d++;
            }
        }
       return $result;
    }
    
    public function fetchClinicBarSampleResultAwaitedDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $globalDb = new \Application\Model\GlobalTable($this->adapter);
        $facilityDb = new \Application\Model\FacilityTable($this->adapter);
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if(isset($params['daterange']) && trim($params['daterange'])!= ''){
            $splitDate = explode('to',$params['daterange']);
        }
        $clinicQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                      ->columns(array())
                      ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_id','facility_name'))
                      ->join(array('f_p_l_d'=>'location_details'),'f_p_l_d.location_id=f.facility_state',array(),'left')
                      ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array(),'left')
                      ->where('vl.facility_id !=0')
                      ->group('vl.facility_id');
        if(isset($params['srcVal']) && trim($params['srcVal'])!= '' && $params['src'] == 'province'){
            if($params['srcVal'] == 'Not Specified'){
                $clinicQuery = $clinicQuery->where('f.facility_state IS NULL OR f.facility_state = ""');
            }else{
                $locationInfo = $facilityDb->fatchLocationInfoByName($params['srcVal']);
                $clinicQuery = $clinicQuery->where(array('f.facility_state'=>$locationInfo->location_id));
            }
        }else if(isset($params['provinces']) && is_array($params['provinces']) && count($params['provinces']) >0){
            $clinicQuery = $clinicQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
        }
        if(isset($params['srcVal']) && trim($params['srcVal'])!= '' && $params['src'] == 'district'){
            if($params['srcVal'] == 'Not Specified'){
                $clinicQuery = $clinicQuery->where('f.facility_district IS NULL OR f.facility_district = ""');
            }else{
               $locationInfo = $facilityDb->fatchLocationInfoByName($params['srcVal']);
               $clinicQuery = $clinicQuery->where(array('f.facility_district'=>$locationInfo->location_id));
            }
        }else if(isset($params['districts']) && is_array($params['districts']) && count($params['districts']) >0){
            $clinicQuery = $clinicQuery->where('f.facility_district IN ("' . implode('", "', $params['districts']) . '")');
        }
        if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
            $clinicQuery = $clinicQuery->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
        }
        if(isset($params['srcVal']) && trim($params['srcVal'])!= '' && $params['src'] == 'lab'){
            $facilityInfo = $facilityDb->fatchFacilityInfoByName($params['srcVal']);
            $clinicQuery = $clinicQuery->where(array('vl.lab_id'=>$facilityInfo->facility_id));
        }else{
            if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
                $clinicQuery = $clinicQuery->where('vl.lab_id IN ("' . implode('", "', $params['lab']) . '")');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $clinicQuery = $clinicQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
        }
        $clinicQueryStr = $sql->getSqlStringForSqlObject($clinicQuery);
        $clinicResult  = $dbAdapter->query($clinicQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(isset($clinicResult) && count($clinicResult) >0){
            $c = 0;
            foreach($clinicResult as $clinic){
                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                             array("total" => new Expression("SUM(CASE WHEN (result is NULL OR result ='') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='') THEN 1
                                                                                 ELSE 0
                                                                                 END)")))
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array())
                                ->where(array('vl.facility_id'=>$clinic['facility_id']));
                if(isset($params['daterange']) && trim($params['daterange'])!= ''){
                    if(trim($splitDate[0])!= '' && trim($splitDate[1])!= ''){
                        $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) ." 00:00:00". "'", "vl.sample_collection_date <='" .trim($splitDate[1])." 23:59:59". "'"));
                    }
                }else{
                    if(isset($params['frmSource']) && trim($params['frmSource']) == '<'){
                       $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
                    }else if(isset($params['frmSource']) && trim($params['frmSource']) == '>'){
                       $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");  
                    }
                }
                if(isset($params['srcVal']) && trim($params['srcVal'])!= '' && $params['src'] == 'lab'){
                    $facilityInfo = $facilityDb->fatchFacilityInfoByName($params['srcVal']);
                    $countQuery = $countQuery->where(array('vl.lab_id'=>$facilityInfo->facility_id));
                }else{
                    if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
                        $countQuery = $countQuery->where('vl.lab_id IN ("' . implode('", "', $params['lab']) . '")');
                    }else{
                        if($logincontainer->role!= 1){
                            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                            $countQuery = $countQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                        }
                    }
                }
                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                    $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                }
                //print_r($params['age']);die;
                if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                    $where = '';
                    for($a=0;$a<count($params['age']);$a++){
                        if(trim($where)!= ''){ $where.= ' OR '; }
                        if($params['age'][$a] == '<2'){
                          $where.= "(vl.patient_age_in_years < 2)";
                        }else if($params['age'][$a] == '2to5') {
                          $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                        }else if($params['age'][$a] == '6to14') {
                          $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                        }else if($params['age'][$a] == '15to49') {
                          $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                        }else if($params['age'][$a] == '>=50'){
                          $where.= "(vl.patient_age_in_years >= 50)";
                        }else if($params['age'][$a] == 'unknown'){
                          $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                        }
                    }
                  $where = '('.$where.')';
                  $countQuery = $countQuery->where($where);
                }
                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                    $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                }
                if(isset($params['gender']) && $params['gender']=='F'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                }else if(isset($params['gender']) && $params['gender']=='M'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                    $countQuery = $countQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
                }
                if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
                }
                if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'"); 
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                }
                $countQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['clinic'][$c] = ucwords($clinic['facility_name']);
                $result['sample']['Results Awaited'][$c] = (isset($countResult->total))?$countResult->total:0;
              $c++;
            }
        }
      return $result;
    }
    
    public function fetchFacilityBarSampleResultAwaitedDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $globalDb = new \Application\Model\GlobalTable($this->adapter);
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if(isset($params['daterange']) && trim($params['daterange'])!= ''){
            $splitDate = explode('to',$params['daterange']);
        }
        $labQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                        ->columns(array())
                        ->join(array('f'=>'facility_details'),'f.facility_id=vl.lab_id',array('facility_id','facility_name'))
                        ->where('vl.lab_id !=0')
                        ->group('vl.lab_id');
        if(isset($params['provinces']) && is_array($params['provinces']) && count($params['provinces']) >0){
            $labQuery = $labQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
        }
        if(isset($params['districts']) && is_array($params['districts']) && count($params['districts']) >0){
            $labQuery = $labQuery->where('f.facility_district IN ("' . implode('", "', $params['districts']) . '")');
        }
        if(isset($params['lab']) && is_array($params['lab']) && count($params['lab']) >0){
            $labQuery = $labQuery->where('vl.lab_id IN ("' . implode('", "', $params['lab']) . '")');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $labQuery = $labQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        $labQueryStr = $sql->getSqlStringForSqlObject($labQuery);
        $labResult  = $dbAdapter->query($labQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(isset($labResult) && count($labResult) >0){
            $l = 0;
            foreach($labResult as $lab){
                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                             ->columns(
                                          array("total" => new Expression("SUM(CASE WHEN (result is NULL OR result ='') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='') THEN 1
                                                                              ELSE 0
                                                                              END)")))
                             ->join(array('f'=>'facility_details'),'f.facility_id=vl.lab_id',array())
                             ->where(array('vl.lab_id'=>$lab['facility_id']));
                if(isset($params['daterange']) && trim($params['daterange'])!= ''){
                    if(trim($splitDate[0])!= '' && trim($splitDate[1])!= ''){
                        $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) ." 00:00:00". "'", "vl.sample_collection_date <='" .trim($splitDate[1])." 23:59:59". "'"));
                    }
                }else{
                    if(isset($params['frmSource']) && trim($params['frmSource']) == '<'){
                       $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
                    }else if(isset($params['frmSource']) && trim($params['frmSource']) == '>'){
                       $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");  
                    }
                }
                if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
                    $countQuery = $countQuery->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
                }
                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                    $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                }
                //print_r($params['age']);die;
                if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                    $where = '';
                    for($a=0;$a<count($params['age']);$a++){
                        if(trim($where)!= ''){ $where.= ' OR '; }
                        if($params['age'][$a] == '<2'){
                          $where.= "(vl.patient_age_in_years < 2)";
                        }else if($params['age'][$a] == '2to5') {
                          $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                        }else if($params['age'][$a] == '6to14') {
                          $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                        }else if($params['age'][$a] == '15to49') {
                          $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                        }else if($params['age'][$a] == '>=50'){
                          $where.= "(vl.patient_age_in_years >= 50)";
                        }else if($params['age'][$a] == 'unknown'){
                          $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                        }
                    }
                  $where = '('.$where.')';
                  $countQuery = $countQuery->where($where);
                }
                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                    $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                }
                if(isset($params['gender']) && $params['gender']=='F'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                }else if(isset($params['gender']) && $params['gender']=='M'){
                    $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                    $countQuery = $countQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
                }
                if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
                }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
                }
                if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'"); 
                }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                    $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                }
                $countQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                //echo $countQueryStr;die;
                $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['lab'][$l] = ucwords($lab['facility_name']);
                $result['sample']['Results Awaited'][$l] = (isset($countResult->total))?$countResult->total:0;
              $l++;
            }
        }
      return $result;
    }
    
    public function fetchFilterSampleResultAwaitedDetails($parameters){
        $logincontainer = new Container('credo');
        $queryContainer = new Container('query');
        $common = new CommonService($this->sm);
        $globalDb = new \Application\Model\GlobalTable($this->adapter);
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('sample_code',"DATE_FORMAT(sample_collection_date,'%d-%b-%Y')",'f.facility_code','f.facility_name','sample_name','l.facility_code','l.facility_name',"DATE_FORMAT(sample_received_at_vl_lab_datetime,'%d-%b-%Y')");
        $orderColumns = array('sample_code','sample_collection_date','f.facility_code','sample_name','l.facility_name','sample_received_at_vl_lab_datetime');
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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        if(isset($parameters['daterange']) && trim($parameters['daterange'])!= ''){
            $splitDate = explode('to',$parameters['daterange']);
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array('sample_code','collectionDate' => new Expression('DATE(sample_collection_date)'),'receivedDate' => new Expression('DATE(sample_received_at_vl_lab_datetime)')))
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facilityName'=>'facility_name','facilityCode'=>'facility_code'))
                                ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'),'left')
                                ->join(array('l'=>'facility_details'),'l.facility_id=vl.lab_id',array('labName'=>'facility_name'))
                                ->where('(result IS NULL OR result ="") AND (reason_for_sample_rejection IS NULL OR reason_for_sample_rejection ="")');
        if(isset($parameters['daterange']) && trim($parameters['daterange'])!= ''){
            if(trim($splitDate[0])!= '' && trim($splitDate[1])!= ''){
                $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $splitDate[0] ." 00:00:00". "'", "vl.sample_collection_date <='" .$splitDate[1]." 23:59:59". "'"));
            }
        }else{
            if(isset($parameters['frmSource']) && trim($parameters['frmSource']) == '<'){
                $sQuery = $sQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
            }else if(isset($parameters['frmSource']) && trim($parameters['frmSource']) == '>'){
                $sQuery = $sQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");  
            }
        }
        if(isset($parameters['provinces']) && trim($parameters['provinces'])!= ''){
            $sQuery = $sQuery->where('f.facility_state IN (' . $parameters['provinces'] . ')');
        }
        if(isset($parameters['districts']) && trim($parameters['districts'])!= ''){
            $sQuery = $sQuery->where('f.facility_district IN (' . $parameters['districts'] . ')');
        }
        if(isset($parameters['lab']) && trim($parameters['lab'])!= ''){
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['lab'] . ')');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        if(isset($parameters['clinicId']) && trim($parameters['clinicId'])!= ''){
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
        }
        if(isset($parameters['currentRegimen']) && trim($parameters['currentRegimen'])!=''){
            $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($parameters['currentRegimen'])).'"');
        }
        //print_r($parameters['age']);die;
        if(isset($parameters['age']) && trim($parameters['age'])!= ''){
            $where = '';
            $parameters['age'] = explode(',',$parameters['age']);
            for($a=0;$a<count($parameters['age']);$a++){
                if(trim($where)!= ''){ $where.= ' OR '; }
                if($parameters['age'][$a] == '<2'){
                  $where.= "(vl.patient_age_in_years < 2)";
                }else if($parameters['age'][$a] == '2to5') {
                  $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                }else if($parameters['age'][$a] == '6to14') {
                  $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                }else if($parameters['age'][$a] == '15to49') {
                  $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                }else if($parameters['age'][$a] == '>=50'){
                  $where.= "(vl.patient_age_in_years >= 50)";
                }else if($parameters['age'][$a] == 'unknown'){
                  $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                }
            }
          $where = '('.$where.')';
          $sQuery = $sQuery->where($where);
        }
        if(isset($parameters['sampleType']) && trim($parameters['sampleType'])!=''){
            $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleType'])).'"');
        }
        if(isset($parameters['gender']) && $parameters['gender']=='F'){
            $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        }else if(isset($parameters['gender']) && $parameters['gender']=='M'){
            $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        }else if(isset($parameters['gender']) && $parameters['gender']=='not_specified'){
            $sQuery = $sQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
        }
        if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='yes'){
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
        }else if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='no'){
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'"); 
        }else if(isset($parameters['isPregnant']) && $parameters['isPregnant']=='unreported'){
            $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
        }
        if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='yes'){
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
        }else if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='no'){
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'"); 
        }else if(isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding']=='unreported'){
            $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
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
        $queryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $queryStr;die;
        $rResult = $common->cacheQuery($queryStr,$dbAdapter);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array('sample_code','collectionDate' => new Expression('DATE(sample_collection_date)'),'receivedDate' => new Expression('DATE(sample_received_at_vl_lab_datetime)')))
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facilityName'=>'facility_name','facilityCode'=>'facility_code'))
                                ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'),'left')
                                ->join(array('l'=>'facility_details'),'l.facility_id=vl.lab_id',array('labName'=>'facility_name'))
                                ->where('(result IS NULL OR result ="") AND (reason_for_sample_rejection IS NULL OR reason_for_sample_rejection ="")');
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
            $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
        //error_log($iQueryStr);die;
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
            $row[] = $aRow['facilityCode'].' - '.ucwords($aRow['facilityName']);
            $row[] = (isset($aRow['sample_name']))?ucwords($aRow['sample_name']):'';
            $row[] = ucwords($aRow['labName']);
            $row[] = $displayReceivedDate;
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchSampleTestedResultPregnantPatientDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $j = 0;
            $lessTotal = 0;
            $greaterTotal = 0;
            $notTargetTotal = 0;
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                    
                                                    "greaterThan1000" => new Expression("SUM(CASE WHEN (vl.is_patient_pregnant in('yes','Yes','YES') and vl.result>=1000) THEN 1 ELSE 0 END)"),
                                                    "lesserThan1000" => new Expression("SUM(CASE WHEN (vl.is_patient_pregnant in('yes','Yes','YES') and (vl.result<1000 or vl.result='Target Not Detected')) THEN 1 ELSE 0 END)")
                                              )
                                            );
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")'); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')
                        AND DATE(sample_collection_date) >= '".$startMonth."'
                        AND DATE(sample_collection_date) <= '".$endMonth."' ");
                
            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));             
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //echo $queryStr;die;
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            $j=0;
            foreach($sampleResult as $sRow){
                if($sRow["monthDate"] == null) continue;
                $result['sampleName']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["greaterThan1000"]))?$sRow["greaterThan1000"]:0;
                $result['sampleName']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["lesserThan1000"]))?$sRow["lesserThan1000"]:0;
                
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
            
        }
      return $result;
    }
    
    public function fetchSampleTestedResultBreastfeedingPatientDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $j = 0;
            $lessTotal = 0;
            $greaterTotal = 0;
            $notTargetTotal = 0;
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                    
                                                    "greaterThan1000" => new Expression("SUM(CASE WHEN (vl.is_patient_breastfeeding in('yes','Yes','YES') and vl.result>=1000) THEN 1 ELSE 0 END)"),
                                                    "lesserThan1000" => new Expression("SUM(CASE WHEN (vl.is_patient_breastfeeding in('yes','Yes','YES') and (vl.result<1000 or vl.result='Target Not Detected')) THEN 1 ELSE 0 END)")
                                              )
                                            );
            if(isset($params['facilityId']) && is_array($params['facilityId']) && count($params['facilityId']) >0){
                $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $params['facilityId']) . '")'); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')
                        AND DATE(sample_collection_date) >= '".$startMonth."'
                        AND DATE(sample_collection_date) <= '".$endMonth."' ");
                
            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));               
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //echo $queryStr;die;
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            $j=0;
            foreach($sampleResult as $sRow){
                if($sRow["monthDate"] == null) continue;
                $result['sampleName']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["greaterThan1000"]))?$sRow["greaterThan1000"]:0;
                $result['sampleName']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["lesserThan1000"]))?$sRow["lesserThan1000"]:0;
                
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
            
        }
      return $result;
    }
    
    public function fetchAllSamples($parameters){
        $logincontainer = new Container('credo');
        $queryContainer = new Container('query');
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('sample_code','DATE_FORMAT(sample_collection_date,"%d-%b-%Y")','batch_code','patient_art_no','patient_first_name','patient_last_name','facility_name','f_p_l_d.location_name','f_d_l_d.location_name','sample_name','result','status_name');
        $orderColumns = array('vl_sample_id','sample_code','sample_collection_date','batch_code','patient_art_no','patient_first_name','facility_name','f_p_l_d.location_name','f_d_l_d.location_name','sample_name','result','status_name');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        $startDate = '';
        $endDate = '';
	if(isset($parameters['sampleCollectionDate']) && trim($parameters['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $parameters['sampleCollectionDate']);
            if(isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $startDate = trim($s_c_date[0]);
            }
            if(isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $endDate = trim($s_c_date[1]);
            }
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array('vl_sample_id','sample_code','facility_id','patient_first_name','patient_last_name','patient_art_no','sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),'result'))
                                ->join(array('rss'=>'r_sample_status'),'rss.status_id=vl.result_status',array('status_name'))
                                ->join(array('b'=>'batch_details'),'b.batch_id=vl.sample_batch_id',array('batch_code'),'left')
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'),'left')
                                ->join(array('f_p_l_d'=>'location_details'),'f_p_l_d.location_id=f.facility_state',array('province'=>'location_name'),'left')
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'),'left')
                                ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'),'left')
                                //->group('sample_code')
                                //->group('facility_id')
                                //->having('COUNT(*) > 1');
                                ->where('sample_code in (select sample_code from dash_vl_request_form group by sample_code,facility_id having count(*) > 1)');
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }
        $queryContainer->resultQuery = $sQuery;
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array('vl_sample_id','sample_code','facility_id','patient_first_name','patient_last_name','patient_art_no','sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),'result'))
                                ->join(array('rss'=>'r_sample_status'),'rss.status_id=vl.result_status',array('status_name'))
                                ->join(array('b'=>'batch_details'),'b.batch_id=vl.sample_batch_id',array('batch_code'),'left')
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'),'left')
                                ->join(array('f_p_l_d'=>'location_details'),'f_p_l_d.location_id=f.facility_state',array('province'=>'location_name'),'left')
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'),'left')
                                ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'),'left')
                                //->group('sample_code')
                                //->group('facility_id')
                                //->having('COUNT(*) > 1');
                                ->where('sample_code in (select sample_code from dash_vl_request_form group by sample_code,facility_id having count(*) > 1)');
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);
        
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        
	$common = new CommonService($this->sm);
        $buttText = $common->translate('Edit');
        foreach ($rResult as $aRow) {
            $sampleCollectionDate = '';
            if(isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate']!= NULL && trim($aRow['sampleCollectionDate'])!="" && $aRow['sampleCollectionDate']!= '0000-00-00'){
                $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
            }
            $row = array();
            $row[]='<input type="checkbox" name="duplicate-select[]" class="'.$aRow['sample_code'].'" id="'.$aRow['vl_sample_id'].'" value="'.$aRow['vl_sample_id'].'" onchange="duplicateCheck(this);"/>';
	    $row[]=$aRow['sample_code'];
            $row[]=$sampleCollectionDate;
            $row[]=(isset($aRow['batch_code']))?$aRow['batch_code']:'';
            $row[]=$aRow['patient_art_no'];
            $row[]=ucwords($aRow['patient_first_name'].' ' .$aRow['patient_last_name']);
            $row[]=(isset($aRow['facility_name']))?ucwords($aRow['facility_name']):'';
            $row[]=(isset($aRow['province']))?ucwords($aRow['province']):'';
            $row[]=(isset($aRow['district']))?ucwords($aRow['district']):'';
            $row[]=(isset($aRow['sample_name']))?ucwords($aRow['sample_name']):'';
            $row[]=$aRow['result'];
            $row[]=ucwords($aRow['status_name']);
            $row[]='<a href="/data-management/duplicate-data/edit/' . base64_encode($aRow['vl_sample_id']) . '" class="btn green" title="Edit">'.$buttText.'</a>';
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function removeDuplicateSampleRows($params){
        $response = 0;
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $removedSamplesDb = new \Application\Model\RemovedSamplesTable($this->adapter);
        if(isset($params['rows']) && trim($params['rows'])!= ''){
            $duplicateSamples = explode(',',$params['rows']);
            for($r=0;$r<count($duplicateSamples);$r++){
                $rQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                              ->where(array('vl.vl_sample_id'=>$duplicateSamples[$r]));
                $rQueryStr = $sql->getSqlStringForSqlObject($rQuery);
                $rResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if($rResult){
                    $data = array(
                                'vl_sample_id'=>$rResult->vl_sample_id,
                                'vlsm_instance_id'=>$rResult->vlsm_instance_id,
                                'vlsm_country_id'=>$rResult->vlsm_country_id,
                                'serial_no'=>$rResult->serial_no,
                                'facility_id'=>$rResult->facility_id,
                                'facility_sample_id'=>$rResult->facility_sample_id,
                                'sample_batch_id'=>$rResult->sample_batch_id,
                                'sample_code_key'=>$rResult->sample_code_key,
                                'sample_code_format'=>$rResult->sample_code_format,
                                'sample_code'=>$rResult->sample_code,
                                'sample_reordered'=>$rResult->sample_reordered,
                                'test_urgency'=>$rResult->test_urgency,
                                'patient_first_name'=>$rResult->patient_first_name,
                                'patient_last_name'=>$rResult->patient_last_name,
                                'patient_nationality'=>$rResult->patient_nationality,
                                'patient_art_no'=>$rResult->patient_art_no,
                                'patient_dob'=>$rResult->patient_dob,
                                'patient_gender'=>$rResult->facility_sample_id,
                                'patient_mobile_number'=>$rResult->patient_mobile_number,
                                'patient_location'=>$rResult->patient_location,
                                'patient_address'=>$rResult->patient_address,
                                'patient_art_date'=>$rResult->patient_art_date,
                                'sample_collection_date'=>$rResult->sample_collection_date,
                                'sample_type'=>$rResult->sample_type,
                                'is_patient_new'=>$rResult->is_patient_new,
                                'treatment_initiation'=>$rResult->treatment_initiation,
                                'line_of_treatment'=>$rResult->line_of_treatment,
                                'current_regimen'=>$rResult->current_regimen,
                                'date_of_initiation_of_current_regimen'=>$rResult->date_of_initiation_of_current_regimen,
                                'is_patient_pregnant'=>$rResult->is_patient_pregnant,
                                'is_patient_breastfeeding'=>$rResult->is_patient_breastfeeding,
                                'pregnancy_trimester'=>$rResult->pregnancy_trimester,
                                'arv_adherance_percentage'=>$rResult->arv_adherance_percentage,
                                'is_adherance_poor'=>$rResult->is_adherance_poor,
                                'consent_to_receive_sms'=>$rResult->consent_to_receive_sms,
                                'number_of_enhanced_sessions'=>$rResult->number_of_enhanced_sessions,
                                'last_vl_date_routine'=>$rResult->last_vl_date_routine,
                                'last_vl_result_routine'=>$rResult->last_vl_result_routine,
                                'last_vl_sample_type_routine'=>$rResult->last_vl_sample_type_routine,
                                'last_vl_date_failure_ac'=>$rResult->last_vl_date_failure_ac,
                                'last_vl_result_failure_ac'=>$rResult->last_vl_result_failure_ac,
                                'last_vl_sample_type_failure_ac'=>$rResult->last_vl_sample_type_failure_ac,
                                'last_vl_date_failure'=>$rResult->last_vl_date_failure,
                                'last_vl_result_failure'=>$rResult->last_vl_result_failure,
                                'last_vl_sample_type_failure'=>$rResult->last_vl_sample_type_failure,
                                'request_clinician_name'=>$rResult->request_clinician_name,
                                'request_clinician_phone_number'=>$rResult->request_clinician_phone_number,
                                'sample_testing_date'=>$rResult->sample_testing_date,
                                'vl_focal_person'=>$rResult->vl_focal_person,
                                'vl_focal_person_phone_number'=>$rResult->vl_focal_person_phone_number,
                                'sample_received_at_vl_lab_datetime'=>$rResult->sample_received_at_vl_lab_datetime,
                                'result_dispatched_datetime'=>$rResult->result_dispatched_datetime,
                                'is_sample_rejected'=>$rResult->is_sample_rejected,
                                'sample_rejection_facility'=>$rResult->sample_rejection_facility,
                                'reason_for_sample_rejection'=>$rResult->reason_for_sample_rejection,
                                'request_created_by'=>$rResult->request_created_by,
                                'request_created_datetime'=>$rResult->request_created_datetime,
                                'last_modified_by'=>$rResult->last_modified_by,
                                'last_modified_datetime'=>$rResult->last_modified_datetime,
                                'patient_other_id'=>$rResult->patient_other_id,
                                'patient_age_in_years'=>$rResult->patient_age_in_years,
                                'patient_age_in_months'=>$rResult->patient_age_in_months,
                                'treatment_initiated_date'=>$rResult->treatment_initiated_date,
                                'patient_anc_no'=>$rResult->patient_anc_no,
                                'treatment_details'=>$rResult->treatment_details,
                                'lab_name'=>$rResult->lab_name,
                                'lab_id'=>$rResult->lab_id,
                                'lab_code'=>$rResult->lab_code,
                                'lab_contact_person'=>$rResult->lab_contact_person,
                                'lab_phone_number'=>$rResult->lab_phone_number,
                                'sample_tested_datetime'=>$rResult->sample_tested_datetime,
                                'result_value_log'=>$rResult->result_value_log,
                                'result_value_absolute'=>$rResult->result_value_absolute,
                                'result_value_text'=>$rResult->result_value_text,
                                'result_value_absolute_decimal'=>$rResult->result_value_absolute_decimal,
                                'result'=>$rResult->result,
                                'approver_comments'=>$rResult->approver_comments,
                                'result_approved_by'=>$rResult->result_approved_by,
                                'result_approved_datetime'=>$rResult->result_approved_datetime,
                                'result_reviewed_by'=>$rResult->result_reviewed_by,
                                'result_reviewed_datetime'=>$rResult->result_reviewed_datetime,
                                'test_methods'=>$rResult->test_methods,
                                'contact_complete_status'=>$rResult->contact_complete_status,
                                'last_viral_load_date'=>$rResult->last_viral_load_date,
                                'last_viral_load_result'=>$rResult->last_viral_load_result,
                                'last_vl_result_in_log'=>$rResult->last_vl_result_in_log,
                                'reason_for_vl_testing'=>$rResult->reason_for_vl_testing,
                                'drug_substitution'=>$rResult->drug_substitution,
                                'sample_collected_by'=>$rResult->sample_collected_by,
                                'vl_test_platform'=>$rResult->vl_test_platform,
                                'facility_support_partner'=>$rResult->facility_support_partner,
                                'has_patient_changed_regimen'=>$rResult->has_patient_changed_regimen,
                                'reason_for_regimen_change'=>$rResult->reason_for_regimen_change,
                                'regimen_change_date'=>$rResult->regimen_change_date,
                                'plasma_conservation_temperature'=>$rResult->plasma_conservation_temperature,
                                'plasma_conservation_duration'=>$rResult->plasma_conservation_duration,
                                'physician_name'=>$rResult->physician_name,
                                'date_test_ordered_by_physician'=>$rResult->date_test_ordered_by_physician,
                                'vl_test_number'=>$rResult->vl_test_number,
                                'date_dispatched_from_clinic_to_lab'=>$rResult->date_dispatched_from_clinic_to_lab,
                                'result_printed_datetime'=>$rResult->result_printed_datetime,
                                'result_sms_sent_datetime'=>$rResult->result_sms_sent_datetime,
                                'is_request_mail_sent'=>$rResult->is_request_mail_sent,
                                'is_result_mail_sent'=>$rResult->is_result_mail_sent,
                                'is_result_sms_sent'=>$rResult->is_result_sms_sent,
                                'test_request_export'=>$rResult->test_request_export,
                                'test_request_import'=>$rResult->test_request_import,
                                'test_result_export'=>$rResult->test_result_export,
                                'test_result_import'=>$rResult->test_result_import,
                                'result_status'=>$rResult->result_status,
                                'import_machine_file_name'=>$rResult->import_machine_file_name,
                                'manual_result_entry'=>$rResult->manual_result_entry,
                                'source'=>$rResult->source,
                                'ward'=>$rResult->ward,
                                'art_cd_cells'=>$rResult->art_cd_cells,
                                'art_cd_date'=>$rResult->art_cd_date,
                                'who_clinical_stage'=>$rResult->who_clinical_stage,
                                'reason_testing_png'=>$rResult->reason_testing_png,
                                'tech_name_png'=>$rResult->tech_name_png,
                                'qc_tech_name'=>$rResult->qc_tech_name,
                                'qc_tech_sign'=>$rResult->qc_tech_sign,
                                'qc_date'=>$rResult->qc_date,
                                'whole_blood_ml'=>$rResult->whole_blood_ml,
                                'whole_blood_vial'=>$rResult->whole_blood_vial,
                                'plasma_ml'=>$rResult->plasma_ml,
                                'plasma_vial'=>$rResult->plasma_vial,
                                'plasma_process_time'=>$rResult->plasma_process_time,
                                'plasma_process_tech'=>$rResult->plasma_process_tech,
                                'batch_quality'=>$rResult->batch_quality,
                                'sample_test_quality'=>$rResult->sample_test_quality,
                                'failed_test_date'=>$rResult->failed_test_date,
                                'failed_test_tech'=>$rResult->failed_test_tech,
                                'failed_vl_result'=>$rResult->failed_vl_result,
                                'failed_batch_quality'=>$rResult->failed_batch_quality,
                                'failed_sample_test_quality'=>$rResult->failed_sample_test_quality,
                                'failed_batch_id'=>$rResult->failed_batch_id,
                                'clinic_date'=>$rResult->clinic_date,
                                'report_date'=>$rResult->report_date,
                                'sample_to_transport'=>$rResult->sample_to_transport
                            );
                    $hasInserted = $removedSamplesDb->insert($data);
                    if($hasInserted){
                        $response = $this->delete(array('vl_sample_id'=>$rResult->vl_sample_id));
                    }
                }
            }
            
        }
      return $response;
    }
    
    public function getSummaryTabDetails(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array(
                            "total_samples_received" => new Expression("COUNT(*)"),
                            "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                            "suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)"),
                            //"suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                            //"not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                            //"not_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                            "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                            //"rejected_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                            "all_current_regimen_samples" => new Expression("SUM(CASE WHEN (vl.line_of_treatment != 0 AND vl.line_of_treatment IS NOT NULL AND vl.line_of_treatment!= '') THEN 1 ELSE 0 END)"),
                            "1st_line_of_current_regimen_samples" => new Expression("SUM(CASE WHEN (vl.line_of_treatment = 1) THEN 1 ELSE 0 END)"),
                            ))
                            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')");
        $queryStr = $sql->getSqlStringForSqlObject($queryStr);
        $summaryResult = $common->cacheQuery($queryStr,$dbAdapter);
      return $summaryResult;
    }
    
    /* Samples Received District*/
    public function fetchAllSamplesReceivedByDistrict($parameters){
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.location_name');
        $orderColumns = array('f_d_l_d.location_name','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                'vl_sample_id',
                                                'facility_id',
                                                'sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),
                                                'result',
                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_dbs_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type = 2) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                                                "total_plasma_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type = 11) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                                                "total_others_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type IS NOT NULL AND sample_type!= '' AND sample_type!= 2 AND sample_type!= 11) THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('f.facility_district');
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                'vl_sample_id',
                                                'facility_id',
                                                'sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),
                                                'result',
                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_dbs_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type = 2) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                                                "total_plasma_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type = 11) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                                                "total_others_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type IS NOT NULL AND sample_type!= '' AND sample_type!= 2 AND sample_type!= 11) THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('f.facility_district');
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
            
            $row[]=$aRow['district'];         
            $row[]=$aRow['total_samples_received'];    
            $row[]=$aRow['total_samples_tested'];            
            $row[]=$aRow['total_samples_pending'];            
            $row[]=$aRow['total_samples_rejected'];            
            $row[]=$aRow['total_dbs_percentage'].'%';            
            $row[]=$aRow['total_plasma_percentage'].'%';
            $row[]=$aRow['total_others_percentage'].'%';
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchAllSamplesReceivedByFacility($parameters){
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('facility_name','f_d_l_d.location_name');
        $orderColumns = array('facility_name','f_d_l_d.location_name','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                'vl_sample_id',
                                                'facility_id',
                                                'sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),
                                                'result',
                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_dbs_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type = 2) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                                                "total_plasma_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type = 11) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                                                "total_others_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type IS NOT NULL AND sample_type!= '' AND sample_type!= 2 AND sample_type!= 11) THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('vl.facility_id');
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                'vl_sample_id',
                                                'facility_id',
                                                'sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),
                                                'result',
                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_dbs_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type = 2) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                                                "total_plasma_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type = 11) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                                                "total_others_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (sample_type IS NOT NULL AND sample_type!= '' AND sample_type!= 2 AND sample_type!= 11) THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('vl.facility_id');
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
            $row[]= "<span style='white-space:nowrap !important;' >".$aRow['facility_name']."</span>";
            $row[]=$aRow['district'];            
            $row[]=$aRow['total_samples_received'];          
            $row[]=$aRow['total_samples_tested'];            
            $row[]=$aRow['total_samples_pending'];            
            $row[]=$aRow['total_samples_rejected'];            
            $row[]=$aRow['total_dbs_percentage'].'%';            
            $row[]=$aRow['total_plasma_percentage'].'%';
            $row[]=$aRow['total_others_percentage'].'%';
            
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchSamplesReceivedBarChartDetails($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result=array();
        $common = new CommonService($this->sm);
        $start = strtotime(date("Y", strtotime("-1 year")).'-'.date('m', strtotime('+1 month', strtotime('-1 year'))));
        $end = strtotime(date('Y').'-'.date('m'));
        $j=0;
        while($start <= $end){
            $month = date('m', $start);$year = date('Y', $start);$monthYearFormat = date("M-Y", $start);
            $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(
                                          array(
                                                "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                "total_dbs" => new Expression("SUM(CASE WHEN (sample_type=2) THEN 1 ELSE 0 END)"),
                                                "total_plasma" => new Expression("SUM(CASE WHEN (sample_type=11) THEN 1 ELSE 0 END)"),
                                                "total_others" => new Expression("SUM(CASE WHEN (sample_type!= 2 AND sample_type!= 11 AND sample_type IS NOT NULL AND sample_type!= '') THEN 1 ELSE 0 END)")
                                                )
                                          )                                
                                    ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                                    ->where("Month(sample_collection_date)='".$month."' AND Year(sample_collection_date)='".$year."'");
            $queryStr = $sql->getSqlStringForSqlObject($sQuery);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            $result['sampleName']['dbs'][$j] = (isset($sampleResult[0]["total_dbs"]))?$sampleResult[0]["total_dbs"]:0;
            $result['sampleName']['plasma'][$j] = (isset($sampleResult[0]["total_plasma"]))?$sampleResult[0]["total_plasma"]:0;
            $result['sampleName']['others'][$j] = (isset($sampleResult[0]["total_others"]))?$sampleResult[0]["total_others"]:0;
            $result['date'][$j] = $monthYearFormat;
           $start = strtotime("+1 month", $start);
           $j++;
        }
       return $result;
    }
    
    public function fetchAllSuppressionRateByDistrict($parameters){
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.location_name');
        $orderColumns = array('f_d_l_d.location_name','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                'vl_sample_id',
                                                'facility_id',
                                                'sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),
                                                'result',
                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_suppressed_samples" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                //"total_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('f.facility_district');
                                
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                'vl_sample_id',
                                                'facility_id',
                                                'sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),
                                                'result',
                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_suppressed_samples" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                //"total_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('f.facility_district');
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
            
            $row[]=ucwords($aRow['district']);            
            $row[]=$aRow['total_samples_tested'];           
            $row[]=$aRow['total_suppressed_samples'];            
            $row[]=$aRow['total_samples_tested']-$aRow['total_suppressed_samples'];            
            $row[]=($aRow['total_samples_rejected'] > 0 && $aRow['total_samples_received'] > 0)?round((($aRow['total_samples_rejected']/$aRow['total_samples_received'])*100),2).'%':'';
            
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchAllSuppressionRateByFacility($parameters){
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('facility_name','f_d_l_d.location_name');
        $orderColumns = array('facility_name','f_d_l_d.location_name','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                'vl_sample_id',
                                                'facility_id',
                                                'sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),
                                                'result',
                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_suppressed_samples" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                //"total_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('vl.facility_id');
                                
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                'vl_sample_id',
                                                'facility_id',
                                                'sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),
                                                'result',
                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_suppressed_samples" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                //"total_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('vl.facility_id');
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
            
            $row[]= "<span style='white-space:nowrap !important;' >".$aRow['facility_name']."</span>";
            $row[]=ucwords($aRow['district']);     
            $row[]=$aRow['total_samples_tested'];        
            $row[]=$aRow['total_suppressed_samples'];            
            $row[]=$aRow['total_samples_tested']-$aRow['total_suppressed_samples'];
            $row[]=($aRow['total_samples_rejected'] > 0 && $aRow['total_samples_received'] > 0)?round((($aRow['total_samples_rejected']/$aRow['total_samples_received'])*100),2).'%':'';
           
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchSuppressionRateBarChartDetails($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result=array();
        $common = new CommonService($this->sm);
        $start = strtotime(date("Y", strtotime("-1 year")).'-'.date('m', strtotime('+1 month', strtotime('-1 year'))));
        $end = strtotime(date("Y-m", strtotime("-2 months")));
        $j=0;
        while($start <= $end){
            $month = date('m', $start);$year = date('Y', $start);$monthYearFormat = date("M-Y", $start);
            $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(
                                              array(
                                                    "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                    "total_suppressed_samples" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                    //"total_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                                                    )
                                              )
                                    ->where("Month(sample_collection_date)='".$month."' AND Year(sample_collection_date)='".$year."'");
            $queryStr = $sql->getSqlStringForSqlObject($sQuery);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            $result['valid_results'][$j] = (isset($sampleResult[0]["total_samples_tested"]))?$sampleResult[0]["total_samples_tested"]:0;
            $result['samples_rate'][$j] = ($sampleResult[0]["total_suppressed_samples"] > 0 && $sampleResult[0]["total_samples_tested"] > 0)?round((($sampleResult[0]["total_suppressed_samples"]/$sampleResult[0]["total_samples_tested"])*100),2):0;
            $result['date'][$j] = $monthYearFormat;
           $start = strtotime("+1 month", $start);
           $j++;
        }
       return $result;
    }

    public function fetchAllSamplesRejectedByDistrict($parameters){
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.location_name');
        $orderColumns = array('f_d_l_d.location_name','f_d_l_d.location_id','f_d_l_d.location_id','f_d_l_d.location_id');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                "total_samples_received" => new Expression('COUNT(*)'),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('f.facility_district');
                                
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                "total_samples_received" => new Expression('COUNT(*)'),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('f.facility_district');
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
            $row[] = ($aRow['total_samples_received'] > 0 && $aRow['total_samples_rejected'] > 0)?round(($aRow['total_samples_rejected']/$aRow['total_samples_received'])*100,2):0;
            $output['aaData'][] = $row;
        }
       return $output;
    }

    public function fecthAllSamplesRejectedByFacility($parameters){
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f.facility_name','f_d_l_d.location_name');
        $orderColumns = array('f.facility_name','f_d_l_d.location_name','f_d_l_d.location_id','f_d_l_d.location_id');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                "total_samples_received" => new Expression('COUNT(*)'),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('f.facility_id');
                                
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                "total_samples_received" => new Expression('COUNT(*)'),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->join(array('f_d_l_d'=>'location_details'),'f_d_l_d.location_id=f.facility_district',array('district'=>'location_name'))
                                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                                ->group('f.facility_id');
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
            
            $row[]= "<span style='white-space:nowrap !important;' >".$aRow['facility_name']."</span>";      
            $row[] = ucwords($aRow['district']); 
            $row[] = $aRow['total_samples_received'];    
            $row[] = ($aRow['total_samples_received'] > 0 && $aRow['total_samples_rejected'] > 0)?round(($aRow['total_samples_rejected']/$aRow['total_samples_received'])*100,2):0;
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchSamplesRejectedBarChartDetails($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $mostRejectionReasons = array();
        $mostRejectionQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                  ->columns(array('rejections' => new Expression('COUNT(*)')))                    
                                  ->join(array('r_r_r'=>'r_sample_rejection_reasons'),'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection',array('rejection_reason_id'))
                                  ->group('vl.reason_for_sample_rejection')
                                  ->order('rejections DESC')
                                  ->limit(4);
        $mostRejectionQueryStr = $sql->getSqlStringForSqlObject($mostRejectionQuery);
        $mostRejectionResult = $dbAdapter->query($mostRejectionQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(isset($mostRejectionResult) && count($mostRejectionResult) >0){
            foreach($mostRejectionResult as $rejectionReason){
                $mostRejectionReasons[] = $rejectionReason['rejection_reason_id'];
            }
          $mostRejectionReasons[] = 0;
        }
        $result=array();
        $common = new CommonService($this->sm);
        $start = strtotime(date("Y", strtotime("-1 year")).'-'.date('m', strtotime('+1 month', strtotime('-1 year'))));
        $end = strtotime(date('Y').'-'.date('m'));
        $j = 0;
        while($start <= $end){
            $month = date('m', $start);$year = date('Y', $start);$monthYearFormat = date("M-Y", $start);
            for($m=0;$m<count($mostRejectionReasons);$m++){
                $rejectionQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                      ->columns(array('rejections' => new Expression('COUNT(*)')))                    
                                      ->join(array('r_r_r'=>'r_sample_rejection_reasons'),'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection',array('rejection_reason_name'))
                                      ->where("Month(sample_collection_date)='".$month."' AND Year(sample_collection_date)='".$year."'");
                if($mostRejectionReasons[$m] == 0){
                    $rejectionQuery = $rejectionQuery->where('vl.reason_for_sample_rejection is not null and vl.reason_for_sample_rejection!= "" and vl.reason_for_sample_rejection NOT IN("' . implode('", "', $mostRejectionReasons) . '")');
                }else{
                   $rejectionQuery = $rejectionQuery->where('vl.reason_for_sample_rejection = "'.$mostRejectionReasons[$m].'"'); 
                }
                $rejectionQueryStr = $sql->getSqlStringForSqlObject($rejectionQuery);
                $rejectionResult = $common->cacheQuery($rejectionQueryStr ,$dbAdapter);
                $rejectionReasonName = ($mostRejectionReasons[$m] == 0)?'Others':$rejectionResult[0]['rejection_reason_name'];
                $result['rejection'][$rejectionReasonName][$j] = (isset($rejectionResult[0]['rejections']))?$rejectionResult[0]['rejections']:0;
                $result['date'][$j] = $monthYearFormat;
            }
          $start = strtotime("+1 month", $start);
          $j++;
        }
       return $result;
    }
    
    public function fetchRegimenGroupSamplesDetails($parameters){
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('current_regimen');
        $orderColumns = array('current_regimen','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id','vl_sample_id');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                "total_samples" => new Expression('COUNT(*)'),
                                                "total_samples_received" => new Expression("(SUM(CASE WHEN (vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_suppressed_samples" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                'current_regimen'
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->where(array('vl.line_of_treatment'=>1))
                                ->group('vl.current_regimen');
                                
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                "total_samples" => new Expression('COUNT(*)'),
                                                "total_samples_received" => new Expression("(SUM(CASE WHEN (vl.sample_collection_date is not null AND vl.sample_collection_date != '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_suppressed_samples" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                'current_regimen'
                                                )
                                          )
                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name'))
                                ->where(array('vl.line_of_treatment'=>1))
                                ->group('vl.current_regimen');
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
            
            $row[] = $aRow['current_regimen'];
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_suppressed_samples'];           
            $row[] = ($aRow['total_suppressed_samples'] > 0 && $aRow['total_samples_tested'] > 0)?round((($aRow['total_suppressed_samples']/$aRow['total_samples_tested'])*100),2).'%':'';
            $row[] = ($parameters['t_received'] > 0 && $aRow['total_samples_received'] >0)?round((($aRow['total_samples_received']/$parameters['t_received'])*100),2).'%':'';
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchRegimenGroupBarChartDetails($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                "current_regimen",
                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                "total_suppressed_samples" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                //"total_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND vl.result IS NOT NULL AND vl.result!= '') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                                                )
                                          )
                                ->where(array('vl.line_of_treatment'=>1))
                                ->group('vl.current_regimen');
            $queryStr = $sql->getSqlStringForSqlObject($sQuery);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            $j=0;
            $result=array();
            foreach($sampleResult as $sRow){
                $result['valid_results'][$j] = $aRow['total_samples_tested'];
                $result['samples_rate'][$j] = ($aRow['total_suppressed_samples'] > 0 && $aRow['total_samples_tested'] > 0)?round((($aRow['total_suppressed_samples']/$aRow['total_samples_tested'])*100),2):0;
                $result['current_regimen'][$j] = $sRow["current_regimen"];
                $j++;
            }
        return $result;  
    }
    
    public function fetchAllLineOfTreatmentDetails(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                "1stLineofSuppressed" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND vl.line_of_treatment = 1) THEN 1 ELSE 0 END))"),
                                                "1stLineofNotSuppressed" => new Expression("(SUM(CASE WHEN (vl.result >= 1000 AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND vl.line_of_treatment = 1) THEN 1 ELSE 0 END))"),
                                                "2ndLineofSuppressed" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND vl.line_of_treatment = 2) THEN 1 ELSE 0 END))"),
                                                "2ndLineofNotSuppressed" => new Expression("(SUM(CASE WHEN (vl.result >= 1000 AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND vl.line_of_treatment = 2) THEN 1 ELSE 0 END))"),
                                                "otherLineofSuppressed" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND vl.line_of_treatment NOT IN(1,2)) THEN 1 ELSE 0 END))"),
                                                "otherLineofNotSuppressed" => new Expression("(SUM(CASE WHEN (vl.result >= 1000 AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND vl.line_of_treatment NOT IN(1,2)) THEN 1 ELSE 0 END))")
                                                )
                                          );
        $queryStr = $sql->getSqlStringForSqlObject($sQuery);
        //echo $queryStr;die;
        //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $allLineofTreatmentResult = $common->cacheQuery($queryStr,$dbAdapter);
       return $allLineofTreatmentResult;
    }
    
    public function fetchAllCollapsibleLineOfTreatmentDetails(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(
                                          array(
                                                "current_regimen",
                                                "validResults"=>new Expression("(SUM(CASE WHEN (vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "totalSuppressed" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                "totalNotSuppressed" => new Expression("(SUM(CASE WHEN (vl.result >= 1000 AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))")
                                                )
                                          );
        $queryStr = $sql->getSqlStringForSqlObject($sQuery);
        //lineofTreatmentResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $adult1stLineofTreatmentResult = $common->cacheQuery($queryStr." Where line_of_treatment = 1 AND patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years >= 18 group by current_regimen order by validResults desc limit 9",$dbAdapter);
        $adult1stLineofTreatmentOthersResult = $common->cacheQuery($queryStr." Where line_of_treatment = 1 AND patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years >= 18 group by current_regimen order by validResults desc limit 10,18446744073709551615",$dbAdapter);
        
        $paeds1stLineofTreatmentResult = $common->cacheQuery($queryStr." Where line_of_treatment = 1 AND patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years < 18 group by current_regimen order by validResults desc limit 9",$dbAdapter);
        $paeds1stLineofTreatmentOthersResult = $common->cacheQuery($queryStr." Where line_of_treatment = 1 AND patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years < 18 group by current_regimen order by validResults desc limit 10,18446744073709551615",$dbAdapter);
        
        $adult2ndLineofTreatmentResult = $common->cacheQuery($queryStr." Where line_of_treatment = 2 AND patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years >= 18 group by current_regimen order by validResults desc limit 9",$dbAdapter);
        $adult2ndLineofTreatmentOthersResult = $common->cacheQuery($queryStr." Where line_of_treatment = 2 AND patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years >= 18 group by current_regimen order by validResults desc limit 10,18446744073709551615",$dbAdapter);
        
        $paeds2ndLineofTreatmentResult = $common->cacheQuery($queryStr." Where line_of_treatment = 2 AND patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years < 18 group by current_regimen order by validResults desc limit 8",$dbAdapter);
        $paeds2ndLineofTreatmentOthersResult = $common->cacheQuery($queryStr." Where line_of_treatment = 2 AND patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years < 18 group by current_regimen order by validResults desc limit 9,18446744073709551615",$dbAdapter);
        
        $otherLineofTreatmentResult = $common->cacheQuery($queryStr." Where line_of_treatment is not null AND line_of_treatment!= '' AND line_of_treatment NOT IN(1,2) group by current_regimen",$dbAdapter);
       return array('adult1stLineofTreatmentResult'=>$adult1stLineofTreatmentResult,'adult1stLineofTreatmentOthersResult'=>$adult1stLineofTreatmentOthersResult,'paeds1stLineofTreatmentResult'=>$paeds1stLineofTreatmentResult,'paeds1stLineofTreatmentOthersResult'=>$paeds1stLineofTreatmentOthersResult,'adult2ndLineofTreatmentResult'=>$adult2ndLineofTreatmentResult,'adult2ndLineofTreatmentOthersResult'=>$adult2ndLineofTreatmentOthersResult,'paeds2ndLineofTreatmentResult'=>$paeds2ndLineofTreatmentResult,'paeds2ndLineofTreatmentOthersResult'=>$paeds2ndLineofTreatmentOthersResult,'otherLineofTreatmentResult'=>$otherLineofTreatmentResult);
    }
    
    public function fetchKeySummaryIndicatorsDetails(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $summaryResult = array();
        $common = new CommonService($this->sm);
        $start = strtotime(date("Y", strtotime("-1 year")).'-'.date('m', strtotime('+1 month', strtotime('-1 year'))));
        $end = strtotime(date('Y').'-'.date('m'));
        $j = 0;
        while($start <= $end){
            $month = date('m', $start);$year = date('Y', $start);$monthYearFormat = date("M 'y", $start);
            $samplesReceivedSummaryQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                               ->columns(
                                                          array(
                                                                "total_samples_received" => new Expression("(COUNT(*))"),
                                                                "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                                                                "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime = '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                                                                "total_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)"),
                                                                "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                                                                //"suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                                                                //"rejected_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL AND sample_tested_datetime is not null AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                                                                )
                                                        )
                                               ->where("Month(sample_collection_date)='".$month."' AND Year(sample_collection_date)='".$year."'");
            $samplesReceivedSummaryCacheQuery = $sql->getSqlStringForSqlObject($samplesReceivedSummaryQuery);
            $samplesReceivedSummaryResult = $common->cacheQuery($samplesReceivedSummaryCacheQuery,$dbAdapter);
            $summaryResult['sample']['samples-received']['month'][$j] = (isset($samplesReceivedSummaryResult[0]["total_samples_received"]))?$samplesReceivedSummaryResult[0]["total_samples_received"]:0;
            $summaryResult['sample']['samples-tested']['month'][$j] = (isset($samplesReceivedSummaryResult[0]["total_samples_tested"]))?$samplesReceivedSummaryResult[0]["total_samples_tested"]:0;
            $summaryResult['sample']['valid-tested']['month'][$j] = (isset($samplesReceivedSummaryResult[0]["total_samples_tested"]))?$samplesReceivedSummaryResult[0]["total_samples_tested"]:0;;
            $summaryResult['sample']['suppression-rate']['month'][$j] = (isset($samplesReceivedSummaryResult[0]["total_suppressed_samples"]) && $samplesReceivedSummaryResult[0]["total_suppressed_samples"] >0 && $samplesReceivedSummaryResult[0]["total_samples_tested"] > 0)?round((($samplesReceivedSummaryResult[0]["total_suppressed_samples"]/$samplesReceivedSummaryResult[0]["total_samples_tested"])*100),2).' %':'';
            $summaryResult['sample']['rejection-rate']['month'][$j] = (isset($samplesReceivedSummaryResult[0]["total_samples_rejected"]) && $samplesReceivedSummaryResult[0]["total_samples_rejected"] >0 && $samplesReceivedSummaryResult[0]["total_samples_received"] > 0)?round((($samplesReceivedSummaryResult[0]["total_samples_rejected"]/$samplesReceivedSummaryResult[0]["total_samples_received"])*100),2).' %':'';
            $summaryResult['month'][$j] = $monthYearFormat;
          $start = strtotime("+1 month", $start);
          $j++;
        }
      return $summaryResult;
    }
    
    public function getVLTestReasonBasedOnAgeGroup($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rResult = array();
        $common = new CommonService($this->sm);
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $endDate = trim($s_c_date[1]);
            }
            $rQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array(
                                                "AgeLt2" => new Expression("SUM(CASE WHEN (patient_age_in_years < 2 AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)"),
                                                "AgeGte2Lte5" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 2 and patient_age_in_years <= 5) AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)"),
                                                "AgeGte6Lte14" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 6 and patient_age_in_years <= 14) AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)"),
                                                "AgeGte15Lte49" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 15 and patient_age_in_years <= 49) AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)"),
                                                "AgeGt50" => new Expression("SUM(CASE WHEN (patient_age_in_years > 50 AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)"),
                                                "AgeUnknown" => new Expression("SUM(CASE WHEN ((patient_age_in_years IS NULL OR patient_age_in_years = '' OR patient_age_in_years = 0) AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)")
                                          )
                                        )
                            ->join(array('tr'=>'r_vl_test_reasons'),'tr.test_reason_id=vl.reason_for_vl_testing', array('test_reason_name'))
                            ->where(array("DATE(vl.sample_collection_date) >='$startDate'", "DATE(vl.sample_collection_date) <='$endDate'"));
                            //->where('vl.facility_id !=0')
            if(isset($params['clinic']) && sizeof($params['clinic']) >0){
                $rQuery = $rQuery->where("vl.facility_id IN (".implode(',',$params['clinic']).")");
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $rQuery = $rQuery->where('vl.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            if(isset($params['testReason']) && sizeof($params['testReason']) > 0){
                $rQuery = $rQuery->where("vl.reason_for_vl_testing IN (".implode(',',$params['testReason']).")");
            }else{
                $rQuery = $rQuery->where("vl.reason_for_vl_testing IS NOT NULL AND vl.reason_for_vl_testing != '' AND vl.reason_for_vl_testing!= 0");
            }
            if(isset($params['sampleTypeId']) && $params['sampleTypeId']!=''){
                $rQuery = $rQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleTypeId'])).'"');
            }
            if(isset($params['adherence']) && trim($params['adherence'])!=''){
                $rQuery = $rQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
            }
            if(isset($params['testResult']) && trim($params['testResult']) == '<1000'){
              $rQuery = $rQuery->where("vl.result < 1000 or vl.result='Target Not Detected'");
            }else if(isset($params['testResult']) && trim($params['testResult']) == '>=1000') {
              $rQuery = $rQuery->where("vl.result >= 1000");
            }
            if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                $where = '';
                for($a=0;$a<count($params['age']);$a++){
                    if(trim($where)!= ''){ $where.= ' OR '; }
                    if($params['age'][$a] == '<2'){
                      $where.= "(vl.patient_age_in_years < 2)";
                    }else if($params['age'][$a] == '2to5') {
                      $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    }else if($params['age'][$a] == '6to14') {
                      $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    }else if($params['age'][$a] == '15to49') {
                      $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    }else if($params['age'][$a] == '>=50'){
                      $where.= "(vl.patient_age_in_years >= 50)";
                    }else if($params['age'][$a] == 'unknown'){
                      $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                    }
                }
              $where = '('.$where.')';
              $rQuery = $rQuery->where($where);
            }
            if(isset($params['gender']) && $params['gender']=='F'){
                $rQuery = $rQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            }else if(isset($params['gender']) && $params['gender']=='M'){
                $rQuery = $rQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
            }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                $rQuery = $rQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
            }
            if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                $rQuery = $rQuery->where("vl.is_patient_pregnant = 'yes'");
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                $rQuery = $rQuery->where("vl.is_patient_pregnant = 'no'"); 
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                $rQuery = $rQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
            }
            if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                $rQuery = $rQuery->where("vl.is_patient_breastfeeding = 'yes'");
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                $rQuery = $rQuery->where("vl.is_patient_breastfeeding = 'no'"); 
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                $rQuery = $rQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
            }
            $rQueryStr = $sql->getSqlStringForSqlObject($rQuery);
            //$qResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $qResult = $common->cacheQuery($rQueryStr,$dbAdapter);
            $rResult['total']['Age < 2'][0] = (int)$qResult[0]['AgeLt2'];
            $rResult['total']['Age 2-5'][0] = (int)$qResult[0]['AgeGte2Lte5'];
            $rResult['total']['Age 6-14'][0] = (int)$qResult[0]['AgeGte6Lte14'];
            $rResult['total']['Age 15-49'][0] = (int)$qResult[0]['AgeGte15Lte49'];
            $rResult['total']['Age > 50'][0] = (int)$qResult[0]['AgeGt50'];
            $rResult['total']['Age Unknown'][0] = (int)$qResult[0]['AgeUnknown'];
        }
        return $rResult;
    }
    
    public function getVLTestReasonBasedOnGender($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rResult = array();
        $common = new CommonService($this->sm);
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $endDate = trim($s_c_date[1]);
            }
            $rQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array(
                                                "mTotal" => new Expression("SUM(CASE WHEN (vl.patient_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),
                                                "fTotal" => new Expression("SUM(CASE WHEN (vl.patient_gender in('f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                                                "nsTotal" => new Expression("SUM(CASE WHEN (vl.patient_gender NOT in('m','Male','M','MALE','f','Female','F','FEMALE')) THEN 1 ELSE 0 END)")
                                              )
                                            )
                            ->join(array('tr'=>'r_vl_test_reasons'),'tr.test_reason_id=vl.reason_for_vl_testing', array('test_reason_name'))
                            ->where(array("DATE(vl.sample_collection_date) >='$startDate'", "DATE(vl.sample_collection_date) <='$endDate'"));
                            //->where('vl.facility_id !=0')
            if(isset($params['clinic']) && sizeof($params['clinic']) >0){
                $rQuery = $rQuery->where("vl.facility_id IN (".implode(',',$params['clinic']).")");
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $rQuery = $rQuery->where('vl.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            if(isset($params['testReason']) && sizeof($params['testReason']) > 0){
                $rQuery = $rQuery->where("vl.reason_for_vl_testing IN (".implode(',',$params['testReason']).")");
            }else{
                $rQuery = $rQuery->where("vl.reason_for_vl_testing IS NOT NULL AND vl.reason_for_vl_testing != '' AND vl.reason_for_vl_testing!= 0");
            }
            if(isset($params['sampleTypeId']) && $params['sampleTypeId']!=''){
                $rQuery = $rQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleTypeId'])).'"');
            }
            if(isset($params['adherence']) && trim($params['adherence'])!=''){
                $rQuery = $rQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
            }
            if(isset($params['testResult']) && trim($params['testResult']) == '<1000'){
              $rQuery = $rQuery->where("vl.result < 1000 or vl.result='Target Not Detected'");
            }else if(isset($params['testResult']) && trim($params['testResult']) == '>=1000') {
              $rQuery = $rQuery->where("vl.result >= 1000");
            }
            if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                $where = '';
                for($a=0;$a<count($params['age']);$a++){
                    if(trim($where)!= ''){ $where.= ' OR '; }
                    if($params['age'][$a] == '<2'){
                      $where.= "(vl.patient_age_in_years < 2)";
                    }else if($params['age'][$a] == '2to5') {
                      $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    }else if($params['age'][$a] == '6to14') {
                      $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    }else if($params['age'][$a] == '15to49') {
                      $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    }else if($params['age'][$a] == '>=50'){
                      $where.= "(vl.patient_age_in_years >= 50)";
                    }else if($params['age'][$a] == 'unknown'){
                      $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                    }
                }
              $where = '('.$where.')';
              $rQuery = $rQuery->where($where);
            }
            if(isset($params['gender']) && $params['gender']=='F'){
                $rQuery = $rQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            }else if(isset($params['gender']) && $params['gender']=='M'){
                $rQuery = $rQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
            }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                $rQuery = $rQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
            }
            if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                $rQuery = $rQuery->where("vl.is_patient_pregnant = 'yes'");
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                $rQuery = $rQuery->where("vl.is_patient_pregnant = 'no'"); 
            }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                $rQuery = $rQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
            }
            if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                $rQuery = $rQuery->where("vl.is_patient_breastfeeding = 'yes'");
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                $rQuery = $rQuery->where("vl.is_patient_breastfeeding = 'no'"); 
            }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                $rQuery = $rQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
            }
            $rQueryStr = $sql->getSqlStringForSqlObject($rQuery);
            //$qResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $qResult = $common->cacheQuery($rQueryStr,$dbAdapter);
            $rResult['total']['Male'][0] = (int)$qResult[0]['mTotal'];
            $rResult['total']['Female'][0] = (int)$qResult[0]['fTotal'];
            $rResult['total']['Other'][0] = (int)$qResult[0]['nsTotal'];
        }
        return $rResult;
    }
    
    public function getVLTestReasonBasedOnClinics($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = array();
        $facilityDb = new \Application\Model\FacilityTable($this->adapter);
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $endDate = trim($s_c_date[1]);
            }
            $clinicQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                               ->columns(array())
                               ->join(array('tr'=>'r_vl_test_reasons'),'tr.test_reason_id=vl.reason_for_vl_testing', array())
                               ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_id','facility_name'))
                               ->where('vl.facility_id !=0')
                               ->group('vl.facility_id');
            if(isset($params['clinic']) && sizeof($params['clinic']) >0){
                $clinicQuery = $clinicQuery->where("vl.facility_id IN (".implode(',',$params['clinic']).")");
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
                    $clinicQuery = $clinicQuery->where('vl.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
                }
            }
            $clinicQueryStr = $sql->getSqlStringForSqlObject($clinicQuery);
            $clinicResult  = $dbAdapter->query($clinicQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if(isset($clinicResult) && count($clinicResult) >0){
                $c = 0;
                foreach($clinicResult as $clinic){
                    $rQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                                ->columns(array("total" => new Expression('COUNT(*)')))
                                                ->join(array('tr'=>'r_vl_test_reasons'),'tr.test_reason_id=vl.reason_for_vl_testing', array())
                                                ->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array())
                                                ->where(array('vl.facility_id'=>$clinic['facility_id']))
                                                ->where(array("DATE(vl.sample_collection_date) >='$startDate'", "DATE(vl.sample_collection_date) <='$endDate'"));
                    if(isset($params['testReason']) && sizeof($params['testReason']) > 0){
                        $rQuery = $rQuery->where("vl.reason_for_vl_testing IN (".implode(',',$params['testReason']).")");
                    }else{
                        $rQuery = $rQuery->where("vl.reason_for_vl_testing IS NOT NULL AND vl.reason_for_vl_testing != '' AND vl.reason_for_vl_testing!= 0");
                    }
                    if(isset($params['sampleTypeId']) && $params['sampleTypeId']!=''){
                        $rQuery = $rQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleTypeId'])).'"');
                    }
                    if(isset($params['adherence']) && trim($params['adherence'])!=''){
                        $rQuery = $rQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                    }
                    if(isset($params['testResult']) && trim($params['testResult']) == '<1000'){
                      $rQuery = $rQuery->where("vl.result < 1000 or vl.result='Target Not Detected'");
                    }else if(isset($params['testResult']) && trim($params['testResult']) == '>=1000') {
                      $rQuery = $rQuery->where("vl.result >= 1000");
                    }
                    if(isset($params['age']) && is_array($params['age']) && count($params['age']) > 0){
                        $where = '';
                        for($a=0;$a<count($params['age']);$a++){
                            if(trim($where)!= ''){ $where.= ' OR '; }
                            if($params['age'][$a] == '<2'){
                              $where.= "(vl.patient_age_in_years < 2)";
                            }else if($params['age'][$a] == '2to5') {
                              $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                            }else if($params['age'][$a] == '6to14') {
                              $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                            }else if($params['age'][$a] == '15to49') {
                              $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                            }else if($params['age'][$a] == '>=50'){
                              $where.= "(vl.patient_age_in_years >= 50)";
                            }else if($params['age'][$a] == 'unknown'){
                              $where.= "(vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL)";
                            }
                        }
                      $where = '('.$where.')';
                      $rQuery = $rQuery->where($where);
                    }
                    if(isset($params['gender']) && $params['gender']=='F'){
                        $rQuery = $rQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                    }else if(isset($params['gender']) && $params['gender']=='M'){
                        $rQuery = $rQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                    }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                        $rQuery = $rQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
                    }
                    if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                        $rQuery = $rQuery->where("vl.is_patient_pregnant = 'yes'");
                    }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                        $rQuery = $rQuery->where("vl.is_patient_pregnant = 'no'"); 
                    }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                        $rQuery = $rQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
                    }
                    if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                        $rQuery = $rQuery->where("vl.is_patient_breastfeeding = 'yes'");
                    }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                        $rQuery = $rQuery->where("vl.is_patient_breastfeeding = 'no'"); 
                    }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                        $rQuery = $rQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                    }
                    $rQueryStr = $sql->getSqlStringForSqlObject($rQuery);
                    $rResult  = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result['clinic'][$c] = ucwords($clinic['facility_name']);
                    $result['sample']['total'][$c] = (isset($rResult->total))?$rResult->total:0;
                  $c++;
                }
            }
        }
      return $result;
    }
    
    public function getSample($id){
        return false;
    }
    ////////////////////////////////////////////
    /////////*** Turnaround Time Page ***///////
    ///////////////////////////////////////////

    public function getTATbyProvince($provinceID,$labs,$startDate,$endDate){
      $dbAdapter = $this->adapter;
      $sql = new Sql($dbAdapter);
      $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
      ->columns(
          array(
            "Collection_Receive"  => new Expression("AVG(DATEDIFF(IF(`sample_received_at_vl_lab_datetime`='',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='1970-01-01',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='0000-00-00',NULL,IFNULL(`sample_received_at_vl_lab_datetime`,NULL)))), IF(`sample_collection_date`='',NULL,IF(DATE(`sample_collection_date`)='1970-01-01',NULL,IF(DATE(`sample_collection_date`)='0000-00-00',NULL, IFNULL(`sample_collection_date`,NULL))))))"),
            "Receive_Register"    => new Expression("AVG(DATEDIFF(IF(`sample_registered_at_lab`='',NULL,IF(DATE(`sample_registered_at_lab`)='1970-01-01',NULL,IF(DATE(`sample_registered_at_lab`)='0000-00-00',NULL,IFNULL(`sample_registered_at_lab`,NULL)))), IF(`sample_received_at_vl_lab_datetime`='',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='1970-01-01',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='0000-00-00',NULL, IFNULL(`sample_received_at_vl_lab_datetime`,NULL))))))"),
            "Register_Analysis"   => new Expression("AVG(DATEDIFF(IF(`sample_tested_datetime`='',NULL,IF(DATE(`sample_tested_datetime`)='1970-01-01',NULL,IF(DATE(`sample_tested_datetime`)='0000-00-00',NULL,IFNULL(`sample_tested_datetime`,NULL)))), IF(`sample_registered_at_lab`='',NULL,IF(DATE(`sample_registered_at_lab`)='1970-01-01',NULL,IF(DATE(`sample_registered_at_lab`)='0000-00-00',NULL, IFNULL(`sample_registered_at_lab`,NULL))))))"),
            "Analysis_Authorise"  => new Expression("AVG(DATEDIFF(IF(`result_approved_datetime`='',NULL,IF(DATE(`result_approved_datetime`)='1970-01-01',NULL,IF(DATE(`result_approved_datetime`)='0000-00-00',NULL,IFNULL(`result_approved_datetime`,NULL)))), IF(`sample_tested_datetime`='',NULL,IF(DATE(`sample_tested_datetime`)='1970-01-01',NULL,IF(DATE(`sample_tested_datetime`)='0000-00-00',NULL, IFNULL(sample_tested_datetime,NULL))))))")
          )
        )
      ->join('facility_details', 'facility_details.facility_id = vl.facility_id')
      ->where(
          array(
            "sample_tested_datetime >= '$startDate' AND sample_tested_datetime <= '$endDate'",
            "facility_details.facility_id = vl.facility_id AND facility_details.facility_state = '$provinceID'"
          )
      );
      if(isset($labs) && !empty($labs)){
        $squery = $squery->where('vl.lab_id IN (' . implode(',',$labs) . ')');
      }
      $sQueryStr = $sql->getSqlStringForSqlObject($squery);
      $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
      return $sResult;
    }
    
    public function getTATbyDistrict($districtID,$labs,$startDate,$endDate){
      $dbAdapter = $this->adapter;
      $sql = new Sql($dbAdapter);
      $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
      ->columns(
          array(
            "Collection_Receive"  => new Expression("AVG(DATEDIFF(IF(`sample_received_at_vl_lab_datetime`='',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='1970-01-01',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='0000-00-00',NULL,IFNULL(`sample_received_at_vl_lab_datetime`,NULL)))), IF(`sample_collection_date`='',NULL,IF(DATE(`sample_collection_date`)='1970-01-01',NULL,IF(DATE(`sample_collection_date`)='0000-00-00',NULL, IFNULL(`sample_collection_date`,NULL))))))"),
            "Receive_Register"    => new Expression("AVG(DATEDIFF(IF(`sample_registered_at_lab`='',NULL,IF(DATE(`sample_registered_at_lab`)='1970-01-01',NULL,IF(DATE(`sample_registered_at_lab`)='0000-00-00',NULL,IFNULL(`sample_registered_at_lab`,NULL)))), IF(`sample_received_at_vl_lab_datetime`='',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='1970-01-01',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='0000-00-00',NULL, IFNULL(`sample_received_at_vl_lab_datetime`,NULL))))))"),
            "Register_Analysis"   => new Expression("AVG(DATEDIFF(IF(`sample_tested_datetime`='',NULL,IF(DATE(`sample_tested_datetime`)='1970-01-01',NULL,IF(DATE(`sample_tested_datetime`)='0000-00-00',NULL,IFNULL(`sample_tested_datetime`,NULL)))), IF(`sample_registered_at_lab`='',NULL,IF(DATE(`sample_registered_at_lab`)='1970-01-01',NULL,IF(DATE(`sample_registered_at_lab`)='0000-00-00',NULL, IFNULL(`sample_registered_at_lab`,NULL))))))"),
            "Analysis_Authorise"  => new Expression("AVG(DATEDIFF(IF(`result_approved_datetime`='',NULL,IF(DATE(`result_approved_datetime`)='1970-01-01',NULL,IF(DATE(`result_approved_datetime`)='0000-00-00',NULL,IFNULL(`result_approved_datetime`,NULL)))), IF(`sample_tested_datetime`='',NULL,IF(DATE(`sample_tested_datetime`)='1970-01-01',NULL,IF(DATE(`sample_tested_datetime`)='0000-00-00',NULL, IFNULL(sample_tested_datetime,NULL))))))")
          )
      )
      ->join('facility_details', 'facility_details.facility_id = vl.facility_id')
      ->where(
          array(
            "sample_tested_datetime >= '$startDate' AND sample_tested_datetime <= '$endDate'",
            "facility_details.facility_id = vl.facility_id AND facility_details.facility_district = '$districtID'"
          )
      );
      if(isset($labs) && !empty($labs)){
        $squery = $squery->where('vl.lab_id IN (' . implode(',',$labs) . ')');
      }
      $sQueryStr = $sql->getSqlStringForSqlObject($squery);
      $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
      return $sResult;
    }
    
    public function getTATbyClinic($clinicID,$labs,$startDate,$endDate){
      $dbAdapter = $this->adapter;
      $sql = new Sql($dbAdapter);
      $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
      ->columns(
          array(
            "Collection_Receive"  => new Expression("AVG(DATEDIFF(IF(`sample_received_at_vl_lab_datetime`='',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='1970-01-01',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='0000-00-00',NULL,IFNULL(`sample_received_at_vl_lab_datetime`,NULL)))), IF(`sample_collection_date`='',NULL,IF(DATE(`sample_collection_date`)='1970-01-01',NULL,IF(DATE(`sample_collection_date`)='0000-00-00',NULL, IFNULL(`sample_collection_date`,NULL))))))"),
            "Receive_Register"    => new Expression("AVG(DATEDIFF(IF(`sample_registered_at_lab`='',NULL,IF(DATE(`sample_registered_at_lab`)='1970-01-01',NULL,IF(DATE(`sample_registered_at_lab`)='0000-00-00',NULL,IFNULL(`sample_registered_at_lab`,NULL)))), IF(`sample_received_at_vl_lab_datetime`='',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='1970-01-01',NULL,IF(DATE(`sample_received_at_vl_lab_datetime`)='0000-00-00',NULL, IFNULL(`sample_received_at_vl_lab_datetime`,NULL))))))"),
            "Register_Analysis" => new Expression("AVG(DATEDIFF(IF(`sample_tested_datetime`='',NULL,IF(DATE(`sample_tested_datetime`)='1970-01-01',NULL,IF(DATE(`sample_tested_datetime`)='0000-00-00',NULL,IFNULL(`sample_tested_datetime`,NULL)))), IF(`sample_registered_at_lab`='',NULL,IF(DATE(`sample_registered_at_lab`)='1970-01-01',NULL,IF(DATE(`sample_registered_at_lab`)='0000-00-00',NULL, IFNULL(`sample_registered_at_lab`,NULL))))))"),
            "Analysis_Authorise"  => new Expression("AVG(DATEDIFF(IF(`result_approved_datetime`='',NULL,IF(DATE(`result_approved_datetime`)='1970-01-01',NULL,IF(DATE(`result_approved_datetime`)='0000-00-00',NULL,IFNULL(`result_approved_datetime`,NULL)))), IF(`sample_tested_datetime`='',NULL,IF(DATE(`sample_tested_datetime`)='1970-01-01',NULL,IF(DATE(`sample_tested_datetime`)='0000-00-00',NULL, IFNULL(sample_tested_datetime,NULL))))))")
          )
      )
      ->join('facility_details', 'facility_details.facility_id = vl.facility_id')
      ->where(
          array(
            "sample_tested_datetime >= '$startDate' AND sample_tested_datetime <= '$endDate'",
            "facility_details.facility_id = vl.facility_id AND vl.facility_id = '$clinicID'"
          )
      );
      if(isset($labs) && !empty($labs)){
        $squery = $squery->where('vl.lab_id IN (' . implode(',',$labs) . ')');
      }
      $sQueryStr = $sql -> getSqlStringForSqlObject($squery);
      $sResult   = $dbAdapter -> query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE) -> toArray();
      return $sResult;
    }
    
    /////////////////////////////////////////////
    /////////*** Turnaround Time Page ***////////
    ////////////////////////////////////////////
}