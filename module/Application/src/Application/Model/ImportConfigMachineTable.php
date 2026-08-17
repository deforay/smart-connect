<?php

namespace Application\Model;

use Application\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;


/**
 * Description of Countries
 *
 * @author thanaseelan
 */
class ImportConfigMachineTable extends AbstractTableGateway
{

    protected $table = 'instrument_machines';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }
}
