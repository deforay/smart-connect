<?php

namespace Eid\Service;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Expression;
use Application\Service\CommonService;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use Zend\Debug\Debug;

class EidSampleService
{

    public $sm = null;

    public function __construct($sm)
    {
        $this->sm = $sm;
    }

    public function getServiceManager()
    {
        return $this->sm;
    }

    //get all sample types
    public function getSampleType()
    {
        $sampleDb = $this->sm->get('SampleTypeTable');
        return $sampleDb->fetchAllSampleType();
    }

    public function getStats($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->getStats($params);
    }

    public function getMonthlySampleCount($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->getMonthlySampleCount($params);
    }
    public function getMonthlySampleCountByLabs($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->getMonthlySampleCountByLabs($params);
    }

    public function saveFileFromVlsmAPI(){
        $apiData = array();
        $common = new CommonService();
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        $facilityDb = $this->sm->get('FacilityTable');
        $facilityTypeDb = $this->sm->get('FacilityTypeTable');
        $testStatusDb = $this->sm->get('SampleStatusTable');
        $locationDb = $this->sm->get('LocationDetailsTable');
        $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');
        
        $fileName = $_FILES['eidFile']['name'];
        $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = $ranNumber . "." . $extension;

        if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
            mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "uploads",0777);
        }
        if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid")) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid",0777);
        }

        $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid" . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($pathname)) {
            if (move_uploaded_file($_FILES['eidFile']['tmp_name'], $pathname)) {
                // $apiData = (array)json_decode(file_get_contents($pathname));
                $apiData = \JsonMachine\JsonMachine::fromFile($pathname);
            }
        }
        if($apiData !== false){
            foreach($apiData as $rowData){
                // Debug::dump($rowData);die;
                foreach($rowData as $row){
                    // Debug::dump($row['vlsm_instance_id']);die;
                    if (trim($row['sample_code']) != '' && trim($row['vlsm_instance_id']) != '') {
                        $sampleCode = trim($row['sample_code']);
                        $instanceCode = trim($row['vlsm_instance_id']);
    
                        $sampleCollectionDate = (trim($row['sample_collection_date']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_collection_date']))) : null);
                        $sampleReceivedAtLab = (trim($row['sample_registered_at_lab']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_registered_at_lab']))) : null);
                        // $dateOfInitiationOfRegimen = (trim($row['date_of_initiation_of_current_regimen']) != '' ? trim(date('Y-m-d H:i', strtotime($row['date_of_initiation_of_current_regimen']))) : null);
                        $resultApprovedDateTime = (trim($row['result_approved_datetime']) != '' ? trim(date('Y-m-d H:i', strtotime($row['result_approved_datetime']))) : null);
                        $sampleTestedDateTime = (trim($row['sample_tested_datetime']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_tested_datetime']))) : null);
    
    
    
                        foreach($row as $index=>$value){                
                            if($index == 'status_id'){
                                break;
                            } else{
                                if($index != 'eid_id'){
                                    $data[$index] = $value;
                                }
                            }
                        }
                        $data['sample_code']                = $sampleCode;
                        $data['sample_collection_date']     = $sampleCollectionDate;
                        $data['sample_registered_at_lab']   = $sampleReceivedAtLab;
                        $data['result_approved_datetime']   = $resultApprovedDateTime;
                        $data['sample_tested_datetime']     = $sampleTestedDateTime;
    
                        $facilityData = array(
                            'vlsm_instance_id'          => trim($row['vlsm_instance_id']),
                            'facility_name'             => trim($row['facility_name']),
                            'facility_code'             => trim($row['facility_code']),
                            'facility_mobile_numbers'   => trim($row['facility_mobile_numbers']),
                            'address'                   => trim($row['address']),
                            'facility_hub_name'         => trim($row['facility_hub_name']),
                            'contact_person'            => trim($row['contact_person']),
                            'report_email'              => trim($row['report_email']),
                            'country'                   => trim($row['country']),
                            'facility_state'            => trim($row['facility_state']),
                            'facility_district'         => trim($row['facility_district']),
                            'longitude'                 => trim($row['longitude']),
                            'latitude'                  => trim($row['latitude']),
                            'status'                    => trim($row['facility_status'])
                        );
                        if (trim($row['facility_state']) != '') {
                            $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($row['facility_state']), 0);
                            if ($sQueryResult) {
                                $facilityData['facility_state'] = $sQueryResult['location_id'];
                            } else {
                                $locationDb->insert(array('parent_location' => 0, 'location_name' => trim($row['facility_state'])));
                                $facilityData['facility_state'] = $locationDb->lastInsertValue;
                            }
                        }
                        if (trim($row['facility_district']) != '') {
                            $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($row['facility_district']), $facilityData['facility_state']);
                            if ($sQueryResult) {
                                $facilityData['facility_district'] = $sQueryResult['location_id'];
                            } else {
                                $locationDb->insert(array('parent_location' => $facilityData['facility_state'], 'location_name' => trim($row['facility_district'])));
                                $facilityData['facility_district'] = $locationDb->lastInsertValue;
                            }
                        }
                        //check facility type
                        if (trim($row['facility_type']) != '') {
                            $facilityTypeDataResult = $this->checkFacilityTypeDetails(trim($row['facility_type_name']));
                            if ($facilityTypeDataResult) {
                                $facilityData['facility_type'] = $facilityTypeDataResult['facility_type_id'];
                            } else {
                                $facilityTypeDb->insert(array('facility_type_name' => trim($row['facility_type_name'])));
                                $facilityData['facility_type'] = $facilityTypeDb->lastInsertValue;
                            }
                        }
    
                        //check clinic details
                        if (trim($row['facility_name']) != '') {
                            $facilityDataResult = $this->checkFacilityDetails(trim($row['facility_name']));
                            if ($facilityDataResult) {
                                $facilityDb->update($facilityData, array('facility_id' => $facilityDataResult['facility_id']));
                                $data['facility_id'] = $facilityDataResult['facility_id'];
                            } else {
                                $facilityDb->insert($facilityData);
                                $data['facility_id'] = $facilityDb->lastInsertValue;
                            }
                        } else {
                            $data['facility_id'] = NULL;
                        }
    
                        $labData = array(
                            'vlsm_instance_id'          => trim($row['vlsm_instance_id']),
                            'facility_name'             => trim($row['labName']),
                            'facility_code'             => trim($row['labCode']),
                            'facility_mobile_numbers'   => trim($row['labPhone']),
                            'address'                   => trim($row['labAddress']),
                            'facility_hub_name'         => trim($row['labHub']),
                            'contact_person'            => trim($row['labContactPerson']),
                            'report_email'              => trim($row['labReportMail']),
                            'country'                   => trim($row['labCountry']),
                            'facility_state'            => trim($row['labState']),
                            'facility_district'         => trim($row['labDistrict']),
                            'longitude'                 => trim($row['labLongitude']),
                            'latitude'                  => trim($row['labLatitude']),
                            'status'                    => trim($row['labFacilityStatus'])
                        );
                        if (trim($row['labState']) != '') {
                            $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($row['labState']), 0);
                            if ($sQueryResult) {
                                $labData['facility_state'] = $sQueryResult['location_id'];
                            } else {
                                $locationDb->insert(array('parent_location' => 0, 'location_name' => trim($row['labState'])));
                                $labData['facility_state'] = $locationDb->lastInsertValue;
                            }
                        }
                        if (trim($row['labDistrict']) != '') {
                            $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($row['labDistrict']), $labData['facility_state']);
                            if ($sQueryResult) {
                                $labData['facility_district'] = $sQueryResult['location_id'];
                            } else {
                                $locationDb->insert(array('parent_location' => $labData['facility_state'], 'location_name' => trim($row['labDistrict'])));
                                $labData['facility_district'] = $locationDb->lastInsertValue;
                            }
                        }
                        //check lab type
                        if (trim($row['labFacilityTypeName']) != '') {
                            $labTypeDataResult = $this->checkFacilityTypeDetails(trim($row['labFacilityTypeName']));
                            if ($labTypeDataResult) {
                                $labData['facility_type'] = $labTypeDataResult['facility_type_id'];
                            } else {
                                $facilityTypeDb->insert(array('facility_type_name' => trim($row['labFacilityTypeName'])));
                                $labData['facility_type'] = $facilityTypeDb->lastInsertValue;
                            }
                        }
    
                        //check lab details
                        if (trim($row['labName']) != '') {
                            $labDataResult = $this->checkFacilityDetails(trim($row['labName']));
                            if ($labDataResult) {
                                $facilityDb->update($labData, array('facility_id' => $labDataResult['facility_id']));
                                $data['lab_id'] = $labDataResult['facility_id'];
                            } else {
                                $facilityDb->insert($labData);
                                $data['lab_id'] = $facilityDb->lastInsertValue;
                            }
                        } else {
                            $data['lab_id'] = 0;
                        }
                        //check testing reason
                        if (trim($row['status_name']) != '') {
                            $sampleStatusResult = $this->checkSampleStatus(trim($row['status_name']));
                            if ($sampleStatusResult) {
                                $data['result_status'] = $sampleStatusResult['status_id'];
                            } else {
                                $testStatusDb->insert(array('status_name' => trim($row['status_name'])));
                                $data['result_status'] = $testStatusDb->lastInsertValue;
                            }
                        } else {
                            $data['result_status'] = 6;
                        }
                        
                        //check sample rejection reason
                        if (trim($row['reason_for_sample_rejection']) != '') {
                            $sampleRejectionReason = $this->checkSampleRejectionReason(trim($row['reason_for_sample_rejection']));
                            if ($sampleRejectionReason) {
                                $sampleRjtReasonDb->update(array('rejection_reason_name' => trim($row['reason_for_sample_rejection']), 'rejection_reason_status' => trim($row['rejection_reason_status'])), array('rejection_reason_id' => $sampleRejectionReason['rejection_reason_id']));
                                $data['reason_for_sample_rejection'] = $sampleRejectionReason['rejection_reason_id'];
                            } else {
                                $sampleRjtReasonDb->insert(array('rejection_reason_name' => trim($row['reason_for_sample_rejection']), 'rejection_reason_status' => trim($row['rejection_reason_status'])));
                                $data['reason_for_sample_rejection'] = $sampleRjtReasonDb->lastInsertValue;
                            }
                        } else {
                            $data['reason_for_sample_rejection'] = NULL;
                        }
                        // Debug::dump($data);die;
                        //check existing sample code
                        $sampleCode = $this->checkSampleCode($sampleCode, $instanceCode);
                        if ($sampleCode) {
                            //sample data update
                            $sampleDb->update($data, array('eid_id' => $sampleCode['eid_id']));
                        } else {
                            //sample data insert
                            $sampleDb->insert($data);
                        }
                    }
                }
            }
            //remove directory
            // $common->removeDirectory($pathname);
            return array(
                'status'    => 'success',
                'message'   => 'Uploaded successfully',
            );
        }
        return array(
            'status'    => 'fail',
            'message'   => 'Uploaded failed',
        );
        
    }

    public function checkSampleCode($sampleCode, $instanceCode, $dashTable = 'dash_eid_form')
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from($dashTable)->where(array('sample_code LIKE "%'.$sampleCode.'%"', 'vlsm_instance_id' => $instanceCode));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }

    public function checkFacilityStateDistrictDetails($location, $parent)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('l' => 'location_details'))
            ->where(array('l.parent_location' => $parent, 'l.location_name' => trim($location)));
        $sQuery = $sql->buildSqlString($sQuery);
        $sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sQueryResult;
    }

    public function checkFacilityDetails($clinicName)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from('facility_details')->where(array('facility_name' => $clinicName));
        $fQueryStr = $sql->buildSqlString($fQuery);
        $fResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $fResult;
    }
    public function checkFacilityTypeDetails($facilityTypeName)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from('facility_type')->where(array('facility_type_name' => $facilityTypeName));
        $fQueryStr = $sql->buildSqlString($fQuery);
        $fResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $fResult;
    }
    public function checkTestingReson($testingReson)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $tQuery = $sql->select()->from('r_vl_test_reasons')->where(array('test_reason_name' => $testingReson));
        $tQueryStr = $sql->buildSqlString($tQuery);
        $tResult = $dbAdapter->query($tQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $tResult;
    }
    public function checkSampleStatus($testingStatus)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_sample_status')->where(array('status_name' => $testingStatus));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    public function checkSampleType($sampleType)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_eid_sample_type')->where(array('sample_name' => $sampleType));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    public function checkSampleRejectionReason($rejectReasonName)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_sample_rejection_reasons')->where(array('rejection_reason_name' => $rejectReasonName));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }

    public function getAllLabName()
    {
        $logincontainer = new Container('credo');
        $mappedFacilities = null;
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : null;
        }
        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllLabName($mappedFacilities);
    }

    //get all Lab Name
    public function getAllClinicName()
    {

        $logincontainer = new Container('credo');
        $mappedFacilities = null;
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : null;
        }

        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllClinicName($mappedFacilities);
    }
    //get all province name
    public function getAllProvinceList()
    {

        $logincontainer = new Container('credo');
        $mappedFacilities = null;
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : null;
        }

        $locationDb = $this->sm->get('LocationDetailsTable');
        return $locationDb->fetchLocationDetails($mappedFacilities);
    }
    public function getAllDistrictList()
    {

        $logincontainer = new Container('credo');
        $mappedFacilities = null;
        if ($logincontainer->role != 1) {
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : null;
        }
        $locationDb = $this->sm->get('LocationDetailsTable');
        return $locationDb->fetchAllDistrictsList();
    }
}
