<?php

namespace Application\Service;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Expression;
use Application\Service\CommonService;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use Zend\Debug\Debug;
use JsonMachine\JsonMachine;

class SampleService
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

    public function checkSampleCode($sampleCode, $instanceCode = null, $dashTable = 'dash_vl_request_form')
    {
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from($dashTable)->where(array('sample_code LIKE "%' . $sampleCode . '%"'));
        if (isset($instanceCode) && $instanceCode != "") {
            $sQuery = $sQuery->where(array('vlsm_instance_id' => $instanceCode));
        }
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
        $sQuery = $sql->select()->from('r_vl_sample_type')->where(array('sample_name' => $sampleType));
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

    //lab details start
    //get sample status for lab dash
    public function getSampleStatusDataTable($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getSampleStatusDataTable($params);
    }

    //lab details start
    //get sample result details
    public function getSampleResultDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleResultDetails($params);
    }
    //get sample tested result details
    public function getSampleTestedResultDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultDetails($params);
    }

    //get sample tested result details
    public function getSampleTestedResultBasedVolumeDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultBasedVolumeDetails($params);
    }

    public function getSampleTestedResultGenderDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultGenderDetails($params);
    }

    public function getLabTurnAroundTime($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabTurnAroundTime($params);
    }

    public function getSampleTestedResultAgeGroupDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultAgeGroupDetails($params);
    }

    public function getSampleTestedResultPregnantPatientDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultPregnantPatientDetails($params);
    }

    public function getSampleTestedResultBreastfeedingPatientDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultBreastfeedingPatientDetails($params);
    }

    //get Requisition Forms tested
    public function getRequisitionFormsTested($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getRequisitionFormsTested($params);
    }

    public function getSampleVolume($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getSampleVolume($params);
    }

    public function getFemalePatientResult($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getFemalePatientResult($params);
    }

    public function getLineOfTreatment($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getLineOfTreatment($params);
    }

    public function getFacilites($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFacilites($params);
    }

    public function getVlOutComes($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getVlOutComes($params);
    }
    //lab details end

    //clinic details start
    public function getOverallViralLoadStatus($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchOverallViralLoadResult($params);
    }

    public function getViralLoadStatusBasedOnGender($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchViralLoadStatusBasedOnGender($params);
    }

    public function getSampleTestedResultBasedGenderDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultBasedGenderDetails($params);
    }

    public function fetchSampleTestedReason($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedReason($params);
    }

    public function getAllTestReasonName()
    {
        $reasonDb = $this->sm->get('TestReasonTable');
        return $reasonDb->fetchAllTestReasonName();
    }
    public function getClinicSampleTestedResultAgeGroupDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchClinicSampleTestedResultAgeGroupDetails($params);
    }
    public function getClinicRequisitionFormsTested($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchClinicRequisitionFormsTested($params);
    }
    //clinic details end

    //get all smaple type
    public function getSampleType()
    {
        $sampleDb = $this->sm->get('SampleTypeTable');
        return $sampleDb->fetchAllSampleType();
    }
    //get all Lab Name
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

    public function getAllTestResults($parameters)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllTestResults($parameters);
    }

    public function getClinicSampleTestedResults($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchClinicSampleTestedResults($params);
    }

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

    public function getSampleDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleDetails($params);
    }

    public function getBarSampleDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchBarSampleDetails($params);
    }

    public function getLabFilterSampleDetails($parameters)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabFilterSampleDetails($parameters);
    }

    public function getFilterSampleDetails($parameters)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFilterSampleDetails($parameters);
    }

    public function getFilterSampleTatDetails($parameters)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFilterSampleTatDetails($parameters);
    }

    public function getLabSampleDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabSampleDetails($params);
    }

    public function getLabBarSampleDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabBarSampleDetails($params);
    }

    public function getIncompleteSampleDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchIncompleteSampleDetails($params);
    }

    public function getIncompleteBarSampleDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
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
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.sample_type', array('sample_name'), 'left')
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array('labName' => 'facility_name'), 'left')
            ->join(array('u' => 'user_details'), 'u.user_id=vl.result_approved_by', array('approvedBy' => 'user_name'), 'left')
            ->join(array('r_r_r' => 'r_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection', array('rejection_reason_name'), 'left')
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
                //error_log($hQueryStr);die;
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
                        if (isset($aRow['treatmentInitiateDate']) && $aRow['treatmentInitiateDate'] != NULL && trim($aRow['treatmentInitiateDate']) != "" && $aRow['treatmentInitiateDate'] != '0000-00-00') {
                            $treatmentInitiateDate = $common->humanDateFormat($aRow['treatmentInitiateDate']);
                        }
                        if (isset($aRow['patientDOB']) && $aRow['patientDOB'] != NULL && trim($aRow['patientDOB']) != "" && $aRow['patientDOB'] != '0000-00-00') {
                            $patientDOB = $common->humanDateFormat($aRow['patientDOB']);
                        }
                        if (isset($aRow['treatmentInitiateCurrentRegimen']) && $aRow['treatmentInitiateCurrentRegimen'] != NULL && trim($aRow['treatmentInitiateCurrentRegimen']) != "" && $aRow['treatmentInitiateCurrentRegimen'] != '0000-00-00') {
                            $patientDOB = $common->humanDateFormat($aRow['patitreatmentInitiateCurrentRegimenentDOB']);
                        }
                        if (isset($aRow['requestDate']) && $aRow['requestDate'] != NULL && trim($aRow['requestDate']) != "" && $aRow['requestDate'] != '0000-00-00') {
                            $requestDate = $common->humanDateFormat($aRow['requestDate']);
                        }
                        if (isset($aRow['receivedAtLab']) && $aRow['receivedAtLab'] != NULL && trim($aRow['receivedAtLab']) != "" && $aRow['receivedAtLab'] != '0000-00-00') {
                            $requestDate = $common->humanDateFormat($aRow['receivedAtLab']);
                        }
                        $row[] = $i;
                        $row[] = $aRow['sample_code'];
                        $row[] = ucwords($aRow['facility_name']);
                        $row[] = $aRow['facility_code'];
                        $row[] = $aRow['facilityDistrict'];
                        $row[] = $aRow['facilityState'];
                        $row[] = $aRow['patient_art_no'];
                        $row[] = $patientDOB;
                        $row[] = $aRow['patient_age_in_years'];
                        $row[] = $aRow['patient_gender'];
                        $row[] = $sampleCollectionDate;
                        $row[] = $aRow['sample_name'];
                        $row[] = $treatmentInitiateDate;
                        $row[] = $aRow['current_regimen'];
                        $row[] = $treatmentInitiateDate;
                        $row[] = $aRow['is_patient_pregnant'];
                        $row[] = $aRow['is_patient_breastfeeding'];
                        $row[] = $aRow['arv_adherance_percentage'];
                        $row[] = $aRow['request_clinician_name'];
                        $row[] = $requestDate;
                        $row[] = $aRow['receivedAtLab'];
                        $row[] = $aRow['result'];
                        $row[] = $aRow['result_value_log'];
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
                    $sheet->setCellValue('G1', html_entity_decode($translator->translate('Unique ART No.'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('I1', html_entity_decode($translator->translate('Date of Birth'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('J1', html_entity_decode($translator->translate('Age'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('K1', html_entity_decode($translator->translate('Gender'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('L1', html_entity_decode($translator->translate('Date of Sample Collection'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('M1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('N1', html_entity_decode($translator->translate('Date of Treatment Initiation'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('O1', html_entity_decode($translator->translate('Current Regimen'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('P1', html_entity_decode($translator->translate('Date of Initiation of Current Regimen'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('Q1', html_entity_decode($translator->translate('Is Patient Pregnant'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('R1', html_entity_decode($translator->translate('Is Patient Breastfeeding'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('S1', html_entity_decode($translator->translate('ARV Adherence'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('T1', html_entity_decode($translator->translate('Requesting Clinican'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('U1', html_entity_decode($translator->translate('Request Date'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('V1', html_entity_decode($translator->translate('Date Sample Received at Lab'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('W1', html_entity_decode($translator->translate('VL Result (cp/ml)'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('X1', html_entity_decode($translator->translate('Vl Result (log)'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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
                            $row[] = $aRow['suppressed_samples'];
                            $row[] = $aRow['not_suppressed_samples'];
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
                        $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Suppressed'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Not Suppressed'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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
    public function generateLabTestedSampleTatExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if (isset($queryContainer->sampleResultTestedTATQuery)) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->sampleResultTestedTATQuery);
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
                        $row[] = $aRow['monthDate'];
                        $row[] = $aRow['total_samples_received'];
                        $row[] = $aRow['total_samples_tested'];
                        $row[] = $aRow['total_samples_pending'];
                        $row[] = $aRow['suppressed_samples'];
                        $row[] = $aRow['not_suppressed_samples'];
                        $row[] = $aRow['rejected_samples'];
                        $row[] = (isset($aRow['AvgDiff'])) ? round($aRow['AvgDiff'], 2) : 0;
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

                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Month and Year'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Samples Collected'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Samples Tested'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Samples Pending'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Suppressed'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Not Suppressed'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('G1', html_entity_decode($translator->translate('Samples Rejected'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue('H1', html_entity_decode($translator->translate('Average TAT in Days'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

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
                            if ($colNo > 7) {
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
                    $filename = 'LAB-TAT-REPORT--' . date('d-M-Y-H-i-s') . '.xlsx';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                } else {
                    return "";
                }
            } catch (Exception $exc) {
                error_log("LAB-TAT-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
            }
        } else {
            return "";
        }
    }

    public function getProvinceWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchProvinceWiseResultAwaitedDrillDown($params);
    }

    public function getLabWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabWiseResultAwaitedDrillDown($params);
    }

    public function getDistrictWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchDistrictWiseResultAwaitedDrillDown($params);
    }

    public function getClinicWiseResultAwaitedDrillDown($params)
    {
        $sampleDb = $this->sm->get('SampleTableWithoutCache');
        return $sampleDb->fetchClinicWiseResultAwaitedDrillDown($params);
    }

    public function getFilterSampleResultAwaitedDetails($parameters)
    {
        $sampleDb = $this->sm->get('SampleTable');
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

    public function getAllSamples($parameters)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSamples($parameters);
    }

    public function removeDuplicateSampleRows($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->removeDuplicateSampleRows($params);
    }

    public function getVLTestReasonBasedOnAgeGroup($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getVLTestReasonBasedOnAgeGroup($params);
    }

    public function getVLTestReasonBasedOnGender($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getVLTestReasonBasedOnGender($params);
    }

    public function getVLTestReasonBasedOnClinics($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getVLTestReasonBasedOnClinics($params);
    }

    public function getSample($id)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getSample($id);
    }
    ////////////////////////////////////////
    /////////*** Turnaround Time ***///////
    ///////////////////////////////////////

    public function getTATbyProvince($labs, $startDate, $endDate)
    {
        // set_time_limit(10000);
        $result = array();
        $resultSet = array();
        $sampleDb = $this->sm->get('SampleTableWithoutCache');
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
        $sampleDb = $this->sm->get('SampleTable');
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
        $sampleDb = $this->sm->get('SampleTable');
        $resultSet = $sampleDb->getTATbyClinic($labs, $startDate, $endDate);
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

    public function importSampleResultFile()
    {
        $pathname = UPLOAD_PATH . DIRECTORY_SEPARATOR . "not-import-vl";
        $common = new CommonService();
        $sampleDb = $this->sm->get('SampleTableWithoutCache');
        $facilityDb = $this->sm->get('FacilityTableWithoutCache');
        $facilityTypeDb = $this->sm->get('FacilityTypeTable');
        $testStatusDb = $this->sm->get('SampleStatusTable');
        $testReasonDb = $this->sm->get('TestReasonTable');
        $sampleTypeDb = $this->sm->get('SampleTypeTable');
        $locationDb = $this->sm->get('LocationDetailsTable');
        $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        //$files = scandir($pathname, SCANDIR_SORT_DESCENDING);

        $files = glob($pathname . DIRECTORY_SEPARATOR . '*.{xls,xlsx,csv}', GLOB_BRACE);

        array_multisort(
            array_map('filemtime', $files),
            SORT_NUMERIC,
            SORT_DESC,
            $files
        );

        $fileName = $files[0]; // OLDEST FILE

        if (trim($fileName) != "") {
            try {
                if (file_exists($pathname . DIRECTORY_SEPARATOR . $fileName)) {
                    $objPHPExcel = \PhpOffice\PhpSpreadsheet\IOFactory::load($pathname . DIRECTORY_SEPARATOR . $fileName);
                    $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
                    $count = count($sheetData);
                    for ($i = 2; $i <= $count; $i++) {
                        if (trim($sheetData[$i]['A']) != '' && trim($sheetData[$i]['B']) != '') {


                            $sampleCode = trim($sheetData[$i]['A']);
                            $instanceCode = trim($sheetData[$i]['B']);


                            $VLAnalysisResult = (float) $sheetData[$i]['AN'];
                            $DashVL_Abs = NULL;
                            $DashVL_AnalysisResult = NULL;

                            if (
                                $sheetData[$i]['AM'] == 'Target not Detected' || $sheetData[$i]['AM'] == 'Target Not Detected' || strtolower($sheetData[$i]['AM']) == 'target not detected' || strtolower($sheetData[$i]['AM']) == 'tnd'
                                || $sheetData[$i]['AO'] == 'Target not Detected' || $sheetData[$i]['AO'] == 'Target Not Detected' || strtolower($sheetData[$i]['AO']) == 'target not detected' || strtolower($sheetData[$i]['AO']) == 'tnd'
                            ) {
                                $VLAnalysisResult = 20;
                            } else if ($sheetData[$i]['AM'] == '< 20' || $sheetData[$i]['AM'] == '<20' || $sheetData[$i]['AO'] == '< 20' || $sheetData[$i]['AO'] == '<20') {
                                $VLAnalysisResult = 20;
                            } else if ($sheetData[$i]['AM'] == '< 40' || $sheetData[$i]['AM'] == '<40' || $sheetData[$i]['AO'] == '< 40' || $sheetData[$i]['AO'] == '<40') {
                                $VLAnalysisResult = 40;
                            } else if ($sheetData[$i]['AM'] == 'Nivel de detecÁao baixo' || $sheetData[$i]['AM'] == 'NÌvel de detecÁ„o baixo' || $sheetData[$i]['AO'] == 'Nivel de detecÁao baixo' || $sheetData[$i]['AO'] == 'NÌvel de detecÁ„o baixo') {
                                $VLAnalysisResult = 20;
                            } else if ($sheetData[$i]['AM'] == 'Suppressed' || $sheetData[$i]['AO'] == 'Suppressed') {
                                $VLAnalysisResult = 500;
                            } else if ($sheetData[$i]['AM'] == 'Not Suppressed' || $sheetData[$i]['AO'] == 'Not Suppressed') {
                                $VLAnalysisResult = 1500;
                            } else if ($sheetData[$i]['AM'] == 'Negative' || $sheetData[$i]['AM'] == 'NEGAT' || $sheetData[$i]['AO'] == 'Negative' || $sheetData[$i]['AO'] == 'NEGAT') {
                                $VLAnalysisResult = 20;
                            } else if ($sheetData[$i]['AM'] == 'Positive' || $sheetData[$i]['AO'] == 'Positive') {
                                $VLAnalysisResult = 1500;
                            } else if ($sheetData[$i]['AM'] == 'Indeterminado' || $sheetData[$i]['AO'] == 'Indeterminado') {
                                $VLAnalysisResult = "";
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




                            $sampleCollectionDate = (trim($sheetData[$i]['U']) != '' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['U']))) : null);
                            $sampleReceivedAtLab = (trim($sheetData[$i]['AS']) != '' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['AS']))) : null);
                            $dateOfInitiationOfRegimen = (trim($sheetData[$i]['BA']) != '' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['BA']))) : null);
                            $resultApprovedDateTime = (trim($sheetData[$i]['BD']) != '' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['BD']))) : null);
                            $sampleTestedDateTime = (trim($sheetData[$i]['AJ']) != '' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['AJ']))) : null);
                            $sampleRegisteredAtLabDateTime = (trim($sheetData[$i]['BH']) != '' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['BH']))) : null);




                            $data = array(
                                'sample_code' => $sampleCode,
                                'vlsm_instance_id' => trim($sheetData[$i]['B']),
                                'source' => '1',
                                'patient_gender' => (trim($sheetData[$i]['C']) != '' ? trim($sheetData[$i]['C']) : NULL),
                                'patient_age_in_years' => (trim($sheetData[$i]['D']) != '' ? trim($sheetData[$i]['D']) : NULL),
                                'sample_collection_date' => $sampleCollectionDate,
                                'sample_registered_at_lab' => $sampleReceivedAtLab,
                                'line_of_treatment' => (trim($sheetData[$i]['AT']) != '' ? trim($sheetData[$i]['AT']) : NULL),
                                'is_sample_rejected' => (trim($sheetData[$i]['AU']) != '' ? trim($sheetData[$i]['AU']) : NULL),
                                'is_patient_pregnant' => (trim($sheetData[$i]['AX']) != '' ? trim($sheetData[$i]['AX']) : NULL),
                                'is_patient_breastfeeding' => (trim($sheetData[$i]['AY']) != '' ? trim($sheetData[$i]['AY']) : NULL),
                                'current_regimen' => (trim($sheetData[$i]['AZ']) != '' ? trim($sheetData[$i]['AZ']) : NULL),
                                'date_of_initiation_of_current_regimen' => $dateOfInitiationOfRegimen,
                                'arv_adherance_percentage' => (trim($sheetData[$i]['BB']) != '' ? trim($sheetData[$i]['BB']) : NULL),
                                'is_adherance_poor' => (trim($sheetData[$i]['BC']) != '' ? trim($sheetData[$i]['BC']) : NULL),
                                'result_approved_datetime' => $resultApprovedDateTime,
                                'sample_tested_datetime' => $sampleTestedDateTime,
                                'result_value_log' => (trim($sheetData[$i]['AK']) != '' ? trim($sheetData[$i]['AK']) : NULL),
                                'result_value_absolute' => (trim($sheetData[$i]['AL']) != '' ? trim($sheetData[$i]['AL']) : NULL),
                                'result_value_text' => (trim($sheetData[$i]['AM']) != '' ? trim($sheetData[$i]['AM']) : NULL),
                                'result_value_absolute_decimal' => (trim($sheetData[$i]['AN']) != '' ? trim($sheetData[$i]['AN']) : NULL),
                                'result' => (trim($sheetData[$i]['AO']) != '' ? trim($sheetData[$i]['AO']) : NULL),
                                'DashVL_Abs' =>   $DashVL_Abs,
                                'DashVL_AnalysisResult' =>   $DashVL_AnalysisResult,
                                'current_regimen' => (trim($sheetData[$i]['BG']) != '' ? trim($sheetData[$i]['BG']) : NULL),
                                'sample_registered_at_lab' => $sampleRegisteredAtLabDateTime
                            );


                            $facilityData = array(
                                'vlsm_instance_id' => trim($sheetData[$i]['B']),
                                'facility_name' => trim($sheetData[$i]['E']),
                                'facility_code' => trim($sheetData[$i]['F']),
                                'facility_mobile_numbers' => trim($sheetData[$i]['I']),
                                'address' => trim($sheetData[$i]['J']),
                                'facility_hub_name' => trim($sheetData[$i]['K']),
                                'contact_person' => trim($sheetData[$i]['L']),
                                'report_email' => trim($sheetData[$i]['M']),
                                'country' => trim($sheetData[$i]['N']),
                                'facility_state' => trim($sheetData[$i]['G']),
                                'facility_district' => trim($sheetData[$i]['H']),
                                'longitude' => trim($sheetData[$i]['O']),
                                'latitude' => trim($sheetData[$i]['P']),
                                'status' => trim($sheetData[$i]['Q']),
                            );
                            if (trim($sheetData[$i]['G']) != '') {
                                $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($sheetData[$i]['G']), 0);
                                if ($sQueryResult) {
                                    $facilityData['facility_state'] = $sQueryResult['location_id'];
                                } else {
                                    $locationDb->insert(array('parent_location' => 0, 'location_name' => trim($sheetData[$i]['G'])));
                                    $facilityData['facility_state'] = $locationDb->lastInsertValue;
                                }
                            }
                            if (trim($sheetData[$i]['H']) != '') {
                                $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($sheetData[$i]['H']), $facilityData['facility_state']);
                                if ($sQueryResult) {
                                    $facilityData['facility_district'] = $sQueryResult['location_id'];
                                } else {
                                    $locationDb->insert(array('parent_location' => $facilityData['facility_state'], 'location_name' => trim($sheetData[$i]['H'])));
                                    $facilityData['facility_district'] = $locationDb->lastInsertValue;
                                }
                            }
                            //check facility type
                            if (trim($sheetData[$i]['R']) != '') {
                                $facilityTypeDataResult = $this->checkFacilityTypeDetails(trim($sheetData[$i]['R']));
                                if ($facilityTypeDataResult) {
                                    $facilityData['facility_type'] = $facilityTypeDataResult['facility_type_id'];
                                } else {
                                    $facilityTypeDb->insert(array('facility_type_name' => trim($sheetData[$i]['R'])));
                                    $facilityData['facility_type'] = $facilityTypeDb->lastInsertValue;
                                }
                            }

                            //check clinic details
                            if (trim($sheetData[$i]['E']) != '') {
                                $facilityDataResult = $this->checkFacilityDetails(trim($sheetData[$i]['E']));
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
                                'vlsm_instance_id' => trim($sheetData[$i]['B']),
                                'facility_name' => trim($sheetData[$i]['V']),
                                'facility_code' => trim($sheetData[$i]['W']),
                                'facility_state' => trim($sheetData[$i]['X']),
                                'facility_district' => trim($sheetData[$i]['Y']),
                                'facility_mobile_numbers' => trim($sheetData[$i]['Z']),
                                'address' => trim($sheetData[$i]['AA']),
                                'facility_hub_name' => trim($sheetData[$i]['AB']),
                                'contact_person' => trim($sheetData[$i]['AC']),
                                'report_email' => trim($sheetData[$i]['AD']),
                                'country' => trim($sheetData[$i]['AE']),
                                'longitude' => trim($sheetData[$i]['AF']),
                                'latitude' => trim($sheetData[$i]['AG']),
                                'status' => trim($sheetData[$i]['AH']),
                            );
                            if (trim($sheetData[$i]['X']) != '') {
                                $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($sheetData[$i]['X']), 0);
                                if ($sQueryResult) {
                                    $labData['facility_state'] = $sQueryResult['location_id'];
                                } else {
                                    $locationDb->insert(array('parent_location' => 0, 'location_name' => trim($sheetData[$i]['X'])));
                                    $labData['facility_state'] = $locationDb->lastInsertValue;
                                }
                            }
                            if (trim($sheetData[$i]['Y']) != '') {
                                $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($sheetData[$i]['Y']), $labData['facility_state']);
                                if ($sQueryResult) {
                                    $labData['facility_district'] = $sQueryResult['location_id'];
                                } else {
                                    $locationDb->insert(array('parent_location' => $labData['facility_state'], 'location_name' => trim($sheetData[$i]['Y'])));
                                    $labData['facility_district'] = $locationDb->lastInsertValue;
                                }
                            }
                            //check lab type
                            if (trim($sheetData[$i]['AI']) != '') {
                                $labTypeDataResult = $this->checkFacilityTypeDetails(trim($sheetData[$i]['AI']));
                                if ($labTypeDataResult) {
                                    $labData['facility_type'] = $labTypeDataResult['facility_type_id'];
                                } else {
                                    $facilityTypeDb->insert(array('facility_type_name' => trim($sheetData[$i]['AI'])));
                                    $labData['facility_type'] = $facilityTypeDb->lastInsertValue;
                                }
                            }

                            //check lab details
                            if (trim($sheetData[$i]['V']) != '') {
                                $labDataResult = $this->checkFacilityDetails(trim($sheetData[$i]['V']));
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
                            if (trim($sheetData[$i]['AP']) != '') {
                                $testReasonResult = $this->checkTestingReson(trim($sheetData[$i]['AP']));
                                if ($testReasonResult) {
                                    $testReasonDb->update(array('test_reason_name' => trim($sheetData[$i]['AP']), 'test_reason_status' => trim($sheetData[$i]['AQ'])), array('test_reason_id' => $testReasonResult['test_reason_id']));
                                    $data['reason_for_vl_testing'] = $testReasonResult['test_reason_id'];
                                } else {
                                    $testReasonDb->insert(array('test_reason_name' => trim($sheetData[$i]['AP']), 'test_reason_status' => trim($sheetData[$i]['AQ'])));
                                    $data['reason_for_vl_testing'] = $testReasonDb->lastInsertValue;
                                }
                            } else {
                                $data['reason_for_vl_testing'] = 0;
                            }
                            //check testing reason
                            if (trim($sheetData[$i]['AR']) != '') {
                                $sampleStatusResult = $this->checkSampleStatus(trim($sheetData[$i]['AR']));
                                if ($sampleStatusResult) {
                                    $data['result_status'] = $sampleStatusResult['status_id'];
                                } else {
                                    $testStatusDb->insert(array('status_name' => trim($sheetData[$i]['AR'])));
                                    $data['result_status'] = $testStatusDb->lastInsertValue;
                                }
                            } else {
                                $data['result_status'] = 6;
                            }
                            //check sample type
                            if (trim($sheetData[$i]['S']) != '') {
                                $sampleType = $this->checkSampleType(trim($sheetData[$i]['S']));
                                if ($sampleType) {
                                    $sampleTypeDb->update(array('sample_name' => trim($sheetData[$i]['S']), 'status' => trim($sheetData[$i]['T'])), array('sample_id' => $sampleType['sample_id']));
                                    $data['sample_type'] = $sampleType['sample_id'];
                                } else {
                                    $sampleTypeDb->insert(array('sample_name' => trim($sheetData[$i]['S']), 'status' => trim($sheetData[$i]['T'])));
                                    $data['sample_type'] = $sampleTypeDb->lastInsertValue;
                                }
                            } else {
                                $data['sample_type'] = NULL;
                            }
                            //check sample rejection reason
                            if (trim($sheetData[$i]['AV']) != '') {
                                $sampleRejectionReason = $this->checkSampleRejectionReason(trim($sheetData[$i]['AV']));
                                if ($sampleRejectionReason) {
                                    $sampleRjtReasonDb->update(array('rejection_reason_name' => trim($sheetData[$i]['AV']), 'rejection_reason_status' => trim($sheetData[$i]['AW'])), array('rejection_reason_id' => $sampleRejectionReason['rejection_reason_id']));
                                    $data['reason_for_sample_rejection'] = $sampleRejectionReason['rejection_reason_id'];
                                } else {
                                    $sampleRjtReasonDb->insert(array('rejection_reason_name' => trim($sheetData[$i]['AV']), 'rejection_reason_status' => trim($sheetData[$i]['AW'])));
                                    $data['reason_for_sample_rejection'] = $sampleRjtReasonDb->lastInsertValue;
                                }
                            } else {
                                $data['reason_for_sample_rejection'] = NULL;
                            }

                            //check existing sample code
                            $sampleCode = $this->checkSampleCode($sampleCode, $instanceCode);
                            if ($sampleCode) {
                                //sample data update
                                $sampleDb->update($data, array('vl_sample_id' => $sampleCode['vl_sample_id']));
                            } else {
                                //sample data insert
                                $sampleDb->insert($data);
                            }
                        }
                    }

                    $destination = UPLOAD_PATH . DIRECTORY_SEPARATOR . "import-vl";
                    if (!file_exists($destination) && !is_dir($destination)) {
                        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "import-vl");
                    }

                    if (copy($pathname . DIRECTORY_SEPARATOR . $fileName, $destination . DIRECTORY_SEPARATOR . $fileName)) {
                        unlink($pathname . DIRECTORY_SEPARATOR . $fileName);
                    }
                }
            } catch (Exception $exc) {
                error_log($exc->getMessage());
                error_log($exc->getTraceAsString());
            }
        }
    }
    public function getSampleTestReasonBarChartDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestReasonBarChartDetails($params);
    }
    //api for fecth samples
    public function getSourceData($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSourceData($params);
    }

    // public function generateSampleStatusResultExcel($params){
    //     $queryContainer = new Container('query');
    //     $translator = $this->sm->get('translator');
    //     $common = new CommonService();
    //     if(isset($queryContainer->sampleStatusResultQuery)){
    //         try{
    //             $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
    //             $sql = new Sql($dbAdapter);
    //             $sQueryStr = $sql->buildSqlString($queryContainer->sampleStatusResultQuery);

    //             $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    //             if(isset($sResult) && count($sResult)>0){
    //                 $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    //                 $cacheMethod = \PhpOffice\PhpSpreadsheet\Collection\CellsFactory::cache_to_phpTemp;
    //                 $cacheSettings = array('memoryCacheSize' => '80MB');
    //                 \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
    //                 $sheet = $excel->getActiveSheet();
    //                 $output = array();
    //                 foreach ($sResult as $aRow) {
    //                     $row = array();

    //                     $row[]=$aRow['monthyear'];
    //                     $row[] = ucwords($aRow['facility_name']);
    //                     $row[]=$aRow['district'];
    //                     $row[]=$aRow['lab_name'];
    //                     $row[]=$aRow['total_samples_received'];
    //                     $row[]=$aRow['total_samples_tested'];
    //                     $row[]=$aRow['total_samples_rejected'];
    //                     $row[]=$aRow['total_hvl_samples'];
    //                     $row[]=$aRow['total_lvl_samples'];

    //                     $output[] = $row;
    //                 }
    //                 $styleArray = array(
    //                     'font' => array(
    //                         'bold' => true,
    //                     ),
    //                     'alignment' => array(
    //                         'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    //                         'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    //                     ),
    //                     'borders' => array(
    //                         'outline' => array(
    //                             'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
    //                         ),
    //                     )
    //                 );
    //                 $borderStyle = array(
    //                     'alignment' => array(
    //                         'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
    //                     ),
    //                     'borders' => array(
    //                         'outline' => array(
    //                             'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
    //                         ),
    //                     )
    //                 );

    //                 $sheet->setCellValue('A1', html_entity_decode($translator->translate('Month and Year'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //                 $sheet->setCellValue('B1', html_entity_decode($translator->translate('Facility Name'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //                 $sheet->setCellValue('C1', html_entity_decode($translator->translate('District'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //                 $sheet->setCellValue('D1', html_entity_decode($translator->translate('Lab Name'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //                 $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Registered'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //                 $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Tested'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //                 $sheet->setCellValue('G1', html_entity_decode($translator->translate('Samples Rejected'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //                 $sheet->setCellValue('H1', html_entity_decode($translator->translate('No.Of High VL'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //                 $sheet->setCellValue('I1', html_entity_decode($translator->translate('No.Of Low VL'), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);


    //                 $sheet->getStyle('A1')->applyFromArray($styleArray);
    //                 $sheet->getStyle('B1')->applyFromArray($styleArray);
    //                 $sheet->getStyle('C1')->applyFromArray($styleArray);
    //                 $sheet->getStyle('D1')->applyFromArray($styleArray);
    //                 $sheet->getStyle('E1')->applyFromArray($styleArray);
    //                 $sheet->getStyle('F1')->applyFromArray($styleArray);
    //                 $sheet->getStyle('G1')->applyFromArray($styleArray);
    //                 $sheet->getStyle('H1')->applyFromArray($styleArray);
    //                 $sheet->getStyle('I1')->applyFromArray($styleArray);

    //                 $currentRow = 2;
    //                 foreach ($output as $rowData) {
    //                     $colNo = 0;
    //                     foreach ($rowData as $field => $value) {
    //                         if (!isset($value)) {
    //                             $value = "";
    //                         }
    //                         if($colNo > 8){
    //                             break;
    //                         }
    //                         if (is_numeric($value)) {
    //                             $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    //                         }else{
    //                             $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //                         }
    //                         $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
    //                         $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
    //                         $sheet->getDefaultRowDimension()->setRowHeight(20);
    //                         $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
    //                         $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getAlignment()->setWrapText(true);
    //                         $colNo++;
    //                     }
    //                   $currentRow++;
    //                 }
    //                 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    //                 $filename = 'SAMPLE-STATUS-RESULT-REPORT--' . date('d-M-Y-H-i-s') . '.xlsx';
    //                 $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
    //                 return $filename;
    //             }else{
    //                 return "";
    //             }
    //          }catch (Exception $exc) {
    //             error_log("SAMPLE-STATUS-RESULT-REPORT--" . $exc->getMessage());
    //             error_log($exc->getTraceAsString());
    //             return "";
    //          }  
    //     }else{
    //         return "";
    //     }

    // }


    public function generateBackup()
    {
        $sampleDb = $this->sm->get('SampleTableWithoutCache');
        $response = $sampleDb->generateBackup();
        if (isset($response['fileName']) && file_exists($response['fileName'])) {
            $generateBackupDb = $this->sm->get('GenerateBackupTable');
            $generateBackupDb->completeBackup($response['backupId']);
        }
    }

    public function saveFileFromVlsmAPIV2()
    {
        $apiTrackDb = $this->sm->get('DashApiReceiverStatsTable');

        $apiData = array();
        $this->config = $this->sm->get('Config');
        $input = $this->config['db']['dsn'];
        preg_match('~=(.*?);~', $input, $output);
        $dbname = $output[1];
        $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');

        $fileName = $_FILES['vlFile']['name'];
        $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = $ranNumber . "." . $extension;

        if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
            mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "uploads", 0777);
        }
        if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl")) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl", 0777);
        }

        $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl" . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($pathname)) {
            if (move_uploaded_file($_FILES['vlFile']['tmp_name'], $pathname)) {
                $apiData = json_decode(file_get_contents($pathname), true);
                //$apiData = \JsonMachine\JsonMachine::fromFile($pathname);
            }
        }

        // ob_start();
        // var_dump($apiData);
        // error_log(ob_get_clean());


        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $dbname . "' AND table_name='dash_vl_request_form'";
        $sResult = $dbAdapter->query($allColumns, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $columnList = array_map('current', $sResult);

        $removeKeys = array(
            'vl_sample_id'
        );

        $columnList = array_diff($columnList, $removeKeys);
        $sampleDb = $this->sm->get('SampleTableWithoutCache');
        // Debug::dump($apiData);die;

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
                $numRows += $sampleDb->update($data, array('vl_sample_id' => $sampleCode['vl_sample_id']));
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
        $apiData = array();
        $common = new CommonService();
        $sampleDb = $this->sm->get('SampleTableWithoutCache');
        $facilityDb = $this->sm->get('FacilityTable');
        $facilityTypeDb = $this->sm->get('FacilityTypeTable');
        $testStatusDb = $this->sm->get('SampleStatusTable');
        $testReasonDb = $this->sm->get('TestReasonTable');
        $sampleTypeDb = $this->sm->get('SampleTypeTable');
        $locationDb = $this->sm->get('LocationDetailsTable');
        $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');

        $fileName = $_FILES['vlFile']['name'];
        $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileName = $ranNumber . "." . $extension;

        if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
            mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "temporary", 0777);
        }
        if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl")) {
            mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl", 0777);
        }

        $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl" . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($pathname)) {
            if (move_uploaded_file($_FILES['vlFile']['tmp_name'], $pathname)) {
                $apiData = (array)json_decode(file_get_contents($pathname));
                //$apiData = \JsonMachine\JsonMachine::fromFile($pathname);
            }
        }
        ob_start();
        var_dump(file_exists($pathname));
        error_log(ob_get_clean());


        if ($apiData !== FALSE) {
            foreach ($apiData['data'] as $rowData) {
                ob_start();
                var_dump($rowData);
                error_log(ob_get_clean());
                exit(0);
                foreach ($rowData as $row) {
                    if (trim($row['sample_code']) != '' && trim($row['vlsm_instance_id']) != '') {
                        $sampleCode = trim($row['sample_code']);
                        $instanceCode = trim($row['vlsm_instance_id']);

                        $VLAnalysisResult = (float) $row['result_value_absolute_decimal'];
                        $DashVL_Abs = NULL;
                        $DashVL_AnalysisResult = NULL;

                        if (
                            $row['result_value_text'] == 'Target not Detected' || $row['result_value_text'] == 'Target Not Detected' || strtolower($row['result_value_text']) == 'target not detected' || strtolower($row['result_value_text']) == 'tnd'
                            || $row['result'] == 'Target not Detected' || $row['result'] == 'Target Not Detected' || strtolower($row['result']) == 'target not detected' || strtolower($row['result']) == 'tnd'
                        ) {
                            $VLAnalysisResult = 20;
                        } else if ($row['result_value_text'] == '< 20' || $row['result_value_text'] == '<20' || $row['result'] == '< 20' || $row['result'] == '<20') {
                            $VLAnalysisResult = 20;
                        } else if ($row['result_value_text'] == '< 40' || $row['result_value_text'] == '<40' || $row['result'] == '< 40' || $row['result'] == '<40') {
                            $VLAnalysisResult = 40;
                        } else if ($row['result_value_text'] == 'Nivel de detecÁao baixo' || $row['result_value_text'] == 'NÌvel de detecÁ„o baixo' || $row['result'] == 'Nivel de detecÁao baixo' || $row['result'] == 'NÌvel de detecÁ„o baixo') {
                            $VLAnalysisResult = 20;
                        } else if ($row['result_value_text'] == 'Suppressed' || $row['result'] == 'Suppressed') {
                            $VLAnalysisResult = 500;
                        } else if ($row['result_value_text'] == 'Not Suppressed' || $row['result'] == 'Not Suppressed') {
                            $VLAnalysisResult = 1500;
                        } else if ($row['result_value_text'] == 'Negative' || $row['result_value_text'] == 'NEGAT' || $row['result'] == 'Negative' || $row['result'] == 'NEGAT') {
                            $VLAnalysisResult = 20;
                        } else if ($row['result_value_text'] == 'Positive' || $row['result'] == 'Positive') {
                            $VLAnalysisResult = 1500;
                        } else if ($row['result_value_text'] == 'Indeterminado' || $row['result'] == 'Indeterminado') {
                            $VLAnalysisResult = "";
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




                        $sampleCollectionDate = (trim($row['sample_collection_date']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_collection_date']))) : null);
                        $sampleReceivedAtLab = (trim($row['sample_registered_at_lab']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_registered_at_lab']))) : null);
                        $dateOfInitiationOfRegimen = (trim($row['date_of_initiation_of_current_regimen']) != '' ? trim(date('Y-m-d H:i', strtotime($row['date_of_initiation_of_current_regimen']))) : null);
                        $resultApprovedDateTime = (trim($row['result_approved_datetime']) != '' ? trim(date('Y-m-d H:i', strtotime($row['result_approved_datetime']))) : null);
                        $sampleTestedDateTime = (trim($row['sample_tested_datetime']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_tested_datetime']))) : null);
                        $sampleRegisteredAtLabDateTime = (trim($row['sample_registered_at_lab']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_registered_at_lab']))) : null);




                        $data = array(
                            'sample_code'                           => $sampleCode,
                            'vlsm_instance_id'                      => trim($row['vlsm_instance_id']),
                            'source'                                => '1',
                            'patient_gender'                        => (trim($row['patient_gender']) != '' ? trim($row['patient_gender']) : NULL),
                            'patient_age_in_years'                  => (trim($row['patient_age_in_years']) != '' ? trim($row['patient_age_in_years']) : NULL),
                            'sample_collection_date'                => $sampleCollectionDate,
                            'sample_registered_at_lab'              => $sampleReceivedAtLab,
                            'line_of_treatment'                     => (trim($row['line_of_treatment']) != '' ? trim($row['line_of_treatment']) : NULL),
                            'is_sample_rejected'                    => (trim($row['is_sample_rejected']) != '' ? trim($row['is_sample_rejected']) : NULL),
                            'is_patient_pregnant'                   => (trim($row['is_patient_pregnant']) != '' ? trim($row['is_patient_pregnant']) : NULL),
                            'is_patient_breastfeeding'              => (trim($row['is_patient_breastfeeding']) != '' ? trim($row['is_patient_breastfeeding']) : NULL),
                            'current_regimen'                       => (trim($row['current_regimen']) != '' ? trim($row['current_regimen']) : NULL),
                            'date_of_initiation_of_current_regimen' => $dateOfInitiationOfRegimen,
                            'arv_adherance_percentage'              => (trim($row['arv_adherance_percentage']) != '' ? trim($row['arv_adherance_percentage']) : NULL),
                            'is_adherance_poor'                     => (trim($row['is_adherance_poor']) != '' ? trim($row['is_adherance_poor']) : NULL),
                            'result_approved_datetime'              => $resultApprovedDateTime,
                            'sample_tested_datetime'                => $sampleTestedDateTime,
                            'result_value_log'                      => (trim($row['result_value_log']) != '' ? trim($row['result_value_log']) : NULL),
                            'result_value_absolute'                 => (trim($row['result_value_absolute']) != '' ? trim($row['result_value_absolute']) : NULL),
                            'result_value_text'                     => (trim($row['result_value_text']) != '' ? trim($row['result_value_text']) : NULL),
                            'result_value_absolute_decimal'         => (trim($row['result_value_absolute_decimal']) != '' ? trim($row['result_value_absolute_decimal']) : NULL),
                            'result'                                => (trim($row['result']) != '' ? trim($row['result']) : NULL),
                            'DashVL_Abs'                            =>   $DashVL_Abs,
                            'DashVL_AnalysisResult'                 =>   $DashVL_AnalysisResult,
                            'sample_registered_at_lab'              => $sampleRegisteredAtLabDateTime
                        );


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
                        if (isset($row['facility_type_name']) && trim($row['facility_type_name']) != '') {
                            $facilityTypeDataResult = $this->checkFacilityTypeDetails(trim($row['facility_type_name']));
                            if ($facilityTypeDataResult) {
                                $facilityData['facility_type'] = $facilityTypeDataResult['facility_type_id'];
                            } else {
                                $facilityTypeDb->insert(array('facility_type_name' => trim($row['facility_type_name'])));
                                $facilityData['facility_type'] = $facilityTypeDb->lastInsertValue;
                            }
                        }

                        //check clinic details
                        if (isset($row['facility_name']) && trim($row['facility_name']) != '') {
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
                        if (trim($row['test_reason_name']) != '') {
                            $testReasonResult = $this->checkTestingReson(trim($row['test_reason_name']));
                            if ($testReasonResult) {
                                $testReasonDb->update(array('test_reason_name' => trim($row['test_reason_name']), 'test_reason_status' => trim($row['test_reason_status'])), array('test_reason_id' => $testReasonResult['test_reason_id']));
                                $data['reason_for_vl_testing'] = $testReasonResult['test_reason_id'];
                            } else {
                                $testReasonDb->insert(array('test_reason_name' => trim($row['test_reason_name']), 'test_reason_status' => trim($row['test_reason_status'])));
                                $data['reason_for_vl_testing'] = $testReasonDb->lastInsertValue;
                            }
                        } else {
                            $data['reason_for_vl_testing'] = 0;
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
                        //check sample type
                        if (trim($row['sample_name']) != '') {
                            $sampleType = $this->checkSampleType(trim($row['sample_name']));
                            if ($sampleType) {
                                $sampleTypeDb->update(array('sample_name' => trim($row['sample_name']), 'status' => trim($row['sample_type_status'])), array('sample_id' => $sampleType['sample_id']));
                                $data['sample_type'] = $sampleType['sample_id'];
                            } else {
                                $sampleTypeDb->insert(array('sample_name' => trim($row['sample_name']), 'status' => trim($row['sample_type_status'])));
                                $data['sample_type'] = $sampleTypeDb->lastInsertValue;
                            }
                        } else {
                            $data['sample_type'] = NULL;
                        }
                        //check sample rejection reason
                        if (trim($row['rejection_reason_name']) != '') {
                            $sampleRejectionReason = $this->checkSampleRejectionReason(trim($row['rejection_reason_name']));
                            if ($sampleRejectionReason) {
                                $sampleRjtReasonDb->update(array('rejection_reason_name' => trim($row['rejection_reason_name']), 'rejection_reason_status' => trim($row['rejection_reason_status'])), array('rejection_reason_id' => $sampleRejectionReason['rejection_reason_id']));
                                $data['reason_for_sample_rejection'] = $sampleRejectionReason['rejection_reason_id'];
                            } else {
                                $sampleRjtReasonDb->insert(array('rejection_reason_name' => trim($row['rejection_reason_name']), 'rejection_reason_status' => trim($row['rejection_reason_status'])));
                                $data['reason_for_sample_rejection'] = $sampleRjtReasonDb->lastInsertValue;
                            }
                        } else {
                            $data['reason_for_sample_rejection'] = NULL;
                        }

                        //check existing sample code
                        $sampleCode = $this->checkSampleCode($sampleCode, $instanceCode);
                        if ($sampleCode) {
                            //sample data update
                            $sampleDb->update($data, array('vl_sample_id' => $sampleCode['vl_sample_id']));
                        } else {
                            //sample data insert
                            $sampleDb->insert($data);
                        }
                    }
                }
            }
            //remove directory
            // $common->removeDirectory($pathname);
        }
        return array(
            'status'    => 'success',
            'message'   => 'Uploaded successfully',
        );
    }

    public function saveWeblimsVLAPI($params)
    {
        $common = new CommonService();
        $sampleDb = $this->sm->get('SampleTableWithoutCache');
        $facilityDb = $this->sm->get('FacilityTable');
        // $facilityTypeDb = $this->sm->get('FacilityTypeTable');
        $testStatusDb = $this->sm->get('SampleStatusTable');
        // $testReasonDb = $this->sm->get('TestReasonTable');
        $sampleTypeDb = $this->sm->get('SampleTypeTable');
        $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');
        $provinceDb = $this->sm->get('ProvinceTable');
        $apiTrackDb = $this->sm->get('DashApiReceiverStatsTable');
        $return = array();
        $params = json_decode($params, true);
        // Debug::dump($params['timestamp']);die;
        if (!empty($params)) {
            if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
                mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "temporary", 0777);
            }
            if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "weblims-vl") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "weblims-vl")) {
                mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "weblims-vl", 0777);
            }

            $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "weblims-vl" . DIRECTORY_SEPARATOR . $params['timestamp'] . '.json';
            if (!file_exists($pathname)) {
                $file = file_put_contents($pathname, json_encode($params));
                if (move_uploaded_file($pathname, $pathname)) {
                    // $apiData = file_put_contents($pathname);
                }
            }
            foreach ($params['data'] as $key => $row) {
                // Debug::dump($row);die;
                if (!empty(trim($row['SampleID'])) && trim($row['TestId']) == 'VIRAL_LOAD_2') {
                    $sampleCode = trim($row['SampleID']);
                    $instanceCode = 'nrl-weblims';

                    // Check duplicate data
                    $province = $provinceDb->select(array('province_name' => $row['ProvinceName']))->current();
                    if (!$province) {
                        $provinceDb->insert(array(
                            'province_name'     => $row['ProvinceName'],
                            'updated_datetime'  => $common->getDateTime()
                        ));
                        $province['province_id'] = $provinceDb->lastInsertValue;
                    }

                    $VLAnalysisResult = (float) $row['result_value_absolute_decimal'];
                    $DashVL_Abs = NULL;
                    $DashVL_AnalysisResult = NULL;

                    if ($row['Result']['Copies'] == 'Target not Detected' || $row['Result']['Copies'] == 'Target Not Detected' || strtolower($row['Result']['Copies']) == 'target not detected' || strtolower($row['Result']['Copies']) == 'tnd' || $row['result'] == 'Target not Detected' || $row['result'] == 'Target Not Detected' || strtolower($row['result']) == 'target not detected' || strtolower($row['result']) == 'tnd') {
                        $VLAnalysisResult = 20;
                    } else if ($row['Result']['Copies'] == '< 20' || $row['Result']['Copies'] == '<20' || $row['result'] == '< 20' || $row['result'] == '<20') {
                        $VLAnalysisResult = 20;
                    } else if ($row['Result']['Copies'] == '< 40' || $row['Result']['Copies'] == '<40' || $row['result'] == '< 40' || $row['result'] == '<40') {
                        $VLAnalysisResult = 40;
                    } else if ($row['Result']['Copies'] == 'Suppressed' || $row['result'] == 'Suppressed') {
                        $VLAnalysisResult = 500;
                    } else if ($row['Result']['Copies'] == 'Not Suppressed' || $row['result'] == 'Not Suppressed') {
                        $VLAnalysisResult = 1500;
                    } else if ($row['Result']['Copies'] == 'Negative' || $row['Result']['Copies'] == 'NEGAT' || $row['result'] == 'Negative' || $row['result'] == 'NEGAT') {
                        $VLAnalysisResult = 20;
                    } else if ($row['Result']['Copies'] == 'Positive' || $row['result'] == 'Positive') {
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


                    $sampleReceivedAtLab = ((trim($row['SampleReceivedDate']) != '' && $row['SampleReceivedDate'] != "T") ? trim(str_replace("T", " ", $row['SampleReceivedDate'])) : null);
                    $sampleTestedDateTime = ((trim($row['SampleTestedDate']) != '' && $row['SampleTestedDate'] != "T") ? trim(str_replace("T", " ", $row['SampleTestedDate'])) : null);
                    $sampleCollectionDate = ((trim($row['CollectionDate']) != '' && $row['CollectionDate'] != "T") ? trim(str_replace("T", " ", $row['CollectionDate'])) : null);
                    $dob = ((trim($row['BirthDate']) != '' && $row['BirthDate'] != "T") ? trim(str_replace("T", " ", $row['BirthDate'])) : null);
                    $resultApprovedDateTime = ((trim($row['SampleDateApprovedDateTime']) != '' && $row['SampleDateApprovedDateTime'] != "T") ? trim(str_replace("T", " ", $row['SampleDateApprovedDateTime'])) : null);
                    $dateOfInitiationOfRegimen = ((trim($row['DateInitiaitonCurrentRegiment']) != '' && $row['DateInitiaitonCurrentRegiment'] != "T") ? trim(str_replace("T", " ", $row['DateInitiaitonCurrentRegiment'])) : null);
                    $sampleRegisteredAtLabDateTime = ((trim($row['SampleRegisteredAtLabDate']) != '' && $row['SampleRegisteredAtLabDate'] != "T") ? trim(str_replace("T", " ", $row['SampleRegisteredAtLabDate'])) : null);
                    $resultPrinterDateTime = ((trim($row['Result']['ResultReturnDate']) != '' && $row['Result']['ResultReturnDate'] != "T") ? trim(str_replace("T", " ", $row['Result']['ResultReturnDate'])) : null);

                    $data = array(
                        'sample_code'                           => $sampleCode,
                        'vlsm_instance_id'                      => 'nrl-weblims',
                        'province_id'                           => (trim($province['province_id']) != '' ? trim($province['province_id']) : NULL),
                        'source'                                => '1',
                        'patient_gender'                        => (trim($row['patientGender']) != '' ? trim($row['patientGender']) : NULL),
                        'patient_age_in_years'                  => (trim($row['PatientAge']) != '' ? trim($row['PatientAge']) : NULL),
                        'patient_dob'                           => $dob,
                        'sample_collection_date'                => $sampleCollectionDate,
                        'sample_registered_at_lab'              => $sampleReceivedAtLab,
                        'result_printed_datetime'               => $resultPrinterDateTime,
                        'line_of_treatment'                     => (trim($row['CurrentTreatment']) != '' ? trim($row['CurrentTreatment']) : NULL),
                        'is_sample_rejected'                    => (trim($row['IsSampleRejected']) != '' ? strtolower($row['IsSampleRejected']) : NULL),
                        'is_patient_pregnant'                   => (trim($row['IsPatientPregnant']) != '' ? trim($row['IsPatientPregnant']) : NULL),
                        'is_patient_breastfeeding'              => (trim($row['IsPatientBreastfeeding']) != '' ? trim($row['IsPatientBreastfeeding']) : NULL),
                        'patient_mobile_number'                 => (trim($row['PhoneNumber']) != '' ? trim($row['PhoneNumber']) : NULL),
                        'current_regimen'                       => (trim($row['currentRegimen']) != '' ? trim($row['currentRegimen']) : NULL),
                        'date_of_initiation_of_current_regimen' => $dateOfInitiationOfRegimen,
                        'arv_adherance_percentage'              => (trim($row['ArvAdhenrence']) != '' ? trim($row['ArvAdhenrence']) : NULL),
                        'is_adherance_poor'                     => (trim($row['is_adherance_poor']) != '' ? trim($row['is_adherance_poor']) : NULL),
                        'result_approved_datetime'              => $resultApprovedDateTime,
                        'sample_tested_datetime'                => $sampleTestedDateTime,
                        'vl_test_platform'                      => (trim($row['vlTestingPlatform']) != '' ? trim($row['vlTestingPlatform']) : NULL),
                        'result_value_log'                      => (trim($row['Result']['log']) != '' ? (float)($row['Result']['log']) : NULL),
                        'result_value_absolute'                 => (trim($row['result_value_absolute']) != '' ? trim($row['result_value_absolute']) : NULL),
                        'result_value_text'                     => (trim($row['Result']['Copies']) != '' ? trim($row['Result']['Copies']) : NULL),
                        'result_value_absolute_decimal'         => (trim($row['result_value_absolute_decimal']) != '' ? trim($row['result_value_absolute_decimal']) : NULL),
                        'result'                                => (trim($row['result']) != '' ? trim($row['result']) : NULL),
                        'result_approved_by'                    => (trim($row['ApprovedBy']) != '' ? trim($row['ApprovedBy']) : NULL),
                        'DashVL_Abs'                            => $DashVL_Abs,
                        'DashVL_AnalysisResult'                 => $DashVL_AnalysisResult,
                        'sample_registered_at_lab'              => $sampleRegisteredAtLabDateTime
                    );


                    //check clinic details
                    if (isset($row['FacilityName']) && trim($row['FacilityName']) != '') {
                        $facilityDataResult = $this->checkFacilityDetails(trim($row['FacilityName']));
                        if ($facilityDataResult) {
                            $data['facility_id'] = $facilityDataResult['facility_id'];
                        } else {
                            $facilityDb->insert(array(
                                'vlsm_instance_id'  => 'nrl-weblims',
                                'facility_name'     => $row['FacilityName'],
                                'facility_code'     => !empty($row['FacilityName']) ? $row['FacilityName'] : null,
                                'facility_type'     => '1',
                                'status'            => 'active'
                            ));
                            $data['facility_id'] = $facilityDb->lastInsertValue;
                        }
                    } else {
                        $data['facility_id'] = NULL;
                    }

                    //check lab details
                    $labDataResult = $this->checkFacilityDetails("NRL");
                    if ($labDataResult) {
                        $data['lab_id'] = $labDataResult['facility_id'];
                    } else {
                        $data['lab_id'] = NULL;
                    }

                    //check testing reason
                    if (trim($row['TestStatus']) != '') {
                        $sampleStatusResult = $this->checkSampleStatus(trim($row['TestStatus']));
                        if ($sampleStatusResult) {
                            $data['result_status'] = $sampleStatusResult['status_id'];
                        } else {
                            $testStatusDb->insert(array('status_name' => trim($row['TestStatus'])));
                            $data['result_status'] = $testStatusDb->lastInsertValue;
                        }
                    } else {
                        $data['result_status'] = 6;
                    }
                    //check sample type
                    if (trim($row['SampleType']) != '') {
                        $sampleType = $this->checkSampleType(trim($row['SampleType']));
                        if ($sampleType) {
                            $sampleTypeDb->update(array('sample_name' => trim($row['SampleType'])), array('sample_id' => $sampleType['sample_id']));
                            $data['sample_type'] = $sampleType['sample_id'];
                        } else {
                            $sampleTypeDb->insert(array('sample_name' => trim($row['SampleType'])));
                            $data['sample_type'] = $sampleTypeDb->lastInsertValue;
                        }
                    } else {
                        $data['sample_type'] = NULL;
                    }

                    //check sample test reason
                    if (!empty(trim($row['ReasonForTesting']))) {
                        $data['reason_for_vl_testing'] =  $this->checkTestReason(trim($row['ReasonForTesting']));
                    } else {
                        $data['reason_for_vl_testing'] = NULL;
                    }



                    //check sample rejection reason
                    if (trim($row['SampleRejectionReason']) != '') {
                        $sampleRejectionReason = $this->checkSampleRejectionReason(trim($row['SampleRejectionReason']));
                        if ($sampleRejectionReason) {
                            $sampleRjtReasonDb->update(array('rejection_reason_name' => trim($row['SampleRejectionReason'])), array('rejection_reason_id' => $sampleRejectionReason['rejection_reason_id']));
                            $data['reason_for_sample_rejection'] = $sampleRejectionReason['rejection_reason_id'];
                        } else {
                            $sampleRjtReasonDb->insert(array('rejection_reason_name' => trim($row['SampleRejectionReason'])));
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
                        $return[$key][] = $row['SampleID'];
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
            if ((count($params) - count($return)) == 0) {
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
            'message'   => 'Received ' . count($params) . ' records.'
        );

        // Track API Records
        $apiTrackData = array(
            'tracking_id'                   => $params['timestamp'],
            'received_on'                   => $common->getDateTime(),
            'number_of_records_received'    => count($params),
            'number_of_records_processed'   => (count($params) - count($return)),
            'source'                        => 'Weblims VL',
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
