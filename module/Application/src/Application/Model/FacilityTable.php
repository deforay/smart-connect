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

    public function fetchAllLabName()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type')
                        ->where('ft.facility_type_name="Viral Load Lab"');
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
    
    public function fetchAllClinicName()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type')
                        ->where('ft.facility_type_name="clinic"');
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
    
    public function fetchAllHubName()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type')
                        ->where('ft.facility_type_name="hub"');
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
}
