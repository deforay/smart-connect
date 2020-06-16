<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;

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
class OrganizationTypesTable extends AbstractTableGateway {

    protected $table = 'organization_type';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    
    public function fetchOrganizationTypes(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('ot' => 'organization_type'));
        
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $rResult;
    
    }
    
    
}