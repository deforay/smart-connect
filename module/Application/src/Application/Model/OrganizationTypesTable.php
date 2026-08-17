<?php

namespace Application\Model;

use Application\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;


class OrganizationTypesTable extends AbstractTableGateway
{

    protected $table = 'organization_type';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }


    public function fetchOrganizationTypes()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('ot' => 'organization_type'));

        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }
}
