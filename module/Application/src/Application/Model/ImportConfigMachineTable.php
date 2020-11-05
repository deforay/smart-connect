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
class ImportConfigMachineTable extends AbstractTableGateway {

    protected $table = 'r_eid_sample_type';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }

    public function fetchAllSampleType()
    {
        $query = $this->select(array('status' => 'active'));
        return $query;
    }
}
