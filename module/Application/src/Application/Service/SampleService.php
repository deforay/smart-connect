<?php

namespace Application\Service;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Select;
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
        $sampleRjtReasonDb = $this->sm->get('SampleRejectionReasonTable');
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
                            $instanceCode = trim($sheetData[$i]['B']);

                            $sampleCollectionDate = (trim($sheetData[$i]['U'])!='' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['U']))) :  null);
                            $sampleReceivedAtLab = (trim($sheetData[$i]['AS'])!='' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['AS']))) :  null);
                            $dateOfInitiationOfRegimen = (trim($sheetData[$i]['BA'])!='' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['BA']))) :  null);
                            $resultApprovedDateTime = (trim($sheetData[$i]['BD'])!='' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['BD']))) :  null);
                            $sampleTestedDateTime = (trim($sheetData[$i]['AJ'])!='' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['AJ']))) :  null);
                            

                            $data = array('sample_code'=>$sampleCode,
                                          'vlsm_instance_id'=>trim($sheetData[$i]['B']),
                                          'source'=>$params['sourceName'],
                                          'patient_gender'=>(trim($sheetData[$i]['C'])!='' ? trim($sheetData[$i]['C']) :  NULL),
                                          'patient_age_in_years'=>(trim($sheetData[$i]['D'])!='' ? trim($sheetData[$i]['D']) :  NULL),
                                          'sample_collection_date'=>$sampleCollectionDate,
                                          'sample_received_at_vl_lab'=>$sampleReceivedAtLab,
                                          'line_of_treatment'=>(trim($sheetData[$i]['AT'])!='' ? trim($sheetData[$i]['AT']) :  NULL),
                                          'is_sample_rejected'=>(trim($sheetData[$i]['AU'])!='' ? trim($sheetData[$i]['AU']) :  NULL),
                                          'is_patient_pregnant'=>(trim($sheetData[$i]['AX'])!='' ? trim($sheetData[$i]['AX']) :  NULL),
                                          'is_patient_breastfeeding'=>(trim($sheetData[$i]['AY'])!='' ? trim($sheetData[$i]['AY']) :  NULL),
                                          'patient_art_no'=>(trim($sheetData[$i]['AZ'])!='' ? trim($sheetData[$i]['AZ']) :  NULL),
                                          'date_of_initiation_of_current_regimen'=>$dateOfInitiationOfRegimen,
                                          'arv_adherance_percentage'=>(trim($sheetData[$i]['BB'])!='' ? trim($sheetData[$i]['BB']) :  NULL),
                                          'is_adherance_poor'=>(trim($sheetData[$i]['BC'])!='' ? trim($sheetData[$i]['BC']) :  NULL),
                                          'result_approved_datetime'=>$resultApprovedDateTime,
                                          'sample_tested_datetime'=>$sampleTestedDateTime,
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
                            //check sample rejection reason
                            if(trim($sheetData[$i]['AV'])!=''){
                                $sampleRejectionReason = $this->checkSampleRejectionReason(trim($sheetData[$i]['AV']));
                                if($sampleRejectionReason){
                                    $sampleRjtReasonDb->update(array('rejection_reason_name'=>trim($sheetData[$i]['AV']),'rejection_reason_status'=>trim($sheetData[$i]['AW'])),array('rejection_reason_id'=>$sampleRejectionReason['rejection_reason_id']));
                                    $data['reason_for_sample_rejection'] = $sampleRejectionReason['rejection_reason_id'];
                                }else{
                                    $sampleRjtReasonDb->insert(array('rejection_reason_name'=>trim($sheetData[$i]['AV']),'rejection_reason_status'=>trim($sheetData[$i]['AW'])));
                                    $data['reason_for_sample_rejection'] = $sampleRjtReasonDb->lastInsertValue;
                                }
                            }else{
                                $data['reason_for_sample_rejection'] = NULL;
                            }
                            
                            //check existing sample code
                            $sampleCode = $this->checkSampleCode($sampleCode,$instanceCode);
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
    
    public function checkSampleCode($sampleCode,$instanceCode){
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('dash_vl_request_form')->where(array('sample_code' => $sampleCode,'vlsm_instance_id'=>$instanceCode));
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
    public function checkSampleRejectionReason($rejectReasonName)
    {
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('r_sample_rejection_reasons')->where(array('rejection_reason_name' => $rejectReasonName));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }

    //lab details start
    //get sample status for lab dash
    public function getSampleStatusDataTable($params){
        $sampleDb = $this->sm->get('SampleTableWithoutCache');
        return $sampleDb->getSampleStatusDataTable($params);
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
    
    //get sample tested result details
    public function getSampleTestedResultBasedVolumeDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultBasedVolumeDetails($params);
    }
    
    public function getSampleTestedResultGenderDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultGenderDetails($params);
    }
    
    public function getLabTurnAroundTime($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabTurnAroundTime($params);
    }
    
    public function getSampleTestedResultAgeGroupDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultAgeGroupDetails($params);
    }
    
    public function getSampleTestedResultPregnantPatientDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultPregnantPatientDetails($params);
    }
    
    public function getSampleTestedResultBreastfeedingPatientDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultBreastfeedingPatientDetails($params);
    }
    
    //get Requisition Forms tested
    public function getRequisitionFormsTested($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getRequisitionFormsTested($params);
    }
   
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
    
    public function getFacilites($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFacilites($params);
    }
    
    public function getVlOutComes($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getVlOutComes($params);
    }
    //lab details end
    
    //clinic details start
    public function getOverallViralLoadStatus($params){
        $sampleDb = $this->sm->get('SampleTable');
        //return $sampleDb->fetchOverallViralLoadStatus($params);
        return $sampleDb->fetchOverallViralLoadResult($params);
    }
    
    public function getViralLoadStatusBasedOnGender($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchViralLoadStatusBasedOnGender($params);
    }

    public function getSampleTestedResultBasedGenderDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedResultBasedGenderDetails($params);
    }
    
    public function fetchSampleTestedReason($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSampleTestedReason($params);
    }
    
    public function getAllTestReasonName(){
        $reasonDb = $this->sm->get('TestReasonTable');
        return $reasonDb->fetchAllTestReasonName();
    }
    public function getClinicSampleTestedResultAgeGroupDetails($params){
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
    public function getSampleType(){
        $sampleDb = $this->sm->get('SampleTypeTable');
        return $sampleDb->fetchAllSampleType();
    }
    //get all Lab Name
    public function getAllLabName(){
        $logincontainer = new Container('credo');
        $mappedFacilities = null;
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:null;
        }        
        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllLabName($mappedFacilities);
    }
    //get all Lab Name
    public function getAllClinicName(){

        $logincontainer = new Container('credo');
        $mappedFacilities = null;
        if($logincontainer->role!= 1){
            $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) >0)?$logincontainer->mappedFacilities:null;
        }

        $facilityDb = $this->sm->get('FacilityTable');
        return $facilityDb->fetchAllClinicName($mappedFacilities);
    }
    //get all province name
    public function getAllProvinceList()
    {
        $locationDb = $this->sm->get('LocationDetailsTable');
        return $locationDb->fetchLocationDetails();
    }
    public function getAllDistrictList()
    {
        $locationDb = $this->sm->get('LocationDetailsTable');
        return $locationDb->fetchAllDistrictsList();
    }
    
    public function getAllTestResults($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllTestResults($parameters);
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
    public function getAllCurrentRegimen(){
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
    
    public function getLabFilterSampleDetails($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchLabFilterSampleDetails($parameters);
    }
    
    public function getFilterSampleDetails($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFilterSampleDetails($parameters);
    }
    
    public function getFilterSampleTatDetails($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFilterSampleTatDetails($parameters);
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
				->join(array('f'=>'facility_details'),'f.facility_id=vl.facility_id',array('facility_name','facility_code','facility_logo'),'left')
				->join(array('l_s'=>'location_details'),'l_s.location_id=f.facility_state',array('provinceName'=>'location_name'),'left')
				->join(array('l_d'=>'location_details'),'l_d.location_id=f.facility_district',array('districtName'=>'location_name'),'left')
				->join(array('rs'=>'r_sample_type'),'rs.sample_id=vl.sample_type',array('sample_name'),'left')
				->join(array('l'=>'facility_details'),'l.facility_id=vl.lab_id',array('labName'=>'facility_name'),'left')
				->join(array('u'=>'user_details'),'u.user_id=vl.result_approved_by',array('approvedBy'=>'user_name'),'left')
                                ->join(array('r_r_r'=>'r_sample_rejection_reasons'),'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection',array('rejection_reason_name'),'left')
                                ->join(array('rej_f'=>'facility_details'),'rej_f.facility_id=vl.sample_rejection_facility',array('rejectionFacilityName'=>'facility_name'),'left')
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

    
    public function generateHighVlSampleResultExcel($params){
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
            if(isset($queryContainer->resultQuery)){
             try{
                $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $hQueryStr = $sql->getSqlStringForSqlObject($queryContainer->highVlSampleQuery);
                //error_log($hQueryStr);die;
                $sResult = $dbAdapter->query($hQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(isset($sResult) && count($sResult)>0){
                    $excel = new PHPExcel();
                    $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
                    $cacheSettings = array('memoryCacheSize' => '80MB');
                    \PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    $i = 1;
                    foreach ($sResult as $aRow) {
                        $row = array();
                        if(isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate']!= NULL && trim($aRow['sampleCollectionDate'])!="" && $aRow['sampleCollectionDate']!= '0000-00-00'){
                            $sampleCollectionDate = $common->humanDateFormat($aRow['sampleCollectionDate']);
                        }
                        if(isset($aRow['treatmentInitiateDate']) && $aRow['treatmentInitiateDate']!= NULL && trim($aRow['treatmentInitiateDate'])!="" && $aRow['treatmentInitiateDate']!= '0000-00-00'){
                            $treatmentInitiateDate = $common->humanDateFormat($aRow['treatmentInitiateDate']);
                        }
                        if(isset($aRow['patientDOB']) && $aRow['patientDOB']!= NULL && trim($aRow['patientDOB'])!="" && $aRow['patientDOB']!= '0000-00-00'){
                            $patientDOB = $common->humanDateFormat($aRow['patientDOB']);
                        }
                        if(isset($aRow['treatmentInitiateCurrentRegimen']) && $aRow['treatmentInitiateCurrentRegimen']!= NULL && trim($aRow['treatmentInitiateCurrentRegimen'])!="" && $aRow['treatmentInitiateCurrentRegimen']!= '0000-00-00'){
                            $patientDOB = $common->humanDateFormat($aRow['patitreatmentInitiateCurrentRegimenentDOB']);
                        }
                        if(isset($aRow['requestDate']) && $aRow['requestDate']!= NULL && trim($aRow['requestDate'])!="" && $aRow['requestDate']!= '0000-00-00'){
                            $requestDate = $common->humanDateFormat($aRow['requestDate']);
                        }
                        if(isset($aRow['receivedAtLab']) && $aRow['receivedAtLab']!= NULL && trim($aRow['receivedAtLab'])!="" && $aRow['receivedAtLab']!= '0000-00-00'){
                            $requestDate = $common->humanDateFormat($aRow['receivedAtLab']);
                        }
                        $row[] = $i;
                        $row[] = $aRow['sample_code'];
                        $row[] = ucwords($aRow['facility_name']);
                        $row[] = $aRow['facility_code'];
                        $row[] = $aRow['facilityDistrict'];
                        $row[] = $aRow['facilityState'];
                        $row[] = $aRow['patient_art_no'];
                        $row[] = ucwords($aRow['first_name']." ".$aRow['middle_name']." ".$aRow['last_name']);
                        $row[] = $patientDOB;
                        $row[] = $aRow['patient_age_in_years'];
                        $row[] = $aRow['patient_gender'];
                        $row[] = $sampleCollectionDate;
                        $row[] = $aRow['sample_name'];
                        $row[] = $treatmentInitiateDate;
                        $row[] = $aRow['current_regimen'];
                        $row[] = $treatmentInitiateCurrentRegimen;
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
                            'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PHPExcel_Style_Border::BORDER_THIN,
                            ),
                        )
                    );
                    
                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('No.'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Sample Code'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Health Facility Name'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Health Facility Code'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('District/County'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Province/State'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('G1', html_entity_decode($translator->translate('Unique ART No.'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('H1', html_entity_decode($translator->translate('Patient Name'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('I1', html_entity_decode($translator->translate('Date of Birth'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('J1', html_entity_decode($translator->translate('Age'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('K1', html_entity_decode($translator->translate('Gender'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('L1', html_entity_decode($translator->translate('Date of Sample Collection'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('M1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('N1', html_entity_decode($translator->translate('Date of Treatment Initiation'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('O1', html_entity_decode($translator->translate('Current Regimen'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('P1', html_entity_decode($translator->translate('Date of Initiation of Current Regimen'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('Q1', html_entity_decode($translator->translate('Is Patient Pregnant'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('R1', html_entity_decode($translator->translate('Is Patient Breastfeeding'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('S1', html_entity_decode($translator->translate('ARV Adherence'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('T1', html_entity_decode($translator->translate('Requesting Clinican'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('U1', html_entity_decode($translator->translate('Request Date'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('V1', html_entity_decode($translator->translate('Date Sample Received at Lab'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('W1', html_entity_decode($translator->translate('VL Result (cp/ml)'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('X1', html_entity_decode($translator->translate('Vl Result (log)'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('Y1', html_entity_decode($translator->translate('Rejection Reason (if Rejected)'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                   
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
                    $filename = 'HIGH-VL-SAMPLE-RESULT-REPORT--' . date('d-M-Y-H-i-s') . '.xls';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                }else{
                    return "";
                }
             }catch (Exception $exc) {
                error_log("HIGH-VL-SAMPLE-RESULT-REPORT--" . $exc->getMessage());
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
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Samples Collected'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Samples Tested'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Samples Pending'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Suppressed'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Not Suppressed'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('G1', html_entity_decode($translator->translate('Samples Rejected'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                   
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
                            if($colNo > 6){
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
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Samples Collected'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Samples Tested'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Samples Pending'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Suppressed'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Not Suppressed'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('G1', html_entity_decode($translator->translate('Samples Rejected'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('H1', html_entity_decode($translator->translate('Sample Type'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('I1', html_entity_decode($translator->translate('Clinics'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    
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
                            if($colNo > 8){
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
                        $row[] = $aRow['total_samples_received'];
                        $row[] = $aRow['total_samples_tested'];
                        $row[] = $aRow['total_samples_pending'];
                        $row[] = $aRow['suppressed_samples'];
                        $row[] = $aRow['not_suppressed_samples'];
                        $row[] = $aRow['rejected_samples'];
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
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Samples Collected'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('Samples Tested'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Samples Pending'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Suppressed'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Not Suppressed'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('G1', html_entity_decode($translator->translate('Samples Rejected'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('H1', html_entity_decode($translator->translate('Average TAT in Days'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    
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
                            if($colNo > 7){
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
    
    public function getFacilityBarSampleResultAwaitedDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchFacilityBarSampleResultAwaitedDetails($params);
    }
    
    public function getDistrictBarSampleResultAwaitedDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchDistrictBarSampleResultAwaitedDetails($params);
    }
    
    public function getClinicBarSampleResultAwaitedDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchClinicBarSampleResultAwaitedDetails($params);
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
    
    public function getAllSamples($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSamples($parameters);
    }
    
    public function removeDuplicateSampleRows($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->removeDuplicateSampleRows($params);
    }
    
    public function getVLTestReasonBasedOnAgeGroup($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getVLTestReasonBasedOnAgeGroup($params);
    }
    
    public function getVLTestReasonBasedOnGender($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getVLTestReasonBasedOnGender($params);
    }
    
    public function getVLTestReasonBasedOnClinics($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getVLTestReasonBasedOnClinics($params);
    }
    
    public function getSample($id){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getSample($id);
    }
    ////////////////////////////////////////
    /////////*** Turnaround Time ***///////
    ///////////////////////////////////////

    public function getTATbyProvince($facilities,$labs,$startDate,$endDate){
        set_time_limit(10000);
        $result = array();
        $time = array();
        $sampleDb = $this->sm->get('SampleTable');
        foreach ($facilities as $facility) {
          $time = $sampleDb->getTATbyProvince($facility['location_id'],$labs,$startDate,$endDate);
          foreach ($time as $key) {
            $collect_receive    = $key['Collection_Receive'];
            $receive_register   = $key['Receive_Register'];
            $register_analysis  = $key['Register_Analysis'];
            $analysis_authorise = $key['Analysis_Authorise'];
          }
          $result[] = array(
              "facility"           => $facility['location_name'],
              "facility_id"        => $facility['location_id'],
              "category"           => 0,
              "collect_receive"    => round($collect_receive,1),
              "receive_register"   => round($receive_register,1),
              "register_analysis"  => round($register_analysis,1),
              "analysis_authorise" => round($analysis_authorise,1)
          );
        }
        return $result;
    }
    
    public function getTATbyDistrict($facilities,$labs,$startDate,$endDate){
        set_time_limit(10000);
        $result = array();
        $time = array();
        $sampleDb = $this->sm->get('SampleTable');
        foreach ($facilities as $facility) {
          $time = $sampleDb->getTATbyDistrict($facility['location_id'],$labs,$startDate,$endDate);
          foreach ($time as $key) {
            $collect_receive    = $key['Collection_Receive'];
            $receive_register   = $key['Receive_Register'];
            $register_analysis  = $key['Register_Analysis'];
            $analysis_authorise = $key['Analysis_Authorise'];
          }
          $result[] = array(
              "facility"           => $facility['location_name'],
              "facility_id"        => $facility['location_id'],
              "category"           => 0,
              "collect_receive"    => round($collect_receive,1),
              "receive_register"   => round($receive_register,1),
              "register_analysis"  => round($register_analysis,1),
              "analysis_authorise" => round($analysis_authorise,1)
          );
        }
        return $result;
    }
    
    public function getTATbyClinic($facilities,$labs,$startDate,$endDate){
        set_time_limit(10000);
        $result = array();
        $time = array();
        $sampleDb = $this->sm->get('SampleTable');
        foreach ($facilities as $facility) {
          $time = $sampleDb->getTATbyClinic($facility['facility_id'],$labs,$startDate,$endDate);
          foreach ($time as $key) {
            $collect_receive    = $key['Collection_Receive'];
            $receive_register   = $key['Receive_Register'];
            $register_analysis  = $key['Register_Analysis'];
            $analysis_authorise = $key['Analysis_Authorise'];
          }
          $result[] = array(
              "facility"           => $facility['facility_name'],
              "facility_id"        => $facility['facility_id'],
              "category"           => 1,
              "collect_receive"    => round($collect_receive,1),
              "receive_register"   => round($receive_register,1),
              "register_analysis"  => round($register_analysis,1),
              "analysis_authorise" => round($analysis_authorise,1)
          );
        }
        return $result;
    }
    
    ////////////////////////////////////////
    ////////*** Turnaround Time ***////////
    ///////////////////////////////////////
    
    public function importSampleResultFile(){
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
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $files = scandir($pathname, SCANDIR_SORT_DESCENDING);
        $newest_file = $files[0];
        
        if(trim($newest_file)!=""){
            try {
                //Hardcoded source details
                $fileName =$newest_file;
                if (file_exists($pathname . DIRECTORY_SEPARATOR . $fileName)) {
                    $objPHPExcel = \PHPExcel_IOFactory::load($pathname . DIRECTORY_SEPARATOR . $fileName);
                    $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
                    $count = count($sheetData);
                    for ($i = 2; $i <= $count; $i++) {
                            if(trim($sheetData[$i]['A']) != '' && trim($sheetData[$i]['B']) != '') {

                                
                                $sampleCode = trim($sheetData[$i]['A']);
                                $instanceCode = trim($sheetData[$i]['B']);


                                $VLAnalysisResult = (float)$sheetData[$i]['AN'];
                                $DashVL_Abs = NULL; 
                                $DashVL_AnalysisResult = NULL;                                

                                if ($sheetData[$i]['AM'] == 'Target not Detected' || $sheetData[$i]['AM'] == 'Target Not Detected' || strtolower($sheetData[$i]['AM']) == 'target not detected' || strtolower($sheetData[$i]['AM']) == 'tnd'
                                    || $sheetData[$i]['AO'] == 'Target not Detected' || $sheetData[$i]['AO'] == 'Target Not Detected' || strtolower($sheetData[$i]['AO']) == 'target not detected' || strtolower($sheetData[$i]['AO']) == 'tnd'
                                    ) {
                                    $VLAnalysisResult = 20;
                                }
                                else if ($sheetData[$i]['AM'] == '< 20' || $sheetData[$i]['AM'] == '<20' || $sheetData[$i]['AO'] == '< 20' || $sheetData[$i]['AO'] == '<20') {
                                    $VLAnalysisResult = 20;
                                }
                                else if ($sheetData[$i]['AM'] == '< 40' || $sheetData[$i]['AM'] == '<40' || $sheetData[$i]['AO'] == '< 40' || $sheetData[$i]['AO'] == '<40') {
                                    $VLAnalysisResult = 40;
                                }
                                else if ($sheetData[$i]['AM'] == 'Nivel de detecÁao baixo' || $sheetData[$i]['AM'] == 'NÌvel de detecÁ„o baixo' || $sheetData[$i]['AO'] == 'Nivel de detecÁao baixo' || $sheetData[$i]['AO'] == 'NÌvel de detecÁ„o baixo') {
                                    $VLAnalysisResult = 20;
                                }
                                else if ($sheetData[$i]['AM'] == 'Suppressed' || $sheetData[$i]['AO'] == 'Suppressed') {
                                    $VLAnalysisResult = 500;
                                }
                                else if ($sheetData[$i]['AM'] == 'Not Suppressed' || $sheetData[$i]['AO'] == 'Not Suppressed') {
                                    $VLAnalysisResult = 1500;
                                }
                                else if ($sheetData[$i]['AM'] == 'Negative' || $sheetData[$i]['AM'] == 'NEGAT' || $sheetData[$i]['AO'] == 'Negative' || $sheetData[$i]['AO'] == 'NEGAT' ) {
                                    $VLAnalysisResult = 20;
                                }	
                                else if ($sheetData[$i]['AM'] == 'Positive' || $sheetData[$i]['AO'] == 'Positive') {
                                    $VLAnalysisResult = 1500;
                                }	
                                else if ($sheetData[$i]['AM'] == 'Indeterminado' || $sheetData[$i]['AO'] == 'Indeterminado') {
                                    $VLAnalysisResult = "";
                                }	

                            
                                if ($VLAnalysisResult == 'NULL' || $VLAnalysisResult == '' || $VLAnalysisResult == NULL){
                                    $DashVL_Abs = NULL; 
                                    $DashVL_AnalysisResult = NULL;                                
                                }else if ($VLAnalysisResult < 1000){
                                    $DashVL_AnalysisResult ='Suppressed';
                                    $DashVL_Abs = $VLAnalysisResult;
                                }else if ($VLAnalysisResult >= 1000){
                                    $DashVL_AnalysisResult ='Not Suppressed';
                                    $DashVL_Abs = $VLAnalysisResult;
                                }
                                



                                $sampleCollectionDate = (trim($sheetData[$i]['U'])!='' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['U']))) :  null);
                                $sampleReceivedAtLab = (trim($sheetData[$i]['AS'])!='' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['AS']))) :  null);
                                $dateOfInitiationOfRegimen = (trim($sheetData[$i]['BA'])!='' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['BA']))) :  null);
                                $resultApprovedDateTime = (trim($sheetData[$i]['BD'])!='' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['BD']))) :  null);
                                $sampleTestedDateTime = (trim($sheetData[$i]['AJ'])!='' ? trim(date('Y-m-d H:i', strtotime($sheetData[$i]['AJ']))) :  null);
                                
                                    


                                $data = array('sample_code'=>$sampleCode,
                                        'vlsm_instance_id'=>trim($sheetData[$i]['B']),
                                        'source'=>'1',
                                        'patient_gender'=>(trim($sheetData[$i]['C'])!='' ? trim($sheetData[$i]['C']) :  NULL),
                                        'patient_age_in_years'=>(trim($sheetData[$i]['D'])!='' ? trim($sheetData[$i]['D']) :  NULL),
                                        'sample_collection_date'=>$sampleCollectionDate,
                                        'sample_registered_at_lab'=>$sampleReceivedAtLab,
                                        'line_of_treatment'=>(trim($sheetData[$i]['AT'])!='' ? trim($sheetData[$i]['AT']) :  NULL),
                                        'is_sample_rejected'=>(trim($sheetData[$i]['AU'])!='' ? trim($sheetData[$i]['AU']) :  NULL),
                                        'is_patient_pregnant'=>(trim($sheetData[$i]['AX'])!='' ? trim($sheetData[$i]['AX']) :  NULL),
                                        'is_patient_breastfeeding'=>(trim($sheetData[$i]['AY'])!='' ? trim($sheetData[$i]['AY']) :  NULL),
                                        'current_regimen'=>(trim($sheetData[$i]['AZ'])!='' ? trim($sheetData[$i]['AZ']) :  NULL),
                                        'date_of_initiation_of_current_regimen'=>$dateOfInitiationOfRegimen,
                                        'arv_adherance_percentage'=>(trim($sheetData[$i]['BB'])!='' ? trim($sheetData[$i]['BB']) :  NULL),
                                        'is_adherance_poor'=>(trim($sheetData[$i]['BC'])!='' ? trim($sheetData[$i]['BC']) :  NULL),
                                        'result_approved_datetime'=>$resultApprovedDateTime,
                                        'sample_tested_datetime'=>$sampleTestedDateTime,
                                        'result_value_log'=>(trim($sheetData[$i]['AK'])!='' ? trim($sheetData[$i]['AK']) :  NULL),
                                        'result_value_absolute'=>(trim($sheetData[$i]['AL'])!='' ? trim($sheetData[$i]['AL']) :  NULL),
                                        'result_value_text'=>(trim($sheetData[$i]['AM'])!='' ? trim($sheetData[$i]['AM']) :  NULL),
                                        'result_value_absolute_decimal'=>(trim($sheetData[$i]['AN'])!='' ? trim($sheetData[$i]['AN']) :  NULL),
                                        'result'=>(trim($sheetData[$i]['AO'])!='' ? trim($sheetData[$i]['AO']) :  NULL),
                                        'DashVL_Abs' =>   $DashVL_Abs,
                                        'DashVL_AnalysisResult' =>   $DashVL_AnalysisResult,
                                        'current_regimen'=>(trim($sheetData[$i]['BG'])!='' ? trim($sheetData[$i]['BG']) :  NULL),                                   
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
                                //check sample rejection reason
                                if(trim($sheetData[$i]['AV'])!=''){
                                    $sampleRejectionReason = $this->checkSampleRejectionReason(trim($sheetData[$i]['AV']));
                                    if($sampleRejectionReason){
                                        $sampleRjtReasonDb->update(array('rejection_reason_name'=>trim($sheetData[$i]['AV']),'rejection_reason_status'=>trim($sheetData[$i]['AW'])),array('rejection_reason_id'=>$sampleRejectionReason['rejection_reason_id']));
                                        $data['reason_for_sample_rejection'] = $sampleRejectionReason['rejection_reason_id'];
                                    }else{
                                        $sampleRjtReasonDb->insert(array('rejection_reason_name'=>trim($sheetData[$i]['AV']),'rejection_reason_status'=>trim($sheetData[$i]['AW'])));
                                        $data['reason_for_sample_rejection'] = $sampleRjtReasonDb->lastInsertValue;
                                    }
                                }else{
                                    $data['reason_for_sample_rejection'] = NULL;
                                }
                                
                                //check existing sample code
                                $sampleCode = $this->checkSampleCode($sampleCode,$instanceCode);
                                if($sampleCode){
                                    //sample data update
                                    $sampleDb->update($data,array('vl_sample_id'=>$sampleCode['vl_sample_id']));
                                }else{
                                    //sample data insert
                                    $sampleDb->insert($data);
                                }
                            }
                        }
                        
                        $destination=UPLOAD_PATH . DIRECTORY_SEPARATOR . "import-vl";
                        if (!file_exists($destination) && !is_dir($destination)) {
                            mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "import-vl");
                        }
                        
                        if (copy($pathname . DIRECTORY_SEPARATOR . $fileName, $destination. DIRECTORY_SEPARATOR.$fileName)) {
                            unlink($pathname . DIRECTORY_SEPARATOR . $fileName);
                        }
                }
            }catch (Exception $exc) {
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

    public function generateSampleStatusResultExcel($params){
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if(isset($queryContainer->sampleStatusResultQuery)){
            try{
                $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->getSqlStringForSqlObject($queryContainer->sampleStatusResultQuery);
                
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

                        $row[]=$aRow['monthyear'];
                        $row[] = ucwords($aRow['facility_name']);
                        $row[]=$aRow['district'];
                        $row[]=$aRow['lab_name'];
	                    $row[]=$aRow['total_samples_received'];
	                    $row[]=$aRow['total_samples_tested'];
	                    $row[]=$aRow['total_samples_rejected'];
	                    $row[]=$aRow['total_hvl_samples'];
                        $row[]=$aRow['total_lvl_samples'];

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
                    $sheet->setCellValue('B1', html_entity_decode($translator->translate('Facility Name'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('C1', html_entity_decode($translator->translate('District'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('D1', html_entity_decode($translator->translate('Lab Name'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('E1', html_entity_decode($translator->translate('Samples Registered'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('F1', html_entity_decode($translator->translate('Samples Tested'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('G1', html_entity_decode($translator->translate('Samples Rejected'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('H1', html_entity_decode($translator->translate('No.Of High VL'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    $sheet->setCellValue('I1', html_entity_decode($translator->translate('No.Of Low VL'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    
                   
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
                            if($colNo > 8){
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
                    $filename = 'SAMPLE-STATUS-RESULT-REPORT--' . date('d-M-Y-H-i-s') . '.xls';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                }else{
                    return "";
                }
             }catch (Exception $exc) {
                error_log("SAMPLE-STATUS-RESULT-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
             }  
        }else{
            return "";
        }
        
    }
}
