<?php

namespace Application\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
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
class TestReasonTable extends AbstractTableGateway {

    protected $table = 'r_vl_test_reasons';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    public function fetchAllTestReasonName(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rQuery = $sql->select()->from(array('r'=>'r_vl_test_reasons'))->where(array('test_reason_status' => 'active'));
        $rQueryStr = $sql->buildSqlString($rQuery);
        return $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }
}
