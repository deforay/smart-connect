<?php

namespace Application\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
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
class LocationDetailsTable extends AbstractTableGateway
{

    protected $table = 'geographical_divisions';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }
    public function fetchLocationDetails($mappedFacilities = null)
    {


        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->quantifier(\Laminas\Db\Sql\Select::QUANTIFIER_DISTINCT)
            ->from(array('l' => 'geographical_divisions'))
            ->join(array('f' => 'facility_details'), 'f.facility_state=l.geo_id', array())
            ->join(array('ft' => 'facility_type'), 'ft.facility_type_id=f.facility_type')
            ->where('ft.facility_type_name="clinic"')
            ->where(array('geo_parent' => 0));
        if ($mappedFacilities != null) {
            $sQuery = $sQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $sQueryStr = $sql->buildSqlString($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $rResult;
    }

    public function fetchDistrictList($locationId)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))->where(array('geo_parent' => $locationId));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $rResult;
    }

    public function fetchDistrictListByIds($locationId)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))->where(array('geo_parent IN(' . implode(",", $locationId) . ')'));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $rResult;
    }

    public function fetchAllDistrictsList($mappedFacilities = null)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()
            ->quantifier(\Laminas\Db\Sql\Select::QUANTIFIER_DISTINCT)
            ->from(array('l' => 'geographical_divisions'))
            ->join(array('f' => 'facility_details'), 'f.facility_district=l.geo_id', array())
            ->join(array('ft' => 'facility_type'), 'ft.facility_type_id=f.facility_type')
            ->where('ft.facility_type_name="clinic"')
            ->where(array("geo_parent <> 0"));
        if ($mappedFacilities != null) {
            $sQuery = $sQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $sQueryStr = $sql->buildSqlString($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $rResult;
    }
    public function insertOrUpdate($arrayData)
    {
        $query = 'INSERT INTO `' . $this->table . '` (' . implode(',', array_keys($arrayData)) . ') VALUES (' . implode(',', array_fill(1, count($arrayData), '?')) . ') ON DUPLICATE KEY UPDATE ' . implode(' = ?,', array_keys($arrayData)) . ' = ?';
        $result =  $this->adapter->query($query, array_merge(array_values($arrayData), array_values($arrayData)));
        return $result->getGeneratedValue();
    }
}
