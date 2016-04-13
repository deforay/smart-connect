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
class OrganizationsTable extends AbstractTableGateway {

    protected $table = 'organizations';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    
    public function fetchOrganizations(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('o' => 'organizations'))
                ->join(array('ot' => 'organization_type'), 'o.org_type=ot.id', array('type'));
        
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $rResult;
    }
    
    
    public function addOrganization($params){
        $credoContainer = new Container('credo');
        $newData=array('name'=>$params['name'],
                       'org_type'=>$params['type'],
                       'facility_id'=>$params['facilityId'],
                       'contact_person'=>$params['contactPerson'],
                       'email'=>$params['email'],
                       'secondary_email'=>$params['secondaryEmail'],
                       'phone'=>$params['phone'],
                       'secondary_phone'=>$params['secondaryPhone'],
                       'address'=>$params['address'],
                       'country'=>$params['country'],
                       'sub_level'=>$params['subLevel'],
                       'longitude'=>$params['longitude'],
                       'latitude'=>$params['latitude'],
                       'created_by'=>$credoContainer->userId,
                       'created_on'=> new Expression('NOW()'),
                       'status'=>'active'
                       );
        
        //\Zend\Debug\Debug::dump($newData);die;
        
        $this->insert($newData);
        return $this->lastInsertValue;
    }    
    
    
    public function getOrganization($orgId){
        $dbAdapter = $this->adapter;

        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('o' => 'organizations'))
                      ->where("org_id= $orgId");
        
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);

        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($rResult) > 0){
            return $rResult[0];
        }else{
            return false;    
        }
        
    }    
    
    public function updateOrganization($params){
        $credoContainer = new Container('credo');
        $userId = $credoContainer->userId;        
        $data=array('name'=>$params['name'],
                       'org_type'=>$params['type'],
                       'facility_id'=>$params['facilityId'],
                       'contact_person'=>$params['contactPerson'],
                       'email'=>$params['email'],
                       'secondary_email'=>$params['secondaryEmail'],
                       'phone'=>$params['phone'],
                       'secondary_phone'=>$params['secondaryPhone'],
                       'address'=>$params['address'],
                       'country'=>$params['country'],
                       'sub_level'=>$params['subLevel'],
                       'longitude'=>$params['longitude'],
                       'latitude'=>$params['latitude'],
                       'status'=>$params['status']
                       );
        return $this->update($data,array('org_id' => $params['orgId']));
    }    
    
    
    
    
}
