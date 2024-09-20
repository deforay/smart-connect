<?php

namespace Application\Model;

use Laminas\Db\Sql\Sql;
use Laminas\Db\Adapter\Adapter;
use Application\Service\CommonService;
use Laminas\Db\TableGateway\AbstractTableGateway;


class ArtCodeTable extends AbstractTableGateway
{

    protected $table = 'r_vl_art_regimen';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function fetchAllCurrentRegimen()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rQuery = $sql->select()->from(array('r' => 'r_vl_art_regimen'));
        $rQueryStr = $sql->buildSqlString($rQuery);
        return $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }
    public function insertOrUpdate($arrayData)
    {
        return CommonService::upsert($this->adapter, $this->table, $arrayData);
    }
}
