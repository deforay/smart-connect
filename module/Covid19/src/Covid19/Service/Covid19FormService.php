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
    
    public function saveFileFromVlsmAPI(){
        try{
            // Debug::dump($_FILES['covid19File']);die;
            $apiData = array();
            $common = new CommonService();
            $sampleDb = $this->sm->get('Covid19FormTableWithoutCache');
            $facilityDb = $this->sm->get('FacilityTable');
            $facilityTypeDb = $this->sm->get('FacilityTypeTable');
            $testStatusDb = $this->sm->get('SampleStatusTable');
            $locationDb = $this->sm->get('LocationDetailsTable');
            $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');
            
            $fileName = $_FILES['covid19File']['name'];
            $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileName = $ranNumber . "." . $extension;

            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "uploads",0777);
            }
            if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19")) {
                mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19",0777);
            }

            $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19" . DIRECTORY_SEPARATOR . $fileName;
            if (!file_exists($pathname)) {
                if (move_uploaded_file($_FILES['covid19File']['tmp_name'], $pathname)) {
                    $apiData = \JsonMachine\JsonMachine::fromFile($pathname);
                }
            }

            if($apiData !== FALSE){
                foreach($apiData as $rowData){
                    // Debug::dump($rowData);die;
                    foreach($rowData as $key=>$row){
                        // Debug::dump($row);die;
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
                                    if($index != 'covid19_id'){
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

    public function checkSampleCode($sampleCode, $instanceCode, $dashTable = 'dash_covid19_form')
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from($dashTable)->where(array('sample_code LIKE "%'.$sampleCode.'%"', 'vlsm_instance_id' => $instanceCode));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }

    public function checkFacilityStateDistrictDetails($location, $parent)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('l' => 'location_details'))
            ->where(array('l.parent_location' => $parent, 'l.location_name' => trim($location)));
        $sQuery = $sql->getSqlStringForSqlObject($sQuery);
        $sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sQueryResult;
    }

    public function checkFacilityDetails($clinicName)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from('facility_details')->where(array('facility_name' => $clinicName));
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $fResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $fResult;
    }
    public function checkFacilityTypeDetails($facilityTypeName)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from('facility_type')->where(array('facility_type_name' => $facilityTypeName));
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $fResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $fResult;
    }
    public function checkTestingReson($testingReson)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $tQuery = $sql->select()->from('r_vl_test_reasons')->where(array('test_reason_name' => $testingReson));
        $tQueryStr = $sql->getSqlStringForSqlObject($tQuery);
        $tResult = $dbAdapter->query($tQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $tResult;
    }
    public function checkSampleStatus($testingStatus)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_sample_status')->where(array('status_name' => $testingStatus));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    public function checkSampleType($sampleType)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_covis19_sample_type')->where(array('sample_name' => $sampleType));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    public function checkSampleRejectionReason($rejectReasonName)
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_sample_rejection_reasons')->where(array('rejection_reason_name' => $rejectReasonName));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
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
                $sQueryStr = $sql->getSqlStringForSqlObject($queryContainer->indicatorSummaryQuery);
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

    public function exportPositiveRateByFacility($params, $dashTable = 'dash_covid19_form')
    {

        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        // To set te session table
        $logincontainer = new Container('credo');
        if (isset($logincontainer->EidSampleTable) && $logincontainer->EidSampleTable != "") {
            $dashTable = $logincontainer->EidSampleTable;
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
            $sQueryStr = $sql->getSqlStringForSqlObject($queryContainer->fetchAllSuppressionRateByFacility);
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
}