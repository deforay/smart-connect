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
class UserOrganizationsMapTable extends AbstractTableGateway {

    protected $table = 'user_organization_map';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    
    public function fetchOrganizations($userId){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('uom' => 'user_organization_map'))
                      ->where(array('user_id' => $userId));
        
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $rResult;
    }
    
    
    public function mapUserOrganizations($params){
        
        $this->delete(array('user_id' => $params['userId']));
        
        $credoContainer = new Container('credo');
        
        foreach($params['facilities'] as $facilityId){
            $this->insert(array('user_id'=>$params['userId'],'organization_id' => $facilityId));
        }
    }    
    
    
    
    
}
