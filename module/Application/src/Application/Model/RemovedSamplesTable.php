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
class RemovedSamplesTable extends AbstractTableGateway {

    protected $table = 'removed_samples';
    public $sm = null;

    public function __construct(Adapter $adapter, $sm=null) {
        $this->adapter = $adapter;
        $this->sm = $sm;
        
    }
}