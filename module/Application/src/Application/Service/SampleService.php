<?php

namespace Application\Service;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Sql\Expression;
use Application\Service\CommonService;
use PHPExcel;

class SampleService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }
    public function UploadSampleResultFile($params) {
        $container = new Container('alert');
        $common = new CommonService();
        $sampleDb = $this->sm->get('SampleTable');
        $facilityDb = $this->sm->get('FacilityTable');
        $facilityTypeDb = $this->sm->get('FacilityTypeTable');
        $testStatusDb = $this->sm->get('SampleStatusTable');
        $testReasonDb = $this->sm->get('TestReasonTable');
        $sampleTypeDb = $this->sm->get('SampleTypeTable');
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $allowedExtensions = array('xls', 'xlsx', 'csv');
            $fileName = $_FILES['importFile']['name'];
            $ranNumber = str_pad(rand(0, pow(10, 6)-1), 6, '0', STR_PAD_LEFT);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileName =$ranNumber.".".$extension;
            
            if (!file_exists(UPLOAD_PATH) && !is_dir(UPLOAD_PATH)) {
                mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "uploads");
            }
            if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "vl-sample-result") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "vl-sample-result")) {
                mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "vl-sample-result");
            }
            
            if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR ."vl-sample-result" . DIRECTORY_SEPARATOR . $fileName)) {
                if (move_uploaded_file($_FILES['importFile']['tmp_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR ."vl-sample-result" . DIRECTORY_SEPARATOR . $fileName)) {
                    $objPHPExcel = \PHPExcel_IOFactory::load(UPLOAD_PATH . DIRECTORY_SEPARATOR ."vl-sample-result" . DIRECTORY_SEPARATOR . $fileName);
                    $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
                    $count = count($sheetData);
                    //$common = new \Application\Service\CommonService();
                    for ($i = 2; $i <= $count; $i++) {
                        if(trim($sheetData[$i]['A']) != '' && trim($sheetData[$i]['B']) != '') {
                            $sampleCode = trim($sheetData[$i]['A']);
                            $data = array('sample_code'=>$sampleCode,
                                          'vlsm_instance_id'=>trim($sheetData[$i]['B']),
                                          'source'=>$params['sourceName'],
                                          'patient_gender'=>(trim($sheetData[$i]['C'])!='' ? trim($sheetData[$i]['C']) :  NULL),
                                          'patient_age_in_years'=>(trim($sheetData[$i]['D'])!='' ? trim($sheetData[$i]['D']) :  NULL),
                                          'sample_collection_date'=>(trim($sheetData[$i]['U'])!='' ? trim($sheetData[$i]['U']) :  NULL),
                                          'sample_tested_datetime'=>(trim($sheetData[$i]['AJ'])!='' ? trim($sheetData[$i]['AJ']) :  NULL),
                                          'result_value_log'=>(trim($sheetData[$i]['AK'])!='' ? trim($sheetData[$i]['AK']) :  NULL),
                                          'result_value_absolute'=>(trim($sheetData[$i]['AL'])!='' ? trim($sheetData[$i]['AL']) :  NULL),
                                          'result_value_text'=>(trim($sheetData[$i]['AM'])!='' ? trim($sheetData[$i]['AM']) :  NULL),
                                          'result_value_absolute_decimal'=>(trim($sheetData[$i]['AN'])!='' ? trim($sheetData[$i]['AN']) :  NULL),
                                          'result'=>(trim($sheetData[$i]['AO'])!='' ? trim($sheetData[$i]['AO']) :  NULL),
                                          );
                            $facilityData = array('vlsm_instance_id'=>trim($sheetData[$i]['B']),
                                                  'facility_name'=>trim($sheetData[$i]['E']),
                                                  'facility_code'=>trim($sheetData[$i]['F']),
                                                  'facility_mobile_numbers'=>trim($sheetData[$i]['I']),
                                                  'address'=>trim($sheetData[$i]['J']),
                                                  'facility_hub_name'=>trim($sheetData[$i]['K']),
                                                  'contact_person'=>trim($sheetData[$i]['L']),
                                                  'report_email'=>trim($sheetData[$i]['M']),
                                                  'country'=>trim($sheetData[$i]['N']),
                                                  'facility_state'=>trim($sheetData[$i]['G']),
                                                  'facility_district'=>trim($sheetData[$i]['H']),
                                                  'longitude'=>trim($sheetData[$i]['O']),
                                                  'latitude'=>trim($sheetData[$i]['P']),
                                                  'status'=>trim($sheetData[$i]['Q']),
                                                  );
                            //check facility type
                            if(trim($sheetData[$i]['R'])!=''){
                                $facilityTypeDataResult = $this->checkFacilityTypeDetails(trim($sheetData[$i]['R']));
                                if($facilityTypeDataResult){
                                    $facilityData['facility_type'] = $facilityTypeDataResult['facility_type_id'];
                                }else{
                                    $facilityTypeDb->insert(array('facility_type_name'=>trim($sheetData[$i]['R'])));
                                    $facilityData['facility_type'] = $facilityTypeDb->lastInsertValue;
                                }
                            }
                            
                            //check clinic details
                            if(trim($sheetData[$i]['E'])!=''){
                                $facilityDataResult = $this->checkFacilityDetails(trim($sheetData[$i]['E']));
                                if($facilityDataResult){
                                    $facilityDb->update($facilityData,array('facility_id'=>$facilityDataResult['facility_id']));
                                    $data['facility_id'] = $facilityDataResult['facility_id'];
                                }else{
                                    $facilityDb->insert($facilityData);
                                    $data['facility_id'] = $facilityDb->lastInsertValue;
                                }
                            }else{
                                    $data['facility_id'] = NULL;
                            }
                            
                            $labData = array('vlsm_instance_id'=>trim($sheetData[$i]['B']),
                                                  'facility_name'=>trim($sheetData[$i]['V']),
                                                  'facility_code'=>trim($sheetData[$i]['W']),
                                                  'facility_state'=>trim($sheetData[$i]['X']),
                                                  'facility_district'=>trim($sheetData[$i]['Y']),
                                                  'facility_mobile_numbers'=>trim($sheetData[$i]['Z']),
                                                  'address'=>trim($sheetData[$i]['AA']),
                                                  'facility_hub_name'=>trim($sheetData[$i]['AB']),
                                                  'contact_person'=>trim($sheetData[$i]['AC']),
                                                  'report_email'=>trim($sheetData[$i]['AD']),
                                                  'country'=>trim($sheetData[$i]['AE']),
                                                  'longitude'=>trim($sheetData[$i]['AF']),
                                                  'latitude'=>trim($sheetData[$i]['AG']),
                                                  'status'=>trim($sheetData[$i]['AH']),
                                                  );
                            //check lab type
                            if(trim($sheetData[$i]['AI'])!=''){
                                $labTypeDataResult = $this->checkFacilityTypeDetails(trim($sheetData[$i]['AI']));
                                if($labTypeDataResult){
                                    $labData['facility_type'] = $labTypeDataResult['facility_type_id'];
                                }else{
                                    $facilityTypeDb->insert(array('facility_type_name'=>trim($sheetData[$i]['AI'])));
                                    $labData['facility_type'] = $facilityTypeDb->lastInsertValue;
                                }
                            }
                            
                            //check lab details
                            if(trim($sheetData[$i]['V'])!=''){
                                $labDataResult = $this->checkFacilityDetails(trim($sheetData[$i]['V']));
                                if($labDataResult){
                                    $facilityDb->update($labData,array('facility_id'=>$labDataResult['facility_id']));
                                    $data['lab_id'] = $labDataResult['facility_id'];
                                }else{
                                    $facilityDb->insert($labData);
                                    $data['lab_id'] = $facilityDb->lastInsertValue;
                                }
                            }else{
                                $data['lab_id'] = 0;
                            }
                            //check testing reason
                            if(trim($sheetData[$i]['AP'])!=''){
                                $testReasonResult = $this->checkTestingReson(trim($sheetData[$i]['AP']));
                                if($testReasonResult){
                                    $testReasonDb->update(array('test_reason_name'=>trim($sheetData[$i]['AP']),'test_reason_status'=>trim($sheetData[$i]['AQ'])),array('test_reason_id'=>$testReasonResult['test_reason_id']));
                                    $data['reason_for_vl_testing'] = $testReasonResult['test_reason_id'];
                                }else{
                                    $testReasonDb->insert(array('test_reason_name'=>trim($sheetData[$i]['AP']),'test_reason_status'=>trim($sheetData[$i]['AQ'])));
                                    $data['reason_for_vl_testing'] = $testReasonDb->lastInsertValue;
                                }
                            }else{
                                    $data['reason_for_vl_testing'] = 0;
                            }
                            //check testing reason
                            if(trim($sheetData[$i]['AR'])!=''){
                                $sampleStatusResult = $this->checkSampleStatus(trim($sheetData[$i]['AR']));
                                if($sampleStatusResult){
                                    $data['result_status'] = $sampleStatusResult['status_id'];
                                }else{
                                    $testStatusDb->insert(array('status_name'=>trim($sheetData[$i]['AR'])));
                                    $data['result_status'] = $testStatusDb->lastInsertValue;
                                }
                            }else{
                                $data['result_status'] = 6;
                            }
                            //check sample type
                            if(trim($sheetData[$i]['S'])!=''){
                                $sampleType = $this->checkSampleType(trim($sheetData[$i]['S']));
                                if($sampleType){
                                    $sampleTypeDb->update(array('sample_name'=>trim($sheetData[$i]['S']),'status'=>trim($sheetData[$i]['T'])),array('sample_id'=>$sampleType['sample_id']));
                                    $data['sample_type'] = $sampleType['sample_id'];
                                }else{
                                    $sampleTypeDb->insert(array('sample_name'=>trim($sheetData[$i]['S']),'status'=>trim($sheetData[$i]['T'])));
                                    $data['sample_type'] = $sampleTypeDb->lastInsertValue;
                                }
                            }else{
                                $data['sample_type'] = NULL;
                            }
                            
                            //check existing sample code
                            $sampleCode = $this->checkSampleCode($sampleCode);
                            if($sampleCode){
                                //sample data update
                                $sampleDb->update($data,array('vl_sample_id'=>$sampleCode['vl_sample_id']));
                            }else{
                                //sample data insert
                                $sampleDb->insert($data);
                            }
                        }
                    }
                    //remove directory
                    $common->removeDirectory(UPLOAD_PATH . DIRECTORY_SEPARATOR ."vl-sample-result" . DIRECTORY_SEPARATOR . $fileName);
                    //for loop end
                    $container->alertMsg = 'File Uploaded Successfully';
                }
            }
    }
    public function checkSampleCode($sampleCode)
    {
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('dash_vl_request_form')->where(array('sample_code' => $sampleCode));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    public function checkFacilityDetails($clinicName)
    {
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from('facility_details')->where(array('facility_name' => $clinicName));
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $fResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $fResult;
    }
    public function checkFacilityTypeDetails($facilityTypeName)
    {
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $fQuery = $sql->select()->from('facility_type')->where(array('facility_type_name' => $facilityTypeName));
        $fQueryStr = $sql->getSqlStringForSqlObject($fQuery);
        $fResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $fResult;
    }
    public function checkTestingReson($testingReson)
    {
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $tQuery = $sql->select()->from('r_vl_test_reasons')->where(array('test_reason_name' => $testingReson));
        $tQueryStr = $sql->getSqlStringForSqlObject($tQuery);
        $tResult = $dbAdapter->query($tQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $tResult;
    }
    public function checkSampleStatus($testingStatus)
    {
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_sample_status')->where(array('status_name' => $testingStatus));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    public function checkSampleType($sampleType)
    {
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_sample_type')->where(array('sample_name' => $sampleType));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    //lab details start
    //get sample result details
    public function getSampleResultDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleResultDetails($params);
    }
    //get sample tested result details
    public function getSampleTestedResultDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultDetails($params);
    }
    public function getSampleTestedResultGenderDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultGenderDetails($params);
    }
    public function getSampleTestedResultAgeDetails($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultAgeDetails($params);
    }
    //get sample tested result details
    public function getSampleTestedResultBasedVolumeDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultBasedVolumeDetails($params);
    }
    //get Requisition Forms tested
    public function getRequisitionFormsTested($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getRequisitionFormsTested($params);
    }
    //get Requisition Forms tested
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
    public function getLabTurnAroundTime($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabTurnAroundTime($params);
    }
    
    public function getFacilites($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFacilites($params);
    }
    //lab details end
    
    //clinic details start
    public function getOverAllLoadStatus($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchOverAllLoadStatus($params);
    }
    public function getChartOverAllLoadStatus($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        $result =  $sampleDb->fetchChartOverAllLoadStatus($params);
        return $result;
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
        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllLabName();
    }
    //get all Lab Name
    public function getAllClinicName()
    {
        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllClinicName();
    }
    
    public function getAllTestResults($params)
    {
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllTestResults($params);
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
    
     public function getBarSampleDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchBarSampleDetails($params);
    }
    
    public function getFilterSampleDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFilterSampleDetails($params);
    }
    
    public function getLabSampleDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabSampleDetails($params);
    }
    
    public function getLabBarSampleDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabBarSampleDetails($params);
    }
    
    public function getIncompleteSampleDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchIncompleteSampleDetails($params);
    }
    
    public function getIncompleteBarSampleDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchIncompleteBarSampleDetails($params);
    }
    
    public function getSampleInfo($params){
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name','facility_code'),'left')
				->join(array('r_s_t'=>'r_sample_type'),'r_s_t.sample_id=vl.sample_type',array('sample_name'),'left')
				->join(array('l'=>'facility_details'),'l.facility_id=vl.lab_id',array('labName'=>'facility_name'),'left')
				->join(array('u'=>'user_details'),'u.user_id=vl.result_approved_by',array('approvedBy'=>'user_name'),'left')
				->where(array('vl.vl_sample_id'=>$params['id']));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
      return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }
    
    public function generateResultExcel($params){
        $queryContainer = new Container('query');
        $common = new CommonService();
        if(isset($queryContainer->resultQuery)){
            try{
                $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->getSqlStringForSqlObject($queryContainer->resultQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(isset($sResult) && count($sResult)>0){
                    $excel = new PHPExcel();
                    $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
                    $cacheSettings = array('memoryCacheSize' => '80MB');
                    \PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    foreach ($sResult as $aRow) {
                        $row = array();
                        if(isset($aRow['sample_collection_date']) && trim($aRow['sample_collection_date'])!=""){
                            $xepCollectDate=explode(" ",$aRow['sample_collection_date']);
                            $aRow['sample_collection_date']=$common->humanDateFormat($xepCollectDate[0])." ".$xepCollectDate[1];
                        }
                        if(isset($aRow['sample_testing_date']) && trim($aRow['sample_testing_date'])!=""){
                            $xepTestingDate=explode(" ",$aRow['sample_testing_date']);
                            $aRow['sample_testing_date']=$common->humanDateFormat($xepTestingDate[0])." ".$xepTestingDate[1];
                        }
                        $row[] = $aRow['sample_code'];
                        $row[] = $aRow['sample_collection_date'];
                        if(trim($params['result']) == '' || trim($params['result']) == 'result'){
                           $row[] = $aRow['sample_testing_date'];
                           $row[] = $aRow['result'];
                        }
                        $output[] = $row;
                    }
                    $styleArray = array(
                        'font' => array(
                            'bold' => true,
                        ),
                        'alignment' => array(
                            'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PHPExcel_Style_Border::BORDER_THIN,
                            ),
                        )
                    );
                    $borderStyle = array(
                        'alignment' => array(
                            'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PHPExcel_Style_Border::BORDER_THIN,
                            ),
                        )
                    );
                    
                    $sheet->setCellValue('A1', html_entity_decode('Sample ID ', ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode('Date Collected ', ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    if(trim($params['result']) == '' || trim($params['result']) == 'result'){
                       $sheet->setCellValue('C1', html_entity_decode('Date Tested ', ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                       $sheet->setCellValue('D1', html_entity_decode('Viral Load(cp/mL) ', ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                    $sheet->getStyle('A1')->applyFromArray($styleArray);
                    $sheet->getStyle('B1')->applyFromArray($styleArray);
                    if(trim($params['result']) == '' || trim($params['result']) == 'result'){
                      $sheet->getStyle('C1')->applyFromArray($styleArray);
                      $sheet->getStyle('D1')->applyFromArray($styleArray);
                    }
                    $currentRow = 2;
                    $endColumn =  (trim($params['result']) == '' || trim($params['result']) == 'result')?3:1;
                    foreach ($output as $rowData) {
                        $colNo = 0;
                        foreach ($rowData as $field => $value) {
                            if (!isset($value)) {
                                $value = "";
                            }
                            if($colNo > $endColumn){
                                break;
                            }
                            if (is_numeric($value)) {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_NUMERIC);
                            }else{
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
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
                    $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel5');
                    $filename = 'TEST-RESULT-REPORT--' . date('d-M-Y-H-i-s') . '.xls';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                }else{
                    return "";
                }
            }catch (Exception $exc) {
                error_log("TEST-RESULT-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
            }  
        }else{
            return "";
        }
    }
}