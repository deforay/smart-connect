<?php

namespace Application\Model;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Zend\Db\TableGateway\AbstractTableGateway;

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
class FacilityTable extends AbstractTableGateway {

    protected $table = 'facility_details';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    public function saveFacility($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        if(!isset($params['vlsm_instance_id']) || $params['vlsm_instance_id'] == ''){
            $params['vlsm_instance_id'] = 'mozambiquedisaopenldr';
        }
        
        if(!isset($params['facility_name']) || $params['facility_name'] == ''){
            $params['facility_name'] = $params['facility_code'];
        }
        
        
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                      ->where('f.facility_code=?',$params['facility_code'])
                      ->where('f.vlsm_instance_id=?',$params['vlsm_instance_id']);
        
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);

        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($rResult) > 0){
            return true;
        }else{
            $newData=array(
                           'vlsm_instance_id'=>$params['vlsm_instance_id'],
                           'facility_name'=>$params['facility_name'],
                           'facility_code'=>$params['facility_code'],
                           'facility_state'=>$params['facility_province'],
                           'facility_country'=>$params['facility_country'],
                           'latitude'=>$params['facility_latitude'],
                           'longitude'=>$params['facility_longitude'],
                           'status'=>'active'
                           );

            $this->insert($newData);
            return $this->lastInsertValue;
        }
    }

    public function fetchAllLabName(){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                      ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type')
                      ->where('ft.facility_type_name="Viral Load Lab"');
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
    
    public function fetchAllClinicName(){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type')
                        ->where('ft.facility_type_name="clinic"');
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
    
    public function fetchAllHubName(){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type')
                        ->where('ft.facility_type_name="hub"');
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:array();
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
    
    public function fetchRoleFacilities($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                      ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type');
        if(isset($params['role']) && $params['role'] == 2){
           $fQuery = $fQuery->where('f.facility_type=2');
        }else if(isset($params['role']) && $params['role'] == 3){
           $fQuery = $fQuery->where('f.facility_type IN (1,4)');
        }else if(isset($params['role']) && $params['role'] == 4){
           $fQuery = $fQuery->where('f.facility_type=3');  
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
    
    public function fetchSampleTestedFacilityInfo($params){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        //set filter labs
        if(isset($params['fromSrc']) && $params['fromSrc'] =='tested-lab'){
            $params['labs'] = array();
            $testedLabQuery = $sql->select()->from(array('f'=>'facility_details'))
                                  ->columns(array('facility_id','facility_name'))
                                  ->order('facility_name asc');
            if(isset($params['labNames']) && count($params['labNames']) >0){
               $testedLabQuery = $testedLabQuery->where('f.facility_name IN ("' . implode('", "', $params['labNames']) . '")');
            }
            $testedLabQueryStr = $sql->getSqlStringForSqlObject($testedLabQuery);
            $testedLabResult = $dbAdapter->query($testedLabQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($testedLabResult as $testedLab){
                $params['labs'][] = $testedLab['facility_id'];
            }
        }else if(isset($params['fromSrc']) && $params['fromSrc'] =='sample-volume'){
            $params['labs'] = array();
            $volumeLabQuery = $sql->select()->from(array('f'=>'facility_details'))
                                  ->columns(array('facility_id','facility_name'))
                                  ->order('facility_name asc');
            if(isset($params['labCodes']) && count($params['labCodes']) >0){
               $volumeLabQuery = $volumeLabQuery->where('f.facility_code IN ("' . implode('", "', $params['labCodes']) . '")');
            }
            $volumeLabQueryStr = $sql->getSqlStringForSqlObject($volumeLabQuery);
            $volumeLabResult = $dbAdapter->query($volumeLabQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($volumeLabResult as $volumeLab){
                $params['labs'][] = $volumeLab['facility_id'];
            }
        }
        $facilityInfo = array();
        //set accessible provinces
        $provinceQuery = $sql->select()->from(array('l_d'=>'location_details'))
                             ->columns(array('location_id','location_name'))
                             ->where(array('parent_location'=>0))
                             ->order('location_name asc');
        if($logincontainer->role!= 1){
            $provinceQuery = $provinceQuery->where('l_d.location_id IN ("' . implode('", "', array_values(array_filter($logincontainer->provinces))) . '")');
        }
        $provinceQueryStr = $sql->getSqlStringForSqlObject($provinceQuery);
        $facilityInfo['provinces'] = $dbAdapter->query($provinceQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //set selected provinces
        $facilityInfo['selectedProvinces'] = array();
        $labProvinces = array();
        if(isset($params['labs']) && count($params['labs']) >0){
            $labProvinceQuery = $sql->select()->from(array('l_d'=>'location_details'))
                                    ->columns(array('location_id','location_name'))
                                    ->join(array('f'=>'facility_details'),'f.facility_state=l_d.location_id',array())
                                    ->where(array('parent_location'=>0))
                                    ->group('f.facility_state')
                                    ->order('location_name asc');
            $labProvinceQuery = $labProvinceQuery->where('f.facility_id IN ("' . implode('", "', $params['labs']) . '")');
            $labProvinceQueryStr = $sql->getSqlStringForSqlObject($labProvinceQuery);
            $facilityInfo['selectedProvinces'] = $dbAdapter->query($labProvinceQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($facilityInfo['selectedProvinces'] as $province){
                $labProvinces[] = $province['location_id'];
            }
        }
        //set accessible province districts
        $provinceDistrictQuery = $sql->select()->from(array('l_d'=>'location_details'))
                                     ->columns(array('location_id','location_name'))
                                     ->where('parent_location != 0')
                                     ->order('location_name asc');
        if($logincontainer->role!= 1){
            $provinceDistrictQuery = $provinceDistrictQuery->where('l_d.location_id IN ("' . implode('", "', array_values(array_filter($logincontainer->districts))) . '")');
        }
        $provinceDistrictQueryStr = $sql->getSqlStringForSqlObject($provinceDistrictQuery);
        $facilityInfo['provinceDistricts'] = $dbAdapter->query($provinceDistrictQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //set lab districts
        $facilityInfo['labDistricts'] = array();
        $labDistricts = array();
        if(isset($params['labs']) && count($params['labs']) >0){
            $labDistrictQuery = $sql->select()->from(array('f'=>'facility_details'))
                                    ->columns(array('facility_district'))
                                    ->group('f.facility_district');
            $labDistrictQuery = $labDistrictQuery->where('f.facility_id IN ("' . implode('", "', $params['labs']) . '")');
            $labDistrictQueryStr = $sql->getSqlStringForSqlObject($labDistrictQuery);
            $facilityInfo['labDistricts'] = $dbAdapter->query($labDistrictQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($facilityInfo['labDistricts'] as $district){
               $labDistricts[] = $district['facility_district'];
            }
        }
        //set accessible district labs
        $labQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->columns(array('facility_id','facility_name','facility_code'))
                        ->where(array('f.facility_type'=>2))
                        ->order('facility_name asc');
        if(isset($labDistricts) && count(array_values(array_filter($labDistricts))) >0){
           $labQuery = $labQuery->where('f.facility_district IN ("' . implode('", "', array_values(array_filter($labDistricts))) . '")');
        }
        $labQueryStr = $sql->getSqlStringForSqlObject($labQuery);
        $facilityInfo['labs'] = $dbAdapter->query($labQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //set accessible district clinics
        $clinicQuery = $sql->select()->from(array('f'=>'facility_details'))
                           ->columns(array('facility_id','facility_name'))
                           ->where('f.facility_type IN ("1","4")')
                           ->order('facility_name asc');
        if(isset($labDistricts) && count(array_values(array_filter($labDistricts))) >0){
           $clinicQuery = $clinicQuery->where('f.facility_district IN ("' . implode('", "', array_values(array_filter($labDistricts))) . '")');
        }
        $clinicQueryStr = $sql->getSqlStringForSqlObject($clinicQuery);
        $facilityInfo['clinics'] = $dbAdapter->query($clinicQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //print_r($facilityInfo);die;
      return $facilityInfo;
    }
    
    public function fetchSampleTestedLocationInfo($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $locationInfo = array();
        $provinceDistricts = array();
        if($params['fromSrc'] == 'provinces'){
            //set province districts
            $provinceDistrictQuery = $sql->select()->from(array('l_d'=>'location_details'))
                                         ->columns(array('location_id','location_name'))
                                         ->where('parent_location != 0')
                                         ->order('location_name asc');
            if(isset($params['provinces']) && count($params['provinces']) >0){
               $provinceDistrictQuery = $provinceDistrictQuery->where('l_d.parent_location IN ("' . implode('", "', $params['provinces']) . '")');
            }
            $provinceDistrictQueryStr = $sql->getSqlStringForSqlObject($provinceDistrictQuery);
            $locationInfo['provinceDistricts'] = $dbAdapter->query($provinceDistrictQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach($locationInfo['provinceDistricts'] as $district){
                $provinceDistricts[] = $district['location_id'];
            }
        }else{
            $provinceDistricts = $params['districts'];
        }
        //set province district labs
        $labQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->columns(array('facility_id','facility_name'))
                        ->where(array('f.facility_type'=>2))
                        ->order('facility_name asc');
        if(isset($provinceDistricts) && count($provinceDistricts) >0){
           $labQuery = $labQuery->where('f.facility_district IN ("' . implode('", "', $provinceDistricts) . '")');
        }
        $labQueryStr = $sql->getSqlStringForSqlObject($labQuery);
        $locationInfo['labs'] = $dbAdapter->query($labQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //set province district clinics
        $clinicQuery = $sql->select()->from(array('f'=>'facility_details'))
                           ->columns(array('facility_id','facility_name'))
                           ->where('f.facility_type IN ("1","4")')
                           ->order('facility_name asc');
        if(isset($provinceDistricts) && count($provinceDistricts) >0){
           $clinicQuery = $clinicQuery->where('f.facility_district IN ("' . implode('", "', $provinceDistricts) . '")');
        }
        $clinicQueryStr = $sql->getSqlStringForSqlObject($clinicQuery);
        //echo $clinicQueryStr;die;
        $locationInfo['clinics'] = $dbAdapter->query($clinicQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
      return $locationInfo;
    }
}
