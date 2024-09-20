<?php

namespace Application\Model;

use Laminas\Db\Sql\Sql;
use Laminas\Db\Adapter\Adapter;
use Application\Service\CommonService;
use Laminas\Db\TableGateway\AbstractTableGateway;


class TestReasonTable extends AbstractTableGateway
{

    protected $table = 'r_vl_test_reasons';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function fetchAllTestReasonName()
    {
        return $this->select(array('test_reason_status' => 'active'));
    }
    public function insertOrUpdate($arrayData)
    {
        return CommonService::upsert($this->adapter, $this->table, $arrayData);
    }
}
