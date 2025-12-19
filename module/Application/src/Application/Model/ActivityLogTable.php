<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;

class ActivityLogTable extends AbstractTableGateway
{
    protected $table = 'activity_log';

     public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function insertLog(array $data)
    {
        return $this->tableGateway->insert($data);
    }

    public function addActivityLog($event, $action, $resource)
    {
        $session = new Container('credo');
        $user = $session->userId;
        $data = array(
            'event_type' => $event,
            'action'     => $action,
            'resource'   => $resource,
            'user_id'    => $user,
            'date_time'  => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
        );
        $this->insert($data);
        return $this->lastInsertValue;
    }
}