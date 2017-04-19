<?php

namespace Application\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Sql\Sql;
use Application\Service\CommonService;

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
class GlobalTable extends AbstractTableGateway {

    protected $table = 'dash_global_config';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    
    public function getGlobalValue($globalName) {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('dash_global_config')->where(array('name' => $globalName));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $configValues = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $configValues[0]['value'];
    }
    
}
