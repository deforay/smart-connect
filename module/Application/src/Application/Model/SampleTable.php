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

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    //start lab dashboard details 
    public function fetchSampleResultDetails($params){
        $common = new CommonService();
        $cDate = date('Y-m-d');
        $lastThirtyDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            //print_r($s_c_date);die;
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastThirtyDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        
        $dbAdapter = $this->adapter;$sql = new Sql($dbAdapter);
        $sResult = $this->getDistinctDate($cDate,$lastThirtyDay);
        //set count
            $i = 0;
            $waitingTotal = 0;$acceptedTotal = 0;$rejectedTotal = 0;$receivedTotal = 0;
            $tResult = array();$acceptedResult = array();$waitingResult = array();$rejectedResult = array();
            if($sResult){
            foreach($sResult as $sampleData){
                if($sampleData['year']!=NULL){
                    $date = $sampleData['year']."-".$sampleData['month']."-".$sampleData['day'];
                    $dFormat = date("d M", strtotime($date));
                    //get waiting data
                    $waitingQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                        ->columns(array('total' => new Expression('COUNT(*)')))
                                        ->where(array("(vl.result='' OR vl.result is NULL)"));
                if(isset($cDate) && trim($cDate)!= ''){
                   $waitingQuery = $waitingQuery->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"));
                }
                if($params['facilityId'] !=''){
                   $waitingQuery = $waitingQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                }
                 $wQueryStr = $sql->getSqlStringForSqlObject($waitingQuery);
                 $waitingResult[$i] = $dbAdapter->query($wQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                 if($waitingResult[$i][0]['total']!=0){
                    $waitingTotal = $waitingTotal + $waitingResult[$i][0]['total'];
                    $waitingResult[$i]['date'] = $dFormat;
                    $waitingResult[$i]['waitingDate'] = $dFormat;
                    $waitingResult[$i]['waitingTotal'] = $waitingTotal;
                 }else{
                    unset($waitingResult[$i]);
                 }
                 
                    //get accepted data
                    $acceptedQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                                   ->columns(array('total' => new Expression('COUNT(*)')))
                                                   ->where(array("(vl.result!='' AND vl.result is NOT NULL)"));
                if(isset($cDate) && trim($cDate)!= ''){
                    $acceptedQuery = $acceptedQuery->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"));
                }
                if($params['facilityId'] !=''){
                    $acceptedQuery = $acceptedQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                }
                 $aQueryStr = $sql->getSqlStringForSqlObject($acceptedQuery);
                 $acceptedResult[$i] = $dbAdapter->query($aQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                 if($acceptedResult[$i][0]['total']!=0){
                 $acceptedTotal = $acceptedTotal + $acceptedResult[$i][0]['total'];
                 $acceptedResult[$i]['date'] = $dFormat;
                 $acceptedResult[$i]['acceptDate'] = $dFormat;
                 $acceptedResult[$i]['acceptTotal'] = $acceptedTotal;
                 }else{
                 unset($acceptedResult[$i]);
                 }
                //get rejected data
                $rejectedQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                               ->columns(array('total' => new Expression('COUNT(*)')))
                                               ->where(array("(vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection != 'NULL')"));
                if(isset($cDate) && trim($cDate)!= ''){
                   $rejectedQuery = $rejectedQuery->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"));
                }
                if($params['facilityId'] !=''){
                   $rejectedQuery = $rejectedQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                }
                 $rQueryStr = $sql->getSqlStringForSqlObject($rejectedQuery);
                 $rejectedResult[$i] = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                 if($rejectedResult[$i][0]['total']!=0){
                 $rejectedTotal = $rejectedTotal + $rejectedResult[$i][0]['total'];
                 $rejectedResult[$i]['date'] = $dFormat;
                 $rejectedResult[$i]['rejectDate'] = $dFormat;
                 $rejectedResult[$i]['rejectTotal'] = $rejectedTotal;
                 }else{
                 unset($rejectedResult[$i]);
                 }
                $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')));
                if(isset($cDate) && trim($cDate)!= ''){
                   $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"));
                }
                if($params['facilityId'] !=''){
                   $sQuery = $sQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                }
                $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
                $tResult[$i] = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if($tResult[$i][0]['total']!=0){
                $receivedTotal = $receivedTotal + $tResult[$i][0]['total'];
                $tResult[$i]['date'] = $dFormat;
                $tResult[$i]['accessDate'] = $dFormat;
                $tResult[$i]['accessTotal'] = $receivedTotal;
                }else{
                unset($tResult[$i]);
                }
                $i++;
                }
            }
            }
        return array('stResult'=>$tResult,'saResult'=>$acceptedResult,'swResult'=>$waitingResult,'srResult'=>$rejectedResult);
    }
    
    //get sample tested result details
    public function fetchSampleTestedResultDetails($params)
    {
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
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
        
        $rsQuery = $sql->select()->from(array('rs'=>'r_sample_type'));
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            $rsQuery = $rsQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
        $sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if($sampleTypeResult){
            //set datewise query
            $sResult = $this->getDistinctDate($cDate,$lastThirtyDay);
            $j = 0;
            if($sResult){
                foreach($sResult as $sampleData){
                    if($sampleData['year']!=NULL){
                        $date = $sampleData['year']."-".$sampleData['month']."-".$sampleData['day'];
                        $dFormat = date("d M", strtotime($date));
                        $i = 0;
                        $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
                        foreach($sampleTypeResult as $sample){
                            $lessThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                                ->where(array('vl.result<1000'));
                            if($params['facilityId'] !=''){
                                $lessThanQuery = $lessThanQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                             }
                            $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                            $lessResult[$i] = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result[$sample['sample_name']]['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult[$i]['total'];
                            
                            $greaterThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                    ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                    ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                                    ->where(array('vl.result>1000'));
                            if($params['facilityId'] !=''){
                                $greaterThanQuery = $greaterThanQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                             }
                            $gQueryStr = $sql->getSqlStringForSqlObject($greaterThanQuery);
                            $greaterResult[$i] = $dbAdapter->query($gQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result[$sample['sample_name']]['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult[$i]['total'];
                            
                            $notDetectQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                                ->where(array('vl.result'=>'Target Not Detected'));
                            if($params['facilityId'] !=''){
                                $notDetectQuery = $notDetectQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                            }
                            $nQueryStr = $sql->getSqlStringForSqlObject($notDetectQuery);
                            $notTargetResult[$i] = $dbAdapter->query($nQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result[$sample['sample_name']]['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult[$i]['total'];
                            $i++;
                        }
                        $result['date'][$j] = $dFormat;
                        $j++;
                    }
                }
            }
            //\Zend\Debug\Debug::dump($result);die;
            return $result;
        }
    }
    public function fetchSampleTestedResultBasedVolumeDetails($params)
    {
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
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
        
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                    ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type'))
                    ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                    ->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"))
                    ->where('vl.lab_id !=0')
                    ->group('f.facility_id');
        if(isset($params['facilityId']) && trim($params['facilityId'])!=''){
            $fQuery = $fQuery->where('f.facility_id="'.base64_decode(trim($params['facilityId'])).'"');
        }
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            $fQuery = $fQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        
        $rsQuery = $sql->select()->from(array('rs'=>'r_sample_type'));
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            $rsQuery = $rsQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
        $sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        
        if($facilityResult && $sampleTypeResult){
            $j = 0;
            foreach($facilityResult as $facility)
            {
                $i = 0;
                $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
                foreach($sampleTypeResult as $sample){
                    $lessThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                            ->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"))
                                            ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                            ->where(array('vl.lab_id'=>$facility['facility_id']))
                                        ->where(array('vl.result<1000'));
                    $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                    $lessResult[$i] = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result[$sample['sample_name']]['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult[$i]['total'];
                    
                    $greaterThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                            ->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"))
                                            ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                            ->where(array('vl.lab_id'=>$facility['facility_id']))
                                            ->where(array('vl.result>1000'));
                    $gQueryStr = $sql->getSqlStringForSqlObject($greaterThanQuery);
                    $greaterResult[$i] = $dbAdapter->query($gQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result[$sample['sample_name']]['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult[$i]['total'];
                    
                    $notDetectQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                        ->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"))
                                        ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                        ->where(array('vl.lab_id'=>$facility['facility_id']))
                                        ->where(array('vl.result'=>'Target Not Detected'));
                    $nQueryStr = $sql->getSqlStringForSqlObject($notDetectQuery);
                    $notTargetResult[$i] = $dbAdapter->query($nQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result[$sample['sample_name']]['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult[$i]['total'];
                    $i++;
                }
                $result['lab'][$j] = $facility['facility_name'];
                $j++;
            }
        }
        return $result;
    }
    
    public function getRequisitionFormsTested($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
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
        //set datewise query
        $sResult = $this->getDistinctDate($cDate,$lastThirtyDay);
        if($sResult){
            $i = 0;
            $completeResultCount = 0;
            $inCompleteResultCount = 0;
            foreach($sResult as $sampleData){
                if($sampleData['year']!=NULL){
                    $date = $sampleData['year']."-".$sampleData['month']."-".$sampleData['day'];
                    $dFormat = date("d M", strtotime($date));
                    
                    $completeQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                         ->columns(array('total' => new Expression('COUNT(*)')))
                                         ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                         ->where(array("vl.sample_collection_date !=''"))
                                         ->where(array('vl.patient_art_no !=""'))
                                         ->where(array('vl.current_regimen !=""'))
                                         ->where(array('vl.patient_gender !=""'));
                    if($params['facilityId'] !=''){
                        $completeQuery = $completeQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                    }
                    $cQueryStr = $sql->getSqlStringForSqlObject($completeQuery);
                    $completeResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result['Complete'][$i] = $completeResultCount+$completeResult['total'];
                    
                    $inCompleteQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                                           ->columns(array('total' => new Expression('COUNT(*)')))
                                           ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                           ->where(array('(vl.sample_collection_date ="" OR  vl.patient_art_no="" OR vl.current_regimen="" OR vl.patient_gender="")'));
                    if($params['facilityId'] !=''){
                        $inCompleteQuery = $inCompleteQuery->where(array("vl.lab_id ='".base64_decode($params['facilityId'])."'")); 
                    }
                    
                    
                    $incQueryStr = $sql->getSqlStringForSqlObject($inCompleteQuery);
                    $inCompleteResult = $dbAdapter->query($incQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result['Incomplete'][$i] = $inCompleteResultCount+$inCompleteResult['total'];
                    if($completeResult['total']!=0 || $inCompleteResult['total']!=0){
                    $result['date'][$i] = $dFormat;
                    }else if($completeResult['total']==0 && $inCompleteResult['total']==0){
                        unset($result['Complete'][$i]);
                        unset($result['Incomplete'][$i]);
                    }
                    $i++;
                }
            }
            return $result;
        }
    }
    public function getSampleVolume($params)
    {
        $result = '';
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
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
        
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('vl'=>'dash_vl_request_form'),'vl.lab_id=f.facility_id',array('lab_id','sample_type'))
                        ->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'))
                        ->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"))
                        ->where('vl.lab_id !=0')
                        ->group('f.facility_id');
        if(isset($params['facilityId']) && trim($params['facilityId'])!=''){
            $fQuery = $fQuery->where('f.facility_id="'.base64_decode(trim($params['facilityId'])).'"');
        }
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            $fQuery = $fQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //\Zend\Debug\Debug::dump($facilityResult);die;
        if($facilityResult){
            $i = 0;
            foreach($facilityResult as $facility){
                $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                    ->where(array("vl.sample_collection_date >='" . $lastThirtyDay ." 00:00:00". "'", "vl.sample_collection_date <='" .$cDate." 23:59:00". "'"))
                                    ->where('vl.lab_id="'.$facility['facility_id'].'"');
                $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                $countResult[$i] = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                $result[$i][0] = $countResult[$i]['total'];
                $result[$i][1] = $facility['facility_name'];
                $i++;
            }
        }
        return $result;
    }
    public function fetchLabTurnAroundTime($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
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
        //set datewise query
        $sResult = $this->getDistinctDate($cDate,$lastThirtyDay);

        $rsQuery = $sql->select()->from(array('rs'=>'r_sample_type'));
        $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
        $sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $avgResult = array();
        if($sResult && $sampleTypeResult){
            $j = 0;
            foreach($sResult as $sampleData){
                if($sampleData['year']!=NULL){
                    $date = $sampleData['year']."-".$sampleData['month']."-".$sampleData['day'];
                    $dFormat = date("d M", strtotime($date));
                    $i = 0;
                    foreach($sampleTypeResult as $sample){
                        $lQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('sample_tested_datetime','sample_collection_date'))
                                            ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                            ->where(array('vl.sample_collection_date IS NOT NULL','vl.sample_tested_datetime IS NOT NULL'))
                                            ->where('vl.sample_type="'.$sample['sample_id'].'"');
                        $lQueryStr = $sql->getSqlStringForSqlObject($lQuery);
                        $lResult = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        if(count($lResult)>0){
                        $total = 0;
                        foreach($lResult as $data){
                            if(($data['sample_tested_datetime']!='0000-00-00 00:00:00' && $data['sample_collection_date']!='0000-00-00 00:00:00') && ($data['sample_tested_datetime']!=NULL && $data['sample_collection_date']!=NULL)){
                            $date1 = $data['sample_collection_date'];$date2 = $data['sample_tested_datetime'];
                            $hourdiff = round((strtotime($date2) - strtotime($date1))/3600, 1)."<br/>";
                            $total = $total + ($hourdiff);
                            }
                        }
                            $avgResult[$sample['sample_name']][$i][$j] = round($total/count($lResult),1);
                        }else{
                            $avgResult[$sample['sample_name']][$i][$j] = 0;
                            
                        }
                        $i++;
                    }
                    //all result
                    $alQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('sample_tested_datetime','sample_collection_date'))
                                            ->where(array('vl.sample_collection_date IS NOT NULL','vl.sample_tested_datetime IS NOT NULL'))
                                            ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"));
                    $alQueryStr = $sql->getSqlStringForSqlObject($alQuery);
                    $alResult = $dbAdapter->query($alQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if(count($alResult)>0){
                    $total = 0;
                    foreach($alResult as $data){
                        if(($data['sample_tested_datetime']!='0000-00-00 00:00:00' && $data['sample_collection_date']!='0000-00-00 00:00:00') && ($data['sample_tested_datetime']!=NULL && $data['sample_collection_date']!=NULL)){
                        $date1 = $data['sample_collection_date'];$date2 = $data['sample_tested_datetime'];
                        $hourdiff = round((strtotime($date2) - strtotime($date1))/3600, 1);
                        $total = $total + ($hourdiff);
                        }
                    }
                        $avgResult['all'][$j] = round($total/count($alResult),1);
                    }else{
                        $avgResult['all'][$j] = 0;
                    }
                }
                $avgResult['date'][$j] = $dFormat;
                $j++;
            }
            
        }
        return $avgResult;
    }
    public function fetchFacilites($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
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
        //set datewise query
        $sResult = $this->getDistinctDate($cDate,$lastThirtyDay);

        $lQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('sample_tested_datetime','sample_collection_date','lab_id','labCount' => new \Zend\Db\Sql\Expression("COUNT(vl.lab_id)")))
                                            ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.lab_id',array('facility_name','latitude','longitude'))
                                            ->where(array("vl.sample_collection_date >='" . $lastThirtyDay ." 00:00:00". "'", "vl.sample_collection_date <='" .$cDate." 23:59:00". "'"))
                                            ->group('vl.lab_id');
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
        //\Zend\Debug\Debug::dump($lResult);die;
        return $lResult;
    }
    
    //end lab dashboard details 
    
    //start clinic details
    public function fetchOverAllLoadStatus($params)
    {
        $common = new CommonService();
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
        $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('sample_code','sample_tested_datetime','result','sample_type'))
                        ->join(array('rst'=>'r_sample_type'),'rst.sample_id=vl.sample_type')
                        ->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"))
                        ->where('vl.facility_id !=0');
        if(isset($params['clinicId']) && $params['clinicId']!=''){
            $squery = $squery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
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
        if(isset($params['adherence']) && trim($params['adherence'])!=''){
            $squery = $squery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($squery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }
    public function fetchChartOverAllLoadStatus($params)
    {
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
        $where = 'vl.result>1000';
        $gTotal = $this->fetchChartOverAllLoadResult($params,$where);
        
        return array($testedTotal,$lessTotal,$gTotal,$overAllTotal);
    }
    public function fetchSampleTestedReason($params)
    {
        $rResult = '';
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
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
        $sResult = $this->getDistinctDate($cDate,$lastThirtyDay);
        $rsQuery = $sql->select()->from(array('r'=>'r_vl_test_reasons'));
        if(isset($params['testReason']) && $params['testReason']!=''){
            $rsQuery = $rsQuery->where('r.test_reason_id="'.base64_decode(trim($params['testReason'])).'"');
        }
        $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
        $testReason = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if($sResult && $testReason){
             $i = 0;
            foreach($testReason as $reason){
                $j = 0;
                foreach($sResult as $sampleData){
                    if($sampleData['year']!=NULL){
                        $date = $sampleData['year']."-".$sampleData['month']."-".$sampleData['day'];
                        $dFormat = date("d M", strtotime($date));
                        $rQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                        ->join(array('rst'=>'r_sample_type'),'rst.sample_id=vl.sample_type')
                                        ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                        ->where('vl.facility_id !=0')
                                        ->where('vl.reason_for_vl_testing="'.$reason['test_reason_id'].'"');
                        if(isset($params['facilityId']) && $params['facilityId']!=''){
                            $rQuery = $rQuery->where('vl.facility_id="'.base64_decode(trim($params['facilityId'])).'"');
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
                        $rResult[$reason['test_reason_name']][$j] = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $rResult['date'][$j] = $dFormat;
                    }
                    $j++;
                    }
                    $i++;
                }
            }
        return $rResult;
    }
    public function fetchChartOverAllLoadResult($params,$where)
    {
        $common = new CommonService();
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
        $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                        ->join(array('rst'=>'r_sample_type'),'rst.sample_id=vl.sample_type')
                        ->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"))
                        ->where('vl.facility_id !=0');
        if(isset($params['clinicId']) && $params['clinicId']!=''){
            $squery = $squery->where('vl.facility_id="'.base64_decode(trim($params['clinicId'])).'"');
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
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                ->columns(array('vl_sample_id','sample_code','sample_collection_date','sample_type','sample_testing_date','result_value_log','result_value_absolute','result_value_text','result'))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
				->where(array('fd.facility_type'=>'1'));
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
        if($cDate!='' && $lastThirtyDay!='')
        {
            $sQuery = $sQuery->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"));
        }
        if($parameters['clinicId'] !=''){
            $sQuery = $sQuery->where(array("vl.facility_id ='".base64_decode(trim($parameters['clinicId']))."'")); 
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
        $iQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('vl_sample_id'))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
				->where(array('fd.facility_type'=>'1'));
        $iQueryStr = $sql->getSqlStringForSqlObject($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);
        
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        
		$common = new CommonService();
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
			$row[]='<a href="#" class="btn btn-primary btn-xs">View</a>';
            
            $output['aaData'][] = $row;
        }
        return $output;
    }
    
    //get sample tested result details
    public function fetchClinicSampleTestedResults($params)
    {
        $result = array();
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
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
        
        $rsQuery = $sql->select()->from(array('rs'=>'r_sample_type'));
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            $rsQuery = $rsQuery->where('rs.sample_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
        $sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if($sampleTypeResult){
            //set datewise query
            $sResult = $this->getClinicDistinicDate($cDate,$lastThirtyDay);
            $j = 0;
            if($sResult){
                foreach($sResult as $sampleData){
                    if($sampleData['year']!=NULL){
                        $date = $sampleData['year']."-".$sampleData['month']."-".$sampleData['day'];
                        $dFormat = date("d M", strtotime($date));
                        $i = 0;
                        $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
                        foreach($sampleTypeResult as $sample){
                            if(isset($params['testResult']) && ($params['testResult']=="" || $params['testResult']=='<1000')){
                                $lessThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
                                                ->where(array('fd.facility_type'=>'1'))
                                                ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                                ->where(array('vl.result<1000'));
                                if($params['facilityId'] !=''){
                                    $lessThanQuery = $lessThanQuery->where(array("vl.facility_id ='".base64_decode($params['facilityId'])."'")); 
                                }
                                if(isset($params['gender'] ) && trim($params['gender'])!=''){
                                    $lessThanQuery = $lessThanQuery->where(array("vl.patient_gender ='".$params['gender']."'")); 
                                }
                                if(isset($params['age']) && trim($params['age'])!=''){
                                    $expAge=explode("-",$params['age']);
                                    if(trim($expAge[0])!="" && trim($expAge[1])!=""){
                                        $lessThanQuery=$lessThanQuery->where("(vl.patient_age_in_years>='".$expAge[0]."' AND vl.patient_age_in_years<='".$expAge[1]."')");
                                    }else{
                                        $lessThanQuery = $lessThanQuery->where(array("vl.patient_age_in_years >'".$expAge[0]."'"));
                                    }
                                }
                                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                                    $lessThanQuery = $lessThanQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                                }
                                $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                                $lessResult[$i] = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $result[$sample['sample_name']]['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult[$i]['total'];
                            }
                            
                            if(isset($params['testResult']) && ($params['testResult']=="" || $params['testResult']=='>1000')){
                                $greaterThanQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                    ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
                                                    ->where(array('fd.facility_type'=>'1'))
                                                    ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                    ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                                    ->where(array('vl.result>1000'));
                                if($params['facilityId'] !=''){
                                    $greaterThanQuery = $greaterThanQuery->where(array("vl.facility_id ='".base64_decode($params['facilityId'])."'")); 
                                }
                                if(isset($params['gender'] ) && trim($params['gender'])!=''){
                                    $greaterThanQuery = $greaterThanQuery->where(array("vl.patient_gender ='".$params['gender']."'")); 
                                }
                                if(isset($params['age']) && trim($params['age'])!=''){
                                    $expAge=explode("-",$params['age']);
                                    if(trim($expAge[0])!="" && trim($expAge[1])!=""){
                                        $greaterThanQuery=$greaterThanQuery->where("(vl.patient_age_in_years>='".$expAge[0]."' AND vl.patient_age_in_years<='".$expAge[1]."')");
                                    }else{
                                        $greaterThanQuery = $greaterThanQuery->where(array("vl.patient_age_in_years >'".$expAge[0]."'"));
                                    }
                                }
                                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                                    $greaterThanQuery = $greaterThanQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                                }
                                $gQueryStr = $sql->getSqlStringForSqlObject($greaterThanQuery);
                                $greaterResult[$i] = $dbAdapter->query($gQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $result[$sample['sample_name']]['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult[$i]['total'];
                            }
                            
                            if(isset($params['testResult']) && $params['testResult']==""){
                                $notDetectQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                                ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
                                                ->where(array('fd.facility_type'=>'1'))
                                                ->where(array("vl.sample_collection_date >='" . $date ." 00:00:00". "'", "vl.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                ->where('vl.sample_type="'.$sample['sample_id'].'"')
                                                ->where(array('vl.result'=>'Target Not Detected'));
                                if($params['facilityId'] !=''){
                                    $notDetectQuery = $notDetectQuery->where(array("vl.facility_id ='".base64_decode($params['facilityId'])."'")); 
                                }
                                if($params['gender'] !=''){
                                    $notDetectQuery = $notDetectQuery->where(array("vl.patient_gender ='".$params['gender']."'")); 
                                }
                                if(isset($params['age']) && trim($params['age'])!=''){
                                    $expAge=explode("-",$params['age']);
                                    if(trim($expAge[0])!="" && trim($expAge[1])!=""){
                                        $notDetectQuery=$notDetectQuery->where("(vl.patient_age_in_years>='".$expAge[0]."' AND vl.patient_age_in_years<='".$expAge[1]."')");
                                    }else{
                                        $notDetectQuery = $notDetectQuery->where(array("vl.patient_age_in_years >'".$expAge[0]."'"));
                                    }
                                }
                                if(isset($params['adherence']) && trim($params['adherence'])!=''){
                                    $notDetectQuery = $notDetectQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                                }
                                $nQueryStr = $sql->getSqlStringForSqlObject($notDetectQuery);
                                $notTargetResult[$i] = $dbAdapter->query($nQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                                $result[$sample['sample_name']]['VL Not Detected'][$j] = $notTargetTotal+$notTargetResult[$i]['total'];
                            }
                            $i++;
                        }
                        $result['date'][$j] = $dFormat;
                        $j++;
                    }
                }
            }
            //\Zend\Debug\Debug::dump($result);die;
            return $result;
        }
    }
    
    public function getClinicDistinicDate($cDate,$lastThirtyDay)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $squery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
                            ->columns(array(new Expression('DISTINCT YEAR(sample_collection_date) as year,MONTH(sample_collection_date) as month,DAY(sample_collection_date) as day')))
                            ->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name'))
                            ->where(array('fd.facility_type'=>'1'))
                            ->where('vl.lab_id !=0')
                            ->order('month ASC')->order('day ASC');
        if(isset($cDate) && trim($cDate)!= ''){
            $squery = $squery->where(array("vl.sample_collection_date <='" . $cDate ." 23:59:00". "'", "vl.sample_collection_date >='" . $lastThirtyDay." 00:00:00". "'"));
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($squery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }
}