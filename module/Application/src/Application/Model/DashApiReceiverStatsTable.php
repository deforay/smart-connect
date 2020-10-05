<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;

class DashApiReceiverStatsTable extends AbstractTableGateway {

    protected $table = 'dash_api_receiver_stats';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
}
