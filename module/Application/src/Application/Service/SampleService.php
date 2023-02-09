<?php

namespace Application\Service;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Expression;
use Exception;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use Zend\Debug\Debug;
use JsonMachine\JsonMachine;

class SampleService
{

    public $sm = null;
    public $commonService = null;
    public $sampleTable = null;
    public $apiTrackerTable = null;
    public $dbAdapter = null;

    public function __construct($sm, $sampleTable, $commonService, $apiTrackerTable, $dbAdapter)
    {
        $this->sm = $sm;
        $this->commonService = $commonService;
        $this->sampleTable = $sampleTable;
        $this->apiTrackerTable = $apiTrackerTable;
        $this->dbAdapter = $dbAdapter;
    }

    public function getServiceManager()
    {
        return $this->sm;
    }

    public function checkSampleCode($uniqueId, $sampleCode, $remoteSampleCode = null, $instanceCode = null, $dashTable = 'dash_form_vl')
    {

        $sql = new Sql($this->dbAdapter);
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
        if (isset($uniqueId) && $uniqueId != "") {
            $sQuery = $sQuery->where(array('unique_id' => $uniqueId), 'OR');
        }
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }

    public function checkFacilityStateDistrictDetails($location, $parent)
    {

        $sql = new Sql($this->dbAdapter);
        $sQuery = $sql->select()->from(array('l' => 'location_details'))
            ->where(array('l.parent_location' => $parent, 'l.location_name' => trim($location)));
        $sQuery = $sql->buildSqlString($sQuery);
        $sQueryResult = $this->dbAdapter->query($sQuery, $this->dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sQueryResult;
    }

    public function checkFacilityDetails($clinicName)
    {

        $sql = new Sql($this->dbAdapter);
        $fQuery = $sql->select()->from('facility_details')->where(array('facility_name' => $clinicName));
        $fQueryStr = $sql->buildSqlString($fQuery);
        $fResult = $this->dbAdapter->query($fQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $fResult;
    }
    public function checkFacilityTypeDetails($facilityTypeName)
    {

        $sql = new Sql($this->dbAdapter);
        $fQuery = $sql->select()->from('facility_type')->where(array('facility_type_name' => $facilityTypeName));
        $fQueryStr = $sql->buildSqlString($fQuery);
        $fResult = $this->dbAdapter->query($fQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $fResult;
    }
    public function checkTestingReson($testingReson)
    {

        $sql = new Sql($this->dbAdapter);
        $tQuery = $sql->select()->from('r_vl_test_reasons')->where(array('test_reason_name' => $testingReson));
        $tQueryStr = $sql->buildSqlString($tQuery);
        $tResult = $this->dbAdapter->query($tQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $tResult;
    }
    public function checkSampleStatus($testingStatus)
    {

        $sql = new Sql($this->dbAdapter);
        $sQuery = $sql->select()->from('r_sample_status')->where(array('status_name' => $testingStatus));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    public function checkSampleType($sampleType)
    {

        $sql = new Sql($this->dbAdapter);
        $sQuery = $sql->select()->from('r_vl_sample_type')->where(array('sample_name' => $sampleType));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    public function checkSampleRejectionReason($rejectReasonName)
    {

        $sql = new Sql($this->dbAdapter);
        $sQuery = $sql->select()->from('r_vl_sample_rejection_reasons')->where(array('rejection_reason_name' => $rejectReasonName));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $sResult = $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->current();
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

        return $this->sampleTable->getSampleStatusDataTable($params);
    }

    //lab details start
    //get sample result details
    public function getSampleResultDetails($params)
    {

        return $this->sampleTable->fetchSampleResultDetails($params);
    }
    //get sample tested result details
    public function getSampleTestedResultDetails($params)
    {

        return $this->sampleTable->fetchSampleTestedResultDetails($params);
    }

    //get sample tested result details
    public function getSampleTestedResultBasedVolumeDetails($params)
    {

        return $this->sampleTable->fetchSampleTestedResultBasedVolumeDetails($params);
    }

    public function getSampleTestedResultGenderDetails($params)
    {

        return $this->sampleTable->fetchSampleTestedResultGenderDetails($params);
    }

    public function getLabTurnAroundTime($params)
    {

        return $this->sampleTable->fetchLabTurnAroundTime($params);
    }

    public function getSampleTestedResultAgeGroupDetails($params)
    {

        return $this->sampleTable->fetchSampleTestedResultAgeGroupDetails($params);
    }

    public function getSampleTestedResultPregnantPatientDetails($params)
    {

        return $this->sampleTable->fetchSampleTestedResultPregnantPatientDetails($params);
    }

    public function getSampleTestedResultBreastfeedingPatientDetails($params)
    {

        return $this->sampleTable->fetchSampleTestedResultBreastfeedingPatientDetails($params);
    }

    //get Requisition Forms tested
    public function getRequisitionFormsTested($params)
    {

        return $this->sampleTable->getRequisitionFormsTested($params);
    }

    public function getSampleVolume($params)
    {

        return $this->sampleTable->getSampleVolume($params);
    }

    public function getFemalePatientResult($params)
    {

        return $this->sampleTable->getFemalePatientResult($params);
    }

    public function getLineOfTreatment($params)
    {

        return $this->sampleTable->getLineOfTreatment($params);
    }

    public function getFacilites($params)
    {

        return $this->sampleTable->fetchFacilites($params);
    }

    public function getVlOutComes($params)
    {

        return $this->sampleTable->getVlOutComes($params);
    }
    //lab details end

    //clinic details start
    public function getOverallViralLoadStatus($params)
    {

        return $this->sampleTable->fetchOverallViralLoadResult($params);
    }

    public function getViralLoadStatusBasedOnGender($params)
    {

        return $this->sampleTable->fetchViralLoadStatusBasedOnGender($params);
    }

    public function getSampleTestedResultBasedGenderDetails($params)
    {

        return $this->sampleTable->fetchSampleTestedResultBasedGenderDetails($params);
    }

    public function fetchSampleTestedReason($params)
    {

        return $this->sampleTable->fetchSampleTestedReason($params);
    }

    public function getAllTestReasonName()
    {
        $reasonDb = $this->sm->get('TestReasonTable');
        return $reasonDb->fetchAllTestReasonName();
    }
    public function getClinicSampleTestedResultAgeGroupDetails($params)
    {

        return $this->sampleTable->fetchClinicSampleTestedResultAgeGroupDetails($params);
    }
    public function getClinicRequisitionFormsTested($params)
    {

        return $this->sampleTable->fetchClinicRequisitionFormsTested($params);
    }
    //clinic details end

    //get all smaple type
    public function getSampleType()
    {
        $sampleTypeTable = $this->sm->get('SampleTypeTable');
        return $sampleTypeTable->fetchAllSampleType();
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

        return $this->sampleTable->fetchAllTestResults($parameters);
    }

    public function getClinicSampleTestedResults($params)
    {

        return $this->sampleTable->fetchClinicSampleTestedResults($params);
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

        return $this->sampleTable->fetchSampleDetails($params);
    }

    public function getBarSampleDetails($params)
    {

        return $this->sampleTable->fetchBarSampleDetails($params);
    }

    public function getLabFilterSampleDetails($parameters)
    {

        return $this->sampleTable->fetchLabFilterSampleDetails($parameters);
    }

    public function getFilterSampleDetails($parameters)
    {

        return $this->sampleTable->fetchFilterSampleDetails($parameters);
    }

    public function getFilterSampleTatDetails($parameters)
    {

        return $this->sampleTable->fetchFilterSampleTatDetails($parameters);
    }

    public function getLabSampleDetails($params)
    {

        return $this->sampleTable->fetchLabSampleDetails($params);
    }

    public function getLabBarSampleDetails($params)
    {

        return $this->sampleTable->fetchLabBarSampleDetails($params);
    }

    public function getIncompleteSampleDetails($params)
    {

        return $this->sampleTable->fetchIncompleteSampleDetails($params);
    }

    public function getIncompleteBarSampleDetails($params)
    {

        return $this->sampleTable->fetchIncompleteBarSampleDetails($params);
    }

    public function getSampleInfo($params, $dashTable = 'dash_form_vl')
    {

        $sql = new Sql($this->dbAdapter);
        $sQuery = $sql->select()->from(array('vl' => $dashTable))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name', 'facility_code', 'facility_logo'), 'left')
            ->join(array('l_s' => 'location_details'), 'l_s.location_id=f.facility_state', array('provinceName' => 'location_name'), 'left')
            ->join(array('l_d' => 'location_details'), 'l_d.location_id=f.facility_district', array('districtName' => 'location_name'), 'left')
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.sample_type', array('sample_name'), 'left')
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array('labName' => 'facility_name'), 'left')
            ->join(array('u' => 'user_details'), 'u.user_id=vl.result_approved_by', array('approvedBy' => 'user_name'), 'left')
            ->join(array('r_r_r' => 'r_vl_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection', array('rejection_reason_name'), 'left')
            ->join(array('rej_f' => 'facility_details'), 'rej_f.facility_id=vl.sample_rejection_facility', array('rejectionFacilityName' => 'facility_name'), 'left')
            ->where(array('vl.vl_sample_id' => $params['id']));
        $sQueryStr = $sql->buildSqlString($sQuery);
        return $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function generateResultExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');

        if (isset($queryContainer->resultQuery)) {
            try {

                $sql = new Sql($this->dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->resultQuery);
                $sResult = $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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
                            $sampleCollectionDate = $this->commonService->humanDateFormat($aRow['sampleCollectionDate']);
                        }
                        if (isset($aRow['sampleTestingDate']) && $aRow['sampleTestingDate'] != NULL && trim($aRow['sampleTestingDate']) != "" && $aRow['sampleTestingDate'] != '0000-00-00') {
                            $sampleTestedDate = $this->commonService->humanDateFormat($aRow['sampleTestingDate']);
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

        if (isset($queryContainer->resultQuery)) {
            try {

                $sql = new Sql($this->dbAdapter);
                $hQueryStr = $sql->buildSqlString($queryContainer->highVlSampleQuery);
                //error_log($hQueryStr);die;
                $sResult = $this->dbAdapter->query($hQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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
                            $sampleCollectionDate = $this->commonService->humanDateFormat($aRow['sampleCollectionDate']);
                        }
                        if (isset($aRow['treatmentInitiateDate']) && $aRow['treatmentInitiateDate'] != NULL && trim($aRow['treatmentInitiateDate']) != "" && $aRow['treatmentInitiateDate'] != '0000-00-00') {
                            $treatmentInitiateDate = $this->commonService->humanDateFormat($aRow['treatmentInitiateDate']);
                        }
                        if (isset($aRow['patientDOB']) && $aRow['patientDOB'] != NULL && trim($aRow['patientDOB']) != "" && $aRow['patientDOB'] != '0000-00-00') {
                            $patientDOB = $this->commonService->humanDateFormat($aRow['patientDOB']);
                        }
                        if (isset($aRow['treatmentInitiateCurrentRegimen']) && $aRow['treatmentInitiateCurrentRegimen'] != NULL && trim($aRow['treatmentInitiateCurrentRegimen']) != "" && $aRow['treatmentInitiateCurrentRegimen'] != '0000-00-00') {
                            $patientDOB = $this->commonService->humanDateFormat($aRow['patitreatmentInitiateCurrentRegimenentDOB']);
                        }
                        if (isset($aRow['requestDate']) && $aRow['requestDate'] != NULL && trim($aRow['requestDate']) != "" && $aRow['requestDate'] != '0000-00-00') {
                            $requestDate = $this->commonService->humanDateFormat($aRow['requestDate']);
                        }
                        if (isset($aRow['receivedAtLab']) && $aRow['receivedAtLab'] != NULL && trim($aRow['receivedAtLab']) != "" && $aRow['receivedAtLab'] != '0000-00-00') {
                            $requestDate = $this->commonService->humanDateFormat($aRow['receivedAtLab']);
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

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            if (isset($queryContainer->sampleResultQuery)) {
                try {

                    $sql = new Sql($this->dbAdapter);
                    $sQueryStr = $sql->buildSqlString($queryContainer->sampleResultQuery);
                    $sResult = $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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

        if (isset($queryContainer->labTestedSampleQuery)) {
            try {

                $sql = new Sql($this->dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->labTestedSampleQuery);
                $sResult = $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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
                            $sampleCollectionDate = $this->commonService->humanDateFormat($aRow['sampleCollectionDate']);
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

        if (isset($queryContainer->sampleResultTestedTATQuery)) {
            try {

                $sql = new Sql($this->dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->sampleResultTestedTATQuery);
                $sResult = $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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

        return $this->sampleTable->fetchProvinceWiseResultAwaitedDrillDown($params);
    }

    public function getLabWiseResultAwaitedDrillDown($params)
    {

        return $this->sampleTable->fetchLabWiseResultAwaitedDrillDown($params);
    }

    public function getDistrictWiseResultAwaitedDrillDown($params)
    {

        return $this->sampleTable->fetchDistrictWiseResultAwaitedDrillDown($params);
    }

    public function getClinicWiseResultAwaitedDrillDown($params)
    {
        return $this->sampleTable->fetchClinicWiseResultAwaitedDrillDown($params);
    }

    public function getFilterSampleResultAwaitedDetails($parameters)
    {

        return $this->sampleTable->fetchFilterSampleResultAwaitedDetails($parameters);
    }

    public function generateResultsAwaitedSampleExcel($params)
    {
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');

        if (isset($queryContainer->resultsAwaitedQuery)) {
            try {

                $sql = new Sql($this->dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->resultsAwaitedQuery);
                $sResult = $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && count($sResult) > 0) {
                    $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    // $cacheMethod = \PhpOffice\PhpSpreadsheet\Collection\CellsFactory::cache_to_phpTemp;
                    // $cacheSettings = array('memoryCacheSize' => '80MB');
                    // \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    foreach ($sResult as $aRow) {
                        $displayCollectionDate = $this->commonService->humanDateFormat($aRow['collectionDate']);
                        $displayReceivedDate = $this->commonService->humanDateFormat($aRow['receivedDate']);
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

        return $this->sampleTable->fetchAllSamples($parameters);
    }

    public function removeDuplicateSampleRows($params)
    {

        return $this->sampleTable->removeDuplicateSampleRows($params);
    }

    public function getVLTestReasonBasedOnAgeGroup($params)
    {

        return $this->sampleTable->getVLTestReasonBasedOnAgeGroup($params);
    }

    public function getVLTestReasonBasedOnGender($params)
    {

        return $this->sampleTable->getVLTestReasonBasedOnGender($params);
    }

    public function getVLTestReasonBasedOnClinics($params)
    {

        return $this->sampleTable->getVLTestReasonBasedOnClinics($params);
    }

    public function getSample($id)
    {

        return $this->sampleTable->getSample($id);
    }
    ////////////////////////////////////////
    /////////*** Turnaround Time ***///////
    ///////////////////////////////////////

    public function getTATbyProvince($labs, $startDate, $endDate)
    {
        // set_time_limit(10000);
        $result = array();
        $resultSet = array();
        $resultSet = $this->sampleTable->getTATbyProvince($labs, $startDate, $endDate);
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

        $resultSet = $this->sampleTable->getTATbyDistrict($labs, $startDate, $endDate);
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

        $resultSet = $this->sampleTable->getTATbyClinic($labs, $startDate, $endDate);
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

    public function getSampleTestReasonBarChartDetails($params)
    {

        return $this->sampleTable->fetchSampleTestReasonBarChartDetails($params);
    }
    //api for fecth samples
    public function getSourceData($params)
    {

        return $this->sampleTable->fetchSourceData($params);
    }

    // public function generateSampleStatusResultExcel($params){
    //     $queryContainer = new Container('query');
    //     $translator = $this->sm->get('translator');
    //     
    //     if(isset($queryContainer->sampleStatusResultQuery)){
    //         try{
    //             
    //             $sql = new Sql($this->dbAdapter);
    //             $sQueryStr = $sql->buildSqlString($queryContainer->sampleStatusResultQuery);

    //             $sResult = $this->dbAdapter->query($sQueryStr, $this->dbAdapter::QUERY_MODE_EXECUTE)->toArray();
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
        ini_set("memory_limit", -1);
        try {
            $apiData = array();
            $this->config = $this->sm->get('Config');
            $input = $this->config['db']['dsn'];
            preg_match('~=(.*?);~', $input, $output);
            $dbname = $output[1];
    
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
    
    
    
    
            $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $dbname . "' AND table_name='dash_form_vl'";
    
            $sResult = $this->dbAdapter->query($allColumns, $this->dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $columnList = array_map('current', $sResult);
    
            $removeKeys = array(
                'vl_sample_id'
            );
    
            $columnList = array_diff($columnList, $removeKeys);
            $sampleDb = $this->sm->get('SampleTableWithoutCache');
    
            $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl" . DIRECTORY_SEPARATOR . $fileName;
            if (!file_exists($pathname)) {
                if (move_uploaded_file($_FILES['vlFile']['tmp_name'], $pathname)) {
                    //$apiData = json_decode(file_get_contents($pathname), true);
                    $apiData = \JsonMachine\JsonMachine::fromFile($pathname, "/data");
                }
            }
            
           /*  echo "<pre>";
            print_r($apiData);die; */
            $numRows = 0;
            $counter = 0;
            foreach ($apiData as $key => $rowData) {
                $counter++;
    
    
                // ob_start();
                // var_dump($rowData);
                // error_log(ob_get_clean());
    
                $data = array();
                foreach ($columnList as $colName) {
                    if (isset($rowData[$colName])) {
                        $data[$colName] = $rowData[$colName];
                    } else {
                        $data[$colName] = null;
                    }
                }
                unset($data['vl_sample_id']);
                // ob_start();
                // var_dump($data);
                // error_log(ob_get_clean());
                // exit(0);
    
    
    
                /* $sampleCode = trim($data['sample_code']);
                $remoteSample = trim($data['remote_sample_code']);
                $instanceCode = trim($data['vlsm_instance_id']); */
                try {
                    $id = $sampleDb->insertOrUpdate($data);
                    if(isset($id) && is_numeric($id) && count($id) > 0){
                        $dashDb = $this->sm->get('DashApiReceiverStatsTable');
                        $params = array(
                            "table" => "dash_form_vl", 
                            "field" => "vl_sample_id", 
                            "id" => $id
                        );
                        $dashDb->updateAttributes($params);
                    }
                    $numRows++;
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }
            }
            // unlink($pathname);
    
            if ($counter == $numRows) {
                $status = "success";
            } else if (($counter - $numRows) != 0) {
                $status = "partial";
            } else if ($numRows == 0) {
                $status = 'failed';
            }
    
            $apiData = JsonMachine::fromFile($pathname, '/timestamp');
            $timestamp = iterator_to_array($apiData)['timestamp'];
            $timestamp = ($timestamp != false && !empty($timestamp)) ? $timestamp : time();
            unset($pathname);
    
            $apiTrackData = array(
                'tracking_id'                   => $timestamp,
                'received_on'                   => $this->commonService->getDateTime(),
                'number_of_records_received'    => $counter,
                'number_of_records_processed'   => $numRows,
                'source'                        => 'VLSM-VL',
                'lab_id'                        => $data['lab_id'],
                'status'                        => $status
            );
            $this->apiTrackerTable->insert($apiTrackData);
    
            return array(
                'status'    => 'success',
                'message'   => $numRows . ' uploaded successfully',
            );
        } catch (Exception $exc) {
            error_log("HIGH-VL-SAMPLE-RESULT-REPORT--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
            return array(
                'status'    => 'failed',
                'message'   => $exc->getMessage(),
            );
        }

    }
    public function saveFileFromVlsmAPIV1()
    {
        ini_set("memory_limit", -1);
        $apiData = array();

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


        if ($apiData !== FALSE) {
            foreach ($apiData['data'] as $rowData) {
                // ob_start();
                // var_dump($rowData);
                // error_log(ob_get_clean());
                // exit(0);
                foreach ($rowData as $row) {
                    if (trim($row['sample_code']) != '' && trim($row['vlsm_instance_id']) != '') {
                        $sampleCode = trim($row['sample_code']);
                        $remoteSampleCode = trim($row['remote_sample_code']);
                        $instanceCode = trim($row['vlsm_instance_id']);
                        $uniqueId = trim($row['unique_id']);

                        $VLAnalysisResult = (float) $row['result_value_absolute_decimal'];
                        $result_value_absolute_decimal = NULL;
                        $vl_result_category = NULL;

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
                            $result_value_absolute_decimal = NULL;
                            $vl_result_category = NULL;
                        } else if ($VLAnalysisResult < 1000) {
                            $vl_result_category = 'Suppressed';
                            $result_value_absolute_decimal = $VLAnalysisResult;
                        } else if ($VLAnalysisResult >= 1000) {
                            $vl_result_category = 'Not Suppressed';
                            $result_value_absolute_decimal = $VLAnalysisResult;
                        }




                        $sampleCollectionDate = (trim($row['sample_collection_date']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_collection_date']))) : null);
                        $sampleReceivedAtLab = (trim($row['sample_registered_at_lab']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_registered_at_lab']))) : null);
                        $dateOfInitiationOfRegimen = (trim($row['date_of_initiation_of_current_regimen']) != '' ? trim(date('Y-m-d H:i', strtotime($row['date_of_initiation_of_current_regimen']))) : null);
                        $resultApprovedDateTime = (trim($row['result_approved_datetime']) != '' ? trim(date('Y-m-d H:i', strtotime($row['result_approved_datetime']))) : null);
                        $sampleTestedDateTime = (trim($row['sample_tested_datetime']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_tested_datetime']))) : null);
                        $sampleRegisteredAtLabDateTime = (trim($row['sample_registered_at_lab']) != '' ? trim(date('Y-m-d H:i', strtotime($row['sample_registered_at_lab']))) : null);




                        $data = array(
                            'sample_code'                           => $sampleCode,
                            'remote_sample_code'                    => $remoteSampleCode,
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
                            'result_value_absolute_decimal'                            =>   $result_value_absolute_decimal,
                            'vl_result_category'                 =>   $vl_result_category,
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
                        $sampleCode = $this->checkSampleCode($uniqueId, $sampleCode, $remoteSampleCode, $instanceCode);
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
            // $this->commonService->removeDirectory($pathname);
        }
        return array(
            'status'    => 'success',
            'message'   => 'Uploaded successfully',
        );
    }

    public function saveWeblimsVLAPI($params)
    {
        ini_set("memory_limit", -1);
        if (empty(trim($params)) || trim($params) == '[]') {
            http_response_code(400);
            $response = array(
                'status'    => 'fail',
                'message'   => 'Missing data in API request',
            );
        }

        $sampleDb = $this->sm->get('SampleTableWithoutCache');
        $facilityDb = $this->sm->get('FacilityTable');
        // $facilityTypeDb = $this->sm->get('FacilityTypeTable');
        $testStatusDb = $this->sm->get('SampleStatusTable');
        // $testReasonDb = $this->sm->get('TestReasonTable');
        $sampleTypeDb = $this->sm->get('SampleTypeTable');
        $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');
        $provinceDb = $this->sm->get('ProvinceTable');
        $apiTrackDb = $this->sm->get('DashApiReceiverStatsTable');
        $userDb = $this->sm->get('UsersTable');
        $failedImports = array();
        //$params = json_decode($params, true);
        $apiData = \JsonMachine\JsonMachine::fromString($params, "/data");
        $counter = 0;
        foreach ($apiData as $key => $row) {
            $counter++;
            // Debug::dump($row);die;
            if (!empty(trim($row['SampleID'])) && trim($row['TestId']) == 'VIRAL_LOAD_2') {
                $remoteSampleCode = $sampleCode = trim($row['SampleID']);
                $instanceCode = 'nrl-weblims';

                // Check duplicate data
                $province = $provinceDb->select(array('province_name' => $row['ProvinceName']))->current();
                if (!$province) {
                    $provinceDb->insert(array(
                        'province_name'     => $row['ProvinceName'],
                        'updated_datetime'  => $this->commonService->getDateTime()
                    ));
                    $province['province_id'] = $provinceDb->lastInsertValue;
                }

                $row['result_value_absolute_decimal'] = $VLAnalysisResult = (float) $row['Result']['Copies'];
                $result_value_absolute_decimal = NULL;
                $vl_result_category = NULL;

                if (strtolower($row['Result']['Raw Data']) == 'target not detected' || strtolower($row['Result']['Copies']) == 'target not detected' || strtolower($row['Result']['Copies']) == 'tnd' || strtolower($row['Result']['Raw Data']) == '< titre min' || strtolower($row['Result']['Copies']) == '< titre min') {
                    $row['result_value_absolute_decimal'] = $VLAnalysisResult = 20;
                    $row['Result']['Copies'] = "Target Not Detected";
                } else if ($row['Result']['Copies'] == '< 20' || $row['Result']['Copies'] == '<20') {
                    $row['result_value_absolute_decimal'] = $VLAnalysisResult = 20;
                } else if ($row['Result']['Copies'] == '< 40' || $row['Result']['Copies'] == '<40') {
                    $row['result_value_absolute_decimal'] = $VLAnalysisResult = 40;
                } else if ($row['Result']['Copies'] == '< 839' || $row['Result']['Copies'] == '<839') {
                    $row['result_value_absolute_decimal'] = $VLAnalysisResult = 20;
                } else if (strtolower($row['Result']['Copies']) == 'suppressed') {
                    $row['result_value_absolute_decimal'] = $VLAnalysisResult = 500;
                } else if (strtolower($row['Result']['Copies']) == 'not suppressed') {
                    $row['result_value_absolute_decimal'] = $VLAnalysisResult = 1500;
                } else if (strtolower($row['Result']['Copies']) == 'negative' || strtolower($row['Result']['Copies']) == 'negat') {
                    $row['result_value_absolute_decimal'] = $VLAnalysisResult = 20;
                } else if (strtolower($row['Result']['Copies']) == 'positive') {
                    $row['result_value_absolute_decimal'] = $VLAnalysisResult = 1500;
                }


                if ($VLAnalysisResult == 'NULL' || $VLAnalysisResult == '' || $VLAnalysisResult == NULL) {
                    $result_value_absolute_decimal = NULL;
                    $vl_result_category = NULL;
                } else if ($VLAnalysisResult < 1000) {
                    $vl_result_category = 'Suppressed';
                    $result_value_absolute_decimal = $VLAnalysisResult;
                } else if ($VLAnalysisResult >= 1000) {
                    $vl_result_category = 'Not Suppressed';
                    $result_value_absolute_decimal = $VLAnalysisResult;
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
                    'remote_sample_code'                    => $remoteSampleCode,
                    'vlsm_instance_id'                      => 'nrl-weblims',
                    'province_id'                           => (trim($province['province_id']) != '' ? trim($province['province_id']) : NULL),
                    'source'                                => '1',
                    'patient_art_no'                        => (trim($row['TracnetID']) != '' ? trim($row['TracnetID']) : NULL),
                    'patient_gender'                        => (trim($row['patientGender']) != '' ? strtolower($row['patientGender']) : NULL),
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
                    'result_value_absolute_decimal'         => (trim($row['result_value_absolute']) != '' ? trim($row['result_value_absolute']) : NULL),
                    'result'                                => (trim($row['Result']['Copies']) != '' ? trim($row['Result']['Copies']) : NULL),
                    'result_approved_by'                    => (trim($row['ApprovedBy']) != '' ? $userDb->checkExistUser($row['ApprovedBy']) : NULL),
                    'result_value_absolute_decimal'                            => $result_value_absolute_decimal,
                    'vl_result_category'                 => $vl_result_category,
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
                $data['result_status'] = null;
                if (trim($row['TestStatus']) != '') {
                    $row['TestStatus'] = strtolower($row['TestStatus']);
                    if ($row['TestStatus'] == 'complete' || $row['TestStatus'] == 'authorized') {
                        $row['TestStatus'] = 'accepted';
                        $data['result_status'] = 7;
                    } else if ($row['TestStatus'] == 'cancelled' || $row['TestStatus'] == 'rejected') {
                        $row['TestStatus'] = 'rejected';
                        $data['result_status'] = 4;
                    } else if ($row['TestStatus'] == 'invalid' || $row['TestStatus'] == 'fail' || $row['TestStatus'] == 'failed' || $row['TestStatus'] == 'fail' || $row['TestStatus'] == 'inconclusive' || strtolower($row['Result']['Copies']) == 'inconclusive') {
                        $row['TestStatus'] = 'invalid';
                        $data['result_status'] = 5;
                    } else if ($row['TestStatus'] == 'registered' || $row['TestStatus'] == 'progress') {
                        $row['TestStatus'] = 'registered';
                        $data['result_status'] = 6;
                    }
                    // if (!empty($data['result_status'])) {
                    //     $sampleStatusResult = $this->checkSampleStatus(trim($row['TestStatus']));
                    //     if ($sampleStatusResult) {
                    //         $data['result_status'] = $sampleStatusResult['status_id'];
                    //     } else {
                    //         $testStatusDb->insert(array('status_name' => trim($row['TestStatus'])));
                    //         $data['result_status'] = $testStatusDb->lastInsertValue;
                    //     }
                    // }
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
                        $sampleTypeDb->insert(array('sample_name' => trim($row['SampleType']), 'status' => 'active'));
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
                        $sampleRjtReasonDb->insert(array('rejection_reason_name' => trim($row['SampleRejectionReason']), 'rejection_reason_status' => 'active'));
                        $data['reason_for_sample_rejection'] = $sampleRjtReasonDb->lastInsertValue;
                    }
                } else {
                    $data['reason_for_sample_rejection'] = NULL;
                }

                $status = $sampleDb->insertOrUpdate($data);

                if ($status === false || $status == 0) {
                    $failedImports[$key][] = $row['SampleID'];
                }
            }
        }

        http_response_code(202);
        $status = 'success';
        if (count($failedImports) > 0) {
            $status = 'partial';
            if (($counter - count($failedImports)) == 0) {
                $status = 'failed';
            }
        }
        $response = array(
            'status'    => 'success',
            'message'   => 'Received ' . $counter . ' records.'
        );

        // Track API Records
        $apiData = JsonMachine::fromString($params, '/timestamp');
        $timestamp = iterator_to_array($apiData)['timestamp'];
        $timestamp = ($timestamp != false && !empty($timestamp)) ? $timestamp : time();
        $apiTrackData = array(
            'tracking_id'                   => $timestamp,
            'received_on'                   => $this->commonService->getDateTime(),
            'number_of_records_received'    => $counter,
            'number_of_records_processed'   => ($counter - count($failedImports)),
            'source'                        => 'WEBLIMS-VL',
            'lab_id'                        => $data['lab_id'],
            'status'                        => $status
        );
        $apiTrackDb->insert($apiTrackData);

        return $response;
    }

    public function saveVLDataFromAPI($params)
    {
        ini_set("memory_limit", -1);
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
                if (!empty(trim($row['sample_code'])) && trim($params['api_version']) == $config['defaults']['vl-api-version']) {
                    $sampleCode = trim($row['sample_code']);
                    $uniqueId = trim($row['unique_id']);
                    $remoteSampleCode = trim($row['remote_sample_code']);
                    $instanceCode = 'api-data';

                    // Check dublicate data
                    $province = $provinceDb->select(array('province_name' => $row['health_centre_province']))->current();
                    if (!$province) {
                        $provinceDb->insert(array(
                            'province_name'     => $row['health_centre_province'],
                            'updated_datetime'  => $this->commonService->getDateTime()
                        ));
                        $province['province_id'] = $provinceDb->lastInsertValue;
                    }

                    $VLAnalysisResult = (float) $row['result_value_absolute_decimal'];
                    $result_value_absolute_decimal = NULL;
                    $vl_result_category = NULL;

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
                        $result_value_absolute_decimal = NULL;
                        $vl_result_category = NULL;
                    } else if ($VLAnalysisResult < 1000) {
                        $vl_result_category = 'Suppressed';
                        $result_value_absolute_decimal = $VLAnalysisResult;
                    } else if ($VLAnalysisResult >= 1000) {
                        $vl_result_category = 'Not Suppressed';
                        $result_value_absolute_decimal = $VLAnalysisResult;
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
                        'remote_sample_code'                    => $remoteSampleCode,
                        'vlsm_instance_id'                      => $instanceCode,
                        'province_id'                           => (trim($province['province_id']) != '' ? trim($province['province_id']) : NULL),
                        'source'                                => 'WebLIMS',
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
                        // 'tested_by'                             => (trim($row['tested_by']) != '' ? $userDb->checkExistUser($row['tested_by']) : NULL),
                        'result_approved_by'                    => (trim($row['result_approved_by']) != '' ? $userDb->checkExistUser($row['result_approved_by']) : NULL),
                        'result_value_absolute_decimal'                            => $result_value_absolute_decimal,
                        'vl_result_category'                 => $vl_result_category,
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
                    $sampleCode = $this->checkSampleCode($uniqueId, $sampleCode, $remoteSampleCode, $instanceCode);
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
            'received_on'                   => $this->commonService->getDateTime(),
            'number_of_records_received'    => count($params['data']),
            'number_of_records_processed'   => (count($params['data']) - count($return)),
            'source'                        => 'API-VIRAL-LOAD',
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
