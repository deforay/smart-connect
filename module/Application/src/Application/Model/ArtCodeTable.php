<?php

namespace Application\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\AbstractTableGateway;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Countries
 *
 * @author amit
 */
class ArtCodeTable extends AbstractTableGateway {

    protected $table = 'r_vl_art_regimen';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    public function fetchAllCurrentRegimen(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rQuery = $sql->select()->from(array('r'=>'r_vl_art_regimen'));
        $rQueryStr = $sql->buildSqlString($rQuery);
        $rResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $rResult;
    }
    public function insertOrUpdate($arrayData)
    {
        $query = 'INSERT INTO `' . $this->table . '` (' . implode(',', array_keys($arrayData)) . ') VALUES (' . implode(',', array_fill(1, count($arrayData), '?')) . ') ON DUPLICATE KEY UPDATE ' . implode(' = ?,', array_keys($arrayData)) . ' = ?';
        $result =  $this->adapter->query($query, array_merge(array_values($arrayData), array_values($arrayData)));
        return $result->getGeneratedValue();
    }
}
