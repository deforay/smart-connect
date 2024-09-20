<?php

namespace Application\Model;

use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Adapter\Adapter;
use Application\Service\CommonService;
use Laminas\Db\TableGateway\AbstractTableGateway;


class LocationDetailsTable extends AbstractTableGateway
{

    protected $table = 'geographical_divisions';
    protected $adapter;

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }
    public function fetchLocationDetails($mappedFacilities = null)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->quantifier(Select::QUANTIFIER_DISTINCT)
            ->from(array('l' => 'geographical_divisions'))
            ->join(array('f' => 'facility_details'), 'f.facility_state_id=l.geo_id', [])
            ->where('f.facility_type IN (1,3)')
            ->where(array('geo_parent' => 0));
        if ($mappedFacilities != null) {
            $sQuery = $sQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $sQueryStr = $sql->buildSqlString($sQuery);
        //error_log($sQueryStr);
        return $dbAdapter->query($sQueryStr, Adapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function fetchDistrictList($locationId)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))->where(array('geo_parent' => $locationId));
        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, Adapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function fetchDistrictListByIds($locationId)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))->where(array('geo_parent IN(' . implode(",", $locationId) . ')'));
        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, Adapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function fetchAllDistrictsList($mappedFacilities = null)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()
            ->quantifier(Select::QUANTIFIER_DISTINCT)
            ->from(array('l' => 'geographical_divisions'))
            ->join(array('f' => 'facility_details'), 'f.facility_district_id=l.geo_id', array())
            //->join(array('ft' => 'facility_type'), 'ft.facility_type_id=f.facility_type')
            ->where('f.facility_type IN (1,3)')
            ->where(array("geo_parent > 0"));
        if ($mappedFacilities != null) {
            $sQuery = $sQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, Adapter::QUERY_MODE_EXECUTE)->toArray();
    }
    public function insertOrUpdate($arrayData)
    {
        return CommonService::upsert($this->adapter, $this->table, $arrayData);
    }
}
