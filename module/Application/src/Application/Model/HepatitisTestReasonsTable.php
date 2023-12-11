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
class HepatitisTestReasonsTable extends AbstractTableGateway {

    protected $table = 'r_hepatitis_test_reasons';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }

    public function fetchAllTestReasons()
    {
        return $this->select(array('test_reason_status' => 'active'));
    }
}
