<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;


class Covid19SampleTypeTable extends AbstractTableGateway
{

    protected $table = 'r_covid19_sample_type';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function fetchAllSampleType()
    {
        return $this->select(array('status' => 'active'));
    }
}
