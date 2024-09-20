<?php

namespace Application\Model;

use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Application\Service\CommonService;
use Laminas\Db\TableGateway\AbstractTableGateway;


class SampleRejectionReasonTable extends AbstractTableGateway
{

    protected $table = 'r_vl_sample_rejection_reasons';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }
    public function insertOrUpdate($arrayData)
    {
        return CommonService::upsert($this->adapter, $this->table, $arrayData);
    }
}
