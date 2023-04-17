<?php

namespace Eid\Service;

use Laminas\Session\Container;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class EidSampleService
{

    public $sm = null;
    public array $config;
    /** @var \Application\Model\EidSampleTable $eidSampleTable */
    public $eidSampleTable;

    public function __construct($sm, $eidSampleTable)
    {
        $this->sm = $sm;
        $this->eidSampleTable = $eidSampleTable;
    }


    //get all sample types
    public function getSampleType()
    {
        /** @var \Application\Model\EidSampleTypeTable $eidSampleTypeDb */

        $eidSampleTypeDb = $this->sm->get('EidSampleTypeTable');
        return $eidSampleTypeDb->fetchAllSampleType();
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
        $apiData = array();

        /** @var \Application\Model\DashApiReceiverStatsTable $apiTrackDb */

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
            mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "temporary", 0777);
        }
        if (
            !file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid")
            && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid")
        ) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid", 0777);
        }

        $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid" . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($pathname) && move_uploaded_file($_FILES['eidFile']['tmp_name'], $pathname)) {
            $apiData = \JsonMachine\JsonMachine::fromFile($pathname, "/data");
        }

        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = '$dbname'
                        AND table_name='dash_form_eid'";
        $sResult = $dbAdapter
            ->query($allColumns, $dbAdapter::QUERY_MODE_EXECUTE)
            ->toArray();
        $columnList = array_map('current', $sResult);

        $removeKeys = array(
            'eid_id'
        );

        $columnList = array_diff($columnList, $removeKeys);

        /** @var \Application\Model\EidSampleTable $sampleDb */
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');


        $numRows = 0;
        $counter = 0;
        foreach ($apiData as $key => $rowData) {
            $counter++;
            $data = array();
            foreach ($columnList as $colName) {
                if (isset($rowData[$colName])) {
                    $data[$colName] = $rowData[$colName];
                } else {
                    $data[$colName] = null;
                }
            }

            $id = $sampleDb->insertOrUpdate($data);
            if (isset($id) && !empty($id) && is_numeric($id)) {
                $dashDb = $this->sm->get('DashApiReceiverStatsTable');
                $params = array(
                    "table" => "dash_form_eid",
                    "field" => "eid_id",
                    "id" => $id
                );
                $dashDb->updateAttributes($params);
            }
            $numRows++;
        }

        if ($counter  == $numRows) {
            $status = "success";
        } elseif (($counter - $numRows) != 0) {
            $status = "partial";
        } elseif ($numRows == 0) {
            $status = 'failed';
        }
        $apiData = \JsonMachine\JsonMachine::fromFile($pathname, '/timestamp');
        $timestamp = iterator_to_array($apiData)['timestamp'];
        $timestamp = ($timestamp !== false && !empty($timestamp)) ? $timestamp : time();

        unset($pathname);
        $apiTrackData = array(
            'tracking_id'                   => $timestamp,
            'received_on'                   => \Application\Service\CommonService::getDateTime(),
            'number_of_records_received'    => $counter,
            'number_of_records_processed'   => $numRows,
            'source'                        => 'VLSM-EID',
            'lab_id'                        => $data['lab_id'],
            'status'                        => $status
        );
        $apiTrackDb->insert($apiTrackData);

        return array(
            'status'    => 'success',
            'message'   => $numRows . ' uploaded successfully',
        );
    }

    public function saveFileFromVlsmAPIV1()
    {
        $apiData = [];
        /** @var \Application\Model\EidSampleTable $sampleDb */
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
            mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "temporary", 0777);
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
                            } else {
                                if ($index != 'eid_id') {
                                    $data[$index] = $value;
                                }
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
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }

    public function checkFacilityStateDistrictDetails($location, $parent)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))
            ->where(array('l.geo_parent' => $parent, 'l.geo_name' => trim($location)));
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
        $loginContainer = new Container('credo');
        $mappedFacilities = null;
        if ($loginContainer->role != 1) {
            $mappedFacilities = (isset($loginContainer->mappedFacilities) && !empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : null;
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
            $mappedFacilities = (isset($loginContainer->mappedFacilities) && !empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : null;
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
            $mappedFacilities = (isset($loginContainer->mappedFacilities) && !empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : null;
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
        $resultSet = $this->eidSampleTable->getTATbyProvince($labs, $startDate, $endDate);
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

    public function getTATbyDistrict($labs, $startDate, $endDate)
    {
        // set_time_limit(10000);
        $result = array();
        $resultSet = $this->eidSampleTable->getTATbyDistrict($labs, $startDate, $endDate);
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

    public function getTATbyClinic($labs, $startDate, $endDate)
    {
        // set_time_limit(10000);
        $result = array();
        $time = array();
        $resultSet = $this->eidSampleTable->getTATbyClinic($labs, $startDate, $endDate);
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
                        $displayCollectionDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['collectionDate']);
                        $displayReceivedDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['receivedDate']);
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
            ->join(array('l_s' => 'geographical_divisions'), 'l_s.geo_id=f.facility_state', array('provinceName' => 'geo_name'), 'left')
            ->join(array('l_d' => 'geographical_divisions'), 'l_d.geo_id=f.facility_district', array('districtName' => 'geo_name'), 'left')
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
        if (isset($queryContainer->resultQuery)) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->resultQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && count($sResult) > 0) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    foreach ($sResult as $aRow) {
                        $row = array();
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

                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Sample ID'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Facility Name'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Date Collected'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Rejection Reason'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Date Tested'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Result'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

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
                            if (is_numeric($value)) {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                            } else {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            }
                            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
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
        return $this->eidSampleTable->getVlOutComes($params);
    }

    public function generateLabTestedSampleExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
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

    public function getClinicSampleTestedResults($params, $sampleType)
    {
        return $this->eidSampleTable->fetchClinicSampleTestedResults($params, $sampleType);
    }

    public function getAllTestResults($parameters)
    {
        return $this->eidSampleTable->fetchAllTestResults($parameters);
    }

    public function generateHighVlSampleResultExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        if (isset($queryContainer->resultQuery)) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $hQueryStr = $sql->buildSqlString($queryContainer->highVlSampleQuery);
                // echo ($hQueryStr);die;
                $sResult = $dbAdapter->query($hQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && count($sResult) > 0) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    $i = 1;
                    foreach ($sResult as $aRow) {
                        $row = array();
                        if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != NULL && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                            $sampleCollectionDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['sampleCollectionDate']);
                        }
                        if (isset($aRow['sample_received_at_vl_lab_datetime']) && $aRow['sample_received_at_vl_lab_datetime'] != NULL && trim($aRow['sample_received_at_vl_lab_datetime']) != "" && $aRow['sample_received_at_vl_lab_datetime'] != '0000-00-00') {
                            $requestDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
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
            if (isset($queryContainer->sampleResultQuery)) {
                try {
                    $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                    $sql = new Sql($dbAdapter);
                    $sQueryStr = $sql->buildSqlString($queryContainer->sampleResultQuery);
                    $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if (isset($sResult) && !empty($sResult)) {
                        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
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
                                $columnName = Coordinate::stringFromColumnIndex($colNo);
                                if (is_numeric($value)) {
                                    $sheet->getCell($columnName . $currentRow)
                                        ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                                } else {
                                    $sheet->getCell($columnName . $currentRow)
                                        ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                }
                                $sheet->getStyle($columnName . $currentRow)->applyFromArray($borderStyle);
                                $sheet->getStyle($columnName . $currentRow)->getAlignment()->setWrapText(true);
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

    public function saveEidDataFromAPI($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        $facilityDb = $this->sm->get('FacilityTable');
        $testStatusDb = $this->sm->get('SampleStatusTable');
        $sampleTypeDb = $this->sm->get('SampleTypeTable');
        $sampleRjtReasonDb = $this->sm->get('EidSampleRejectionReasonTable');
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
            if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "api-data-vl") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "api-data-vl")) {
                mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "api-data-vl", 0777);
            }

            $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "api-data-vl" . DIRECTORY_SEPARATOR . $params['timestamp'] . '.json';
            if (!file_exists($pathname)) {
                $file = file_put_contents($pathname, json_encode($params));
                if (move_uploaded_file($pathname, $pathname)) {
                    // $apiData = file_put_contents($pathname);
                }
            }
            foreach ($params['data'] as $key => $row) {
                // Debug::dump($row);die;
                if (!empty(trim($row['sample_code'])) && trim($params['api_version']) == $this->config['defaults']['eid-api-version']) {
                    $sampleCode = trim($row['sample_code']);
                    $remoteSampleCode = trim($row['remote_sample_code']);
                    $instanceCode = 'api-data';

                    // Check dublicate data
                    $province = $provinceDb->select(array('province_name' => $row['health_centre_province']))->current();
                    if (!$province) {
                        $provinceDb->insert(array(
                            'province_name'     => $row['health_centre_province'],
                            'updated_datetime'  => \Application\Service\CommonService::getDateTime()
                        ));
                        $province['province_id'] = $provinceDb->lastInsertValue;
                    }




                    $sampleReceivedAtLab = ((trim($row['sample_received_date']) != '' && $row['sample_received_date'] != "") ? trim($row['sample_received_date']) : null);
                    $sampleTestedDateTime = ((trim($row['sample_tested_date']) != '' && $row['sample_tested_date'] != "") ? trim($row['sample_tested_date']) : null);
                    $sampleCollectionDate = ((trim($row['sample_collection_date']) != '' && $row['sample_collection_date'] != "") ? trim($row['sample_collection_date']) : null);
                    $dob = ((trim($row['mother_dob']) != '' && $row['mother_dob'] != "") ? trim($row['mother_dob']) : null);
                    $child_dob = ((trim($row['child_dob']) != '' && $row['child_dob'] != "") ? trim($row['child_dob']) : null);
                    $resultApprovedDateTime = ((trim($row['result_approved_datetime']) != '' && $row['result_approved_datetime'] != "") ? trim($row['result_approved_datetime']) : null);
                    $dateOfInitiationOfRegimen = ((trim($row['date_of_initiation_of_current_regimen']) != '' && $row['date_of_initiation_of_current_regimen'] != "") ? trim($row['date_of_initiation_of_current_regimen']) : null);
                    $sampleRegisteredAtLabDateTime = ((trim($row['sample_registered_at_lab']) != '' && $row['sample_registered_at_lab'] != "") ? trim($row['sample_registered_at_lab']) : null);
                    $resultPrinterDateTime = ((trim($row['result_printed_datetime']) != '' && $row['result_printed_datetime'] != "") ? trim($row['result_printed_datetime']) : null);

                    $data = array(
                        'sample_code'                           => $sampleCode,
                        'remote_sample_code'                    => $remoteSampleCode,
                        'vlsm_instance_id'                      => $instanceCode,
                        'province_id'                           => (trim($province['province_id']) != '' ? trim($province['province_id']) : NULL),
                        'mother_id'                             => (trim($row['mother_id']) != '' ? trim($row['mother_id']) : NULL),
                        'caretaker_phone_number'                => (trim($row['caretaker_phone_number']) != '' ? trim($row['caretaker_phone_number']) : NULL),
                        'mother_age_in_years'                  => (trim($row['mother_age_in_years']) != '' ? trim($row['mother_age_in_years']) : NULL),
                        'mother_marital_status'                 => (trim($row['mother_marital_status']) != '' ? trim($row['mother_marital_status']) : NULL),
                        'mother_dob'                           => $dob,
                        'sample_collection_date'                => $sampleCollectionDate,
                        'sample_registered_at_lab'              => $sampleReceivedAtLab,
                        'result_printed_datetime'               => $resultPrinterDateTime,
                        'child_id'                            => (trim($row['child_id']) != '' ? trim($row['child_id']) : NULL),
                        'child_dob'                           => $child_dob,
                        'child_age'                            => (trim($row['child_age']) != '' ? trim($row['child_age']) : NULL),
                        'child_gender'                            => (trim($row['child_gender']) != '' ? trim($row['child_gender']) : NULL),
                        'mother_hiv_status'                            => (trim($row['mother_hiv_status']) != '' ? trim($row['mother_hiv_status']) : NULL),
                        'mother_vl_result'                            => (trim($row['mother_vl_result']) != '' ? trim($row['mother_vl_result']) : NULL),
                        'mother_vl_test_date'                            => (trim($row['mother_vl_test_date']) != '' ? trim($row['mother_vl_test_date']) : NULL),
                        'is_infant_receiving_treatment'                            => (trim($row['is_infant_receiving_treatment']) != '' ? trim($row['is_infant_receiving_treatment']) : NULL),
                        'pcr_test_performed_before'                            => (trim($row['pcr_test_performed_before']) != '' ? trim($row['pcr_test_performed_before']) : NULL),
                        'specimen_type'                            => (trim($row['specimen_type']) != '' ? trim($row['specimen_type']) : NULL),
                        'reason_for_eid_test'                            => (trim($row['reason_for_eid_test']) != '' ? trim($row['reason_for_eid_test']) : NULL),
                        'last_pcr_id'                            => (trim($row['last_pcr_id']) != '' ? trim($row['last_pcr_id']) : NULL),
                        'last_pcr_date'                            => (trim($row['last_pcr_date']) != '' ? trim($row['last_pcr_date']) : NULL),
                        'reason_for_pcr'                            => (trim($row['reason_for_pcr']) != '' ? trim($row['reason_for_pcr']) : NULL),
                        'rapid_test_performed'                            => (trim($row['rapid_test_performed']) != '' ? trim($row['rapid_test_performed']) : NULL),
                        'rapid_test_result'                            => (trim($row['rapid_test_result']) != '' ? trim($row['rapid_test_result']) : NULL),
                        'rapid_test_date'                            => (trim($row['rapid_test_date']) != '' ? trim($row['rapid_test_date']) : NULL),
                        // 'line_of_treatment'                     => (trim($row['line_of_treatment']) != '' ? trim($row['line_of_treatment']) : NULL),
                        'is_sample_rejected'                    => (trim($row['is_sample_rejected']) != '' ? strtolower($row['is_sample_rejected']) : NULL),
                        // 'result_tested_by'                      => (trim($row['result_tested_by']) != '' ? trim($row['result_tested_by']) : NULL),
                        // 'current_regimen'                       => (trim($row['current_regimen']) != '' ? trim($row['current_regimen']) : NULL),
                        'result_approved_datetime'              => $resultApprovedDateTime,
                        'sample_tested_datetime'                => $sampleTestedDateTime,
                        'eid_test_platform'                      => (trim($row['eid_test_platform']) != '' ? trim($row['eid_test_platform']) : NULL),
                        'result'                                => (trim($row['result_value']) != '' ? trim($row['result_value']) : NULL),
                        'result_approved_by'                    => (trim($row['result_approved_by']) != '' ? $userDb->checkExistUser($row['result_approved_by']) : NULL),
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
                    $sampleCode = $this->checkSampleCode($sampleCode, $remoteSampleCode, $instanceCode);
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
        if (!empty($return)) {

            $status = 'partial';
            if ((count($params['data']) - count($return)) == 0) {
                $status = 'failed';
            } else {
                //remove directory
                unlink($pathname);
            }
        } else {
            //remove directory
            unlink($pathname);
        }
        $response = array(
            'status'    => 'success',
            'message'   => 'Received ' . count($params['data']) . ' records. Processed ' . (count($params['data']) - count($return)) . ' records.'
        );

        // Track API Records
        $apiTrackData = array(
            'tracking_id'                   => $params['timestamp'],
            'received_on'                   => \Application\Service\CommonService::getDateTime(),
            'number_of_records_received'    => count($params['data']),
            'number_of_records_processed'   => (count($params['data']) - count($return)),
            'source'                        => 'API-EID',
            'lab_id'                        => $data['lab_id'],
            'status'                        => $status
        );
        $trackResult = $apiTrackDb->select(array('tracking_id' => $params['timestamp']))->current();
        if ($trackResult) {
            $apiTrackDb->update($apiTrackData, array('api_id' => $trackResult['api_id']));
        } else {
            $apiTrackDb->insert($apiTrackData);
        }

        return $response;
    }
}
