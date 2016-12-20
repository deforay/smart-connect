<?php

namespace Application\Model;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Zend\Db\TableGateway\AbstractTableGateway;

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
class FacilityTable extends AbstractTableGateway {

    protected $table = 'facility_details';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    public function fetchAllLabName()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('ft'=>'facility_types'),'ft.facility_type_id=f.facility_type')
                        ->where('ft.facility_type_name="Lab"');
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
    public function fetchAllClinicName()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('ft'=>'facility_types'),'ft.facility_type_id=f.facility_type')
                        ->where('ft.facility_type_name="Clinic"');
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
}
