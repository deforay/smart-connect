<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;


class Covid19TestReasonsTable extends AbstractTableGateway
{

    protected $table = 'r_covid19_test_reasons';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }
}
