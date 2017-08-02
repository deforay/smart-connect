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
    
    public function addFacility($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $locationDb = new LocationDetailsTable($dbAdapter);
        if(trim($params['facilityName']!='')){
            $facilityData=array('facility_name'=>$params['facilityName'],
                        'facility_code'=>$params['facilityCode'],
                        'vlsm_instance_id'=>'mozambiquedisaopenldr',
                        'other_id'=>$params['otherId'],
                        'facility_emails'=>$params['email'],
                        'report_email'=>$params['reportEmail'],
                        'contact_person'=>$params['contactPerson'],
                        'facility_mobile_numbers'=>$params['phoneNo'],
                        'address'=>$params['address'],
                        'country'=>$params['country'],
                        'facility_hub_name'=>$params['hubName'],
                        'latitude'=>$params['latitude'],
                        'longitude'=>$params['longitude'],
                        'facility_type'=>$params['facilityType'],
                        'status'=>'active',
                    );
			if(isset($params['provinceNew']) && $params['provinceNew']!='')
			{
				$sQuery = $sql->select()->from(array('l'=>'location_details'))
							->where(array('l.location_name'=>trim($params['provinceNew'])));
				$sQuery = $sql->getSqlStringForSqlObject($sQuery);
				$sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				if($sQueryResult){
					$facilityData['facility_state'] = $sQueryResult['location_id'];
				}else{
					$locationDb->insert(array('parent_location'=>0,'location_name'=>trim($params['provinceNew'])));
					$facilityData['facility_state'] = $locationDb->lastInsertValue;
				}
			}
			if(isset($params['districtNew']) && $params['districtNew']!='')
			{
				$sQuery = $sql->select()->from(array('l'=>'location_details'))
							->where(array('l.location_name'=>trim($params['districtNew']),'l.parent_location'=>$facilityData['facility_state']));
				$sQuery = $sql->getSqlStringForSqlObject($sQuery);
				$sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				if($sQueryResult){
					$facilityData['facility_district'] = $sQueryResult['location_id'];
				}else{
					$locationDb->insert(array('parent_location'=>$facilityData['facility_state'],'location_name'=>trim($params['districtNew'])));
					$facilityData['facility_district'] = $locationDb->lastInsertValue;
				}
			}
			
            $this->insert($facilityData);
            $facilityId = $this->lastInsertValue;
			//\Zend\Debug\Debug::dump($_FILES);die;
            if (isset($_FILES['logo']['name']) && $_FILES['logo']['name'] != '') {
				if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility")) {
					mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility");
				}
				if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility". DIRECTORY_SEPARATOR . $facilityId)) {
					mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility". DIRECTORY_SEPARATOR . $facilityId);
				}
				$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['logo']['name'], PATHINFO_EXTENSION));
				$fName = str_replace(" ","",$params['facilityName']);
				$imageName = $fName .  ".". $extension;
				if (move_uploaded_file($_FILES["logo"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . $imageName)) {
					$imageData = array('facility_logo' => $imageName);
					$this->update($imageData, array("facility_id" => $lastInsertedId));
				}
			}
        }
      return $facilityId;
    }
    
    public function fetchAllFacility($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('facility_code','facility_name','facility_type_name','status');

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
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
        $sQuery = $sql->select()->from(array('f'=>'facility_details'))
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

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
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
        
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['facility_code'];
            $row[] = ucwords($aRow['facility_name']);
            $row[] = ucwords($aRow['facility_type_name']);
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="edit/' . base64_encode($aRow['facility_id']) . '" class="btn green" style="margin-right: 2px;" title="Edit"><i class="fa fa-pencil"> Edit</i></a>';
            $output['aaData'][] = $row;
        }
        return $output;
    }
	
	public function fetchFacility($facilityId)
	{
		$dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('f'=>'facility_details'))->where(array('facility_id'=>$facilityId));
		$sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
		$rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
		return $rResult;
	}
	public function updateFacility($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $locationDb = new LocationDetailsTable($dbAdapter);
        if(trim($params['facilityName']!='')){
            $facilityData=array('facility_name'=>$params['facilityName'],
                        'facility_code'=>$params['facilityCode'],
                        'vlsm_instance_id'=>'mozambiquedisaopenldr',
                        'other_id'=>$params['otherId'],
                        'facility_emails'=>$params['email'],
                        'report_email'=>$params['reportEmail'],
                        'contact_person'=>$params['contactPerson'],
                        'facility_mobile_numbers'=>$params['phoneNo'],
                        'address'=>$params['address'],
                        'country'=>$params['country'],
                        'facility_hub_name'=>$params['hubName'],
                        'latitude'=>$params['latitude'],
                        'longitude'=>$params['longitude'],
                        'facility_type'=>$params['facilityType'],
                        'status'=>$params['status'],
                    );
			if(isset($params['provinceNew']) && $params['provinceNew']!='')
			{
				$sQuery = $sql->select()->from(array('l'=>'location_details'))
							->where(array('l.location_name'=>trim($params['provinceNew'])));
				$sQuery = $sql->getSqlStringForSqlObject($sQuery);
				$sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				if($sQueryResult){
					$facilityData['facility_state'] = $sQueryResult['location_id'];
				}else{
					$locationDb->insert(array('parent_location'=>0,'location_name'=>trim($params['provinceNew'])));
					$facilityData['facility_state'] = $locationDb->lastInsertValue;
				}
			}
			if(isset($params['districtNew']) && $params['districtNew']!='')
			{
				$sQuery = $sql->select()->from(array('l'=>'location_details'))
							->where(array('l.location_name'=>trim($params['districtNew']),'l.parent_location'=>$facilityData['facility_state']));
				$sQuery = $sql->getSqlStringForSqlObject($sQuery);
				$sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
				if($sQueryResult){
					$facilityData['facility_district'] = $sQueryResult['location_id'];
				}else{
					$locationDb->insert(array('parent_location'=>$facilityData['facility_state'],'location_name'=>trim($params['districtNew'])));
					$facilityData['facility_district'] = $locationDb->lastInsertValue;
				}
			}
			
            $this->update($facilityData,array('facility_id'=>base64_decode($params['facilityId'])));
            $facilityId = base64_decode($params['facilityId']);
			if (isset($param['existLogo']) && $param['existLogo'] == '') {
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
				if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility". DIRECTORY_SEPARATOR . $facilityId)) {
					mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility". DIRECTORY_SEPARATOR . $facilityId);
				}
				$extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['logo']['name'], PATHINFO_EXTENSION));
				$fName = str_replace(" ","",$params['facilityName']);
				$imageName = $fName .  ".". $extension;
				if (move_uploaded_file($_FILES["logo"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility" . DIRECTORY_SEPARATOR . $facilityId . DIRECTORY_SEPARATOR . $imageName)) {
					$imageData = array('facility_logo' => $imageName);
					$this->update($imageData, array("facility_id" => $facilityId));
				}
			}
        }
      return $facilityId;
    }
    
    public function saveFacility($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        if(!isset($params['vlsm_instance_id']) || $params['vlsm_instance_id'] == ''){
            $params['vlsm_instance_id'] = 'mozambiquedisaopenldr';
        }
        
        if(!isset($params['facility_name']) || $params['facility_name'] == ''){
            $params['facility_name'] = $params['facility_code'];
        }
        
        
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                      ->where('f.facility_code=?',$params['facility_code'])
                      ->where('f.vlsm_instance_id=?',$params['vlsm_instance_id']);
        
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);

        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($rResult) > 0){
            return true;
        }else{
            $newData=array(
                           'vlsm_instance_id'=>$params['vlsm_instance_id'],
                           'facility_name'=>$params['facility_name'],
                           'facility_code'=>$params['facility_code'],
                           'facility_state'=>$params['facility_province'],
                           'facility_country'=>$params['facility_country'],
                           'latitude'=>$params['facility_latitude'],
                           'longitude'=>$params['facility_longitude'],
                           'status'=>'active'
                           );

            $this->insert($newData);
            return $this->lastInsertValue;
        }
    }

    public function fetchAllLabName(){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                      ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type')
                      ->where('ft.facility_type_name="Viral Load Lab"');
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
    
    public function fetchAllClinicName(){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type')
                        ->where('ft.facility_type_name="clinic"');
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
    
    public function fetchAllHubName(){
        $logincontainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                        ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type')
                        ->where('ft.facility_type_name="hub"');
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:0;
            $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
    
    public function fetchRoleFacilities($params){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from(array('f'=>'facility_details'))
                      ->join(array('ft'=>'facility_type'),'ft.facility_type_id=f.facility_type');
        if(isset($params['role']) && $params['role'] == 2){
           $fQuery = $fQuery->where('f.facility_type=2');
        }else if(isset($params['role']) && $params['role'] == 3){
           $fQuery = $fQuery->where('f.facility_type IN (1,4)');
        }else if(isset($params['role']) && $params['role'] == 4){
           $fQuery = $fQuery->where('f.facility_type=3');  
        }
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $facilityResult;
    }
}
