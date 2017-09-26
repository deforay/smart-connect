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
    
    public function uploadSampleResultFile($params) {
        $container = new Container('alert');
        $common = new CommonService();
        $sampleDb = $this->sm->get('SampleTable');
        $facilityDb = $this->sm->get('FacilityTable');
        $facilityTypeDb = $this->sm->get('FacilityTypeTable');
        $testStatusDb = $this->sm->get('SampleStatusTable');
        $testReasonDb = $this->sm->get('TestReasonTable');
        $sampleTypeDb = $this->sm->get('SampleTypeTable');
        $locationDb = $this->sm->get('LocationDetailsTable');
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
                            if(trim($sheetData[$i]['G'])!=''){
                                $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($sheetData[$i]['G']),0);
                                if($sQueryResult){
                                    $facilityData['facility_state'] = $sQueryResult['location_id'];
                                }else{
                                    $locationDb->insert(array('parent_location'=>0,'location_name'=>trim($sheetData[$i]['G'])));
                                    $facilityData['facility_state'] = $locationDb->lastInsertValue;
                                }
                            }
                            if(trim($sheetData[$i]['H'])!=''){
                                $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($sheetData[$i]['H']),$facilityData['facility_state']);
                                if($sQueryResult){
                                    $facilityData['facility_district'] = $sQueryResult['location_id'];
                                }else{
                                    $locationDb->insert(array('parent_location'=>$facilityData['facility_state'],'location_name'=>trim($sheetData[$i]['H'])));
                                    $facilityData['facility_district'] = $locationDb->lastInsertValue;
                                }
                            }
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
                            if(trim($sheetData[$i]['X'])!=''){
                                $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($sheetData[$i]['X']),0);
                                if($sQueryResult){
                                    $labData['facility_state'] = $sQueryResult['location_id'];
                                }else{
                                    $locationDb->insert(array('parent_location'=>0,'location_name'=>trim($sheetData[$i]['X'])));
                                    $labData['facility_state'] = $locationDb->lastInsertValue;
                                }
                            }
                            if(trim($sheetData[$i]['Y'])!=''){
                                $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($sheetData[$i]['Y']),$labData['facility_state']);
                                if($sQueryResult){
                                    $labData['facility_district'] = $sQueryResult['location_id'];
                                }else{
                                    $locationDb->insert(array('parent_location'=>$labData['facility_state'],'location_name'=>trim($sheetData[$i]['Y'])));
                                    $labData['facility_district'] = $locationDb->lastInsertValue;
                                }
                            }
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
    
    public function checkSampleCode($sampleCode){
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('dash_vl_request_form')->where(array('sample_code' => $sampleCode));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
    
    public function checkFacilityStateDistrictDetails($location,$parent){
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('l'=>'location_details'))
							->where(array('l.parent_location'=>$parent,'l.location_name'=>trim($location)));
        $sQuery = $sql->getSqlStringForSqlObject($sQuery);
        $sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sQueryResult;
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
    
    public function getSampleTestedResultGenderDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultGenderDetails($params);
    }
    
    public function getSampleTestedResultAgeDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultAgeDetails($params);
    }
    
    //get sample tested result details
    public function getSampleTestedResultBasedVolumeDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultBasedVolumeDetails($params);
    }
    
    //get Requisition Forms tested
    public function getRequisitionFormsTested($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getRequisitionFormsTested($params);
    }
    //get Requisition Forms tested
    public function getSampleVolume($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getSampleVolume($params);
    }
    
    public function getFemalePatientResult($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getFemalePatientResult($params);
    }
    
    public function getLineOfTreatment($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getLineOfTreatment($params);
    }
    
    public function getVlOutComes($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getVlOutComes($params);
    }
    
    public function getLabTurnAroundTime($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabTurnAroundTime($params);
    }
    
    public function getFacilites($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFacilites($params);
    }
    //lab details end
    
    //clinic details start
    public function getOverAllLoadStatus($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchOverAllLoadStatus($params);
    }
    
    public function getChartOverAllLoadStatus($params){
        $sampleDb = $this->sm->get('SampleTable');
        $result =  $sampleDb->fetchChartOverAllLoadStatus($params);
        return $result;
    }
    
    public function fetchSampleTestedReason($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedReason($params);
    }
    
    public function getAllTestReasonName(){
        $reasonDb = $this->sm->get('TestReasonTable');
        return $reasonDb->fetchAllTestReasonName();
    }
    //clinic details end
    
    //get all smaple type
    public function getSampleType(){
        $sampleDb = $this->sm->get('SampleTypeTable');
        return $sampleDb->fetchAllSampleType();
    }
    //get all Lab Name
    public function getAllLabName(){
        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllLabName();
    }
    //get all Lab Name
    public function getAllClinicName(){
        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllClinicName();
    }
    
    public function getAllTestResults($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllTestResults($params);
    }
    
    public function getClinicSampleTestedResults($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchClinicSampleTestedResults($params);
    }
    
    //get all Hub Name
    public function getAllHubName(){
        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllHubName();
    }
    
    //get all Current Regimen
    public function getAllCurrentRegimen()
    {
        $artCodeDb = $this->sm->get('ArtCodeTable');
        return $artCodeDb->fetchAllCurrentRegimen();
    }
    
    public function getSampleDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleDetails($params);
    }
    
    public function getBarSampleDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchBarSampleDetails($params);
    }
    
    public function getLabFilterSampleDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabFilterSampleDetails($params);
    }
    
    public function getFilterSampleDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFilterSampleDetails($params);
    }
    
    public function getFilterSampleTatDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFilterSampleTatDetails($params);
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
				->join(array('fd'=>'facility_details'),'fd.facility_id=vl.facility_id',array('facility_name','facility_code','facility_logo'),'left')
				->join(array('l_s'=>'location_details'),'l_s.location_id=fd.facility_state',array('provinceName'=>'location_name'),'left')
				->join(array('l_d'=>'location_details'),'l_d.location_id=fd.facility_district',array('districtName'=>'location_name'),'left')
				->join(array('r_s_t'=>'r_sample_type'),'r_s_t.sample_id=vl.sample_type',array('sample_name'),'left')
				->join(array('l'=>'facility_details'),'l.facility_id=vl.lab_id',array('labName'=>'facility_name'),'left')
				->join(array('u'=>'user_details'),'u.user_id=vl.result_approved_by',array('approvedBy'=>'user_name'),'left')
				->where(array('vl.vl_sample_id'=>$params['id']));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
      return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }
    
    public function generateResultExcel($params){
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
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
                        $sampleCollectionDate = '';
                        $sampleTestedDate = '';
                        if(isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate']!= NULL && trim($aRow['sampleCollectionDate'])!="" && $aRow['sampleCollectionDate']!= '0000-00-00'){
                            $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
                        }
                        if(isset($aRow['sampleTestingDate']) && $aRow['sampleTestingDate']!= NULL && trim($aRow['sampleTestingDate'])!="" && $aRow['sampleTestingDate']!= '0000-00-00'){
                            $sampleTestedDate = $common->humanDateFormat($aRow['sampleTestingDate']);
                        }
                        $row[] = $aRow['sample_code'];
                        $row[] = ucwords($aRow['facility_name']);
                        $row[] = $sampleCollectionDate;
                        if(trim($params['result']) == '' || trim($params['result']) == 'rejected'){
                           $row[] = (isset($aRow['rejection_reason_name']))?ucwords($aRow['rejection_reason_name']):'';   
                        }
                        if(trim($params['result']) == '' || trim($params['result']) == 'result'){
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
                    
                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Sample ID'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Facility Name'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Date Collected'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    if(trim($params['result']) == ''){
                        $sheet->setCellValue('D1', html_entity_decode($translator->translate('Rejection Reason'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->setCellValue('E1', html_entity_decode($translator->translate('Date Tested'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                        $sheet->setCellValue('F1', html_entity_decode($translator->translate('Viral Load(cp/ml)'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    }else if(trim($params['result']) == 'result'){
                       $sheet->setCellValue('D1', html_entity_decode($translator->translate('Date Tested'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                       $sheet->setCellValue('E1', html_entity_decode($translator->translate('Viral Load(cp/ml)'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    }else if(trim($params['result']) == 'rejected'){
                       $sheet->setCellValue('D1', html_entity_decode($translator->translate('Rejection Reason'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    }
                    
                    $sheet->getStyle('A1')->applyFromArray($styleArray);
                    $sheet->getStyle('B1')->applyFromArray($styleArray);
                    $sheet->getStyle('C1')->applyFromArray($styleArray);
                    if(trim($params['result']) == ''){
                      $sheet->getStyle('D1')->applyFromArray($styleArray);
                      $sheet->getStyle('E1')->applyFromArray($styleArray);
                      $sheet->getStyle('F1')->applyFromArray($styleArray);
                    }else if(trim($params['result']) == 'result'){
                      $sheet->getStyle('D1')->applyFromArray($styleArray);
                      $sheet->getStyle('E1')->applyFromArray($styleArray);
                    }else if(trim($params['result']) == 'rejected'){
                      $sheet->getStyle('D1')->applyFromArray($styleArray);
                    }
                    $currentRow = 2;
                    $endColumn = 5;
                    if(trim($params['result']) == 'result'){
                        $endColumn = 4;
                    }else if(trim($params['result']) == 'noresult'){
                        $endColumn = 2;
                    }else if(trim($params['result']) == 'rejected'){
                       $endColumn = 3; 
                    }
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
    
    public function generateSampleResultExcel($params){
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
            $startMonth = date("Y-m", strtotime(trim($params['fromDate'])))."-01";
            $endMonth = date("Y-m", strtotime(trim($params['toDate'])))."-31";
            if(isset($queryContainer->sampleResultQuery)){
             try{
                $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->getSqlStringForSqlObject($queryContainer->sampleResultQuery);
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
                        $countQuery = $sql->select()->from(array('vl'=>'dash_vl_request_form'))->columns(array('total' => new Expression('COUNT(*)')))
                                          ->where('vl.lab_id="'.$aRow['facility_id'].'"');
                        if(trim($params['fromDate'])!= '' && trim($params['toDate'])!= ''){
                            $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth ." 00:00:00". "'", "vl.sample_collection_date <='" .$endMonth." 23:59:59". "'"));
                        }
                        if(isset($params['clinicId']) && is_array($params['clinicId']) && count($params['clinicId']) >0){
                            $countQuery = $countQuery->where('vl.facility_id IN ("' . implode('", "', $params['clinicId']) . '")');
                        }
                        if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
                            $countQuery = $countQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
                        }
                        if(isset($params['adherence']) && trim($params['adherence'])!=''){
                            $countQuery = $countQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'")); 
                        }
                        if(isset($params['age']) && $params['age']!=''){
                            if($params['age'] == '<18'){
                              $countQuery = $countQuery->where("vl.patient_age_in_years < 18");
                            }else if($params['age'] == '>18') {
                              $countQuery = $countQuery->where("vl.patient_age_in_years > 18");
                            }else if($params['age'] == 'unknown'){
                              $countQuery = $countQuery->where("vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = '' OR vl.patient_age_in_years IS NULL");
                            }
                        }
                        if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
                            $countQuery = $countQuery->where('vl.sample_type="'.base64_decode(trim($params['sampleType'])).'"');
                        }
                        if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested'){
                            $countQuery = $countQuery->where("vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL'");
                        }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                            $countQuery = $countQuery->where("(vl.result IS NULL OR vl.result = 'NULL' OR vl.result = '')");
                        }else if(isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                            $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
                        }
                        if(isset($params['gender']) && $params['gender']=='F'){
                            $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                        }else if(isset($params['gender']) && $params['gender']=='M'){
                            $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                        }else if(isset($params['gender']) && $params['gender']=='not_specified'){
                            $countQuery = $countQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
                        }
                        if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
                            $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
                        }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
                            $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'"); 
                        }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
                            $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')"); 
                        }
                        if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
                            $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
                        }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
                            $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'"); 
                        }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
                            $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')"); 
                        }
                        if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='1'){
                            $countQuery = $countQuery->where("vl.line_of_treatment = '1'");
                        }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='2'){
                            $countQuery = $countQuery->where("vl.line_of_treatment = '2'"); 
                        }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='3'){
                            $countQuery = $countQuery->where("vl.line_of_treatment = '3'"); 
                        }else if(isset($params['lineOfTreatment']) && $params['lineOfTreatment']=='not_specified'){
                            $countQuery = $countQuery->where("(vl.line_of_treatment IS NULL OR vl.line_of_treatment = '' OR vl.line_of_treatment = '0')");
                        }
                        $cQueryStr = $sql->getSqlStringForSqlObject($countQuery);
                        $lessResult = $dbAdapter->query($cQueryStr." AND vl.result < 1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $suppressedTotal = $lessResult->total;
                        $greaterResult = $dbAdapter->query($cQueryStr." AND vl.result >= 1000", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $notSuppressedTotal = $greaterResult->total;
                        $rejectionResult = $dbAdapter->query($cQueryStr." AND vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0", $dbAdapter::QUERY_MODE_EXECUTE)->current();
                        $rejectedTotal = $rejectionResult->total;
                        $row[] = ucwords($aRow['facility_name']);
                        $row[] = $suppressedTotal;
                        $row[] = $notSuppressedTotal;
                        $row[] = $rejectedTotal;
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
                    
                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Lab'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Suppressed'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Not Suppressed'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Rejected'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                   
                    $sheet->getStyle('A1')->applyFromArray($styleArray);
                    $sheet->getStyle('B1')->applyFromArray($styleArray);
                    $sheet->getStyle('C1')->applyFromArray($styleArray);
                    $sheet->getStyle('D1')->applyFromArray($styleArray);
                    
                    $currentRow = 2;
                    foreach ($output as $rowData) {
                        $colNo = 0;
                        foreach ($rowData as $field => $value) {
                            if (!isset($value)) {
                                $value = "";
                            }
                            if($colNo > 3){
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
                    $filename = 'SAMPLE-TEST-RESULT-REPORT--' . date('d-M-Y-H-i-s') . '.xls';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                }else{
                    return "";
                }
             }catch (Exception $exc) {
                error_log("SAMPLE-TEST-RESULT-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
             }  
            }else{
                return "";
            }
        }else{
           return "";
        }
    }
    
    public function generateLabTestedSampleExcel($params){
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if(isset($queryContainer->labTestedSampleQuery)){
            try{
                $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->getSqlStringForSqlObject($queryContainer->labTestedSampleQuery);
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
                        $sampleCollectionDate = '';
                        if(isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate']!= null && trim($aRow['sampleCollectionDate'])!="" && $aRow['sampleCollectionDate']!= '0000-00-00'){
                            $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
                        }
                        $row[] = $sampleCollectionDate;
                        $row[] = $aRow['samples'];
                        $row[] = ucwords($aRow['sample_name']);
                        $row[] = ucwords($aRow['facility_name']);
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
                    
                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Date'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('No. of Samples'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Clinics'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    
                    $sheet->getStyle('A1')->applyFromArray($styleArray);
                    $sheet->getStyle('B1')->applyFromArray($styleArray);
                    $sheet->getStyle('C1')->applyFromArray($styleArray);
                    $sheet->getStyle('D1')->applyFromArray($styleArray);
                    
                    $currentRow = 2;
                    foreach ($output as $rowData) {
                        $colNo = 0;
                        foreach ($rowData as $field => $value) {
                            if (!isset($value)) {
                                $value = "";
                            }
                            if($colNo > 3){
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
                    $filename = 'SAMPLE-TESTED-LAB-REPORT--' . date('d-M-Y-H-i-s') . '.xls';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                }else{
                    return "";
                }
            }catch (Exception $exc) {
                error_log("SAMPLE-TESTED-LAB-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
            }  
        }else{
            return "";
        }
    }
    public function generateLabTestedSampleTatExcel($params){
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if(isset($queryContainer->sampleResultTestedTATQuery)){
            try{
                $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->getSqlStringForSqlObject($queryContainer->sampleResultTestedTATQuery);
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
                        $row[] = $aRow['monthDate'];
                        $row[] = $aRow['total'];
                        $row[] = (isset($aRow['AvgDiff']))?round($aRow['AvgDiff'],2):0;
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
                    
                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Month and Year'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Total No. of Samples Tested'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Average TAT in Days'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    
                    $sheet->getStyle('A1')->applyFromArray($styleArray);
                    $sheet->getStyle('B1')->applyFromArray($styleArray);
                    $sheet->getStyle('C1')->applyFromArray($styleArray);
                    
                    $currentRow = 2;
                    foreach ($output as $rowData) {
                        $colNo = 0;
                        foreach ($rowData as $field => $value) {
                            if (!isset($value)) {
                                $value = "";
                            }
                            if($colNo > 2){
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
                    $filename = 'LAB-TAT-REPORT--' . date('d-M-Y-H-i-s') . '.xls';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                }else{
                    return "";
                }
            }catch (Exception $exc) {
                error_log("LAB-TAT-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
            }  
        }else{
            return "";
        }
    }
    
    public function getProvinceBarSampleResultAwaitedDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchProvinceBarSampleResultAwaitedDetails($params);
    }
    
    public function getDistrictBarSampleResultAwaitedDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchDistrictBarSampleResultAwaitedDetails($params);
    }
    
    public function getClinicBarSampleResultAwaitedDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchClinicBarSampleResultAwaitedDetails($params);
    }
    
    public function getFacilityBarSampleResultAwaitedDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFacilityBarSampleResultAwaitedDetails($params);
    }
    
    public function getFilterSampleResultAwaitedDetails($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFilterSampleResultAwaitedDetails($parameters);
    }
    
    public function generateResultsAwaitedSampleExcel($params){
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if(isset($queryContainer->resultsAwaitedQuery)){
            try{
                $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->getSqlStringForSqlObject($queryContainer->resultsAwaitedQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(isset($sResult) && count($sResult)>0){
                    $excel = new PHPExcel();
                    $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
                    $cacheSettings = array('memoryCacheSize' => '80MB');
                    \PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    foreach ($sResult as $aRow) {
                        $displayCollectionDate = $common->humanDateFormat($aRow['collectionDate']);
                        $displayReceivedDate = $common->humanDateFormat($aRow['receivedDate']);
                        $row = array();
                        $row[] = $aRow['sample_code'];
                        $row[] = $displayCollectionDate;
                        $row[] = $aRow['facilityCode'].' - '.ucwords($aRow['facilityName']);
                        $row[] = (isset($aRow['sample_name']))?ucwords($aRow['sample_name']):'';
                        $row[] = ucwords($aRow['labName']);
                        $row[] = $displayReceivedDate;
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
                    
                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Sample ID'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Collection Date'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Facility'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Lab'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Sample Received at Lab'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    
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
                            if($colNo > 5){
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
                    $filename = 'RESULTS-AWAITED--' . date('d-M-Y-H-i-s') . '.xls';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                }else{
                    return "";
                }
            }catch (Exception $exc) {
                error_log("RESULTS-AWAITED--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
            }  
        }else{
            return "";
        }
    }
    
    public function getSampleTestedResultPregnantPatientDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultPregnantPatientDetails($params);
    }
    
    public function getSampleTestedResultBreastfeedingPatientDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultBreastfeedingPatientDetails($params);
    }
}