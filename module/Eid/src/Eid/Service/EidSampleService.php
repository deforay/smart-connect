<?php

namespace Eid\Service;

use Laminas\Session\Container;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Application\Service\CommonService;

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
        $sampleDb = $this->sm->get('EidSampleTypeTable');
        return $sampleDb->fetchAllSampleType();
    }

    public function getStats($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->getStats($params);
    }

    public function getPocStats($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->getPocStats($params);
    }

    public function getTestFailedByTestingPlatform($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->getTestFailedByTestingPlatform($params);
    }

    public function getMonthlySampleCount($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->getMonthlySampleCount($params);
    }

    public function getMonthlySampleCountByLabs($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->getMonthlySampleCountByLabs($params);
    }


    public function getLabTurnAroundTime($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchLabTurnAroundTime($params);
    }

    public function fetchLabPerformance($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchLabPerformance($params);
    }

    // END OF LABS DASHBOARD

    public function saveFileFromVlsmAPIV2()
    {
        $apiData = array();
        $apiTrackDb = $this->sm->get('DashApiReceiverStatsTable');

        $this->config = $this->sm->get('Config');
        $input = $this->config['db']['dsn'];
        preg_match('~=(.*?);~', $input, $output);
        $dbname = $output[1];
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');

        $fileName = $_FILES['eidFile']['name'];
        $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = $ranNumber . "." . $extension;

        if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
            mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "uploads", 0777);
        }
        if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid")) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid", 0777);
        }

        $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid" . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($pathname)) {
            if (move_uploaded_file($_FILES['eidFile']['tmp_name'], $pathname)) {
                $apiData = json_decode(file_get_contents($pathname), true);
                //$apiData = \JsonMachine\JsonMachine::fromFile($pathname);
            }
        }

        // ob_start();
        // var_dump($apiData);
        // error_log(ob_get_clean());


        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $dbname . "' AND table_name='dash_eid_form'";
        $sResult = $dbAdapter->query($allColumns, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $columnList = array_map('current', $sResult);

        $removeKeys = array(
            'eid_id'
        );

        $columnList = array_diff($columnList, $removeKeys);
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');


        $numRows = 0;
        foreach ($apiData['data'] as $rowData) {


            $data = array();
            foreach ($columnList as $colName) {
                if (isset($rowData[$colName])) {
                    $data[$colName] = $rowData[$colName];
                } else {
                    $data[$colName] = null;
                }
            }

            // ob_start();
            // var_dump($data);
            // error_log(ob_get_clean());
            // exit(0);

            $sampleCode = trim($data['sample_code']);
            $instanceCode = trim($data['vlsm_instance_id']);
            //check existing sample code
            $sampleCode = $this->checkSampleCode($sampleCode, $instanceCode);
            if ($sampleCode) {
                //sample data update
                $numRows += $sampleDb->update($data, array('eid_id' => $sampleCode['eid_id']));
            } else {
                //sample data insert
                $numRows += $sampleDb->insert($data);
            }
        }

        $common = new CommonService();
        if(count($apiData['data'])  == $numRows){
            $status = "success";
        } else if((count($apiData['data']) - $numRows) != 0){
            $status = "partial";
        } else if($numRows == 0){
            $status = 'failed';
        }
        $apiTrackData = array(
            'tracking_id'                   => $apiData['timestamp'],
            'received_on'                   => $common->getDateTime(),
            'number_of_records_received'    => count($apiData['data']),
            'number_of_records_processed'   => $numRows,
            'source'                        => 'Sync V2 EID',
            'status'                        => $status
        );
        $trackResult = $apiTrackDb->select(array('tracking_id' => $apiData['timestamp']))->current();
        if($trackResult){
            $apiTrackDb->update($apiTrackData, array('api_id' => $trackResult['api_id']));
        } else{
            $apiTrackDb->insert($apiTrackData);
        }

        return array(
            'status'    => 'success',
            'message'   => $numRows . ' uploaded successfully',
        );
    }

    public function saveFileFromVlsmAPIV1()
    {
        $apiData = array();
        $common = new CommonService();
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        $facilityDb = $this->sm->get('FacilityTable');
        $facilityTypeDb = $this->sm->get('FacilityTypeTable');
        $locationDb = $this->sm->get('LocationDetailsTable');
        $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');

        $fileName = $_FILES['eidFile']['name'];
        $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = $ranNumber . "." . $extension;

        if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
            mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "uploads", 0777);
        }
        if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid")) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid", 0777);
        }

        $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid" . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($pathname)) {
            if (move_uploaded_file($_FILES['eidFile']['tmp_name'], $pathname)) {
                // $apiData = (array)json_decode(file_get_contents($pathname));
                $apiData = \JsonMachine\JsonMachine::fromFile($pathname);
            }
        }
        if ($apiData !== false) {
            foreach ($apiData as $rowData) {
                // Debug::dump($rowData);die;
                foreach ($rowData as $row) {
                    // Debug::dump($row['vlsm_instance_id']);die;
                    if (trim($row['sample_code']) != '' && trim($row['vlsm_instance_id']) != '') {
                        $sampleCode = trim($row['sample_code']);
                        $instanceCode = trim($row['vlsm_instance_id']);

                        $sampleCollectionDate = (trim($row['sample_collection_date']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_collection_date']))) : null);
                        $sampleReceivedAtLab = (trim($row['sample_registered_at_lab']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_registered_at_lab']))) : null);
                        // $dateOfInitiationOfRegimen = (trim($row['date_of_initiation_of_current_regimen']) != '' ? trim(date('Y-m-d H:i', strtotime($row['date_of_initiation_of_current_regimen']))) : null);
                        $resultApprovedDateTime = (trim($row['result_approved_datetime']) != '' ? trim(date('Y-m-d H:i', strtotime($row['result_approved_datetime']))) : null);
                        $sampleTestedDateTime = (trim($row['sample_tested_datetime']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_tested_datetime']))) : null);



                        foreach ($row as $index => $value) {
                            if ($index == 'status_id') {
                                break;
                            } else {
                                if ($index != 'eid_id') {
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
                            $data['result_status'] = $this->checkSampleStatus(trim($row['status_name']));
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
        $sQuery = $sql->select()->from($dashTable)->where(array('sample_code' => $sampleCode, 'vlsm_instance_id' => $instanceCode));
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
        $tQuery = $sql->select()->from('r_eid_test_reasons')->where(array('test_reason_name' => $testingReson));
        $tQueryStr = $sql->buildSqlString($tQuery);
        $tResult = $dbAdapter->query($tQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $tResult;
    }
    public function checkSampleStatus($testingStatus)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $testStatusDb = $this->sm->get('SampleStatusTable');
        $sQuery = $sql->select()->from('r_sample_status')->where(array('status_name' => $testingStatus));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if ($sResult) {
            $resultStatus = $sResult['status_id'];
        } else {
            $testStatusDb->insert(array('status_name' => trim($testingStatus)));
            $resultStatus = $testStatusDb->lastInsertValue;
        }
        return $resultStatus;
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

    public function checkTestReason($reasonName)
    {

        if (empty(trim($reasonName))) return null;
        
        $testReasonDb = $this->sm->get('TestReasonTable');
        $sResult = $testReasonDb->select(array('test_reason_name' => $reasonName))->toArray();
        if ($sResult) {
            $testReasonDb->update(array('test_reason_name' => trim($reasonName)), array('test_reason_id' => $sResult['test_reason_id']));
            return $sResult['test_reason_id'];
        } else {
            $testReasonDb->insert(array('test_reason_name' => trim($reasonName), 'test_reason_status' => 'active', 'updated_datetime' => new Expression('NOW()')));
            return $testReasonDb->lastInsertValue;
        }
    }

    public function checkSampleRejectionReason($rejectReasonName)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_eid_sample_rejection_reasons')->where(array('rejection_reason_name' => $rejectReasonName));
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

    public function getEidFormDetail()
    {
        $sampleDb = $this->sm->get('SampleTableWithoutCache');
        return $sampleDb->fetchEidFormDetail();
    }
    // Get all test reason name for eid
    public function getAllTestReasonName()
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $tQuery = $sql->select()->from('r_eid_test_reasons');
        $tQueryStr = $sql->buildSqlString($tQuery);
        $tResult = $dbAdapter->query($tQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $tResult;
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
    // get all distrcit name
    public function getAllDistrictList()
    {
        $locationDb = $this->sm->get('LocationDetailsTable');
        return $locationDb->fetchAllDistrictsList();
    }

    ////////////////////////////////////////
    /////////*** Turnaround Time ***///////
    ///////////////////////////////////////

    public function getTATbyProvince($labs, $startDate, $endDate)
    {
        // set_time_limit(10000);
        $result = array();
        $sampleDb = $this->sm->get('EidSampleTable');
        $resultSet = $sampleDb->getTATbyProvince($labs, $startDate, $endDate);
        foreach ($resultSet as $key) {
            $result[] = array(
                "facility"           => $key['location_name'],
                "facility_id"        => $key['location_id'],
                "category"           => 0,
                "collect_receive"    => $key['Collection_Receive'],
                "receive_register"   => $key['Receive_Register'],
                "register_analysis"  => $key['Register_Analysis'],
                "analysis_authorise" => $key['Analysis_Authorise'],
                "total" => $key['total']
            );
        }
        return $result;
    }

    public function getTATbyDistrict($labs, $startDate, $endDate)
    {
        // set_time_limit(10000);
        $result = array();
        $sampleDb = $this->sm->get('EidSampleTable');
        $resultSet = $sampleDb->getTATbyDistrict($labs, $startDate, $endDate);
        foreach ($resultSet as $key) {
            $result[] = array(
                "facility"           => $key['location_name'],
                "facility_id"        => $key['location_id'],
                "category"           => 0,
                "collect_receive"    => $key['Collection_Receive'],
                "receive_register"   => $key['Receive_Register'],
                "register_analysis"  => $key['Register_Analysis'],
                "analysis_authorise" => $key['Analysis_Authorise'],
                "total" => $key['total']
            );
        }
        return $result;
    }

    public function getTATbyClinic($labs, $startDate, $endDate)
    {
        // set_time_limit(10000);
        $result = array();
        $time = array();
        $sampleDb = $this->sm->get('EidSampleTable');
        $time = $sampleDb->getTATbyClinic($labs, $startDate, $endDate);
        foreach ($resultSet as $key) {
            $result[] = array(
                "facility"           => $key['location_name'],
                "facility_id"        => $key['location_id'],
                "category"           => 0,
                "collect_receive"    => $key['Collection_Receive'],
                "receive_register"   => $key['Receive_Register'],
                "register_analysis"  => $key['Register_Analysis'],
                "analysis_authorise" => $key['Analysis_Authorise'],
                "total" => $key['total']
            );
        }
        return $result;
    }

    ////////////////////////////////////////
    ////////*** Turnaround Time ***////////
    ///////////////////////////////////////

    //get all Hub Name
    public function getAllHubName()
    {
        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllHubName();
    }

    //get all Current Regimen
    public function getAllCurrentRegimen()
    {
        $artCodeDb = $this->sm->get('ArtCodeTable');
        return $artCodeDb->fetchAllCurrentRegimen();
    }

    public function getProvinceWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchProvinceWiseResultAwaitedDrillDown($params);
    }

    public function getLabWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchLabWiseResultAwaitedDrillDown($params);
    }

    public function getDistrictWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchDistrictWiseResultAwaitedDrillDown($params);
    }

    public function getClinicWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchClinicWiseResultAwaitedDrillDown($params);
    }

    public function getFilterSampleResultAwaitedDetails($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchFilterSampleResultAwaitedDetails($parameters);
    }

    public function generateResultsAwaitedSampleExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if (isset($queryContainer->resultsAwaitedQuery)) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->resultsAwaitedQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && count($sResult) > 0) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    // $cacheMethod = \PhpOffice\PhpSpreadsheet\Collection\CellsFactory::cache_to_phpTemp;
                    // $cacheSettings = array('memoryCacheSize' => '80MB');
                    // \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    foreach ($sResult as $aRow) {
                        $displayCollectionDate = $common->humanDateFormat($aRow['collectionDate']);
                        $displayReceivedDate = $common->humanDateFormat($aRow['receivedDate']);
                        $row = array();
                        $row[] = $aRow['sample_code'];
                        $row[] = $displayCollectionDate;
                        $row[] = $aRow['facilityCode'] . ' - ' . ucwords($aRow['facilityName']);
                        $row[] = (isset($aRow['sample_name'])) ? ucwords($aRow['sample_name']) : '';
                        $row[] = ucwords($aRow['labName']);
                        $row[] = $displayReceivedDate;
                        $output[] = $row;
                    }
                    $styleArray = array(
                        'font' => array(
                            'bold' => true,
                        ),
                        'alignment' => array(
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ),
                        )
                    );
                    $borderStyle = array(
                        'alignment' => array(
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ),
                        )
                    );

                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Sample ID'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Collection Date'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Facility'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Lab'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Sample Received at Lab'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $sheet->getStyle('A1')->applyFromArray($styleArray);
                    $sheet->getStyle('B1')->applyFromArray($styleArray);
                    $sheet->getStyle('C1')->applyFromArray($styleArray);
                    $sheet->getStyle('D1')->applyFromArray($styleArray);
                    $sheet->getStyle('E1')->applyFromArray($styleArray);
                    $sheet->getStyle('F1')->applyFromArray($styleArray);

                    $currentRow = 2;
                    foreach ($output as $rowData) {
                        $colNo = 0;
                        foreach ($rowData as $field => $value) {
                            if (!isset($value)) {
                                $value = "";
                            }
                            if ($colNo > 5) {
                                break;
                            }
                            if (is_numeric($value)) {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                            } else {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            }
                            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                            $sheet->getDefaultRowDimension()->setRowHeight(20);
                            $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
                            $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getAlignment()->setWrapText(true);
                            $colNo++;
                        }
                        $currentRow++;
                    }
                    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
                    $filename = 'RESULTS-AWAITED--' . date('d-M-Y-H-i-s') . '.xlsx';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                } else {
                    return "";
                }
            } catch (Exception $exc) {
                error_log("RESULTS-AWAITED--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
            }
        } else {
            return "";
        }
    }

    /* Drill down page service */

    public function getSampleDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchSampleDetails($params);
    }

    public function getBarSampleDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchBarSampleDetails($params);
    }

    public function getLabFilterSampleDetails($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchLabFilterSampleDetails($parameters);
    }

    public function getFilterSampleDetails($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchFilterSampleDetails($parameters);
    }

    public function getFilterSampleTatDetails($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchFilterSampleTatDetails($parameters);
    }

    public function getLabSampleDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchLabSampleDetails($params);
    }

    public function getLabBarSampleDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchLabBarSampleDetails($params);
    }

    public function getIncompleteSampleDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchIncompleteSampleDetails($params);
    }

    public function getIncompleteBarSampleDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchIncompleteBarSampleDetails($params);
    }

    public function getSampleInfo($params, $dashTable = 'dash_eid_form')
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl' => $dashTable))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name', 'facility_code', 'facility_logo'), 'left')
            ->join(array('l_s' => 'location_details'), 'l_s.location_id=f.facility_state', array('provinceName' => 'location_name'), 'left')
            ->join(array('l_d' => 'location_details'), 'l_d.location_id=f.facility_district', array('districtName' => 'location_name'), 'left')
            ->join(array('rs' => 'r_eid_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'), 'left')
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array('labName' => 'facility_name'), 'left')
            ->join(array('u' => 'user_details'), 'u.user_id=vl.result_approved_by', array('approvedBy' => 'user_name'), 'left')
            ->join(array('ru' => 'user_details'), 'u.user_id=vl.request_created_by', array('requestCreated' => 'user_name'), 'left')
            ->join(array('rtr' => 'r_eid_test_reasons'), 'rtr.test_reason_id=vl.reason_for_eid_test', array('test_reason_name'), 'left')
            ->join(array('r_r_r' => 'r_eid_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection', array('rejection_reason_name'), 'left')
            ->where(array('vl.eid_id' => $params['id']));
        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function generateResultExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if (isset($queryContainer->resultQuery)) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->resultQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && count($sResult) > 0) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    // $cacheMethod = \PhpOffice\PhpSpreadsheet\Collection\CellsFactory::cache_to_phpTemp;
                    // $cacheSettings = array('memoryCacheSize' => '80MB');
                    // \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    foreach ($sResult as $aRow) {
                        $row = array();
                        $sampleCollectionDate = '';
                        $sampleTestedDate = '';
                        if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != NULL && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                            $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
                        }
                        if (isset($aRow['sampleTestingDate']) && $aRow['sampleTestingDate'] != NULL && trim($aRow['sampleTestingDate']) != "" && $aRow['sampleTestingDate'] != '0000-00-00') {
                            $sampleTestedDate = $common->humanDateFormat($aRow['sampleTestingDate']);
                        }
                        $row[] = $aRow['sample_code'];
                        $row[] = ucwords($aRow['facility_name']);
                        $row[] = $sampleCollectionDate;
                        $row[] = (isset($aRow['rejection_reason_name'])) ? ucwords($aRow['rejection_reason_name']) : '';
                        $row[] = $sampleTestedDate;
                        $row[] = ucwords($aRow['result']);
                        $output[] = $row;
                    }
                    $styleArray = array(
                        'font' => array(
                            'bold' => true,
                        ),
                        'alignment' => array(
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ),
                        )
                    );
                    $borderStyle = array(
                        'alignment' => array(
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ),
                        )
                    );

                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Sample ID'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Facility Name'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Date Collected'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Rejection Reason'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Date Tested'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Result'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $sheet->getStyle('A1')->applyFromArray($styleArray);
                    $sheet->getStyle('B1')->applyFromArray($styleArray);
                    $sheet->getStyle('C1')->applyFromArray($styleArray);
                    $sheet->getStyle('D1')->applyFromArray($styleArray);
                    $sheet->getStyle('E1')->applyFromArray($styleArray);
                    $sheet->getStyle('F1')->applyFromArray($styleArray);
                    $currentRow = 2;
                    $endColumn = 5;
                    foreach ($output as $rowData) {
                        $colNo = 0;
                        foreach ($rowData as $field => $value) {
                            if (!isset($value)) {
                                $value = "";
                            }
                            if ($colNo > $endColumn) {
                                break;
                            }
                            if (is_numeric($value)) {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                            } else {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            }
                            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                            $sheet->getDefaultRowDimension()->setRowHeight(20);
                            $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
                            $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getAlignment()->setWrapText(true);
                            $colNo++;
                        }
                        $currentRow++;
                    }
                    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
                    $filename = 'TEST-RESULT-REPORT--' . date('d-M-Y-H-i-s') . '.xlsx';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                } else {
                    return "";
                }
            } catch (Exception $exc) {
                error_log("TEST-RESULT-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
            }
        } else {
            return "";
        }
    }

    public function getVlOutComes($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->getVlOutComes($params);
    }

    public function generateLabTestedSampleExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if (isset($queryContainer->labTestedSampleQuery)) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->labTestedSampleQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && count($sResult) > 0) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    // $cacheMethod = \PhpOffice\PhpSpreadsheet\Collection\CellsFactory::cache_to_phpTemp;
                    // $cacheSettings = array('memoryCacheSize' => '80MB');
                    // \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    foreach ($sResult as $aRow) {
                        $row = array();
                        $sampleCollectionDate = '';
                        if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != null && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                            $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
                        }
                        $row[] = $sampleCollectionDate;
                        $row[] = $aRow['total_samples_received'];
                        $row[] = $aRow['total_samples_tested'];
                        $row[] = $aRow['total_samples_pending'];
                        $row[] = $aRow['suppressed_samples'];
                        $row[] = $aRow['not_suppressed_samples'];
                        $row[] = $aRow['rejected_samples'];
                        $row[] = ucwords($aRow['sample_name']);
                        $row[] = ucwords($aRow['facility_name']);
                        $output[] = $row;
                    }
                    $styleArray = array(
                        'font' => array(
                            'bold' => true,
                        ),
                        'alignment' => array(
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ),
                        )
                    );
                    $borderStyle = array(
                        'alignment' => array(
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ),
                        )
                    );

                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Date'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Samples Collected'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Samples Tested'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Samples Pending'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Suppressed'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Not Suppressed'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('G1', html_entity_decode($translator->translate('Samples Rejected'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('H1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('I1', html_entity_decode($translator->translate('Clinics'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $sheet->getStyle('A1')->applyFromArray($styleArray);
                    $sheet->getStyle('B1')->applyFromArray($styleArray);
                    $sheet->getStyle('C1')->applyFromArray($styleArray);
                    $sheet->getStyle('D1')->applyFromArray($styleArray);
                    $sheet->getStyle('E1')->applyFromArray($styleArray);
                    $sheet->getStyle('F1')->applyFromArray($styleArray);
                    $sheet->getStyle('G1')->applyFromArray($styleArray);
                    $sheet->getStyle('H1')->applyFromArray($styleArray);
                    $sheet->getStyle('I1')->applyFromArray($styleArray);

                    $currentRow = 2;
                    foreach ($output as $rowData) {
                        $colNo = 0;
                        foreach ($rowData as $field => $value) {
                            if (!isset($value)) {
                                $value = "";
                            }
                            if ($colNo > 8) {
                                break;
                            }
                            if (is_numeric($value)) {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                            } else {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            }
                            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                            $sheet->getDefaultRowDimension()->setRowHeight(20);
                            $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
                            $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getAlignment()->setWrapText(true);
                            $colNo++;
                        }
                        $currentRow++;
                    }
                    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
                    $filename = 'SAMPLE-TESTED-LAB-REPORT--' . date('d-M-Y-H-i-s') . '.xlsx';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                } else {
                    return "";
                }
            } catch (Exception $exc) {
                error_log("SAMPLE-TESTED-LAB-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
            }
        } else {
            return "";
        }
    }

    public function getEidOutcomesByAgeInLabsDetails($params)
    {
        $eidSampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $eidSampleDb->fetchEidOutcomesByAgeInLabsDetails($params);
    }

    public function getEidPositivityRateDetails($params)
    {
        $eidSampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $eidSampleDb->fetchEidPositivityRateDetails($params);
    }

    //clinic details start
    public function getOverallEidResult($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchOverallEidResult($params);
    }

    public function getViralLoadStatusBasedOnGender($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchViralLoadStatusBasedOnGender($params);
    }

    
    public function getClinicSampleTestedResultAgeGroupDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchClinicSampleTestedResultAgeGroupDetails($params);
    }
    
    public function fetchSampleTestedReason($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchSampleTestedReason($params);
    }
    
    public function getClinicSampleTestedResults($params, $sampleType)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchClinicSampleTestedResults($params, $sampleType);
    }

    public function getAllTestResults($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchAllTestResults($parameters);
    }

    public function generateHighVlSampleResultExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if (isset($queryContainer->resultQuery)) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $hQueryStr = $sql->buildSqlString($queryContainer->highVlSampleQuery);
                // echo ($hQueryStr);die;
                $sResult = $dbAdapter->query($hQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && count($sResult) > 0) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    // $cacheMethod = \PhpOffice\PhpSpreadsheet\Collection\CellsFactory::cache_to_phpTemp;
                    // $cacheSettings = array('memoryCacheSize' => '80MB');
                    // \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    $i = 1;
                    foreach ($sResult as $aRow) {
                        $row = array();
                        if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != NULL && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                            $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
                        }
                        if (isset($aRow['sample_received_at_vl_lab_datetime']) && $aRow['sample_received_at_vl_lab_datetime'] != NULL && trim($aRow['sample_received_at_vl_lab_datetime']) != "" && $aRow['sample_received_at_vl_lab_datetime'] != '0000-00-00') {
                            $requestDate = $common->humanDateFormat($aRow['sample_received_at_vl_lab_datetime']);
                        }
                        $row[] = $i;
                        $row[] = $aRow['sample_code'];
                        $row[] = ucwords($aRow['facility_name']);
                        $row[] = $aRow['facility_code'];
                        $row[] = $aRow['facilityDistrict'];
                        $row[] = $aRow['facilityState'];
                        $row[] = ucwords($aRow['first_name'] . " " . $aRow['last_name']);
                        $row[] = date('d-M-Y',strtotime($aRow['child_dob']));
                        $row[] = $aRow['child_age'];
                        $row[] = $aRow['child_gender'];
                        $row[] = $sampleCollectionDate;
                        $row[] = $aRow['sample_name'];
                        $row[] = $requestDate;
                        $row[] = $aRow['result'];
                        $row[] = $aRow['rejection_reason_name'];
                        $output[] = $row;
                        $i++;
                    }
                    $styleArray = array(
                        'font' => array(
                            'bold' => true,
                        ),
                        'alignment' => array(
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ),
                        )
                    );
                    $borderStyle = array(
                        'alignment' => array(
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ),
                        )
                    );

                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('No.'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Sample Code'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Health Facility Name'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Health Facility Code'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('District/County'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Province/State'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('H1', html_entity_decode($translator->translate('Patient Name'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('I1', html_entity_decode($translator->translate('Date of Birth'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('J1', html_entity_decode($translator->translate('Age'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('K1', html_entity_decode($translator->translate('Gender'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('L1', html_entity_decode($translator->translate('Date of Sample Collection'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('M1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('V1', html_entity_decode($translator->translate('Date Sample Received at Lab'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('X1', html_entity_decode($translator->translate('Result'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('Y1', html_entity_decode($translator->translate('Rejection Reason (if Rejected)'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $sheet->getStyle('A1')->applyFromArray($styleArray);
                    $sheet->getStyle('B1')->applyFromArray($styleArray);
                    $sheet->getStyle('C1')->applyFromArray($styleArray);
                    $sheet->getStyle('D1')->applyFromArray($styleArray);
                    $sheet->getStyle('E1')->applyFromArray($styleArray);
                    $sheet->getStyle('F1')->applyFromArray($styleArray);
                    $sheet->getStyle('G1')->applyFromArray($styleArray);
                    $sheet->getStyle('H1')->applyFromArray($styleArray);
                    $sheet->getStyle('I1')->applyFromArray($styleArray);
                    $sheet->getStyle('J1')->applyFromArray($styleArray);
                    $sheet->getStyle('K1')->applyFromArray($styleArray);
                    $sheet->getStyle('L1')->applyFromArray($styleArray);
                    $sheet->getStyle('M1')->applyFromArray($styleArray);
                    $sheet->getStyle('N1')->applyFromArray($styleArray);
                    $sheet->getStyle('O1')->applyFromArray($styleArray);
                    $sheet->getStyle('P1')->applyFromArray($styleArray);
                    $sheet->getStyle('Q1')->applyFromArray($styleArray);
                    $sheet->getStyle('R1')->applyFromArray($styleArray);
                    $sheet->getStyle('S1')->applyFromArray($styleArray);
                    $sheet->getStyle('T1')->applyFromArray($styleArray);
                    $sheet->getStyle('U1')->applyFromArray($styleArray);
                    $sheet->getStyle('V1')->applyFromArray($styleArray);
                    $sheet->getStyle('W1')->applyFromArray($styleArray);
                    $sheet->getStyle('X1')->applyFromArray($styleArray);
                    $sheet->getStyle('Y1')->applyFromArray($styleArray);

                    $currentRow = 2;
                    foreach ($output as $rowData) {
                        $colNo = 0;
                        foreach ($rowData as $field => $value) {
                            if (!isset($value)) {
                                $value = "";
                            }
                            if (is_numeric($value)) {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                            } else {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            }
                            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                            $sheet->getDefaultRowDimension()->setRowHeight(20);
                            $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
                            $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getAlignment()->setWrapText(true);
                            $colNo++;
                        }
                        $currentRow++;
                    }
                    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
                    $filename = 'HIGH-VL-SAMPLE-RESULT-REPORT--' . date('d-M-Y-H-i-s') . '.xlsx';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                } else {
                    return "";
                }
            } catch (Exception $exc) {
                error_log("HIGH-VL-SAMPLE-RESULT-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
            }
        } else {
            return "";
        }
    }

    public function generateSampleResultExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            if (isset($queryContainer->sampleResultQuery)) {
                try {
                    $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                    $sql = new Sql($dbAdapter);
                    $sQueryStr = $sql->buildSqlString($queryContainer->sampleResultQuery);
                    $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if (isset($sResult) && count($sResult) > 0) {
                        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                        // $cacheMethod = \PhpOffice\PhpSpreadsheet\Collection\CellsFactory::cache_to_phpTemp;
                        // $cacheSettings = array('memoryCacheSize' => '80MB');
                        // \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                        $sheet = $excel->getActiveSheet();
                        $output = array();
                        foreach ($sResult as $aRow) {
                            $row = array();
                            $row[] = ucwords($aRow['facility_name']);
                            $row[] = $aRow['total_samples_received'];
                            $row[] = $aRow['total_samples_tested'];
                            $row[] = $aRow['total_samples_pending'];
                            $row[] = $aRow['total_samples_positive'];
                            $row[] = $aRow['total_samples_negative'];
                            $row[] = $aRow['rejected_samples'];
                            $output[] = $row;
                        }
                        $styleArray = array(
                            'font' => array(
                                'bold' => true,
                            ),
                            'alignment' => array(
                                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                            ),
                            'borders' => array(
                                'outline' => array(
                                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                ),
                            )
                        );
                        $borderStyle = array(
                            'alignment' => array(
                                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                            ),
                            'borders' => array(
                                'outline' => array(
                                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                ),
                            )
                        );

                        $sheet->setCellValue('A1', html_entity_decode($translator->translate('Lab'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('B1', html_entity_decode($translator->translate('Samples Collected'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('C1', html_entity_decode($translator->translate('Samples Tested'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('D1', html_entity_decode($translator->translate('Samples Pending'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Positive'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Negative'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('G1', html_entity_decode($translator->translate('Samples Rejected'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                        $sheet->getStyle('A1')->applyFromArray($styleArray);
                        $sheet->getStyle('B1')->applyFromArray($styleArray);
                        $sheet->getStyle('C1')->applyFromArray($styleArray);
                        $sheet->getStyle('D1')->applyFromArray($styleArray);
                        $sheet->getStyle('E1')->applyFromArray($styleArray);
                        $sheet->getStyle('F1')->applyFromArray($styleArray);
                        $sheet->getStyle('G1')->applyFromArray($styleArray);

                        $currentRow = 2;
                        foreach ($output as $rowData) {
                            $colNo = 0;
                            foreach ($rowData as $field => $value) {
                                if (!isset($value)) {
                                    $value = "";
                                }
                                if ($colNo > 6) {
                                    break;
                                }
                                if (is_numeric($value)) {
                                    $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                } else {
                                    $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                }
                                $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                                $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                                $sheet->getDefaultRowDimension()->setRowHeight(20);
                                $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
                                $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getAlignment()->setWrapText(true);
                                $colNo++;
                            }
                            $currentRow++;
                        }
                        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
                        $filename = 'SAMPLE-TEST-RESULT-REPORT--' . date('d-M-Y-H-i-s') . '.xlsx';
                        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                        return $filename;
                    } else {
                        return "";
                    }
                } catch (Exception $exc) {
                    error_log("SAMPLE-TEST-RESULT-REPORT--" . $exc->getMessage());
                    error_log($exc->getTraceAsString());
                    return "";
                }
            } else {
                return "";
            }
        } else {
            return "";
        }
    }
    //clinic details end

    public function saveVLDataFromAPI($params)
    {
        $common = new CommonService();
        $sampleDb = $this->sm->get('SampleTableWithoutCache');
        $facilityDb = $this->sm->get('FacilityTable');
        $testStatusDb = $this->sm->get('SampleStatusTable');
        $sampleTypeDb = $this->sm->get('SampleTypeTable');
        $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');
        $provinceDb = $this->sm->get('ProvinceTable');
        $apiTrackDb = $this->sm->get('DashApiReceiverStatsTable');
        $userDb = $this->sm->get('UsersTable');
        $return = array();
        $params = json_decode($params, true);
        $config = $this->sm->get('Config');
        if (!empty($params)) {
            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "temporary", 0777);
            }
            if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "API-data-vl") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "API-data-vl")) {
                mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "API-data-vl", 0777);
            }
    
            $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "API-data-vl" . DIRECTORY_SEPARATOR . $params['timestamp'].'.json';
            if (!file_exists($pathname)) {
                $file = file_put_contents($pathname, json_encode($params));
                if (move_uploaded_file($pathname, $pathname)) {
                    // $apiData = file_put_contents($pathname);
                }
            }
            foreach ($params['data'] as $key => $row) {
                // Debug::dump($row);die;
                if (!empty(trim($row['sample_code'])) && trim($params['api_version']) == $config['defaults']['eid-api-version']) {
                    $sampleCode = trim($row['sample_code']);
                    $instanceCode = 'api-data';

                     // Check dublicate data
                     $province = $provinceDb->select(array('province_name' => $row['health_centre_province']))->current();
                     if(!$province){
                         $provinceDb->insert(array(
                             'province_name'     => $row['health_centre_province'],
                             'updated_datetime'  => $common->getDateTime()
                         ));
                         $province['province_id'] = $provinceDb->lastInsertValue;
                     }

                    $VLAnalysisResult = (float) $row['result_value_absolute_decimal'];
                    $DashVL_Abs = NULL;
                    $DashVL_AnalysisResult = NULL;

                    if ($row['result_value_copies'] == 'Target not Detected' || $row['result_value_copies'] == 'Target Not Detected' || strtolower($row['result_value_copies']) == 'target not detected' || strtolower($row['result_value_copies']) == 'tnd' || $row['result'] == 'Target not Detected' || $row['result'] == 'Target Not Detected' || strtolower($row['result']) == 'target not detected' || strtolower($row['result']) == 'tnd') {
                        $VLAnalysisResult = 20;
                    } else if ($row['result_value_copies'] == '< 20' || $row['result_value_copies'] == '<20' || $row['result_value'] == '< 20' || $row['result_value'] == '<20') {
                        $VLAnalysisResult = 20;
                    } else if ($row['result_value_copies'] == '< 40' || $row['result_value_copies'] == '<40' || $row['result_value'] == '< 40' || $row['result_value'] == '<40') {
                        $VLAnalysisResult = 40;
                    } else if ($row['result_value_copies'] == 'Suppressed' || $row['result_value'] == 'Suppressed') {
                        $VLAnalysisResult = 500;
                    } else if ($row['result_value_copies'] == 'Not Suppressed' || $row['result_value'] == 'Not Suppressed') {
                        $VLAnalysisResult = 1500;
                    } else if ($row['result_value_copies'] == 'Negative' || $row['result_value_copies'] == 'NEGAT' || $row['result_value'] == 'Negative' || $row['result_value'] == 'NEGAT') {
                        $VLAnalysisResult = 20;
                    } else if ($row['result_value_copies'] == 'Positive' || $row['result_value'] == 'Positive') {
                        $VLAnalysisResult = 1500;
                    }


                    if ($VLAnalysisResult == 'NULL' || $VLAnalysisResult == '' || $VLAnalysisResult == NULL) {
                        $DashVL_Abs = NULL;
                        $DashVL_AnalysisResult = NULL;
                    } else if ($VLAnalysisResult < 1000) {
                        $DashVL_AnalysisResult = 'Suppressed';
                        $DashVL_Abs = $VLAnalysisResult;
                    } else if ($VLAnalysisResult >= 1000) {
                        $DashVL_AnalysisResult = 'Not Suppressed';
                        $DashVL_Abs = $VLAnalysisResult;
                    }

                    
                    $sampleReceivedAtLab = ((trim($row['sample_received_date']) != '' && $row['sample_received_date'] != "") ? trim($row['sample_received_date']) : null);
                    $sampleTestedDateTime = ((trim($row['sample_tested_date']) != '' && $row['sample_tested_date'] != "") ? trim($row['sample_tested_date']) : null);
                    $sampleCollectionDate = ((trim($row['sample_collection_date']) != '' && $row['sample_collection_date'] != "") ? trim($row['sample_collection_date']) : null);
                    $dob = ((trim($row['patient_birth_date']) != '' && $row['patient_birth_date'] != "") ? trim($row['patient_birth_date']) : null);
                    $resultApprovedDateTime = ((trim($row['result_approved_datetime']) != '' && $row['result_approved_datetime'] != "") ? trim($row['result_approved_datetime']) : null);
                    $dateOfInitiationOfRegimen = ((trim($row['date_of_initiation_of_current_regimen']) != '' && $row['date_of_initiation_of_current_regimen'] != "") ? trim($row['date_of_initiation_of_current_regimen']) : null);
                    $sampleRegisteredAtLabDateTime = ((trim($row['sample_registered_at_lab']) != '' && $row['sample_registered_at_lab'] != "") ? trim($row['sample_registered_at_lab']) : null);
                    $resultPrinterDateTime = ((trim($row['result_printed_datetime']) != '' && $row['result_printed_datetime'] != "") ? trim($row['result_printed_datetime']) : null);

                    $data = array(
                        'sample_code'                           => $sampleCode,
                        'vlsm_instance_id'                      => $instanceCode,
                        'province_id'                           => (trim($province['province_id']) != '' ? trim($province['province_id']) : NULL),
                        'source'                                => '1',
                        'patient_art_no'                        => (trim($row['patient_art_no']) != '' ? trim($row['patient_art_no']) : NULL),
                        'patient_gender'                        => (trim($row['patient_gender']) != '' ? trim($row['patient_gender']) : NULL),
                        'patient_age_in_years'                  => (trim($row['patient_age_in_years']) != '' ? trim($row['patient_age_in_years']) : NULL),
                        'patient_mobile_number'                 => (trim($row['patient_mobile_number']) != '' ? trim($row['patient_mobile_number']) : NULL),
                        'patient_dob'                           => $dob,
                        'sample_collection_date'                => $sampleCollectionDate,
                        'sample_registered_at_lab'              => $sampleReceivedAtLab,
                        'result_printed_datetime'               => $resultPrinterDateTime,
                        'line_of_treatment'                     => (trim($row['line_of_treatment']) != '' ? trim($row['line_of_treatment']) : NULL),
                        'is_sample_rejected'                    => (trim($row['is_sample_rejected']) != '' ? strtolower($row['is_sample_rejected']) : NULL),
                        'is_patient_pregnant'                   => (trim($row['is_patient_pregnant']) != '' ? trim($row['is_patient_pregnant']) : NULL),
                        'is_patient_breastfeeding'              => (trim($row['is_patient_breastfeeding']) != '' ? trim($row['is_patient_breastfeeding']) : NULL),
                        'current_regimen'                       => (trim($row['current_regimen']) != '' ? trim($row['current_regimen']) : NULL),
                        'date_of_initiation_of_current_regimen' => $dateOfInitiationOfRegimen,
                        'arv_adherance_percentage'              => (trim($row['arv_adherance_percentage']) != '' ? trim($row['arv_adherance_percentage']) : NULL),
                        'is_adherance_poor'                     => (trim($row['is_adherance_poor']) != '' ? trim($row['is_adherance_poor']) : NULL),
                        'result_approved_datetime'              => $resultApprovedDateTime,
                        'sample_tested_datetime'                => $sampleTestedDateTime,
                        'vl_test_platform'                      => (trim($row['vl_test_platform']) != '' ? trim($row['vl_test_platform']) : NULL),
                        'result_value_log'                      => (trim($row['result_value_log']) != '' ? (float)($row['result_value_log']) : NULL),
                        'result_value_absolute'                 => (trim($row['result_value_absolute']) != '' ? trim($row['result_value_absolute']) : NULL),
                        'result_value_text'                     => (trim($row['result_value_copies']) != '' ? trim($row['result_value_copies']) : NULL),
                        'result_value_absolute_decimal'         => (trim($row['result_value_absolute_decimal']) != '' ? trim($row['result_value_absolute_decimal']) : NULL),
                        'result'                                => (trim($row['result_value']) != '' ? trim($row['result_value']) : NULL),
                        'tested_by'                             => (trim($row['tested_by']) != '' ? $userDb->checkExistUser($row['tested_by']) : NULL),
                        'result_approved_by'                    => (trim($row['result_approved_by']) != '' ? $userDb->checkExistUser($row['result_approved_by']) : NULL),
                        'DashVL_Abs'                            => $DashVL_Abs,
                        'DashVL_AnalysisResult'                 => $DashVL_AnalysisResult,
                        'sample_registered_at_lab'              => $sampleRegisteredAtLabDateTime
                    );


                    //check clinic details
                    if (isset($row['facility_name']) && trim($row['facility_name']) != '') {
                        $facilityDataResult = $this->checkFacilityDetails(trim($row['facility_name']));
                        if ($facilityDataResult) {
                            $data['facility_id'] = $facilityDataResult['facility_id'];
                        } else {
                            $facilityDb->insert(array(
                                'vlsm_instance_id'  => $instanceCode,
                                'facility_name'     => $row['facility_name'],
                                'facility_code'     => !empty($row['facility_name']) ? $row['facility_name'] : null,
                                'facility_type'     => '1',
                                'status'            => 'active'
                            ));
                            $data['facility_id'] = $facilityDb->lastInsertValue;
                        }
                    } else {
                        $data['facility_id'] = NULL;
                    }

                    //check lab details
                    $labDataResult = $this->checkFacilityDetails(trim($row['testing_lab_name']));
                    if ($labDataResult) {
                        $data['lab_id'] = $labDataResult['facility_id'];
                    } else {
                        $facilityDb->insert(array(
                            'vlsm_instance_id'  => $instanceCode,
                            'facility_name'     => $row['testing_lab_name'],
                            'facility_code'     => !empty($row['testing_lab_code']) ? $row['testing_lab_code'] : null,
                            'facility_type'     => '2',
                            'status'            => 'active'
                        ));
                        $data['lab_id'] = $facilityDb->lastInsertValue;
                    }

                    //check testing reason
                    if (trim($row['result_value_status']) != '') {
                        $sampleStatusResult = $this->checkSampleStatus(trim($row['result_value_status']));
                        if ($sampleStatusResult) {
                            $data['result_status'] = $sampleStatusResult['status_id'];
                        } else {
                            $testStatusDb->insert(array('status_name' => trim($row['result_value_status'])));
                            $data['result_status'] = $testStatusDb->lastInsertValue;
                        }
                    } else {
                        $data['result_status'] = 6;
                    }
                    //check sample type
                    if (trim($row['sample_type']) != '') {
                        $sampleType = $this->checkSampleType(trim($row['sample_type']));
                        if ($sampleType) {
                            $sampleTypeDb->update(array('sample_name' => trim($row['sample_type'])), array('sample_id' => $sampleType['sample_id']));
                            $data['sample_type'] = $sampleType['sample_id'];
                        } else {
                            $sampleTypeDb->insert(array('sample_name' => trim($row['sample_type']), 'status' => 'active'));
                            $data['sample_type'] = $sampleTypeDb->lastInsertValue;
                        }
                    } else {
                        $data['sample_type'] = NULL;
                    }

                    //check sample test reason
                    if (!empty(trim($row['reason_for_vl_testing']))) {
                        $data['reason_for_vl_testing'] =  $this->checkTestReason(trim($row['reason_for_vl_testing']));
                    } else {
                        $data['reason_for_vl_testing'] = NULL;
                    }

                    //check sample rejection reason
                    if (trim($row['rejection_reason_name']) != '') {
                        $sampleRejectionReason = $this->checkSampleRejectionReason(trim($row['rejection_reason_name']));
                        if ($sampleRejectionReason) {
                            $sampleRjtReasonDb->update(array('rejection_reason_name' => trim($row['rejection_reason_name'])), array('rejection_reason_id' => $sampleRejectionReason['rejection_reason_id']));
                            $data['reason_for_sample_rejection'] = $sampleRejectionReason['rejection_reason_id'];
                        } else {
                            $sampleRjtReasonDb->insert(array('rejection_reason_name' => trim($row['rejection_reason_name']), 'rejection_reason_status' => 'active'));
                            $data['reason_for_sample_rejection'] = $sampleRjtReasonDb->lastInsertValue;
                        }
                    } else {
                        $data['reason_for_sample_rejection'] = NULL;
                    }

                    //check existing sample code
                    $sampleCode = $this->checkSampleCode($sampleCode, $instanceCode);
                    $status = 0;

                    if ($sampleCode) {
                        //sample data update
                        $status = $sampleDb->update($data, array('vl_sample_id' => $sampleCode['vl_sample_id']));
                    } else {
                        //sample data insert
                        $status = $sampleDb->insert($data);
                    }

                    if ($status == 0) {
                        $return[$key][] = $sampleCode;
                    }
                }
            }
        } else {
            http_response_code(400);
            $response = array(
                'status'    => 'fail',
                'message'   => 'Missing data in API request',
            );
        }
        http_response_code(202);
        $status = 'success';
        if (count($return) > 0) {
            
            $status = 'partial';
            if((count($params['data']) - count($return)) == 0){
                $status = 'failed';
            } else{
                //remove directory  
                unlink($pathname);
            }
        } else{
            //remove directory  
            unlink($pathname);
        }
        $response = array(
            'status'    => 'success',
            'message'   => 'Received ' . count($params['data']) . ' records. Processed '.(count($params['data']) - count($return)).' records.'
        );

        // Track API Records
        $apiTrackData = array(
            'tracking_id'                   => $params['timestamp'],
            'received_on'                   => $common->getDateTime(),
            'number_of_records_received'    => count($params['data']),
            'number_of_records_processed'   => (count($params['data']) - count($return)),
            'source'                        => 'API Data',
            'status'                        => $status
        );
        $trackResult = $apiTrackDb->select(array('tracking_id' => $params['timestamp']))->current();
        if($trackResult){
            $apiTrackDb->update($apiTrackData, array('api_id' => $trackResult['api_id']));
        } else{
            $apiTrackDb->insert($apiTrackData);
        }

        return $response;
    }
}
