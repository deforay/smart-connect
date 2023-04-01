<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Application\Service\CommonService;

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
class FacilityTable extends AbstractTableGateway
{

    protected $table = 'facility_details';
    public $sm = null;
    public \Application\Service\CommonService $commonService;

    public function __construct(Adapter $adapter, $sm = null, $commonService)
    {
        $this->adapter = $adapter;
        $this->sm = $sm;
        $this->commonService = $commonService;
    }

    public function addFacility($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $locationDb = new LocationDetailsTable($dbAdapter);
        if (trim($params['facilityName'] != '')) {
            $facilityData = array(
                'facility_name' => $params['facilityName'],
                'facility_code' => $params['facilityCode'],
                'vlsm_instance_id' => 'vldashboard',
                'other_id' => $params['otherId'],
                'facility_emails' => $params['email'],
                'report_email' => $params['reportEmail'],
                'contact_person' => $params['contactPerson'],
                'facility_mobile_numbers' => $params['phoneNo'],
                'facility_state' => $params['state'],
                'facility_district' => $params['district'],
                'address' => $params['address'],
                'country' => $params['country'],
                'facility_hub_name' => $params['hubName'],
                'latitude' => $params['latitude'],
                'longitude' => $params['longitude'],
                'facility_type' => $params['facilityType'],
                'status' => 'active'
            );
            if (isset($params['provinceNew']) && trim($params['provinceNew']) != '') {
                $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))
                    ->where(array('l.geo_name' => trim($params['provinceNew']), 'l.geo_parent' => 0));
                $sQuery = $sql->buildSqlString($sQuery);
                $sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if ($sQueryResult) {
                    $facilityData['facility_state'] = $sQueryResult['geo_id'];
                } else {
                    $locationDb->insert(array('geo_parent' => 0, 'geo_name' => trim($params['provinceNew'])));
                    $facilityData['facility_state'] = $locationDb->lastInsertValue;
                }
            }
            if (isset($params['districtNew']) && trim($params['districtNew']) != '') {
                $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))
                    ->where(array('l.geo_name' => trim($params['districtNew']), 'l.geo_parent' => $facilityData['facility_state']));
                $sQuery = $sql->buildSqlString($sQuery);
                $sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if ($sQueryResult) {
                    $facilityData['facility_district'] = $sQueryResult['geo_id'];
                } else {
                    $locationDb->insert(array('geo_parent' => $facilityData['facility_state'], 'geo_name' => trim($params['districtNew'])));
                    $facilityData['facility_district'] = $locationDb->lastInsertValue;
                }
            }
            $this->insert($facilityData);
            $facilityId = $this->lastInsertValue;
            if (isset($_FILES['logo']['name']) && $_FILES['logo']['name'] != '') {
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility")) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility");
                }
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId)) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId);
                }
                $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['logo']['name'], PATHINFO_EXTENSION));
                $fName = str_replace(" ", "", $params['facilityName']);
                $imageName = $fName .  "." . $extension;
                if (move_uploaded_file($_FILES["logo"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . $imageName)) {
                    $imageData = array('facility_logo' => $imageName);
                    $this->update($imageData, array("facility_id" => $lastInsertedId));
                }
            }
        }
        return $facilityId;
    }

    public function fetchAllFacility($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('facility_code', 'facility_name', 'facility_type_name', 'status');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('ft' => 'facility_type'), "ft.facility_type_id=f.facility_type");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iTotal = $this->select()->count();
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        $buttText = $this->commonService->translate('Edit');
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['facility_code'];
            $row[] = ucwords($aRow['facility_name']);
            $row[] = ucwords($aRow['facility_type_name']);
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="edit/' . base64_encode($aRow['facility_id']) . '" class="btn green" style="margin-right: 2px;" title="' . $buttText . '"><i class="fa fa-pencil"> ' . $buttText . '</i></a>';
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchFacility($facilityId)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('f' => 'facility_details'))->where(array('facility_id' => $facilityId));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $rResult;
    }

    public function updateFacility($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $locationDb = new LocationDetailsTable($dbAdapter);
        if (trim($params['facilityName'] != '')) {
            $facilityData = array(
                'facility_name' => $params['facilityName'],
                'facility_code' => $params['facilityCode'],
                'other_id' => $params['otherId'],
                'facility_emails' => $params['email'],
                'report_email' => $params['reportEmail'],
                'contact_person' => $params['contactPerson'],
                'facility_mobile_numbers' => $params['phoneNo'],
                'facility_state' => $params['state'],
                'facility_district' => $params['district'],
                'address' => $params['address'],
                'country' => $params['country'],
                'facility_hub_name' => $params['hubName'],
                'latitude' => $params['latitude'],
                'longitude' => $params['longitude'],
                'facility_type' => $params['facilityType'],
                'status' => $params['status']
            );
            if (isset($params['provinceNew']) && trim($params['provinceNew']) != '') {
                $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))
                    ->where(array('l.geo_name' => trim($params['provinceNew']), 'l.geo_parent' => 0));
                $sQuery = $sql->buildSqlString($sQuery);
                $sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if ($sQueryResult) {
                    $facilityData['facility_state'] = $sQueryResult['geo_id'];
                } else {
                    $locationDb->insert(array('geo_parent' => 0, 'geo_name' => trim($params['provinceNew'])));
                    $facilityData['facility_state'] = $locationDb->lastInsertValue;
                }
            }
            if (isset($params['districtNew']) && trim($params['districtNew']) != '') {
                $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))
                    ->where(array('l.geo_name' => trim($params['districtNew']), 'l.geo_parent' => $facilityData['facility_state']));
                $sQuery = $sql->buildSqlString($sQuery);
                $sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if ($sQueryResult) {
                    $facilityData['facility_district'] = $sQueryResult['geo_id'];
                } else {
                    $locationDb->insert(array('geo_parent' => $facilityData['facility_state'], 'geo_name' => trim($params['districtNew'])));
                    $facilityData['facility_district'] = $locationDb->lastInsertValue;
                }
            }
            $this->update($facilityData, array('facility_id' => base64_decode($params['facilityId'])));
            $facilityId = base64_decode($params['facilityId']);
            if (isset($param['existLogo']) && trim($param['existLogo']) == '') {
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . $params['removedLogo'])) {
                    unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . $params['removedLogo']);
                    $imageData = array('facility_logo' => '');
                    $result = $this->update($imageData, array("facility_id" => $facilityId));
                }
            }
            if (isset($_FILES['logo']['name']) && $_FILES['logo']['name'] != '') {
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility")) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility");
                }
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId)) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId);
                }
                $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['logo']['name'], PATHINFO_EXTENSION));
                $fName = str_replace(" ", "", $params['facilityName']);
                $imageName = $fName .  "." . $extension;
                if (move_uploaded_file($_FILES["logo"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . $imageName)) {
                    $imageData = array('facility_logo' => $imageName);
                    $this->update($imageData, array("facility_id" => $facilityId));
                }
            }
        }
        return $facilityId;
    }

    public function saveFacility($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        if (!isset($params['vlsm_instance_id']) || $params['vlsm_instance_id'] == '') {
            $params['vlsm_instance_id'] = 'mozambiquedisaopenldr';
        }

        if (!isset($params['facility_name']) || $params['facility_name'] == '') {
            $params['facility_name'] = $params['facility_code'];
        }


        $fQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->where('f.facility_code=?', $params['facility_code'])
            ->where('f.vlsm_instance_id=?', $params['vlsm_instance_id']);

        $sQueryStr = $sql->buildSqlString($sQuery);

        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if (count($rResult) > 0) {
            return true;
        } else {
            $newData = array(
                'vlsm_instance_id' => $params['vlsm_instance_id'],
                'facility_name' => $params['facility_name'],
                'facility_code' => $params['facility_code'],
                'facility_state' => $params['facility_province'],
                'facility_country' => $params['facility_country'],
                'latitude' => $params['facility_latitude'],
                'longitude' => $params['facility_longitude'],
                'status' => 'active'
            );

            $this->insert($newData);
            return $this->lastInsertValue;
        }
    }

    public function fetchAllLabName($mappedFacilities)
    {

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('ft' => 'facility_type'), 'ft.facility_type_id=f.facility_type')
            ->join(array('lp' => 'geographical_divisions'), 'lp.geo_id=f.facility_state', array())
            ->join(array('ld' => 'geographical_divisions'), 'ld.geo_id=f.facility_district', array())
            ->where('ft.facility_type_name="Viral Load Lab"');
        if ($mappedFacilities != null) {
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $fQueryStr = $sql->buildSqlString($fQuery);
        // print_r($fQueryStr);die;
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }

    public function fetchAllClinicName($mappedFacilities)
    {

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('ft' => 'facility_type'), 'ft.facility_type_id=f.facility_type')
            ->join(array('lp' => 'geographical_divisions'), 'lp.geo_id=f.facility_state', array())
            ->join(array('ld' => 'geographical_divisions'), 'ld.geo_id=f.facility_district', array())
            ->where('ft.facility_type_name="clinic"')
            ->order('f.facility_name ASC');
        if ($mappedFacilities != null) {
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $fQueryStr = $sql->buildSqlString($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }

    public function fetchAllHubName()
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('ft' => 'facility_type'), 'ft.facility_type_id=f.facility_type')
            ->where('ft.facility_type_name="hub"');
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array();
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $fQueryStr = $sql->buildSqlString($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }

    public function fetchRoleFacilities($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('ft' => 'facility_type'), 'ft.facility_type_id=f.facility_type');
        if (isset($params['role']) && $params['role'] == 2) {
            $fQuery = $fQuery->where('f.facility_type=2');
        } else if (isset($params['role']) && $params['role'] == 3) {
            $fQuery = $fQuery->where('f.facility_type IN (1,4)');
        } else if (isset($params['role']) && $params['role'] == 4) {
            $fQuery = $fQuery->where('f.facility_type=3');
        }
        $fQueryStr = $sql->buildSqlString($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }

    public function fetchSampleTestedFacilityInfo($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        //set filter labs
        if (isset($params['fromSrc']) && $params['fromSrc'] == 'tested-lab') {
            $params['labs'] = array();
            $testedLabQuery = $sql->select()->from(array('f' => 'facility_details'))
                ->columns(array('facility_id', 'facility_name'))
                ->order('facility_name asc');
            if (isset($params['labNames']) && count($params['labNames']) > 0) {
                $testedLabQuery = $testedLabQuery->where('f.facility_name IN ("' . implode('", "', $params['labNames']) . '")');
            } //default redirect else case
            $testedLabQueryStr = $sql->buildSqlString($testedLabQuery);
            $testedLabResult = $dbAdapter->query($testedLabQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($testedLabResult as $testedLab) {
                $params['labs'][] = $testedLab['facility_id'];
            }
        } else if (isset($params['fromSrc']) && $params['fromSrc'] == 'sample-volume') {
            $params['labs'] = array();
            $volumeLabQuery = $sql->select()->from(array('f' => 'facility_details'))
                ->columns(array('facility_id', 'facility_name'))
                ->order('facility_name asc');
            if (isset($params['labCodes']) && count($params['labCodes']) > 0) {
                $volumeLabQuery = $volumeLabQuery->where('f.facility_code IN ("' . implode('", "', $params['labCodes']) . '")');
            } //default redirect else case
            $volumeLabQueryStr = $sql->buildSqlString($volumeLabQuery);
            $volumeLabResult = $dbAdapter->query($volumeLabQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($volumeLabResult as $volumeLab) {
                $params['labs'][] = $volumeLab['facility_id'];
            }
        }
        $facilityInfo = array();
        //set accessible provinces
        $provinceQuery = $sql->select()->from(array('l_d' => 'geographical_divisions'))
            ->columns(array('geo_id', 'geo_name'))
            ->where(array('geo_parent' => 0))
            ->order('geo_name asc');
        if ($logincontainer->role != 1) {
            $provinceQuery = $provinceQuery->where('l_d.geo_id IN ("' . implode('", "', $logincontainer->provinces) . '")');
        }
        $provinceQueryStr = $sql->buildSqlString($provinceQuery);
        $facilityInfo['provinces'] = $dbAdapter->query($provinceQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //set selected provinces
        $facilityInfo['selectedProvinces'] = array();
        $labProvinces = array();
        if (isset($params['labs']) && count($params['labs']) > 0) {
            $labProvinceQuery = $sql->select()->from(array('l_d' => 'geographical_divisions'))
                ->columns(array('geo_id', 'geo_name'))
                ->join(array('f' => 'facility_details'), 'f.facility_state=l_d.geo_id', array())
                ->where(array('geo_parent' => 0))
                ->group('f.facility_state')
                ->order('geo_name asc');
            $labProvinceQuery = $labProvinceQuery->where('f.facility_id IN ("' . implode('", "', $params['labs']) . '")');
            $labProvinceQueryStr = $sql->buildSqlString($labProvinceQuery);
            $facilityInfo['selectedProvinces'] = $dbAdapter->query($labProvinceQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($facilityInfo['selectedProvinces'] as $province) {
                $labProvinces[] = $province['geo_id'];
            }
        }
        //set accessible province districts
        $provinceDistrictQuery = $sql->select()->from(array('l_d' => 'geographical_divisions'))
            ->columns(array('geo_id', 'geo_name'))
            ->where('geo_parent != 0')
            ->order('geo_name asc');
        if (isset($labProvinces) && count($labProvinces) > 0) {
            $provinceDistrictQuery = $provinceDistrictQuery->where('l_d.geo_parent IN ("' . implode('", "', $labProvinces) . '")');
        } else {
            if ($logincontainer->role != 1) {
                $provinceDistrictQuery = $provinceDistrictQuery->where('l_d.geo_id IN ("' . implode('", "', $logincontainer->districts) . '")');
            }
        }
        $provinceDistrictQueryStr = $sql->buildSqlString($provinceDistrictQuery);
        $facilityInfo['provinceDistricts'] = $dbAdapter->query($provinceDistrictQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //set lab districts
        $facilityInfo['labDistricts'] = array();
        $labDistricts = array();
        if (isset($params['labs']) && count($params['labs']) > 0) {
            $labDistrictQuery = $sql->select()->from(array('f' => 'facility_details'))
                ->columns(array('facility_district'))
                ->group('f.facility_district');
            $labDistrictQuery = $labDistrictQuery->where('f.facility_id IN ("' . implode('", "', $params['labs']) . '")');
            $labDistrictQueryStr = $sql->buildSqlString($labDistrictQuery);
            $facilityInfo['labDistricts'] = $dbAdapter->query($labDistrictQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($facilityInfo['labDistricts'] as $district) {
                $labDistricts[] = $district['facility_district'];
            }
        }
        //set accessible district labs
        $labQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->columns(array('facility_id', 'facility_name', 'facility_code'))
            ->where(array('f.facility_type' => 2))
            ->order('facility_name asc');
        if (isset($labDistricts) && count($labDistricts) > 0) {
            $labQuery = $labQuery->where('f.facility_district IN ("' . implode('", "', $labDistricts) . '")');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $labQuery = $labQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
        }
        $labQueryStr = $sql->buildSqlString($labQuery);
        $facilityInfo['labs'] = $dbAdapter->query($labQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //set accessible district clinics
        $clinicQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->columns(array('facility_id', 'facility_name'))
            ->where('f.facility_type IN ("1","4")')
            ->order('facility_name asc');
        if (isset($labDistricts) && count($labDistricts) > 0) {
            $clinicQuery = $clinicQuery->where('f.facility_district IN ("' . implode('", "', $labDistricts) . '")');
        } else {
            if ($logincontainer->role != 1) {
                $mappedDistricts = (isset($logincontainer->districts) && count($logincontainer->districts) > 0) ? $logincontainer->districts : array(0);
                $clinicQuery = $clinicQuery->where('f.facility_district IN ("' . implode('", "', $mappedDistricts) . '")');
            }
        }
        $clinicQueryStr = $sql->buildSqlString($clinicQuery);
        $facilityInfo['clinics'] = $dbAdapter->query($clinicQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //print_r($facilityInfo);die;
        return $facilityInfo;
    }

    public function fetchSampleTestedLocationInfo($params)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $locationInfo = array();
        $provinceDistricts = array();
        if ($params['fromSrc'] == 'provinces') {
            //set province districts
            $provinceDistrictQuery = $sql->select()->from(array('l_d' => 'geographical_divisions'))
                ->columns(array('geo_id', 'geo_name'))
                ->where('geo_parent != 0')
                ->order('geo_name asc');
            if ($logincontainer->role != 1) {
                if (isset($params['provinces']) && count($params['provinces']) > 0) {
                    $provinceDistrictQuery = $provinceDistrictQuery->where('l_d.geo_parent IN ("' . implode('", "', $params['provinces']) . '") AND l_d.geo_id IN ("' . implode('", "', $logincontainer->districts) . '")');
                } else {
                    $provinceDistrictQuery = $provinceDistrictQuery->where('l_d.geo_id IN ("' . implode('", "', $logincontainer->districts) . '")');
                }
            } else {
                if (isset($params['provinces']) && count($params['provinces']) > 0) {
                    $provinceDistrictQuery = $provinceDistrictQuery->where('l_d.geo_parent IN ("' . implode('", "', $params['provinces']) . '")');
                }
            }
            $provinceDistrictQueryStr = $sql->buildSqlString($provinceDistrictQuery);
            $locationInfo['provinceDistricts'] = $dbAdapter->query($provinceDistrictQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            foreach ($locationInfo['provinceDistricts'] as $district) {
                $provinceDistricts[] = $district['geo_id'];
            }
        } else {
            $provinceDistricts = $params['districts'];
        }
        //set province district labs
        $labQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->columns(array('facility_id', 'facility_name'))
            ->where(array('f.facility_type' => 2))
            ->order('facility_name asc');
        if (isset($provinceDistricts) && count($provinceDistricts) > 0) {
            $labQuery = $labQuery->where('f.facility_district IN ("' . implode('", "', $provinceDistricts) . '")');
        } else if (isset($params['provinces']) && count($params['provinces']) > 0) {
            $labQuery = $labQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
        } else {
            if ($logincontainer->role != 1) {
                $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
                $labQuery = $labQuery->where('f.facility_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
            }
        }
        $labQueryStr = $sql->buildSqlString($labQuery);
        $locationInfo['labs'] = $dbAdapter->query($labQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        //set province district clinics
        $clinicQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->columns(array('facility_id', 'facility_name'))
            ->where('f.facility_type IN ("1","4")')
            ->order('facility_name asc');
        if (isset($provinceDistricts) && count($provinceDistricts) > 0) {
            $clinicQuery = $clinicQuery->where('f.facility_district IN ("' . implode('", "', $provinceDistricts) . '")');
        } else if (isset($params['provinces']) && count($params['provinces']) > 0) {
            $clinicQuery = $clinicQuery->where('f.facility_state IN ("' . implode('", "', $params['provinces']) . '")');
        } else {
            if ($logincontainer->role != 1) {
                $mappedDistricts = (isset($logincontainer->districts) && count($logincontainer->districts) > 0) ? $logincontainer->districts : array(0);
                $clinicQuery = $clinicQuery->where('f.facility_district IN ("' . implode('", "', array_values(array_filter($mappedDistricts))) . '")');
            }
        }
        $clinicQueryStr = $sql->buildSqlString($clinicQuery);
        //echo $clinicQueryStr;die;
        $locationInfo['clinics'] = $dbAdapter->query($clinicQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $locationInfo;
    }

    public function fatchLocationInfoByName($name)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $locationQuery = $sql->select()->from(array('l_d' => 'geographical_divisions'))->where(array('l_d.geo_name' => $name));
        $locationQueryStr = $sql->buildSqlString($locationQuery);
        return $dbAdapter->query($locationQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
    }

    public function fatchFacilityInfoByName($name)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $facilityQuery = $sql->select()->from(array('f' => 'facility_details'))->where(array('f.facility_name' => $name));
        $facilityQueryStr = $sql->buildSqlString($facilityQuery);
        return $dbAdapter->query($facilityQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
    }

    public function fetchFacilityListByDistrict($districtId, $facilityType = 1)
    {
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('f' => 'facility_details'));
        if(!empty($districtId)){
            $sQuery = $sQuery->where(array('facility_district IN(' . implode(",",$districtId) . ')'));
        }
        if(!empty($facilityType)){
            $sQuery = $sQuery->where("f.facility_type = $facilityType");
        }
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : array(0);
            $facilities = implode('", "', array_values(array_filter($mappedFacilities)));
            $sQuery = $sQuery->where('f.facility_id IN ("' . $facilities . '")');
        }
        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function fetchAllFacilitiesInApi()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->columns(array('facility_id', 'facility_name', 'facility_code'))
            ->join(array('lp' => 'geographical_divisions'), 'lp.geo_id=f.facility_state', array('state_name' => 'geo_name'))
            ->join(array('ld' => 'geographical_divisions'), 'ld.geo_id=f.facility_district', array('district_name' => 'geo_name'))
            ->order('facility_name asc');
        $sQueryStr = $sql->buildSqlString($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if (count($rResult) > 0) {
            $result['status'] = '200';
            $result['result'] = $rResult;
        } else {
            $result['status'] = '200';
            $result['result'] = [];
        }
        return $result;
    }
    public function insertOrUpdate($arrayData)
    {
        $query = 'INSERT INTO `' . $this->table . '` (' . implode(',', array_keys($arrayData)) . ') VALUES (' . implode(',', array_fill(1, count($arrayData), '?')) . ') ON DUPLICATE KEY UPDATE ' . implode(' = ?,', array_keys($arrayData)) . ' = ?';

        $result =  $this->adapter->query($query, array_merge(array_values($arrayData), array_values($arrayData)));

        return $result->getGeneratedValue();
    }
}
