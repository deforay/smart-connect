<?php

namespace Eid\Service;

use Exception;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Session\Container;
use Application\Service\CommonService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class EidSampleService
{

    public $sm = null;
    public array $config;
    /** @var \Eid\Model\EidSampleTable $eidSampleTable */
    public $eidSampleTable;

    public function __construct($sm, $eidSampleTable)
    {
        $this->sm = $sm;
        $this->eidSampleTable = $eidSampleTable;
    }


    //get all sample types
    public function getSampleType($asArray = false)
    {
        /** @var \Application\Model\EidSampleTypeTable $eidSampleTypeDb */

        $eidSampleTypeDb = $this->sm->get('EidSampleTypeTable');
        return $eidSampleTypeDb->fetchAllSampleType($asArray);
    }

    public function getStats($params)
    {
        return $this->eidSampleTable->getStats($params);
    }

    public function getPocStats($params)
    {
        return $this->eidSampleTable->getPocStats($params);
    }

    public function getTestFailedByTestingPlatform($params)
    {
        return $this->eidSampleTable->getTestFailedByTestingPlatform($params);
    }

    public function getInstrumentWiseTest($params)
    {
        return $this->eidSampleTable->getInstrumentWiseTest($params);
    }

    public function getMonthlySampleCount($params)
    {
        return $this->eidSampleTable->getMonthlySampleCount($params);
    }

    public function getMonthlySampleCountByLabs($params)
    {
        return $this->eidSampleTable->getMonthlySampleCountByLabs($params);
    }


    public function getLabTurnAroundTime($params)
    {
        return $this->eidSampleTable->fetchLabTurnAroundTime($params);
    }

    public function getCountyOutcomes($params)
    {
        return $this->eidSampleTable->fetchCountyOutcomes($params);
    }

    public function fetchLabPerformance($params)
    {
        return $this->eidSampleTable->fetchLabPerformance($params);
    }

    public function fetchLatLonMap($params)
    {
        return $this->eidSampleTable->fetchLatLonMap($params);
    }

    public function fetchLatLonMapPosNeg($params)
    {
        return $this->eidSampleTable->fetchLatLonMapPosNeg($params);
    }

    // END OF LABS DASHBOARD

    public function saveFileFromVlsmAPIV2()
    {
        $apiData = [];

        /** @var \Application\Model\DashApiReceiverStatsTable $apiTrackDb */
        $apiTrackDb = $this->sm->get('DashApiReceiverStatsTable');
        /** @var \Application\Model\DashTrackApiRequestsTable $trackApiDb */
        $trackApiDb = $this->sm->get('DashTrackApiRequestsTable');
        $source = $_POST['source'] ?? 'LIS';
        $labId = $_POST['labId'] ?? null;

        $this->config = $this->sm->get('Config');
        $input = $this->config['db']['dsn'];
        preg_match('~=(.*?);~', $input, $output);
        $dbname = $output[1];
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');

        $fileName = $_FILES['eidFile']['name'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = CommonService::generateRandomString(6) . "." . $extension;

        if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
            mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "temporary", 0777);
        }
        if (
            !file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid")
            && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid")
        ) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid", 0777, true);
        }

        $fileName = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid" . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($fileName) && move_uploaded_file($_FILES['eidFile']['tmp_name'], $fileName)) {
            [$apiData, $timestamp] = CommonService::processJsonFile($fileName);
        }

        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = '$dbname'
                        AND table_name='dash_form_eid'";
        $sResult = $dbAdapter
            ->query($allColumns, $dbAdapter::QUERY_MODE_EXECUTE)
            ->toArray();
        $columnList = array_map('current', $sResult);

        $removeKeys = ['eid_id'];

        $columnList = array_diff($columnList, $removeKeys);

        /** @var \Eid\Model\EidSampleTable $sampleDb */
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');


        $numRows = $counter = 0;
        $currentDateTime = CommonService::getDateTime();
        foreach ($apiData as $rowData) {
            $counter++;
            $data = [];
            foreach ($columnList as $colName) {
                $data[$colName] = $rowData[$colName] ?? null;
            }

            $id = $sampleDb->insertOrUpdate($data);
            if (isset($id) && !empty($id) && is_numeric($id)) {
                $apiTrackDb->updateFacilityAttributes($data['facility_id'], $currentDateTime);
            }
            $numRows++;
        }

        if ($counter === $numRows) {
            $status = "success";
        } elseif ($counter - $numRows != 0) {
            $status = "partial";
        } elseif ($numRows == 0) {
            $status = 'failed';
        }


        if (is_readable($fileName)) {
            unlink($fileName);
        }

        $apiTrackData = array(
            'tracking_id'                   => $timestamp,
            'received_on'                   => CommonService::getDateTime(),
            'number_of_records_received'    => $counter,
            'number_of_records_processed'   => $numRows,
            'source'                        => $source,
            'test_type'                     => "eid",
            'lab_id'                        => $labId ?? $data['lab_id'],
            'status'                        => $status
        );
        $response =  array(
            'status'    => 'success',
            'message'   => $numRows . ' uploaded successfully',
        );
        $apiTrackDb->insert($apiTrackData);
        $trackApiDb->addApiTracking(CommonService::generateUUID(), 1, $numRows, 'weblims-eid', 'eid', $_SERVER['REQUEST_URI'], $apiData, $response, 'json', $labId ?? $data['lab_id']);
        return $response;
    }

    public function saveFileFromVlsmAPIV1()
    {
        $apiData = [];
        /** @var \Eid\Model\EidSampleTable $sampleDb */
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        $facilityDb = $this->sm->get('FacilityTable');
        //$facilityTypeDb = $this->sm->get('FacilityTypeTable');
        $locationDb = $this->sm->get('LocationDetailsTable');
        $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');


        $extension = strtolower(pathinfo($_FILES['eidFile']['name'], PATHINFO_EXTENSION));
        $newFileName = CommonService::generateRandomString(12) . "." . $extension;
        $fileName = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl" . DIRECTORY_SEPARATOR . $newFileName;

        if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
            mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "temporary", 0777);
        }
        if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid")) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid", 0777, true);
        }

        $apiData = [];
        if (move_uploaded_file($_FILES['eidFile']['tmp_name'], $fileName)) {
            if (is_readable($fileName)) {
                $apiData = CommonService::processJsonFile($fileName);
            }
        }

        foreach ($apiData as $rowData) {
            // Debug::dump($rowData);die;
            foreach ($rowData as $row) {
                // Debug::dump($row['vlsm_instance_id']);die;
                if (trim($row['sample_code']) != '' && trim($row['vlsm_instance_id']) != '') {
                    $sampleCode = trim($row['sample_code']);
                    $remoteSampleCode = trim($row['remote_sample_code']);
                    $instanceCode = trim($row['vlsm_instance_id']);

                    $sampleCollectionDate = (trim($row['sample_collection_date']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_collection_date']))) : null);
                    $sampleReceivedAtLab = (trim($row['sample_registered_at_lab']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_registered_at_lab']))) : null);
                    // $dateOfInitiationOfRegimen = (trim($row['date_of_initiation_of_current_regimen']) != '' ? trim(date('Y-m-d H:i', strtotime($row['date_of_initiation_of_current_regimen']))) : null);
                    $resultApprovedDateTime = (trim($row['result_approved_datetime']) != '' ? trim(date('Y-m-d H:i', strtotime($row['result_approved_datetime']))) : null);
                    $sampleTestedDateTime = (trim($row['sample_tested_datetime']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_tested_datetime']))) : null);



                    foreach ($row as $index => $value) {
                        if ($index == 'status_id') {
                            break;
                        } elseif ($index != 'eid_id') {
                            $data[$index] = $value;
                        }
                    }
                    $data['sample_code']                = $sampleCode;
                    $data['remote_sample_code']         = $remoteSampleCode;
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
                            $facilityData['facility_state'] = $sQueryResult['geo_id'];
                        } else {
                            $locationDb->insert(array('geo_parent' => 0, 'geo_name' => trim($row['facility_state'])));
                            $facilityData['facility_state'] = $locationDb->lastInsertValue;
                        }
                    }
                    if (trim($row['facility_district']) != '') {
                        $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($row['facility_district']), $facilityData['facility_state']);
                        if ($sQueryResult) {
                            $facilityData['facility_district'] = $sQueryResult['geo_id'];
                        } else {
                            $locationDb->insert(array('geo_parent' => $facilityData['facility_state'], 'geo_name' => trim($row['facility_district'])));
                            $facilityData['facility_district'] = $locationDb->lastInsertValue;
                        }
                    }
                    //check facility type
                    if (isset($row['facility_type']) && trim($row['facility_type']) != '') {
                        $facilityData['facility_type'] = trim($row['facility_type']);
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
                        $data['facility_id'] = null;
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
                            $labData['facility_state'] = $sQueryResult['geo_id'];
                        } else {
                            $locationDb->insert(array('geo_parent' => 0, 'geo_name' => trim($row['labState'])));
                            $labData['facility_state'] = $locationDb->lastInsertValue;
                        }
                    }
                    if (trim($row['labDistrict']) != '') {
                        $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($row['labDistrict']), $labData['facility_state']);
                        if ($sQueryResult) {
                            $labData['facility_district'] = $sQueryResult['geo_id'];
                        } else {
                            $locationDb->insert(array('geo_parent' => $labData['facility_state'], 'geo_name' => trim($row['labDistrict'])));
                            $labData['facility_district'] = $locationDb->lastInsertValue;
                        }
                    }
                    //check lab type
                    if (isset($row['labFacilityTypeName']) && trim($row['labFacilityTypeName']) != '') {
                        $labData['facility_type'] = trim($row['labFacilityTypeName']);
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
                    $data['result_status'] = trim($row['status_name']) != '' ? $this->checkSampleStatus(trim($row['status_name'])) : 6;

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
                        $data['reason_for_sample_rejection'] = null;
                    }
                    // Debug::dump($data);die;
                    //check existing sample code
                    // $sampleCode = $this->checkSampleCode($sampleCode, $remoteSampleCode, $instanceCode);
                    // if ($sampleCode) {
                    //     //sample data update
                    //     $sampleDb->update($data, array('eid_id' => $sampleCode['eid_id']));
                    // } else {
                    //     //sample data insert
                    //     $sampleDb->insert($data);
                    // }
                    $sampleDb->insertOrUpdate($data);
                }
            }
        }
        if (is_readable($fileName)) {
            unlink($fileName);
        }
        return array(
            'status'    => 'success',
            'message'   => 'Uploaded successfully',
        );
    }


    public function checkSampleCode($sampleCode, $remoteSampleCode = null, $instanceCode = null, $dashTable = 'dash_form_vl')
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from($dashTable);
        if (isset($instanceCode) && $instanceCode != "") {
            $sQuery = $sQuery->where(array('vlsm_instance_id' => $instanceCode));
        }
        if (isset($instanceCode) && $instanceCode != "") {
            $sQuery = $sQuery->where(array('sample_code' => $sampleCode));
        }
        if (isset($instanceCode) && $instanceCode != "") {
            $sQuery = $sQuery->where(array('remote_sample_code' => $remoteSampleCode));
        }
        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
    }

    public function checkFacilityStateDistrictDetails($location, $parent)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))
            ->where(array('l.geo_parent' => $parent, 'l.geo_name' => trim($location)));
        $sQuery = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
    }

    public function checkFacilityDetails($clinicName)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from('facility_details')->where(array('facility_name' => $clinicName));
        $fQueryStr = $sql->buildSqlString($fQuery);
        return $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
    }
    public function checkFacilityTypeDetails($facilityTypeName)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from('facility_type')->where(array('facility_type' => $facilityTypeName));
        $fQueryStr = $sql->buildSqlString($fQuery);
        return $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
    }
    public function checkTestingReson($testingReson)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $tQuery = $sql->select()->from('r_eid_test_reasons')->where(array('test_reason_name' => $testingReson));
        $tQueryStr = $sql->buildSqlString($tQuery);
        return $dbAdapter->query($tQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
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
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
    }

    public function checkTestReason($reasonName)
    {

        if (trim($reasonName) === '') {
            return null;
        }

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
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
    }

    public function getAllLabName()
    {
        $loginContainer = new Container('credo');
        $mappedFacilities = null;
        if ($loginContainer->role != 1) {
            $mappedFacilities = (!empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : null;
        }
        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllLabName($mappedFacilities);
    }

    //get all Lab Name
    public function getAllClinicName()
    {

        $loginContainer = new Container('credo');
        $mappedFacilities = null;
        if ($loginContainer->role != 1) {
            $mappedFacilities = (!empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : null;
        }

        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllClinicName($mappedFacilities);
    }

    public function getEidFormDetail()
    {
        return $this->eidSampleTable->fetchEidFormDetail();
    }
    // Get all test reason name for eid
    public function getAllTestReasonName()
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $tQuery = $sql->select()->from('r_eid_test_reasons');
        $tQueryStr = $sql->buildSqlString($tQuery);
        return $dbAdapter->query($tQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }
    //get all province name
    public function getAllProvinceList()
    {
        $loginContainer = new Container('credo');
        $mappedFacilities = null;
        if ($loginContainer->role != 1) {
            $mappedFacilities = (!empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : null;
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

    public function getTATbyProvince($params)
    {
        $labs = (isset($params['lab']) && !empty($params['lab'])) ? $params['lab'] : array();
        $dates = explode(" to ", $params['sampleCollectionDate']);
        $startDate = $dates[0];
        $endDate = $dates[1];
        // set_time_limit(10000);
        $result = [];
        $resultSet = $this->eidSampleTable->getTATbyProvince($labs, $startDate, $endDate, $params);
        foreach ($resultSet as $key) {
            $result[] = array(
                "facility"           => $key['geo_name'],
                "facility_id"        => $key['geo_id'],
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

    public function getTATbyDistrict($labs, $startDate, $endDate, $params)
    {
        // set_time_limit(10000);
        $result = [];
        $resultSet = $this->eidSampleTable->getTATbyDistrict($labs, $startDate, $endDate, $params);
        foreach ($resultSet as $key) {
            $result[] = array(
                "facility"           => $key['geo_name'],
                "facility_id"        => $key['geo_id'],
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

    public function getTATbyClinic($labs, $startDate, $endDate, $params)
    {
        // set_time_limit(10000);
        $result = [];
        $time = [];
        $resultSet = $this->eidSampleTable->getTATbyClinic($labs, $startDate, $endDate, $params);
        foreach ($resultSet as $key) {
            $result[] = array(
                "facility"           => $key['geo_name'],
                "facility_id"        => $key['geo_id'],
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
        return $this->eidSampleTable->fetchProvinceWiseResultAwaitedDrillDown($params);
    }

    public function getLabWiseResultAwaitedDrillDown($params)
    {
        return $this->eidSampleTable->fetchLabWiseResultAwaitedDrillDown($params);
    }

    public function getDistrictWiseResultAwaitedDrillDown($params)
    {
        return $this->eidSampleTable->fetchDistrictWiseResultAwaitedDrillDown($params);
    }

    public function getClinicWiseResultAwaitedDrillDown($params)
    {
        return $this->eidSampleTable->fetchClinicWiseResultAwaitedDrillDown($params);
    }

    public function getFilterSampleResultAwaitedDetails($parameters)
    {
        return $this->eidSampleTable->fetchFilterSampleResultAwaitedDetails($parameters);
    }

    public function generateResultsAwaitedSampleExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        if (property_exists($queryContainer, 'resultsAwaitedQuery') && $queryContainer->resultsAwaitedQuery !== null) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->resultsAwaitedQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && !empty($sResult)) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

                    $sheet = $excel->getActiveSheet();
                    $output = [];
                    foreach ($sResult as $aRow) {
                        $displayCollectionDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['collectionDate']);
                        $displayReceivedDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['receivedDate']);
                        $row = [];
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

                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Sample ID'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Collection Date'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Facility'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Lab'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Sample Received at Lab'), ENT_QUOTES, 'UTF-8'));

                    $sheet->getStyle('A1:F1')->applyFromArray($styleArray);

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
                            $columnName = Coordinate::stringFromColumnIndex($colNo);

                            $sheet->setCellValue($columnName . $currentRow, html_entity_decode($value, ENT_QUOTES, 'UTF-8'));

                            $sheet->getStyle($columnName . $currentRow)->applyFromArray($borderStyle);
                            $sheet->getStyle($columnName . $currentRow)->getAlignment()->setWrapText(true);
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
        return $this->eidSampleTable->fetchSampleDetails($params);
    }

    public function getBarSampleDetails($params)
    {
        return $this->eidSampleTable->fetchBarSampleDetails($params);
    }

    public function getLabFilterSampleDetails($parameters)
    {
        return $this->eidSampleTable->fetchLabFilterSampleDetails($parameters);
    }

    public function getFilterSampleDetails($parameters)
    {
        return $this->eidSampleTable->fetchFilterSampleDetails($parameters);
    }

    public function getFilterSampleTatDetails($parameters)
    {
        return $this->eidSampleTable->fetchFilterSampleTatDetails($parameters);
    }

    public function getLabSampleDetails($params)
    {
        return $this->eidSampleTable->fetchLabSampleDetails($params);
    }

    public function getLabBarSampleDetails($params)
    {
        return $this->eidSampleTable->fetchLabBarSampleDetails($params);
    }

    public function getIncompleteSampleDetails($params)
    {
        return $this->eidSampleTable->fetchIncompleteSampleDetails($params);
    }

    public function getIncompleteBarSampleDetails($params)
    {
        return $this->eidSampleTable->fetchIncompleteBarSampleDetails($params);
    }

    public function getSampleInfo($params, $dashTable = 'dash_form_eid')
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl' => $dashTable))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name', 'facility_code', 'facility_logo'), 'left')
            ->join(array('l_s' => 'geographical_divisions'), 'l_s.geo_id=f.facility_state_id', array('provinceName' => 'geo_name'), 'left')
            ->join(array('l_d' => 'geographical_divisions'), 'l_d.geo_id=f.facility_district_id', array('districtName' => 'geo_name'), 'left')
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
        if (isset($queryContainer->resultQuery) && $queryContainer->resultQuery !== null) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->resultQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && !empty($sResult)) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $excel->getActiveSheet();
                    $output = [];
                    foreach ($sResult as $aRow) {
                        $row = [];
                        $sampleCollectionDate = '';
                        $sampleTestedDate = '';
                        if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != NULL && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                            $sampleCollectionDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['sampleCollectionDate']);
                        }
                        if (isset($aRow['sampleTestingDate']) && $aRow['sampleTestingDate'] != NULL && trim($aRow['sampleTestingDate']) != "" && $aRow['sampleTestingDate'] != '0000-00-00') {
                            $sampleTestedDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['sampleTestingDate']);
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

                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Sample ID'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Facility Name'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Date Collected'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Rejection Reason'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Date Tested'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Result'), ENT_QUOTES, 'UTF-8'));

                    $sheet->getStyle('A1:F1')->applyFromArray($styleArray);
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
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $currentRow, html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                            // $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                            // $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                            // $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getAlignment()->setWrapText(true);
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
        return $this->eidSampleTable->getVlOutComes($params);
    }

    public function generateLabTestedSampleExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        if (property_exists($queryContainer, 'labTestedSampleQuery') && $queryContainer->labTestedSampleQuery !== null) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->labTestedSampleQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && !empty($sResult)) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

                    $sheet = $excel->getActiveSheet();
                    $output = [];
                    foreach ($sResult as $aRow) {
                        $row = [];
                        $sampleCollectionDate = '';
                        if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != null && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                            $sampleCollectionDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['sampleCollectionDate']);
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

                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Date'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Samples Collected'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Samples Tested'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Samples Pending'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Suppressed'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Not Suppressed'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('G1', html_entity_decode($translator->translate('Samples Rejected'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('H1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('I1', html_entity_decode($translator->translate('Clinics'), ENT_QUOTES, 'UTF-8'));

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
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $currentRow, html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                            // $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                            // $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                            // $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getAlignment()->setWrapText(true);
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
        return $this->eidSampleTable->fetchEidOutcomesByAgeInLabsDetails($params);
    }

    public function getEidPositivityRateDetails($params)
    {
        return $this->eidSampleTable->fetchEidPositivityRateDetails($params);
    }

    //clinic details start
    public function getOverallEidResult($params)
    {
        return $this->eidSampleTable->fetchOverallEidResult($params);
    }

    public function getViralLoadStatusBasedOnGender($params)
    {
        return $this->eidSampleTable->fetchViralLoadStatusBasedOnGender($params);
    }


    public function getClinicSampleTestedResultAgeGroupDetails($params)
    {
        return $this->eidSampleTable->fetchClinicSampleTestedResultAgeGroupDetails($params);
    }

    public function fetchSampleTestedReason($params)
    {
        return $this->eidSampleTable->fetchSampleTestedReason($params);
    }

    public function getClinicSampleTestedResults($params)
    {
        $sampleTypes = $this->getSampleType(asArray: true);
        return $this->eidSampleTable->fetchClinicSampleTestedResults($params, $sampleTypes);
    }

    public function getAllTestResults($parameters)
    {
        return $this->eidSampleTable->fetchAllTestResults($parameters);
    }

    public function generateHighVlSampleResultExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        if (isset($queryContainer->resultQuery) && $queryContainer->resultQuery !== null) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $hQueryStr = $sql->buildSqlString($queryContainer->highVlSampleQuery);
                //echo ($hQueryStr);die;
                $sResult = $dbAdapter->query($hQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && !empty($sResult)) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $excel->getActiveSheet();
                    $output = [];
                    $i = 1;
                    foreach ($sResult as $aRow) {
                        $row = [];
                        if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != NULL && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                            $sampleCollectionDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['sampleCollectionDate']);
                        }
                        if (isset($aRow['sample_received_at_lab_datetime']) && $aRow['sample_received_at_lab_datetime'] != NULL && trim($aRow['sample_received_at_lab_datetime']) != "" && $aRow['sample_received_at_lab_datetime'] != '0000-00-00') {
                            $requestDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['sample_received_at_lab_datetime']);
                        }
                        $row[] = $i;
                        $row[] = $aRow['sample_code'];
                        $row[] = ucwords($aRow['facility_name']);
                        $row[] = $aRow['facility_code'];
                        $row[] = $aRow['facilityDistrict'];
                        $row[] = $aRow['facilityState'];
                        $row[] = ucwords($aRow['first_name'] . " " . $aRow['last_name']);
                        $row[] = date('d-M-Y', strtotime($aRow['child_dob']));
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

                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('No.'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Sample Code'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Health Facility Name'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Health Facility Code'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('District/County'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Province/State'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('H1', html_entity_decode($translator->translate('Patient Name'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('I1', html_entity_decode($translator->translate('Date of Birth'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('J1', html_entity_decode($translator->translate('Age'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('K1', html_entity_decode($translator->translate('Gender'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('L1', html_entity_decode($translator->translate('Date of Sample Collection'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('M1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('V1', html_entity_decode($translator->translate('Date Sample Received at Lab'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('X1', html_entity_decode($translator->translate('Result'), ENT_QUOTES, 'UTF-8'));
                    $sheet->setCellValue('Y1', html_entity_decode($translator->translate('Rejection Reason (if Rejected)'), ENT_QUOTES, 'UTF-8'));

                    $sheet->getStyle('A1:Y1')->applyFromArray($styleArray);

                    $currentRow = 2;
                    foreach ($output as $rowData) {
                        $colNo = 0;
                        foreach ($rowData as $field => $value) {
                            if (!isset($value)) {
                                $value = "";
                            }
                            $columnName = Coordinate::stringFromColumnIndex($colNo);

                            $sheet->setCellValue($columnName . $currentRow, html_entity_decode($value, ENT_QUOTES, 'UTF-8'));

                            $sheet->getStyle($columnName . $currentRow)->applyFromArray($borderStyle);
                            $sheet->getStyle($columnName . $currentRow)->getAlignment()->setWrapText(true);
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
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            if (property_exists($queryContainer, 'sampleResultQuery') && $queryContainer->sampleResultQuery !== null) {
                try {
                    $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                    $sql = new Sql($dbAdapter);
                    $sQueryStr = $sql->buildSqlString($queryContainer->sampleResultQuery);
                    $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if (isset($sResult) && !empty($sResult)) {
                        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                        $sheet = $excel->getActiveSheet();
                        $output = [];
                        foreach ($sResult as $aRow) {
                            $row = [];
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

                        $sheet->setCellValue('A1', html_entity_decode($translator->translate('Lab'), ENT_QUOTES, 'UTF-8'));
                        $sheet->setCellValue('B1', html_entity_decode($translator->translate('Samples Collected'), ENT_QUOTES, 'UTF-8'));
                        $sheet->setCellValue('C1', html_entity_decode($translator->translate('Samples Tested'), ENT_QUOTES, 'UTF-8'));
                        $sheet->setCellValue('D1', html_entity_decode($translator->translate('Samples Pending'), ENT_QUOTES, 'UTF-8'));
                        $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Positive'), ENT_QUOTES, 'UTF-8'));
                        $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Negative'), ENT_QUOTES, 'UTF-8'));
                        $sheet->setCellValue('G1', html_entity_decode($translator->translate('Samples Rejected'), ENT_QUOTES, 'UTF-8'));

                        $sheet->getStyle('A1:G1')->applyFromArray($styleArray);

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
                                //$columnName = Coordinate::stringFromColumnIndex($colNo);
                                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $currentRow, html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                                // $sheet->getStyle($columnName . $currentRow)->applyFromArray($borderStyle);
                                // $sheet->getStyle($columnName . $currentRow)->getAlignment()->setWrapText(true);
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


}
