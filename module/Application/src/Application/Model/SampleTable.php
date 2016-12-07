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

    protected $table = 'samples';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    public function fetchSampleResultDetails($params)
    {
        $common = new CommonService();
        $cDate = date('Y-m-d');
        $lastSevenDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            //print_r($s_c_date);die;
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastSevenDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        
        $dbAdapter = $this->adapter;$sql = new Sql($dbAdapter);
        $sResult = $this->getDistinicDate($cDate,$lastSevenDay);
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
                $waitingQuery = $sql->select()->from(array('s'=>'samples'))->columns(array('total' => new Expression('COUNT(*)')))->where('s.sample_status="6"');
                if(isset($cDate) && trim($cDate)!= ''){
                   $waitingQuery = $waitingQuery->where(array("s.sample_collection_date >='" . $date ." 00:00:00". "'", "s.sample_collection_date <='" . $date." 23:59:00". "'"));
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
                 $acceptedQuery = $sql->select()->from(array('s'=>'samples'))->columns(array('total' => new Expression('COUNT(*)')))->where('s.sample_status="7"');
                if(isset($cDate) && trim($cDate)!= ''){
                   $acceptedQuery = $acceptedQuery->where(array("s.sample_collection_date >='" . $date ." 00:00:00". "'", "s.sample_collection_date <='" . $date." 23:59:00". "'"));
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
                $rejectedQuery = $sql->select()->from(array('s'=>'samples'))->columns(array('total' => new Expression('COUNT(*)')))->where('s.sample_status="4"');
                if(isset($cDate) && trim($cDate)!= ''){
                   $rejectedQuery = $rejectedQuery->where(array("s.sample_collection_date >='" . $date ." 00:00:00". "'", "s.sample_collection_date <='" . $date." 23:59:00". "'"));
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
                $sQuery = $sql->select()->from(array('s'=>'samples'))->columns(array('total' => new Expression('COUNT(*)')));
                if(isset($cDate) && trim($cDate)!= ''){
                   $sQuery = $sQuery->where(array("s.sample_collection_date >='" . $date ." 00:00:00". "'", "s.sample_collection_date <='" . $date." 23:59:00". "'"));
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
            //\Zend\Debug\Debug::dump($tResult);die;
        return array('stResult'=>$tResult,'saResult'=>$acceptedResult,'swResult'=>$waitingResult,'srResult'=>$rejectedResult);
    }
    
    //get sample tested result details
    public function fetchSampleTestedResultDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        $cDate = date('Y-m-d');
        $lastSevenDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastSevenDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        
        $rsQuery = $sql->select()->from(array('rs'=>'r_sample_types'));
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            $rsQuery = $rsQuery->where('rs.type_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $rsQueryStr = $sql->getSqlStringForSqlObject($rsQuery);
        $sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if($sampleTypeResult){
            //set datewise query
            $sResult = $this->getDistinicDate($cDate,$lastSevenDay);
            $j = 0;
            if($sResult){
                foreach($sResult as $sampleData){
                    if($sampleData['year']!=NULL){
                        $date = $sampleData['year']."-".$sampleData['month']."-".$sampleData['day'];
                        $dFormat = date("d M", strtotime($date));
                        $i = 0;
                        $lessTotal = 0;$greaterTotal = 0;$notTargetTotal = 0;
                        foreach($sampleTypeResult as $sample){
                            $lessThanQuery = $sql->select()->from(array('s'=>'samples'))->columns(array('total' => new Expression('COUNT(*)')))
                                                ->where(array("s.sample_collection_date >='" . $date ." 00:00:00". "'", "s.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                ->where('s.sample_type="'.$sample['type_id'].'"')
                                                ->where(array('s.result<1000'));
                            $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                            $lessResult[$i] = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result[$sample['sample_name']]['VL (< 1000 cp/ml)'][$j] = $lessTotal+$lessResult[$i]['total'];
                            
                            $greaterThanQuery = $sql->select()->from(array('s'=>'samples'))->columns(array('total' => new Expression('COUNT(*)')))
                                                    ->where(array("s.sample_collection_date >='" . $date ." 00:00:00". "'", "s.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                    ->where('s.sample_type="'.$sample['type_id'].'"')
                                                    ->where(array('s.result>1000'));
                            $gQueryStr = $sql->getSqlStringForSqlObject($greaterThanQuery);
                            $greaterResult[$i] = $dbAdapter->query($gQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result[$sample['sample_name']]['VL (> 1000 cp/ml)'][$j] = $greaterTotal+$greaterResult[$i]['total'];
                            
                            $notDetectQuery = $sql->select()->from(array('s'=>'samples'))->columns(array('total' => new Expression('COUNT(*)')))
                                                ->where(array("s.sample_collection_date >='" . $date ." 00:00:00". "'", "s.sample_collection_date <='" . $date." 23:59:00". "'"))
                                                ->where('s.sample_type="'.$sample['type_id'].'"')
                                                ->where(array('s.result'=>'Target Not Detected'));
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
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $common = new CommonService();
        $cDate = date('Y-m-d');
        $lastSevenDay = date('Y-m-d', strtotime('-30 days'));
        if(isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate'])!= ''){
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
              $lastSevenDay = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
              $cDate = trim($s_c_date[1]);
            }
        }
        
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('s'=>'samples'),'s.lab_id=f.facility_id',array('lab_id','sample_id'))
                        ->join(array('rs'=>'r_sample_types'),'rs.type_id=s.sample_type',array('sample_name'))
                        ->where(array("s.sample_collection_date <='" . $cDate ." 00:00:00". "'", "s.sample_collection_date >='" . $lastSevenDay." 23:59:00". "'"))
                        ->group('f.facility_id');
        if(isset($params['facilityId']) && trim($params['facilityId'])!=''){
            $fQuery = $fQuery->where('f.facility_id="'.base64_decode(trim($params['facilityId'])).'"');
        }
        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            $fQuery = $fQuery->where('rs.type_id="'.base64_decode(trim($params['sampleType'])).'"');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //\Zend\Debug\Debug::dump($facilityResult);die;
        if($facilityResult){
                        $i = 0;
                        $lessTotal = 0;$greaterTotal= 0;$notTargetTotal=0;
                        foreach($facilityResult as $facility){
                            $lessThanQuery = $sql->select()->from(array('s'=>'samples'))->columns(array('total' => new Expression('COUNT(*)')))
                                                ->where(array("s.sample_collection_date <='" . $cDate ." 00:00:00". "'", "s.sample_collection_date >='" . $lastSevenDay." 23:59:00". "'"))
                                                ->where('s.lab_id="'.$facility['facility_id'].'"')
                                                ->where(array('s.result<1000'));
                            $lQueryStr = $sql->getSqlStringForSqlObject($lessThanQuery);
                            $lessResult[$i] = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result[$facility['sample_name']]['VL (< 1000 cp/ml)'][$i] = $lessTotal+$lessResult[$i]['total'];
                            
                            $greaterThanQuery = $sql->select()->from(array('s'=>'samples'))->columns(array('total' => new Expression('COUNT(*)')))
                                                    ->where(array("s.sample_collection_date <='" . $cDate ." 00:00:00". "'", "s.sample_collection_date >='" . $lastSevenDay." 23:59:00". "'"))
                                                    ->where('s.sample_type="'.$facility['facility_id'].'"')
                                                    ->where(array('s.result>1000'));
                            $gQueryStr = $sql->getSqlStringForSqlObject($greaterThanQuery);
                            $greaterResult[$i] = $dbAdapter->query($gQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result[$facility['sample_name']]['VL (> 1000 cp/ml)'][$i] = $greaterTotal+$greaterResult[$i]['total'];
                            
                            $notDetectQuery = $sql->select()->from(array('s'=>'samples'))->columns(array('total' => new Expression('COUNT(*)')))
                                                ->where(array("s.sample_collection_date <='" . $cDate ." 00:00:00". "'", "s.sample_collection_date >='" . $lastSevenDay." 23:59:00". "'"))
                                                ->where('s.sample_type="'.$facility['facility_id'].'"')
                                                ->where(array('s.result'=>'Target Not Detected'));
                            $nQueryStr = $sql->getSqlStringForSqlObject($notDetectQuery);
                            $notTargetResult[$i] = $dbAdapter->query($nQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                            $result[$facility['sample_name']]['VL Not Detected'][$i] = $notTargetTotal+$notTargetResult[$i]['total'];
                            if($lessResult[$i]['total']==0 && $greaterResult[$i]['total']==0 && $notTargetResult[$i]['total']==0){
                                unset($result[$facility['sample_name']]['VL (< 1000 cp/ml)'][$i]);
                                unset($result[$facility['sample_name']]['VL (> 1000 cp/ml)'][$i]);
                                unset($result[$facility['sample_name']]['VL Not Detected'][$i]);
                            }
                            $i++;
                        }
                        
            //\Zend\Debug\Debug::dump($result);die;
            return array('result'=>$result,'lab'=>$facilityResult);
        }
    }
    
    //get distinict date
    public function getDistinicDate($cDate,$lastSevenDay)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $squery = $sql->select()->from(array('s'=>'samples'))
                            ->columns(array(new Expression('DISTINCT YEAR(sample_collection_date) as year,MONTH(sample_collection_date) as month,DAY(sample_collection_date) as day')))
                            ->order('month ASC')->order('day ASC');
        if(isset($cDate) && trim($cDate)!= ''){
            $squery = $squery->where(array("s.sample_collection_date <='" . $cDate ." 00:00:00". "'", "s.sample_collection_date >='" . $lastSevenDay." 23:59:00". "'"));
        }
        $sQueryStr = $sql->getSqlStringForSqlObject($squery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }
}
