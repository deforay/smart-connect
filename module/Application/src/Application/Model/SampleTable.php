<?php

namespace Application\Model;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Debug\Debug;
use Zend\Db\Sql\Expression;
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
                                          array("Total Samples" => new Expression('COUNT(*)'),
                                          "Gender Missing" => new Expression("SUM(CASE 
                                                                                    WHEN patient_gender IS NULL OR patient_gender ='' THEN 0
                                                                                    ELSE 1
                                                                                    END)"),
                                          "Age Missing" => new Expression("SUM(CASE 
                                                                                WHEN patient_age_in_years IS NULL OR patient_age_in_years ='' THEN 0
                                                                                ELSE 1
                                                                                END)"),
                                          "Results Awaited (> $samplesWaitingFromLastXMonths months)" => new Expression("SUM(CASE
                                                                                    WHEN (result is NULL OR result ='') AND (sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='')) THEN 1
                                                                                    ELSE 0
                                                                                    END)")
                                          )
                                        );
        if(isset($params['facilityId']) && $params['facilityId'] !=''){
            $query = $query->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        $queryStr = $sql->getSqlStringForSqlObject($query);
        //$result = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $result = $common->cacheQuery($queryStr,$dbAdapter);
        return $result[0];
        
        
    }
    
    //start lab dashboard details 
    public function fetchSampleResultDetails($params){
        $logincontainer = new Container('credo');
        $quickStats = $this->fetchQuickStats($params);
        $dbAdapter = $this->adapter;$sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $timestamp = time();
        //$timestamp = 1485232311;
        $waitingTotal = 0;$receivedTotal = 0;$testedTotal = 0;$rejectedTotal = 0;
        $waitingResult = array();$receivedResult = array();$tResult = array();$rejectedResult = array();
        
        $qDates = array();
        for ($i = 0 ; $i < 7 ; $i++) {
            $qDates[] = "'".date('Y-m-d', $timestamp)."'";
            $timestamp -= 24 * 3600;
        }
        
        $qDates = implode(",",$qDates);
        
        //get received data
        $receivedQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                       ->columns(array('total' => new Expression('COUNT(*)'), 'receivedDate' => new Expression('DATE(sample_collection_date)')))
                                       //->where("vl.result!='' AND vl.result is NOT NULL")
                                       ->where("DATE(sample_collection_date) in ($qDates)")
                                       ->group(array("receivedDate"));
                                       
        if(isset($params['facilityId']) && $params['facilityId'] !=''){
            $receivedQuery = $receivedQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $receivedQuery = $receivedQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
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
                                       ->where("DATE(sample_collection_date) in ($qDates)")
                                       ->group(array("rejectDate"));
                                       
        if(isset($params['facilityId']) && $params['facilityId'] !=''){
            $rejectedQuery = $rejectedQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $rejectedQuery = $rejectedQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
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
                                       ->where("DATE(sample_tested_datetime) in ($qDates)")
                                       ->group(array("testedDate"));
                                       
        if(isset($params['facilityId']) && $params['facilityId'] !=''){
            $testedQuery = $testedQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $testedQuery = $testedQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
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
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            
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
                                                    "GreaterThan1000" => new Expression("SUM(CASE WHEN vl.result>=1000 THEN 1 ELSE 0 END)"),
                                                    "LesserThan1000" => new Expression("SUM(CASE WHEN vl.result<1000 or vl.result='Target Not Detected' THEN 1 ELSE 0 END)"),
                                                    //"TND" => new Expression("SUM(CASE WHEN vl.result='Target Not Detected' THEN 1 ELSE 0 END)"),
                                             
                                              )
                                            );
            if(isset($params['facilityId']) && $params['facilityId'] !=''){
                $queryStr = $queryStr->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."-00' 
                        AND DATE(sample_collection_date) <= '".$endMonth."-00' 
                        AND vl.sample_type IN ($sampleTypes)");
                
            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));   
            
            
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
           
           // \Zend\Debug\Debug::dump($sampleResult);
           //  die();
            
            
            $result = array();
            $j=0;
            foreach($sampleResult as $sRow){
                
                if($sRow["monthDate"] == null) continue;
                
                $result['sampleName']['VL (>= 1000 cp/ml)'][$j] = $sRow["GreaterThan1000"];
                //$result['sampleName']['VL Not Detected'][$j] = $sRow["TND"];
                $result['sampleName']['VL (< 1000 cp/ml)'][$j] = $sRow["LesserThan1000"];
                
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            } 
             
             
            return $result;
        }
    }
    //get sample tested result details
    public function fetchSampleTestedResultGenderDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            $j = 0;
            $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
            
            
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "total" => new Expression('COUNT(*)'),
                                                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                    
                                                    "MGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and vl.patient_gender in('m','M')) THEN 1 ELSE 0 END)"),
                                                    "MLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and vl.patient_gender in('m','M')) THEN 1 ELSE 0 END)"),
                                                    //"MTND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' and vl.patient_gender in('m','M')) THEN 1 ELSE 0 END)"),
                                             
                                                    "FGreaterThan1000" => new Expression("SUM(CASE WHEN vl.result>=1000 and vl.patient_gender in('f','F') THEN 1 ELSE 0 END)"),
                                                    "FLesserThan1000" => new Expression("SUM(CASE WHEN (vl.result<1000 or vl.result='Target Not Detected') and vl.patient_gender in('f','F') THEN 1 ELSE 0 END)"),
                                                    //"FTND" => new Expression("SUM(CASE WHEN vl.result='Target Not Detected' and vl.patient_gender in('f','F') THEN 1 ELSE 0 END)"),

                                                    "OGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and vl.patient_gender NOT in('m','M','f','F')) THEN 1 ELSE 0 END)"),
                                                    "OLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and vl.patient_gender NOT in('m','M','f','F')) THEN 1 ELSE 0 END)"),
                                                    //"OTND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' and vl.patient_gender NOT in('m','M','f','F')) THEN 1 ELSE 0 END)"),
                                             
                                              )
                                            );
            if(isset($params['facilityId']) && $params['facilityId'] !=''){
                $queryStr = $queryStr->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."-00' 
                        AND DATE(sample_collection_date) <= '".$endMonth."-00' ");
                
            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));               
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //echo $queryStr;die;
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            
            
            $result = array();
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
            
            return $result;
            
        }
    }
    
    public function fetchSampleTestedResultAgeDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
        
            $j = 0;
            $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
            
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "total" => new Expression('COUNT(*)'),
                                                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                    
                                                    "A18GreaterThan1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and patient_age_in_years > 18) THEN 1 ELSE 0 END)"),
                                                    "A18LesserThan1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and patient_age_in_years > 18) THEN 1 ELSE 0 END)"),
                                                    //"A18TND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' and patient_age_in_years > 18) THEN 1 ELSE 0 END)"),
                                             
                                                    "B18GreaterThan1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and patient_age_in_years < 18) THEN 1 ELSE 0 END)"),
                                                    "B18LesserThan1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and patient_age_in_years < 18) THEN 1 ELSE 0 END)"),
                                                    //"B18TND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' and patient_age_in_years < 18) THEN 1 ELSE 0 END)"),

                                                    "UnknownGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.result>=1000 and (patient_age_in_years is null || patient_age_in_years = '')) THEN 1 ELSE 0 END)"),
                                                    "UnknownLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.result<1000 or vl.result='Target Not Detected') and (patient_age_in_years is null || patient_age_in_years = '')) THEN 1 ELSE 0 END)"),
                                                    //"UnknownTND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' and (patient_age_in_years is null || patient_age_in_years = '')) THEN 1 ELSE 0 END)"),
                                             
                                              )
                                            );
            if(isset($params['facilityId']) && $params['facilityId'] !=''){
                $queryStr = $queryStr->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."-00' 
                        AND DATE(sample_collection_date) <= '".$endMonth."-00' ");
                
            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));               
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //echo $queryStr;die;
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            
            $result = array();            
            $j=0;
            foreach($sampleResult as $sRow){
                
                if($sRow["monthDate"] == null) continue;
                
                $result['>18']['VL (>= 1000 cp/ml)'][$j] = $sRow["A18GreaterThan1000"];
                $result['<18']['VL (>= 1000 cp/ml)'][$j] = $sRow["B18GreaterThan1000"];
                
               // $result['>18']['VL Not Detected'][$j] = $sRow["A18TND"];
              //  $result['<18']['VL Not Detected'][$j] = $sRow["B18TND"];
                
                $result['>18']['VL (< 1000 cp/ml)'][$j] = $sRow["A18LesserThan1000"];
                $result['<18']['VL (< 1000 cp/ml)'][$j] = $sRow["B18LesserThan1000"];
                
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
                
            }           
            
            return $result;
        }
    }
    
    public function fetchSampleTestedResultBasedVolumeDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-00";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-00";
            
            $fQuery = $sql->select()->from(array('f'=>'facility_details'))->columns(array('facility_id','facility_name'))
                        ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type'))
                        //->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                        ->where(array("DATE(vl.sample_collection_date) <='$endMonth'", "DATE(vl.sample_collection_date) >='$startMonth'"))
                        ->where('vl.lab_id !=0')
                        ->group('vl.lab_id');
            if(isset($params['facilityId']) && trim($params['facilityId'])!=''){
                $fQuery = $fQuery->where('vl.lab_id="'.base64_decode(trim($params['facilityId'])).'"');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                    $fQuery = $fQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
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
            
            if($facilityResult && $sampleTypeResult){
                $sampleId = array();
                foreach($sampleTypeResult as $samples){
                    $sampleId[] = $samples['sample_id'];
                }
                $j = 0;
                $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
                foreach($facilityResult as $facility){
                    $lessThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                            ->where(array("vl.sample_collection_date <='" . $endMonth ." 23:59:00". "'", "vl.sample_collection_date >='" . $startMonth." 00:00:00". "'"))
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
                //\Zend\Debug\Debug::dump($result);die;
            }
        }
        return $result;
    }
    
    public function getRequisitionFormsTested($params) {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            $i = 0;
            $completeResultCount = 0;
            $inCompleteResultCount = 0;
            
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "total" => new Expression('COUNT(*)'),
                                                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                    
                                                    "CompletedForms" => new Expression("SUM(CASE WHEN (vl.patient_art_no !='' AND vl.current_regimen !='' AND vl.patient_age_in_years !='' AND vl.patient_gender !='') THEN 1 ELSE 0 END)"),
                                                    "IncompleteForms" => new Expression("SUM(CASE WHEN (vl.patient_art_no='' OR vl.current_regimen='' OR vl.patient_age_in_years =''  OR vl.patient_gender='') THEN 1 ELSE 0 END)"),
                                             
                                              )
                                            );
            if(isset($params['facilityId']) && $params['facilityId'] !=''){
                $queryStr = $queryStr->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."-00' 
                        AND DATE(sample_collection_date) <= '".$endMonth."-00' ");
                
            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));               
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //echo $queryStr;die;
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);
            
            $result = array();
            $j=0;
            foreach($sampleResult as $sRow){
                
                if($sRow["monthDate"] == null) continue;
                
                $result['Complete'][$j] = (int)$sRow["CompletedForms"];
                $result['Incomplete'][$j] = (int)$sRow["IncompleteForms"];
                $result['date'][$j] = $sRow["monthDate"];
                $j++;                
            }
            //\Zend\Debug\Debug::dump($result);die;
            return $result;
        }
    }
    
    public function fetchIncompleteSampleDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        $i =0;$j =1;$k =2;$l =3;
        $result[$i]['field'] = 'Patient ART No';
        $result[$j]['field'] = 'Current Regimen';
        $result[$k]['field'] = 'Patient Age in Years';
        $result[$l]['field'] = 'Patient Gender';
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        }
       
        $inCompleteQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')));
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            if(trim($params['fromDate'])!= trim($params['toDate'])){
               $inCompleteQuery = $inCompleteQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
            }else{
                $fromMonth = date("Y-m", strtotime(trim($params['fromDate'])));
                $month = strtotime($fromMonth);
                $mnth = date('m', $month);$year = date('Y', $month);
                $inCompleteQuery = $inCompleteQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
            }
        }
        if(isset($params['lab']) && trim($params['lab'])!=''){
            $inCompleteQuery = $inCompleteQuery->where('vl.lab_id="'.base64_decode(trim($params['lab'])).'"');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $inCompleteQuery = $inCompleteQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        $incQueryStr = $sql->getSqlStringForSqlObject($inCompleteQuery);
        $artInCompleteResult = $dbAdapter->query($incQueryStr." AND vl.patient_art_no =''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $currentRegimenInCompleteResult = $dbAdapter->query($incQueryStr." AND vl.current_regimen =''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $ageInYearsInCompleteResult = $dbAdapter->query($incQueryStr." AND vl.patient_age_in_years =''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $patientGenderInCompleteResult = $dbAdapter->query($incQueryStr." AND vl.patient_gender =''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $result[$i]['total'] = $artInCompleteResult->total;
        $result[$j]['total'] = $currentRegimenInCompleteResult->total;
        $result[$k]['total'] = $ageInYearsInCompleteResult->total;
        $result[$l]['total'] = $patientGenderInCompleteResult->total;
       return $result;
    }
    
    public function fetchIncompleteBarSampleDetails($params){
        $logincontainer = new Container('credo');
        $result = '';
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        }
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                      ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                      ->where('vl.lab_id !=0')
                      ->group('f.facility_id');
        if(isset($params['lab']) && trim($params['lab'])!=''){
            $fQuery = $fQuery->where('vl.lab_id="'.base64_decode(trim($params['lab'])).'"');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $fQuery = $fQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $result = array();
        if($facilityResult){
                $j = 0;
                foreach($facilityResult as $facility){
                    $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                      ->where('vl.lab_id="'.$facility['facility_id'].'"');
                    if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                        if(trim($params['fromDate'])!= trim($params['toDate'])){
                           $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
                        }else{
                            $fromMonth = date("Y-m", strtotime(trim($params['fromDate'])));
                            $month = strtotime($fromMonth);
                            $mnth = date('m', $month);$year = date('Y', $month);
                            $countQuery = $countQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                        }
                    }
                    $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                    $completeResult = $dbAdapter->query($cQueryStr." AND vl.patient_art_no !='' AND vl.current_regimen !='' AND vl.patient_age_in_years !=''  AND vl.patient_gender != ''", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result['form']['Complete'][$j] = $completeResult->total;
                    $inCompleteResult = $dbAdapter->query($cQueryStr." AND (vl.patient_art_no='' OR vl.current_regimen='' OR vl.patient_age_in_years =''  OR vl.patient_gender='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result['form']['Incomplete'][$j] = $inCompleteResult->total;
                    $result['lab'][$j] = $facility['facility_name'];
                    $j++;
                }
        }
        return $result;
    }
    
    public function getSampleVolume($params){
        $logincontainer = new Container('credo');
        $result = '';
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        
            $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                          ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type'))
                          ->where(array("vl.sample_collection_date <='" . $endMonth ." 23:59:00". "'", "vl.sample_collection_date >='" . $startMonth." 00:00:00". "'"))
                          ->where('vl.lab_id !=0')
                          ->group('vl.lab_id');
            if(isset($params['facilityId']) && trim($params['facilityId'])!=''){
                $fQuery = $fQuery->where('vl.lab_id="'.base64_decode(trim($params['facilityId'])).'"');
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                    $fQuery = $fQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if($facilityResult){
                $i = 0;
                $result = array();
                foreach($facilityResult as $facility){
                    $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                        ->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"))
                                        ->where('vl.lab_id="'.$facility['facility_id'].'"');
                    if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested'){
                        $countQuery = $countQuery->where("vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL'");
                    }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                        $countQuery = $countQuery->where("(vl.result IS NULL OR vl.result = 'NULL' OR vl.result = '')");
                    }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                        $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
                    }
                    $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                    $countResult[$i] = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result[$i][0] = $countResult[$i]['total'];
                    $result[$i][1] = $facility['facility_name'];
                    $i++;
                }
            }
        }
       return $result;
    }
        //get female result
    public function getFemalePatientResult($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    //"total" => new Expression("SUM(CASE WHEN (patient_gender != '' AND patient_gender IS NOT NULL AND (patient_gender ='f' || patient_gender ='female' || patient_gender='F' || patient_gender='FEMALE')) THEN 1 ELSE 0 END)"),
                                                    "Breast_Feeding" => new Expression("SUM(CASE WHEN (is_patient_breastfeeding ='yes' || is_patient_breastfeeding ='Yes' ||  is_patient_breastfeeding ='YES') THEN 1 ELSE 0 END)"),
                                                    "Not_Breast_Feeding" => new Expression("SUM(CASE WHEN (is_patient_breastfeeding ='no' || is_patient_breastfeeding ='No' ||  is_patient_breastfeeding ='NO') THEN 1 ELSE 0 END)"),
                                                    "Breast_Feeding_Unknown" => new Expression("SUM(CASE WHEN (is_patient_breastfeeding !='' AND is_patient_breastfeeding IS NOT NULL AND (is_patient_breastfeeding !='no' AND is_patient_breastfeeding !='No' AND  is_patient_breastfeeding !='NO') AND (is_patient_breastfeeding !='yes' AND is_patient_breastfeeding !='Yes' AND  is_patient_breastfeeding !='YES')) THEN 1 ELSE 0 END)"),
                                                    "Pregnant" => new Expression("SUM(CASE WHEN (is_patient_pregnant ='yes' || is_patient_pregnant ='Yes' ||  is_patient_pregnant ='YES') THEN 1 ELSE 0 END)"),
                                                    "Not_Pregnant" => new Expression("SUM(CASE WHEN (is_patient_pregnant ='no' || is_patient_pregnant ='No' ||  is_patient_pregnant ='NO') THEN 1 ELSE 0 END)"),
                                                    "Pregnant_Unknown" => new Expression("SUM(CASE WHEN (is_patient_pregnant !='' AND is_patient_pregnant IS NOT NULL AND (is_patient_pregnant !='no' AND is_patient_pregnant !='No' AND  is_patient_pregnant !='NO') AND (is_patient_pregnant !='yes' AND is_patient_pregnant !='Yes' AND  is_patient_pregnant !='YES')) THEN 1 ELSE 0 END)"),
                                              )
                                            );
            if(isset($params['facilityId']) && $params['facilityId'] !=''){
                $queryStr = $queryStr->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."-00' 
                        AND DATE(sample_collection_date) <= '".$endMonth."-00' AND (patient_gender='f' || patient_gender='F' || patient_gender='Female' || patient_gender='FEMALE')");
                
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            $femaleTestResult = $common->cacheQuery($queryStr,$dbAdapter);
            return $femaleTestResult;
        }
    }
    //get Line Of tratment result
    public function getLineOfTreatment($params){
        
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "Line_Of_Treatment_1" => new Expression("SUM(CASE WHEN (line_of_treatment = 1) THEN 1 ELSE 0 END)"),
                                                    "Line_Of_Treatment_2" => new Expression("SUM(CASE WHEN (line_of_treatment = 2) THEN 1 ELSE 0 END)"),
                                                    "Line_Of_Treatment_3" => new Expression("SUM(CASE WHEN (line_of_treatment = 3) THEN 1 ELSE 0 END)"),
                                                    "Not_Specified" => new Expression("SUM(CASE WHEN ((line_of_treatment!=1 AND line_of_treatment!=2 AND line_of_treatment!=3) OR (line_of_treatment IS NULL)) THEN 1 ELSE 0 END)"),
                                              )
                                            );
            if(isset($params['facilityId']) && $params['facilityId'] !=''){
                $queryStr = $queryStr->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."-00' 
                        AND DATE(sample_collection_date) <= '".$endMonth."-00'");
                         
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            $lineOfTreatmentResult = $common->cacheQuery($queryStr,$dbAdapter);
            return $lineOfTreatmentResult;
        }
    }
    //get vl out comes result
    public function getVlOutComes($params){
        
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "Suppressed" => new Expression("SUM(CASE WHEN (vl.result < 1000) THEN 1 ELSE 0 END)"),
                                                    "Not_Suppressed" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                                              )
                                            );
            if(isset($params['facilityId']) && $params['facilityId'] !=''){
                $queryStr = $queryStr->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."-00' 
                        AND DATE(sample_collection_date) <= '".$endMonth."-00'");
                         
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            $vlOutComeResult = $common->cacheQuery($queryStr,$dbAdapter);
            return $vlOutComeResult;
        }
    }
    public function fetchLabTurnAroundTime($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $monthyear = date("Y-m");
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            if(strtotime($startMonth) >= strtotime($monthyear)){
                $startMonth = $endMonth = date("Y-m", strtotime("-1 months"));
            }else if(strtotime($endMonth) >= strtotime($monthyear)){
               $endMonth = date("Y-m", strtotime("-1 months")); 
            }
            //echo $startMonth.'/'.$endMonth;die;
            $j = 0;
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            
            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    "total" => new Expression('COUNT(*)'),
                                                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                                                    
                                                    "AvgDiff" => new Expression("AVG(DATEDIFF(sample_tested_datetime,sample_collection_date))"),
                                             
                                              )
                                            );
            if(isset($params['facilityId']) && $params['facilityId'] !=''){
                $queryStr = $queryStr->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
            }else{
                if($logincontainer->role!= 1){
                    $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                    $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                }
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date != '')
                        AND DATE(sample_collection_date) >= '".$startMonth."-00' 
                        AND DATE(sample_collection_date) <= '".$endMonth."-00' ");
                
            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));   
            $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));               
            $queryStr = $sql->getSqlStringForSqlObject($queryStr);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //echo $queryStr;die;
            $sampleResult = $common->cacheQuery($queryStr,$dbAdapter);            
    
            $result = array();
            $j=0;
            foreach($sampleResult as $sRow){
                if($sRow["monthDate"] == null) continue;
                
                $result['all'][$j] = (isset($sRow["AvgDiff"]) && $sRow["AvgDiff"] > 0 && $sRow["AvgDiff"] != NULL) ? round($sRow["AvgDiff"],2) : "null";
                
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
            
            return $result;
        }
    }
    
    public function fetchFacilites($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            $lQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('sample_tested_datetime','sample_collection_date','lab_id','labCount' => new \Zend\Db\Sql\Expression("COUNT(vl.lab_id)")))
                                                ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.lab_id',array('facility_name','latitude','longitude'))
                                                ->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"))
                                                ->group('vl.lab_id');
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $lQuery = $lQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            $lQueryStr = $sql->getSqlStringForSqlObject($lQuery);
            $lResult = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            
            if(count($lResult)>0){
                $i = 0;
                foreach($lResult as $lab){
                    if($lab['lab_id']!='' && $lab['lab_id']!=NULL && $lab['lab_id']!=0){
                        $lcQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                                ->columns(array('sample_tested_datetime','sample_collection_date','lab_id','facility_id','vl_sample_id','clinicCount' => new \Zend\Db\Sql\Expression("COUNT(vl.facility_id)")))
                                                ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name','latitude','longitude'))
                                                ->where(array("vl.lab_id"=>$lab['lab_id'],'fd.facility_type'=>'1'))
                                                ->group('vl.facility_id');
                        $lcQueryStr = $sql->getSqlStringForSqlObject($lcQuery);
                        $lResult[$i]['clinic'] = $dbAdapter->query($lcQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $i++;
                    }
                }
            }
        }
        //\Zend\Debug\Debug::dump($lResult);die;
        return $lResult;
    }
    
    //end lab dashboard details 
    
    //start clinic details
    public function fetchOverAllLoadStatus($parameters){
        $logincontainer = new Container('credo');
        $common = new CommonService($this->sm);
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('sample_code','DATE_FORMAT(sample_tested_datetime,"%d-%b-%Y")','result');
        $orderColumns = array('sample_code','sample_tested_datetime','result');

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
        $cDate = date('Y-m-d');
        $lastThirtyDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($parameters['sampleCollectionDate']) && trim($parameters['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $parameters['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('sample_code','sample_tested_datetime','result','sample_type'))
                        ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
                        ->join(array('rst'=>'r_sample_type'),'rst.sample_id=vl.sample_type')
                        ->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"))
                        ->where(array('fd.facility_type'=>'1'));
        if(isset($parameters['clinicId']) && $parameters['clinicId']!=''){
            $sQuery = $sQuery->where('vl.facility_id="'.base64_decode(trim($parameters['clinicId'])).'"');
        }else{
           if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $sQuery = $sQuery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            } 
        }
        if(isset($parameters['sampleId']) && $parameters['sampleId']!=''){
            $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleId'])).'"');
        }
        if(isset($parameters['testResult']) && $parameters['testResult']!=''){
            $sQuery = $sQuery->where('vl.result'.$parameters['testResult']);
        }
        
        if(isset($parameters['gender'] ) && trim($parameters['gender'])!=''){
            $sQuery = $sQuery->where(array("vl.patient_gender ='".$parameters['gender']."'")); 
        }
        if(isset($parameters['age']) && $parameters['age']!=''){
            $age = explode("-",$parameters['age']);
            if(isset($age[1])){
              $sQuery = $sQuery->where(array("vl.patient_age_in_years >='".$age[0]."'","vl.patient_age_in_years <='".$age[1]."'"));
            }else{
              $sQuery = $sQuery->where('vl.patient_age_in_years'.$parameters['age']);
            }
        }
        if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'")); 
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('sample_code','sample_tested_datetime','result','sample_type'))
                      ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
                      ->join(array('rst'=>'r_sample_type'),'rst.sample_id=vl.sample_type')
                      ->where(array('fd.facility_type'=>'1'));
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
            $iQuery = $iQuery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
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
            $testedDate = '';
	    if($aRow['sample_tested_datetime']!= null && trim($aRow['sample_tested_datetime'])!="" && $aRow['sample_tested_datetime']!= '0000-00-00 00:00:00'){
                $sampleTestedDateTime = explode(" ",$aRow['sample_tested_datetime']);
                $testedDate = $common->humanDateFormat($sampleTestedDateTime[0]).' '.$sampleTestedDateTime[1];
            }
            $row[] = $aRow['sample_code'];
            $row[] = $testedDate;
            $row[] = $aRow['result'];
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchChartOverAllLoadStatus($params){
        $testedTotal = 0;$lessTotal = 0;$gTotal = 0;$overAllTotal = 0;
        //total tested
        $where = '';
        $overAllTotal = $this->fetchChartOverAllLoadResult($params,$where);
        
        $where = 'vl.result!=""';
        $testedTotal = $this->fetchChartOverAllLoadResult($params,$where);
        
        //total <1000
    
        $where = 'vl.result<1000';
        $lessTotal = $this->fetchChartOverAllLoadResult($params,$where);
        //total >1000
        $where = 'vl.result>=1000';
        $gTotal = $this->fetchChartOverAllLoadResult($params,$where);
        
        return array($testedTotal,$lessTotal,$gTotal,$overAllTotal);
    }
    
    public function fetchSampleTestedReason($params){
        $logincontainer = new Container('credo');
        $rResult = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $cDate = date('Y-m-d');
        $lastThirtyDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        
        $rQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                        ->columns(array('total' => new Expression('COUNT(*)'), 'monthDate' => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%M-%Y')")))
                        ->join(array('tr'=>'r_vl_test_reasons'),'tr.test_reason_id=vl.reason_for_vl_testing', array('test_reason_name'))
                        ->where(array("DATE(vl.sample_collection_date) >='$lastThirtyDay'", "DATE(vl.sample_collection_date) <='$cDate'"))
                        //->where('vl.facility_id !=0')
                        //->where('vl.reason_for_vl_testing="'.$reason['test_reason_id'].'"');
                        ->group('tr.test_reason_id');
        if(isset($params['facilityId']) && $params['facilityId']!=''){
            $rQuery = $rQuery->where('vl.facility_id="'.base64_decode(trim($params['facilityId'])).'"');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $rQuery = $rQuery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if(isset($params['sampleId']) && $params['sampleId']!=''){
            $rQuery = $rQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleId'])).'"');
        }
        if(isset($params['testResult']) && $params['testResult']!=''){
            $rQuery = $rQuery->where('vl.result'.$params['testResult']);
        }
        if(isset($params['gender'] ) && trim($params['gender'])!=''){
            $rQuery = $rQuery->where(array("vl.patient_gender ='".$params['gender']."'")); 
        }
        if(isset($params['age']) && $params['age']!=''){
            $age = explode("-",$params['age']);
            if(isset($age[1])){
            $rQuery = $rQuery->where(array("vl.patient_age_in_years >='".$age[0]."'","vl.patient_age_in_years <='".$age[1]."'"));
            }else{
            $rQuery = $rQuery->where('vl.patient_age_in_years'.$params['age']);
            }
        }
        $rQueryStr = $sql->getSqlStringForSqlObject($rQuery);
        //die($rQueryStr);

        //$qResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $qResult = $common->cacheQuery($rQueryStr,$dbAdapter);
        $j=0;
        foreach($qResult as $r){
            
            $rResult[$r['test_reason_name']][$j]['total'] = (int)$r['total'];
            $rResult['date'][$j] = $r['monthDate'];
            $j++;
            
        }
                    
        //\Zend\Debug\Debug::dump($rResult);//die;
        return $rResult;
    }
    
    public function fetchChartOverAllLoadResult($params,$where){
        $logincontainer = new Container('credo');
        $common = new CommonService($this->sm);
        $cDate = date('Y-m-d');
        $lastThirtyDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                        ->columns(array('total' => new Expression('COUNT(*)')))
                        //->join(array('rst'=>'r_sample_type'),'rst.sample_id=vl.sample_type')
                        ->where(array("DATE(vl.sample_collection_date) <='$cDate'",
                                      "DATE(vl.sample_collection_date) >='$lastThirtyDay'"));
                        //->where('vl.facility_id !=0');
        if(isset($params['clinicId']) && $params['clinicId']!=''){
            $squery = $squery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $squery = $squery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if(isset($params['sampleId']) && $params['sampleId']!=''){
            $squery = $squery->where('vl.sample_type="'.base64_decode(trim($params['sampleId'])).'"');
        }
        if(isset($params['testResult']) && $params['testResult']!=''){
            $squery = $squery->where('vl.result'.$params['testResult']);
        }
        
        if(isset($params['gender'] ) && trim($params['gender'])!=''){
            $squery = $squery->where(array("vl.patient_gender ='".$params['gender']."'")); 
        }
        if(isset($params['age']) && $params['age']!=''){
            $age = explode("-",$params['age']);
            if(isset($age[1])){
            $squery = $squery->where(array("vl.patient_age_in_years >='".$age[0]."'","vl.patient_age_in_years <='".$age[1]."'"));
            }else{
            $squery = $squery->where('vl.patient_age_in_years'.$params['age']);
            }
        }
        if($where!=''){
        $squery = $squery->where($where);    
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($squery);
        //echo $sQueryStr;die;
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    //end clinic details
    
    //get distinict date
    public function getDistinctDate($cDate,$lastThirtyDay)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array(new Expression('DISTINCT YEAR(sample_collection_date) as year,MONTH(sample_collection_date) as month,DAY(sample_collection_date) as day')))
                            //->where('vl.lab_id !=0')
                            ->order('month ASC')->order('day ASC');
        if(isset($cDate) && trim($cDate)!= ''){
            $squery = $squery->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"));
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
        $aColumns = array('sample_code','DATE_FORMAT(sample_collection_date,"%d-%b-%Y")','DATE_FORMAT(sample_testing_date,"%d-%b-%Y")','result');
        $orderColumns = array('sample_code','sample_collection_date','sample_testing_date','result');

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
        $cDate = ''; $lastThirtyDay = '';
	if(isset($parameters['sampleCollectionDate']) && trim($parameters['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $parameters['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array('vl_sample_id','sample_code','sample_collection_date','sample_type','sample_testing_date','result_value_log','result_value_absolute','result_value_text','result'))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
				->where(array('fd.facility_type'=>'1'));
        if($cDate!='' && $lastThirtyDay!=''){
            $sQuery = $sQuery->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"));
        }
        if(isset($parameters['clinicId']) && $parameters['clinicId'] !=''){
            $sQuery = $sQuery->where(array("vl.facility_id ='".base64_decode(trim($parameters['clinicId']))."'")); 
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $sQuery = $sQuery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if(isset($parameters['gender'] ) && trim($parameters['gender'])!=''){
            $sQuery = $sQuery->where(array("vl.patient_gender ='".$parameters['gender']."'"));
        }
        if(isset($parameters['sampleId'] ) && trim($parameters['sampleId'])!=''){
            $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleId'])).'"');
        }
        if(isset($parameters['age']) && trim($parameters['age'])!=''){
            $expAge=explode("-",$parameters['age']);
            if(trim($expAge[0])!="" && trim($expAge[1])!=""){
                $sQuery=$sQuery->where("(vl.patient_age_in_years>='".$expAge[0]."' AND vl.patient_age_in_years<='".$expAge[1]."')");
            }else{
                $sQuery = $sQuery->where(array("vl.patient_age_in_years >'".$expAge[0]."'"));
            }
        }
        if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'")); 
        }
        if(isset($parameters['result']) && trim($parameters['result'])=='result'){
            $sQuery = $sQuery->where("vl.result !='' AND vl.result IS NOT NULL"); 
        }else if(isset($parameters['result']) && trim($parameters['result'])=='noresult'){
            $sQuery = $sQuery->where("(vl.result ='' OR vl.result IS NULL)");
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
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                ->columns(array('vl_sample_id','sample_code','sample_collection_date','sample_type','sample_testing_date','result_value_log','result_value_absolute','result_value_text','result'))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
				->where(array('fd.facility_type'=>'1'));
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
            $iQuery = $iQuery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
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
        foreach ($rResult as $aRow) {
            $row = array();
	    if(isset($aRow['sample_collection_date']) && trim($aRow['sample_collection_date'])!=""){
                $xepCollectDate=explode(" ",$aRow['sample_collection_date']);
                $aRow['sample_collection_date']=$common->humanDateFormat($xepCollectDate[0])." ".$xepCollectDate[1];
            }
            if(isset($aRow['sample_testing_date']) && trim($aRow['sample_testing_date'])!=""){
                $xepTestingDate=explode(" ",$aRow['sample_testing_date']);
                $aRow['sample_testing_date']=$common->humanDateFormat($xepTestingDate[0])." ".$xepTestingDate[1];
            }
            $row[] = $aRow['sample_code'];
            $row[] = $aRow['sample_collection_date'];
            $row[] = $aRow['sample_testing_date'];
	    $row[] = $aRow['result'];
            $display = 'show';
            if($aRow['result']==""){
                $display= "none";
            }
	    $row[]='<a href="/clinics/test-result-view/'.base64_encode($aRow['vl_sample_id']).'" class="btn btn-primary btn-xs" target="_blank" style="display:'.$display.'">View</a>&nbsp;&nbsp;<a href="javascript:void(0);" class="btn btn-danger btn-xs" style="display:'.$display.'" onclick="generateResultPDF('.$aRow['vl_sample_id'].');">PDF</a>';
            
            $output['aaData'][] = $row;
        }
        return $output;
    }
    
    //get sample tested result details
    public function fetchClinicSampleTestedResults($params){
        $logincontainer = new Container('credo');
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        $cDate = date('Y-m-d');
        $lastThirtyDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        
        
        

            $queryStr = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                    ->columns(array(
                                                    //"total" => new Expression('COUNT(*)'),
                                                    "day" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%b-%Y')"),
                                                    
                                                    "DBSGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.result >= 1000 and vl.sample_type=8) THEN 1 ELSE 0 END)"),
                                                    "DBSLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.result < 1000 or vl.result='Target Not Detected') and vl.sample_type=8) THEN 1 ELSE 0 END)"),
                                                   
                                                    "OGreaterThan1000" => new Expression("SUM(CASE WHEN vl.result>=1000 and vl.sample_type!=8 THEN 1 ELSE 0 END)"),
                                                    "OLesserThan1000" => new Expression("SUM(CASE WHEN (vl.result<1000 or vl.result='Target Not Detected') and vl.sample_type!=8 THEN 1 ELSE 0 END)"),
                                                    
                                              )
                                            )
                                    ->where(array("DATE(vl.sample_collection_date) <='$cDate'", "DATE(vl.sample_collection_date) >='$lastThirtyDay'"));        
        
        
        if($params['facilityId'] !=''){
            $queryStr = $queryStr->where(array("vl.facility_id ='".base64_decode($params['facilityId'])."'")); 
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $queryStr = $queryStr->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if(isset($params['gender'] ) && trim($params['gender'])!=''){
            $queryStr = $queryStr->where(array("vl.patient_gender ='".$params['gender']."'")); 
        }
        if(isset($params['age']) && trim($params['age'])!=''){
            $expAge=explode("-",$params['age']);
            if(trim($expAge[0])!="" && trim($expAge[1])!=""){
                $queryStr=$queryStr->where("(vl.patient_age_in_years>='".$expAge[0]."' AND vl.patient_age_in_years<='".$expAge[1]."')");
            }else{
                $queryStr = $queryStr->where(array("vl.patient_age_in_years >'".$expAge[0]."'"));
            }
        }
        if(isset($params['adherence']) && trim($params['adherence'])!=''){
            $queryStr = $queryStr->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
        }
        
        

        $queryStr = $queryStr->group(array(new Expression('DATE(sample_collection_date)')));   
        $queryStr = $queryStr->order(array(new Expression('DATE(sample_collection_date)')));            
        
        $queryStr = $sql->getSqlStringForSqlObject($queryStr);
        
        //echo $queryStr;//die;
        
        $sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        
        
        $result = array();
        $j=0;
        
        
        //\Zend\Debug\Debug::dump($sampleResult);
        foreach($sampleResult as $sRow){
            
            if($sRow["day"] == null) continue;
            
            $result['DBS']['VL (>= 1000 cp/ml)'][$j] = $sRow["DBSGreaterThan1000"];
            $result['DBS']['VL (< 1000 cp/ml)'][$j] = $sRow["DBSLesserThan1000"];
            $result['Others']['VL (>= 1000 cp/ml)'][$j] = $sRow["OGreaterThan1000"];
            $result['Others']['VL (< 1000 cp/ml)'][$j] = $sRow["OLesserThan1000"];

            
            $result['date'][$j] = $sRow["day"];
            $j++;
        }
        //\Zend\Debug\Debug::dump($result);
        return $result;
        
    }
    
    
    public function fetchSampleDetails($params){
                $logincontainer = new Container('credo');
		//\Zend\Debug\Debug::dump($params);
		//die;
                $result = '';
                $dbAdapter = $this->adapter;
                $sql = new Sql($dbAdapter);
                $common = new CommonService($this->sm);
                if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                    $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
                    $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
                }
                $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                                        ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                                        ->where('vl.lab_id !=0')
                                        ->group('f.facility_id');
                                        
                if(isset($params['lab']) && trim($params['lab'])!=''){
                    $fQuery = $fQuery->where('f.facility_id="'.base64_decode(trim($params['lab'])).'"');
                }else{
                    if($logincontainer->role!= 1){
                        $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                        $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
                    }
                }
                
                $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if($facilityResult){
                        $i = 0;
                        $result = array();
                        foreach($facilityResult as $facility){
                                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                                        ->where('vl.lab_id="'.$facility['facility_id'].'"');
                                if(isset($params['clinicId']) && trim($params['clinicId'])!=''){
                                    $countQuery = $countQuery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
                                }
                                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                                    $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                                }
                                if(isset($params['testResult']) && $params['testResult']!=''){
                                    if($params['testResult'] == '<1000'){
                                      $countQuery = $countQuery->where("vl.result < 1000");
                                    }else if($params['testResult'] == '>1000') {
                                      $countQuery = $countQuery->where("vl.result >= 1000");
                                    }
                                }
                                if(isset($params['gender']) && trim($params['gender'])!=''){
                                        $countQuery = $countQuery->where('vl.patient_gender="'.$params['gender'].'"');
                                }
                                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                                        $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                                }
                                
                                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                                        $countQuery = $countQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                                }
                                
                                if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                                        if(trim($params['fromDate'])!= trim($params['toDate'])){
                                           $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
                                        }else{
                                            $fromMonth = date("Y-m", strtotime(trim($params['fromDate'])));
                                            $month = strtotime($fromMonth);
                                            $mnth = date('m', $month);$year = date('Y', $month);
                                            $countQuery = $countQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                                        }
                                }
                                
                                if(isset($params['age']) && $params['age']!=''){
                                        if($params['age'] == '<18'){
                                          $countQuery = $countQuery->where("vl.patient_age_in_years < 18");
                                        }else if($params['age'] == '>18') {
                                          $countQuery = $countQuery->where("vl.patient_age_in_years > 18");
                                        }else if($params['age'] == 'unknown'){
                                          $countQuery = $countQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
                                        }
                                }
                                if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested'){
                                    $countQuery = $countQuery->where("vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL'");
                                }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                                    $countQuery = $countQuery->where("(vl.result IS NULL OR vl.result = 'NULL' OR vl.result = '')");
                                }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                                    $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
                                }
                                
                                $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                               // echo $cQueryStr;die;
                                $countResult[$i] = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $result[$i][0] = $countResult[$i]['total'];
                                $result[$i][1] = $facility['facility_name'];
                                $result[$i][2] = $facility['facility_code'];
                                $i++;
                        }
                }
		//\Zend\Debug\Debug::dump($result);
		//die;
        return $result;
    }
    
    public function fetchBarSampleDetails($params){
            $logincontainer = new Container('credo');
        //\Zend\Debug\Debug::dump($params);
		//die;
                $result = '';
                $dbAdapter = $this->adapter;
                $sql = new Sql($dbAdapter);
                $common = new CommonService($this->sm);
                if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                    $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
                    $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
                }
                $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                                        ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                                        ->where('vl.lab_id !=0')
                                        ->group('f.facility_id');
                if(isset($params['lab']) && trim($params['lab'])!=''){
                    $fQuery = $fQuery->where('f.facility_id="'.base64_decode(trim($params['lab'])).'"');
                }else{
                    if($logincontainer->role!= 1){
                        $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                        $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
                    }
                }
                $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if($facilityResult){
                        $j = 0;
                        $result = array();
                        foreach($facilityResult as $facility){
                            $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                //->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                                                                        ->where('vl.lab_id="'.$facility['facility_id'].'"');
                            if(isset($params['clinicId']) && trim($params['clinicId'])!=''){
                                $countQuery = $countQuery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
                            }
                            if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                                $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                            }
                            if(isset($params['testResult']) && $params['testResult']!=''){
                                if($params['testResult'] == '<1000'){
                                  $countQuery = $countQuery->where("vl.result < 1000");
                                }else if($params['testResult'] == '>1000') {
                                  $countQuery = $countQuery->where("vl.result >= 1000");
                                }
                            }
                            if(isset($params['gender']) && trim($params['gender'])!=''){
                                $countQuery = $countQuery->where('vl.patient_gender="'.$params['gender'].'"');
                            }
                            if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                                $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                            }
                            
                            if(isset($params['adherence']) && trim($params['adherence'])!=''){
                                $countQuery = $countQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                            }
                            
                            if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                                if(trim($params['fromDate'])!= trim($params['toDate'])){
                                   $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
                                }else{
                                    $fromMonth = date("Y-m", strtotime(trim($params['fromDate'])));
                                    $month = strtotime($fromMonth);
                                    $mnth = date('m', $month);$year = date('Y', $month);
                                    $countQuery = $countQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                                }
                            }
                            
                            if(isset($params['age']) && $params['age']!=''){
                                if($params['age'] == '<18'){
                                  $countQuery = $countQuery->where("vl.patient_age_in_years < 18");
                                }else if($params['age'] == '>18') {
                                  $countQuery = $countQuery->where("vl.patient_age_in_years > 18");
                                }else if($params['age'] == 'unknown'){
                                  $countQuery = $countQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
                                }
                            }
                            if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested'){
                                $countQuery = $countQuery->where("vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL'");
                            }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                                $countQuery = $countQuery->where("(vl.result IS NULL OR vl.result = 'NULL' OR vl.result = '')");
                            }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                                $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
                            }
                            $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                            $lessResult = $dbAdapter->query($cQueryStr." AND vl.result < 1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result['sample']['Suppressed'][$j] = $lessResult->total;
                            $greaterResult = $dbAdapter->query($cQueryStr." AND vl.result >= 1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result['sample']['Not Suppressed'][$j] = $greaterResult->total;
                            $rejectionResult = $dbAdapter->query($cQueryStr." AND vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result['sample']['Rejected'][$j] = $rejectionResult->total;
                            $result['lab'][$j] = $facility['facility_name'];
                            $j++;
                        }
                }
		//\Zend\Debug\Debug::dump($result);
		//die;
        return $result;
    }
    
    public function fetchLabSampleDetails($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService($this->sm);
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
        }
        $sQuery = $sql->select()->from(array('rs'=>'r_sample_type'))
                      ->columns(array('sample_name'))
                      ->join(array('vl'=>'dash_vl_request_form'),'rs.sample_id=vl.sample_type',array('samples' => new Expression('COUNT(*)')))
                      ->group('vl.sample_type');
        if(isset($params['lab']) && trim($params['lab'])!=''){
            $sQuery = $sQuery->where('vl.lab_id="'.base64_decode(trim($params['lab'])).'"');
        }else {
            if($logincontainer->role!= 1){
               $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
               $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        if(isset($params['clinicId']) && trim($params['clinicId'])!=''){
            $sQuery = $sQuery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
        }
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                $sQuery = $sQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        if(isset($params['testResult']) && $params['testResult']!=''){
            if($params['testResult'] == '<1000'){
              $sQuery = $sQuery->where("vl.result < 1000");
            }else if($params['testResult'] == '>1000') {
              $sQuery = $sQuery->where("vl.result >= 1000");
            }
        }
        if(isset($params['gender']) && trim($params['gender'])!=''){
                $sQuery = $sQuery->where('vl.patient_gender="'.$params['gender'].'"');
        }
        if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
        }
        
        if(isset($params['adherence']) && trim($params['adherence'])!=''){
                $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
        }
        
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                if(trim($params['fromDate'])!= trim($params['toDate'])){
                   $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
                }else{
                    $fromMonth = date("Y-m", strtotime(trim($params['fromDate'])));
                    $month = strtotime($fromMonth);
                    $mnth = date('m', $month);$year = date('Y', $month);
                    $sQuery = $sQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                }
        }
        
        if(isset($params['age']) && $params['age']!=''){
                if($params['age'] == '<18'){
                  $sQuery = $sQuery->where("vl.patient_age_in_years < 18");
                }else if($params['age'] == '>18') {
                  $sQuery = $sQuery->where("vl.patient_age_in_years > 18");
                }else if($params['age'] == 'unknown'){
                  $sQuery = $sQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
                }
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }
    
    public function fetchLabBarSampleDetails($params){
        $logincontainer = new Container('credo');
        $result = array();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $dbAdapter = $this->adapter;
            $sql = new Sql($dbAdapter);
            $common = new CommonService($this->sm);
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])));
            $start = $month = strtotime($startMonth);
            $end = strtotime($endMonth);
            $j = 0;
            while($month <= $end){
                $monthPlus = date('m', $month);$year = date('Y', $month);$dFormat = date("M-Y", $month);
                $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('samples' => new Expression('COUNT(*)')))
                              ->where("Month(sample_collection_date)='".$monthPlus."' AND Year(sample_collection_date)='".$year."'");
                if(isset($params['lab']) && trim($params['lab'])!=''){
                    $sQuery = $sQuery->where('vl.lab_id="'.base64_decode(trim($params['lab'])).'"');
                }else {
                    if($logincontainer->role!= 1){
                       $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                       $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
                    }
                }
                if(isset($params['clinicId']) && trim($params['clinicId'])!=''){
                    $sQuery = $sQuery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
                }
                if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                        $sQuery = $sQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
                }
                if(isset($params['testResult']) && $params['testResult']!=''){
                    if($params['testResult'] == '<1000'){
                      $sQuery = $sQuery->where("vl.result < 1000");
                    }else if($params['testResult'] == '>1000') {
                      $sQuery = $sQuery->where("vl.result >= 1000");
                    }
                }
                if(isset($params['gender']) && trim($params['gender'])!=''){
                        $sQuery = $sQuery->where('vl.patient_gender="'.$params['gender'].'"');
                }
                if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                        $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                }
                
                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                        $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                }
                
                if(isset($params['age']) && $params['age']!=''){
                        if($params['age'] == '<18'){
                          $sQuery = $sQuery->where("vl.patient_age_in_years < 18");
                        }else if($params['age'] == '>18') {
                          $sQuery = $sQuery->where("vl.patient_age_in_years > 18");
                        }else if($params['age'] == 'unknown'){
                          $sQuery = $sQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
                        }
                }
                $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
                //echo $sQueryStr;die;
                $lessResult = $dbAdapter->query($sQueryStr." AND (vl.result<1000 or vl.result = 'Target Not Detected' or vl.result='tnd')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['rslt']['VL (< 1000 cp/ml)'][$j] = $lessResult->samples;
                
                $greaterResult = $dbAdapter->query($sQueryStr." AND vl.result>=1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result['rslt']['VL (>= 1000 cp/ml)'][$j] = $greaterResult->samples;
                
                //$notTargetResult = $dbAdapter->query($sQueryStr." AND 'vl.result'='Target Not Detected'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                //$result['rslt']['VL Not Detected'][$j] = $notTargetResult->samples;
                $result['date'][$j] = $dFormat;
                $month = strtotime("+1 month", $month);
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
        $aColumns = array('DATE_FORMAT(sample_collection_date,"%d-%b-%Y")','vl_sample_id','sample_name','facility_name');
        $orderColumns = array('sample_collection_date','vl_sample_id','sample_name','facility_name');

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
                                ->columns(array('sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),'samples' => new Expression('COUNT(*)')))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
				->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
				->where('fd.facility_type = "1" AND vl.sample_collection_date!= "" AND vl.sample_collection_date IS NOT NULL AND vl.sample_collection_date!= "0000-00-00 00:00:00"')
                                ->group(new Expression('DATE(sample_collection_date)'))
                                ->group('vl.sample_type')
                                ->group('vl.facility_id');
        //filter start
        if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
            if(trim($parameters['fromDate'])!= trim($parameters['toDate'])){
               $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
            }else{
                $fromMonth = date("Y-m", strtotime(trim($parameters['fromDate'])));
                $month = strtotime($fromMonth);
                $mnth = date('m', $month);$year = date('Y', $month);
                $sQuery = $sQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
            }
        }if(isset($parameters['searchGender'] ) && trim($parameters['searchGender'])!=''){
            $sQuery = $sQuery->where(array("vl.patient_gender ='".$parameters['searchGender']."'")); 
        }if(isset($parameters['testResult']) && $parameters['testResult']!=''){
            if($parameters['testResult'] == '<1000'){
              $sQuery = $sQuery->where("vl.result < 1000");
            }else if($parameters['testResult'] == '>1000') {
              $sQuery = $sQuery->where("vl.result >= 1000");
            }
        }if(isset($parameters['lab'] ) && trim($parameters['lab'])!=''){
            $sQuery = $sQuery->where(array("vl.lab_id ='".base64_decode($parameters['lab'])."'")); 
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }if(isset($parameters['clinicId'] ) && trim($parameters['clinicId'])!=''){
            $sQuery = $sQuery->where(array("vl.facility_id ='".base64_decode($parameters['clinicId'])."'")); 
        }if(isset($parameters['sampleType']) && trim($parameters['sampleType'])!=''){
            $sQuery = $sQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleType'])).'"');
        }if(isset($parameters['currentRegimen']) && trim($parameters['currentRegimen'])!=''){
            $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($parameters['currentRegimen'])).'"');
        }if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'")); 
        }if(isset($parameters['age']) && $parameters['age']!=''){
            if($parameters['age'] == '<18'){
              $sQuery = $sQuery->where("vl.patient_age_in_years < 18");
            }else if($parameters['age'] == '>18') {
              $sQuery = $sQuery->where("vl.patient_age_in_years > 18");
            }else if($parameters['age'] == 'unknown'){
              $sQuery = $sQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
            }
        }
        
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
                                ->columns(array('sampleCollectionDate'=>new Expression('DATE(sample_collection_date)'),'samples' => new Expression('COUNT(*)')))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
				->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
				->where('fd.facility_type = "1" AND vl.sample_collection_date!= "" AND vl.sample_collection_date IS NOT NULL AND vl.sample_collection_date!= "0000-00-00 00:00:00"')
                                ->group(new Expression('DATE(sample_collection_date)'))
                                ->group('vl.sample_type')
                                ->group('vl.facility_id');
        //filter start
        if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
            if(trim($parameters['fromDate'])!= trim($parameters['toDate'])){
               $iQuery = $iQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
            }else{
                $fromMonth = date("Y-m", strtotime(trim($parameters['fromDate'])));
                $month = strtotime($fromMonth);
                $mnth = date('m', $month);$year = date('Y', $month);
                $iQuery = $iQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
            }
        }if(isset($parameters['searchGender'] ) && trim($parameters['searchGender'])!=''){
            $iQuery = $iQuery->where(array("vl.patient_gender ='".$parameters['searchGender']."'")); 
        }if(isset($parameters['testResult']) && $parameters['testResult']!=''){
            if($parameters['testResult'] == '<1000'){
              $iQuery = $iQuery->where("vl.result < 1000");
            }else if($parameters['testResult'] == '>1000') {
              $iQuery = $iQuery->where("vl.result >= 1000");
            }
        }if(isset($parameters['lab'] ) && trim($parameters['lab'])!=''){
            $iQuery = $iQuery->where(array("vl.lab_id ='".base64_decode($parameters['lab'])."'")); 
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }if(isset($parameters['clinicId'] ) && trim($parameters['clinicId'])!=''){
            $iQuery = $iQuery->where(array("vl.facility_id ='".base64_decode($parameters['clinicId'])."'")); 
        }if(isset($parameters['sampleType']) && trim($parameters['sampleType'])!=''){
            $iQuery = $iQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleType'])).'"');
        }if(isset($parameters['currentRegimen']) && trim($parameters['currentRegimen'])!=''){
            $iQuery = $iQuery->where('vl.current_regimen="'.base64_decode(trim($parameters['currentRegimen'])).'"');
        }if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
            $iQuery = $iQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'")); 
        }if(isset($parameters['age']) && $parameters['age']!=''){
            if($parameters['age'] == '<18'){
              $iQuery = $iQuery->where("vl.patient_age_in_years < 18");
            }else if($parameters['age'] == '>18') {
              $iQuery = $iQuery->where("vl.patient_age_in_years > 18");
            }else if($parameters['age'] == 'unknown'){
              $iQuery = $iQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
            }
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
            $row[] = $aRow['samples'];
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
        $aColumns = array('facility_name','vl_sample_id','vl_sample_id','vl_sample_id');
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
                                ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                                ->where('vl.lab_id !=0')
                                ->group('f.facility_id');
        if(isset($parameters['lab']) && trim($parameters['lab'])!=''){
            $sQuery = $sQuery->where('f.facility_id="'.base64_decode(trim($parameters['lab'])).'"');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $sQuery = $sQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
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
                                ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type','result'))
                                ->where('vl.lab_id !=0')
                                ->group('f.facility_id');
        if(isset($parameters['lab']) && trim($parameters['lab'])!=''){
            $iQuery = $iQuery->where('f.facility_id="'.base64_decode(trim($parameters['lab'])).'"');
        }else{
            if($logincontainer->role!= 1){
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
                $iQuery = $iQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
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
            $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                        ->where('vl.lab_id="'.$aRow['facility_id'].'"');
            if(isset($parameters['clinicId']) && trim($parameters['clinicId'])!=''){
                $countQuery = $countQuery->where('vl.facility_id="'.base64_decode(trim($parameters['clinicId'])).'"');
            }
            if(isset($parameters['sampleType']) && trim($parameters['sampleType'])!=''){
                $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($parameters['sampleType'])).'"');
            }
            if(isset($parameters['currentRegimen']) && trim($parameters['currentRegimen'])!=''){
                $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($parameters['currentRegimen'])).'"');
            }
            
            if(isset($parameters['adherence']) && trim($parameters['adherence'])!=''){
                $countQuery = $countQuery->where(array("vl.arv_adherance_percentage ='".$parameters['adherence']."'")); 
            }
            
            if(trim($parameters['fromDate'])!= '' && trim($parameters['toDate'])!= ''){
                if(trim($parameters['fromDate'])!= trim($parameters['toDate'])){
                   $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:00". "'"));
                }else{
                    $fromMonth = date("Y-m", strtotime(trim($parameters['fromDate'])));
                    $month = strtotime($fromMonth);
                    $mnth = date('m', $month);$year = date('Y', $month);
                    $countQuery = $countQuery->where("Month(sample_collection_date)='".$mnth."' AND Year(sample_collection_date)='".$year."'");
                }
            }
            if(isset($parameters['age']) && $parameters['age']!=''){
                if($parameters['age'] == '<18'){
                  $countQuery = $countQuery->where("vl.patient_age_in_years < 18");
                }else if($parameters['age'] == '>18') {
                  $countQuery = $countQuery->where("vl.patient_age_in_years > 18");
                }else if($parameters['age'] == 'unknown'){
                  $countQuery = $countQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
                }
            }
            if(isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'sample_tested'){
                $countQuery = $countQuery->where("vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL'");
            }else if(isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'samples_not_tested') {
                $countQuery = $countQuery->where("(vl.result IS NULL OR vl.result = 'NULL' OR vl.result = '')");
            }else if(isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'sample_rejected') {
                $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
            }
            $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
            $lessResult = $dbAdapter->query($cQueryStr." AND vl.result < 1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $suppressedTotal = $lessResult->total;
            $greaterResult = $dbAdapter->query($cQueryStr." AND vl.result > 1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $notSuppressedTotal = $greaterResult->total;
            $rejectionResult = $dbAdapter->query($cQueryStr." AND vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0", $dbAdapter::QUERY_MODE_EXECUTE)->current();
            $rejectedTotal = $rejectionResult->total;
            $row[] = ucwords($aRow['facility_name']);
            $row[] = $suppressedTotal;
            $row[] = $notSuppressedTotal;
            $row[] = $rejectedTotal;
            $output['aaData'][] = $row;
        }
       return $output;
    }
}
