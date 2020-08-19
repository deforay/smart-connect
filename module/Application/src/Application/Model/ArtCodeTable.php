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

    protected $table = 'r_art_code_details';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    public function fetchAllCurrentRegimen(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rQuery = $sql->select()->from(array('r'=>'r_art_code_details'));
        $rQueryStr = $sql->buildSqlString($rQuery);
        $rResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $rResult;
    }
}
