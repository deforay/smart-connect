<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;


class HepatitisResultsTable extends AbstractTableGateway
{

    protected $table = 'r_hepatitis_results';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function fetchAllResults()
    {
        return $this->select(array('status' => 'active'));
    }
}
