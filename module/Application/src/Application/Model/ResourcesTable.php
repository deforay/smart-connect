<?php

namespace Application\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Zend\Debug\Debug;



class ResourcesTable extends AbstractTableGateway
{

    protected $table = 'dash_resources';
    protected $adapter;

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function fetchAllResourceMap()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($this->adapter);
        $resourceQuery = $sql->select()->from('dash_resources')
            ->order('display_name');
        $resourceQueryStr = $sql->buildSqlString($resourceQuery);
        return $dbAdapter->query($resourceQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }
}
