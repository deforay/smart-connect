<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;


class EidSampleRejectionReasonTable extends AbstractTableGateway
{

    protected $table = 'r_eid_sample_rejection_reasons';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }
}
