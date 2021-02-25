<?php

namespace Covid19\Service;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Expression;
use Application\Service\CommonService;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use Zend\Debug\Debug;

class Covid19FormService
{

    public $sm = null;
    protected $translator = null;

    public function __construct($sm)
    {
        $this->sm = $sm;
        $this->translator = $this->sm->get('translator');
    }

    public function getServiceManager()
    {
        return $this->sm;
    }

    public function saveFileFromVlsmAPIV2()
    {
        // Debug::dump($_FILES['covid19File']);die;
        $apiData = array();
        $apiTrackDb = $this->sm->get('DashApiReceiverStatsTable');

        $this->config = $this->sm->get('Config');
        $input = $this->config['db']['dsn'];
        preg_match('~=(.*?);~', $input, $output);
        $dbname = $output[1];
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');

        $fileName = $_FILES['covid19File']['name'];
        $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = $ranNumber . "." . $extension;

        if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
            mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "uploads", 0777);
        }
        if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19")) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19", 0777);
        }

        $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19" . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($pathname)) {
            if (move_uploaded_file($_FILES['covid19File']['tmp_name'], $pathname)) {
                $apiData = json_decode(file_get_contents($pathname), true);
                //$apiData = \JsonMachine\JsonMachine::fromFile($pathname);
            }
        }

        // ob_start();
        // var_dump($apiData);
        // error_log(ob_get_clean());


        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $dbname . "' AND table_name='dash_form_covid19'";
        $sResult = $dbAdapter->query($allColumns, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $columnList = array_map('current', $sResult);

        $removeKeys = array(
            'covid19_id'
        );

        $columnList = array_diff($columnList, $removeKeys);
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');


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
                $numRows += $sampleDb->update($data, array('covid19_id' => $sampleCode['covid19_id']));
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
            'source'                        => 'Sync V2 Viral Load',
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
        try {
            // Debug::dump($_FILES['covid19File']);die;
            $apiData = array();
            $common = new CommonService();
            $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
            $facilityTypeDb = $this->sm->get('FacilityTypeTable');
            $testStatusDb = $this->sm->get('SampleStatusTable');
            $locationDb = $this->sm->get('LocationDetailsTable');
            $facilityDb = $this->sm->get('FacilityTable');
            $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');
            
            $fileName = $_FILES['covid19File']['name'];
            $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileName = $ranNumber . "." . $extension;
            
            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "uploads", 0777);
            }
            if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19")) {
                mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19", 0777);
            }
            
            $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19" . DIRECTORY_SEPARATOR . $fileName;
            if (!file_exists($pathname)) {
                if (move_uploaded_file($_FILES['covid19File']['tmp_name'], $pathname)) {
                    $apiData = \JsonMachine\JsonMachine::fromFile($pathname);
                }
            }
            
            if ($apiData !== FALSE) {
                foreach ($apiData as $rowData) {
                    // Debug::dump($rowData);die;
                    foreach ($rowData as $key => $row) {
                        // Debug::dump($row);die;
                        // print_r($apiData);die;
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
                                    if ($index != 'covid19_id') {
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
                                $sampleDb->update($data, array('covid19_id' => $sampleCode['covid19_id']));
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
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
            Debug::dump($exc->getMessage());
        }
    }

    public function checkSampleCode($sampleCode, $instanceCode, $dashTable = 'dash_form_covid19')
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from($dashTable)->where(array('sample_code LIKE "%' . $sampleCode . '%"', 'vlsm_instance_id' => $instanceCode));
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
        $tQuery = $sql->select()->from('r_covid19_test_reasons')->where(array('test_reason_name' => $testingReson));
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
        $sQuery = $sql->select()->from('r_covid19_sample_type')->where(array('sample_name' => $sampleType));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    public function checkSampleRejectionReason($rejectReasonName)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_covid19_sample_rejection_reasons')->where(array('rejection_reason_name' => $rejectReasonName));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }

    public function fetchSummaryTabDetails($params)
    {
        $covid19SampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $covid19SampleDb->getSummaryTabDetails($params);
    }

    public function getSamplesReceivedBarChartDetails($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchSamplesReceivedBarChartDetails($params);
    }

    public function getAllSamplesReceivedByFacility($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchAllSamplesReceivedByFacility($parameters);
    }

    public function getAllSamplesReceivedByProvince($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchAllSamplesReceivedByProvince($parameters);
    }

    public function getAllSamplesReceivedByDistrict($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchAllSamplesReceivedByDistrict($parameters);
    }

    public function getPositiveRateBarChartDetails($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchPositiveRateBarChartDetails($params);
    }

    public function getAllPositiveRateByDistrict($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchAllPositiveRateByDistrict($parameters);
    }

    public function getAllPositiveRateByProvince($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchAllPositiveRateByProvince($parameters);
    }

    public function getAllPositiveRateByFacility($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchAllPositiveRateByFacility($parameters);
    }

    public function getSamplesRejectedBarChartDetails($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchSamplesRejectedBarChartDetails($params);
    }

    public function getAllSamplesRejectedByDistrict($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchAllSamplesRejectedByDistrict($parameters);
    }

    public function getAllSamplesRejectedByFacility($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fecthAllSamplesRejectedByFacility($parameters);
    }

    public function getAllSamplesRejectedByProvince($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fecthAllSamplesRejectedByProvince($parameters);
    }

    public function getCovid19OutcomesDetails($params)
    {
        $covid19SampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $covid19SampleDb->fetchCovid19OutcomesDetails($params);
    }

    public function getCovid19OutcomesByAgeDetails($params)
    {
        $covid19SampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $covid19SampleDb->fetchCovid19OutcomesByAgeDetails($params);
    }

    public function getTATDetails($params)
    {
        $covid19SampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $covid19SampleDb->fetchTATDetails($params);
    }

    public function getCovid19OutcomesByProvinceDetails($params)
    {
        $covid19SampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $covid19SampleDb->fetchCovid19OutcomesByProvinceDetails($params);
    }

    public function exportIndicatorResultExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if (isset($queryContainer->indicatorSummaryQuery)) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->indicatorSummaryQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && count($sResult) > 0) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

                    // $cacheMethod = \PhpOffice\PhpSpreadsheet\Collection\CellsFactory::cache_to_phpTemp;
                    // $cacheSettings = array('memoryCacheSize' => '80MB');
                    // \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    $keySummaryIndicators = array();
                    $j = 1;

                    foreach ($sResult as $row) {
                        $keySummaryIndicators['sample'][$this->translator->translate('Samples Received')]['month'][$j] = (isset($row["total_samples_received"])) ? $row["total_samples_received"] : 0;
                        $keySummaryIndicators['sample'][$this->translator->translate('Samples Tested')]['month'][$j] = (isset($row["total_samples_tested"])) ? $row["total_samples_tested"] : 0;
                        $keySummaryIndicators['sample'][$this->translator->translate('Samples Rejected')]['month'][$j] = (isset($row["total_samples_rejected"])) ? $row["total_samples_rejected"] : 0;
                        $keySummaryIndicators['sample'][$this->translator->translate('Valid Tested')]['month'][$j]  = $valid = (isset($row["total_samples_tested"])) ? $row["total_samples_tested"] - $row["total_samples_rejected"] : 0;;
                        $keySummaryIndicators['sample'][$this->translator->translate('No. of Positive')]['month'][$j] = (isset($row["total_positive_samples"])) ? $row["total_positive_samples"] : 0;
                        $keySummaryIndicators['sample'][$this->translator->translate('Positive %') . ' (%)']['month'][$j] = ($valid > 0) ? round((($row["total_positive_samples"] / $valid) * 100), 2) . '' : '0';
                        $keySummaryIndicators['sample'][$this->translator->translate('Rejection %') . ' (%)']['month'][$j] = (isset($row["total_samples_rejected"]) && $row["total_samples_rejected"] > 0 && $row["total_samples_received"] > 0) ? round((($row["total_samples_rejected"] / ($row["total_samples_tested"] + $row["total_samples_rejected"])) * 100), 2) . '' : '0';
                        $keySummaryIndicators['month'][$j] = $row['monthyear'];
                        $j++;
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
                    $eRow = 0;
                    $sheet->setCellValue('A1', html_entity_decode($this->translator->translate('Months'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    foreach ($keySummaryIndicators['month'] as $key => $month) {
                        $colNo = $key + 1;
                        $currentRow = 1;
                        $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($month, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                        $sheet->getStyle($cellName . $currentRow)->applyFromArray($styleArray);
                    }


                    foreach ($keySummaryIndicators['sample'] as $key => $indicators) {
                        $row = array();
                        $row[] = $key;
                        foreach ($indicators['month'] as $months) {
                            $row[] = $months;
                        }
                        $output[] = $row;
                    }


                    $currentRow = 2;
                    foreach ($output as $rowData) {
                        $colNo = 1;
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
                    $filename = 'COVID19-SUMMARY-KEY-INDICATORS-' . date('d-M-Y-H-i-s') . '.xlsx';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                } else {
                    return "";
                }
            } catch (Exception $exc) {
                error_log("SUMMARY-INDICATORS-RESULT-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
            }
        } else {
            return "";
        }
    }

    public function exportPositiveRateByFacility($params, $dashTable = 'dash_form_covid19')
    {

        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        // To set te session table
        $logincontainer = new Container('credo');
        if (isset($logincontainer->Covid19SampleTable) && $logincontainer->Covid19SampleTable != "") {
            $dashTable = $logincontainer->Covid19SampleTable;
        }
        $common = new CommonService();

        if (!isset($queryContainer->fetchAllPositiveRateByFacility)) {

            $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
            $sql = new Sql($dbAdapter);
            $queryContainer->fetchAllPositiveRateByFacility = $sql->select()->from(array('covid19' => $dashTable))
                ->columns(
                    array(
                        'covid19_id',
                        'facility_id',
                        'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                        'result',
                        "total_samples_received" => new Expression("(COUNT(*))"),
                        "total_samples_valid" => new Expression("(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END))"),
                        "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                        "total_positive_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                        "total_negative_samples" => new Expression("SUM(CASE WHEN ((covid19.result like 'negative' OR covid19.result like 'Negative')) THEN 1 ELSE 0 END)"),
                        "positive_rate" => new Expression("ROUND(((SUM(CASE WHEN ((covid19.result like 'positive' OR covid19.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((covid19.result IS NOT NULL AND covid19.result != '' AND covid19.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=covid19.facility_id', array('facility_name'))
                ->join(array('f_d_l_dp' => 'location_details'), 'f_d_l_dp.location_id=f.facility_state', array('province' => 'location_name'))
                ->join(array('f_d_l_d' => 'location_details'), 'f_d_l_d.location_id=f.facility_district', array('district' => 'location_name'))
                ->where("(covid19.sample_collection_date is not null AND covid19.sample_collection_date != '' AND DATE(covid19.sample_collection_date) !='1970-01-01' AND DATE(covid19.sample_collection_date) !='0000-00-00')")
                ->group('covid19.facility_id');
        }



        try {
            $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
            $sql = new Sql($dbAdapter);
            $sQueryStr = $sql->buildSqlString($queryContainer->fetchAllSuppressionRateByFacility);
            $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($sResult) && count($sResult) > 0) {
                $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                //$cacheMethod = \PhpOffice\PhpSpreadsheet\Collection\CellsFactory::cache_to_phpTemp;
                //$cacheSettings = array('memoryCacheSize' => '80MB');
                //\PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                //var_dump('AAYA');die;
                $sheet = $excel->getActiveSheet();
                $output = array();
                foreach ($sResult as $aRow) {

                    $row = array();
                    $row[] = ucwords($aRow['facility_name']);
                    $row[] = ucwords($aRow['province']);
                    $row[] = ucwords($aRow['district']);
                    $row[] = $aRow['total_samples_valid'];
                    $row[] = $aRow['total_positive_samples'];
                    $row[] = $aRow['total_negative_samples'];
                    $row[] = ($aRow['total_samples_rejected'] > 0 && $aRow['total_samples_received'] > 0) ? round((($aRow['total_samples_rejected'] / $aRow['total_samples_received']) * 100), 2) : '';
                    $row[] = ($aRow['total_samples_valid'] > 0 && $aRow['total_positive_samples'] > 0) ? round((($aRow['total_positive_samples'] / $aRow['total_samples_valid']) * 100), 2) : '';
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

                $sheet->setCellValue('A1', html_entity_decode($this->translator->translate('Facility'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('B1', html_entity_decode($this->translator->translate('Province'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('C1', html_entity_decode($this->translator->translate('District/County'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('D1', html_entity_decode($this->translator->translate('Valid Results'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('E1', html_entity_decode($this->translator->translate('Positive Results'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('F1', html_entity_decode($this->translator->translate('Negative Results'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('G1', html_entity_decode($this->translator->translate('Samples Rejected in %'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('H1', html_entity_decode($this->translator->translate('Positive Rate in %'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                $sheet->getStyle('A1')->applyFromArray($styleArray);
                $sheet->getStyle('B1')->applyFromArray($styleArray);
                $sheet->getStyle('C1')->applyFromArray($styleArray);
                $sheet->getStyle('D1')->applyFromArray($styleArray);
                $sheet->getStyle('E1')->applyFromArray($styleArray);
                $sheet->getStyle('F1')->applyFromArray($styleArray);
                $sheet->getStyle('G1')->applyFromArray($styleArray);
                $sheet->getStyle('H1')->applyFromArray($styleArray);

                $currentRow = 2;
                foreach ($output as $rowData) {
                    $colNo = 0;
                    foreach ($rowData as $field => $value) {
                        if (!isset($value)) {
                            $value = "";
                        }
                        // if ($colNo > 5) {
                        //     break;
                        // }
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
                $filename = 'COVID19-Facility-Wise-Positive-Rate-' . date('d-M-Y-H-i-s') . '.xlsx';
                $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                return $filename;
            } else {
                return "";
            }
        } catch (Exception $exc) {
            error_log("COVID19-Facility-Wise-Positive-Rate-" . $exc->getMessage());
            error_log($exc->getTraceAsString());
            return "";
        }
    }

    public function getKeySummaryIndicatorsDetails($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchKeySummaryIndicatorsDetails($params);
    }
    
    /* Lab Dashboard Start */
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
    // get all distrcit name
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

    public function getStats($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->getStats($params);
    }

    public function getMonthlySampleCount($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->getMonthlySampleCount($params);
    }

    //get all sample types
    public function getSampleType()
    {
        $sampleDb = $this->sm->get('Covid19SampleTypeTable');
        return $sampleDb->fetchAllSampleType();
    }
    public function getMonthlySampleCountByLabs($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->getMonthlySampleCountByLabs($params);
    }

    public function getLabTurnAroundTime($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchLabTurnAroundTime($params);
    }

    public function getLabPerformance($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchLabPerformance($params);
    }

    public function getCovid19OutcomesByAgeInLabsDetails($params)
    {
        $eidSampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $eidSampleDb->fetchCovid19OutcomesByAgeInLabsDetails($params);
    }

    public function getCovid19PositivityRateDetails($params)
    {
        $eidSampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $eidSampleDb->fetchCovid19PositivityRateDetails($params);
    }

    /* End of lab dashboard */

    ////////////////////////////////////////
    /////////*** Turnaround Time ***///////
    ///////////////////////////////////////

    public function getTATbyProvince($labs, $startDate, $endDate)
    {
        // set_time_limit(10000);
        $result = array();
        $sampleDb = $this->sm->get('Covid19FormTable');
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
        $sampleDb = $this->sm->get('Covid19FormTable');
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
        $sampleDb = $this->sm->get('Covid19FormTable');
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

    public function getProvinceWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchProvinceWiseResultAwaitedDrillDown($params);
    }

    public function getLabWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchLabWiseResultAwaitedDrillDown($params);
    }

    public function getDistrictWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchDistrictWiseResultAwaitedDrillDown($params);
    }

    public function getClinicWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $sampleDb->fetchClinicWiseResultAwaitedDrillDown($params);
    }

    public function getFilterSampleResultAwaitedDetails($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
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
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchSampleDetails($params);
    }

    public function getBarSampleDetails($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchBarSampleDetails($params);
    }

    public function getLabFilterSampleDetails($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchLabFilterSampleDetails($parameters);
    }

    public function getFilterSampleDetails($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchFilterSampleDetails($parameters);
    }

    public function getFilterSampleTatDetails($parameters)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchFilterSampleTatDetails($parameters);
    }

    public function getLabBarSampleDetails($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchLabBarSampleDetails($params);
    }

    public function getIncompleteSampleDetails($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchIncompleteSampleDetails($params);
    }

    public function getIncompleteBarSampleDetails($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchIncompleteBarSampleDetails($params);
    }

    public function getSampleInfo($params, $dashTable = 'dash_vl_request_form')
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl' => $dashTable))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name', 'facility_code', 'facility_logo'), 'left')
            ->join(array('l_s' => 'location_details'), 'l_s.location_id=f.facility_state', array('provinceName' => 'location_name'), 'left')
            ->join(array('l_d' => 'location_details'), 'l_d.location_id=f.facility_district', array('districtName' => 'location_name'), 'left')
            ->join(array('rs' => 'r_eid_sample_type'), 'rs.sample_id=vl.sample_type', array('sample_name'), 'left')
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array('labName' => 'facility_name'), 'left')
            ->join(array('u' => 'user_details'), 'u.user_id=vl.result_approved_by', array('approvedBy' => 'user_name'), 'left')
            ->join(array('r_r_r' => 'r_eid_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection', array('rejection_reason_name'), 'left')
            ->join(array('rej_f' => 'facility_details'), 'rej_f.facility_id=vl.sample_rejection_facility', array('rejectionFacilityName' => 'facility_name'), 'left')
            ->where(array('vl.vl_sample_id' => $params['id']));
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
                        if (trim($params['result']) == '' || trim($params['result']) == 'rejected') {
                            $row[] = (isset($aRow['rejection_reason_name'])) ? ucwords($aRow['rejection_reason_name']) : '';
                        }
                        if (trim($params['result']) == '' || trim($params['result']) == 'result') {
                            $row[] = $sampleTestedDate;
                            $row[] = $aRow['result'];
                        }
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
                    if (trim($params['result']) == '') {
                        $sheet->setCellValue('D1', html_entity_decode($translator->translate('Rejection Reason'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('E1', html_entity_decode($translator->translate('Date Tested'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('F1', html_entity_decode($translator->translate('Viral Load(cp/ml)'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    } else if (trim($params['result']) == 'result') {
                        $sheet->setCellValue('D1', html_entity_decode($translator->translate('Date Tested'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('E1', html_entity_decode($translator->translate('Viral Load(cp/ml)'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    } else if (trim($params['result']) == 'rejected') {
                        $sheet->setCellValue('D1', html_entity_decode($translator->translate('Rejection Reason'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }

                    $sheet->getStyle('A1')->applyFromArray($styleArray);
                    $sheet->getStyle('B1')->applyFromArray($styleArray);
                    $sheet->getStyle('C1')->applyFromArray($styleArray);
                    if (trim($params['result']) == '') {
                        $sheet->getStyle('D1')->applyFromArray($styleArray);
                        $sheet->getStyle('E1')->applyFromArray($styleArray);
                        $sheet->getStyle('F1')->applyFromArray($styleArray);
                    } else if (trim($params['result']) == 'result') {
                        $sheet->getStyle('D1')->applyFromArray($styleArray);
                        $sheet->getStyle('E1')->applyFromArray($styleArray);
                    } else if (trim($params['result']) == 'rejected') {
                        $sheet->getStyle('D1')->applyFromArray($styleArray);
                    }
                    $currentRow = 2;
                    $endColumn = 5;
                    if (trim($params['result']) == 'result') {
                        $endColumn = 4;
                    } else if (trim($params['result']) == 'noresult') {
                        $endColumn = 2;
                    } else if (trim($params['result']) == 'rejected') {
                        $endColumn = 3;
                    }
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

    public function getCovid19OutComes($params)
    {
        $sampleDb = $this->sm->get('Covid19FormTable');
        return $sampleDb->fetchCovid19OutComes($params);
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
        $eidSampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $eidSampleDb->fetchEidOutcomesByAgeInLabsDetails($params);
    }

    public function getEidPositivityRateDetails($params)
    {
        $eidSampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        return $eidSampleDb->fetchEidPositivityRateDetails($params);
    }

    public function saveCovid19DataFromAPI($params)
    {
        // print_r("Hloo");die;
        $common = new CommonService();
        $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
        $facilityDb = $this->sm->get('FacilityTable');
        $testStatusDb = $this->sm->get('SampleStatusTable');
        $sampleTypeDb = $this->sm->get('SampleTypeTable');
        $covid19SampleRejectionDb = $this->sm->get('Covid19SampleRejectionReasonsTable');
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
                if (!empty(trim($row['sample_code'])) && trim($params['api_version']) == $config['defaults']['vl-api-version']) {
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
                        'patient_gender'                        => (trim($row['patient_gender']) != '' ? trim($row['patient_gender']) : NULL),
                        'patient_phone_number'                 => (trim($row['patient_phone_number']) != '' ? trim($row['patient_phone_number']) : NULL),
                        'patient_dob'                           => $dob,
                        'sample_collection_date'                => $sampleCollectionDate,
                        'sample_registered_at_lab'              => $sampleReceivedAtLab,
                        'result_printed_datetime'               => $resultPrinterDateTime,
                        'is_sample_rejected'                    => (trim($row['is_sample_rejected']) != '' ? strtolower($row['is_sample_rejected']) : NULL),
                        'is_patient_pregnant'                   => (trim($row['is_patient_pregnant']) != '' ? trim($row['is_patient_pregnant']) : NULL),
                        'result_approved_datetime'              => $resultApprovedDateTime,
                        'sample_tested_datetime'                => $sampleTestedDateTime,
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
                            $covid19SampleRejectionDb->update(array('rejection_reason_name' => trim($row['rejection_reason_name'])), array('rejection_reason_id' => $sampleRejectionReason['rejection_reason_id']));
                            $data['reason_for_sample_rejection'] = $sampleRejectionReason['rejection_reason_id'];
                        } else {
                            $covid19SampleRejectionDb->insert(array('rejection_reason_name' => trim($row['rejection_reason_name']), 'rejection_reason_status' => 'active'));
                            $data['reason_for_sample_rejection'] = $covid19SampleRejectionDb->lastInsertValue;
                        }
                    } else {
                        $data['reason_for_sample_rejection'] = NULL;
                    }

                    //check existing sample code
                    $sampleCode = $this->checkSampleCode($sampleCode, $instanceCode);
                    $status = 0;

                    if ($sampleCode) {
                        //sample data update   // $data, array('covid19_id' => $sampleCode['covid19_id'])
                        $status = $sampleDb->update($data, array('covid19_id' => $sampleCode['covid19_id']));
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
