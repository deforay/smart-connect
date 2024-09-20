<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;


class HepatitisSampleTypeTable extends AbstractTableGateway
{

    protected $table = 'r_hepatitis_sample_type';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function fetchAllSampleType()
    {
        return $this->select(array('status' => 'active'));
    }
}
