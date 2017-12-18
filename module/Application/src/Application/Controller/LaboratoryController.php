<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class LaboratoryController extends AbstractActionController{

    public function indexAction(){
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        return $this->_redirect()->toUrl('/labs/dashboard'); 
    }

    public function dashboardAction(){
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $sampleType = $sampleService->getSampleType();
        $labName = $sampleService->getAllLabName();        
        return new ViewModel(array(
                    'sampleType' => $sampleType,
                    'labName' => $labName
                ));
    }
    

    public function samplesAccessionAction(){
        $this->layout()->setVariable('activeTab', 'labs-dashboard');          
        return new ViewModel();
    }
    
    public function samplesWaitingAction(){
        $this->layout()->setVariable('activeTab', 'labs-dashboard');                
        return new ViewModel();
    }
    
    
    public function samplesRejectedAction(){
        $this->layout()->setVariable('activeTab', 'labs-dashboard');                 
        return new ViewModel();
    }
    
    public function samplesTestedAction(){
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $gender="";
        $month="";
        $range="";
        $age="";
        $fromMonth="";
        $toMonth="";
        $labFilter="";
        $femaleFilter="";
        $lt="";
        $result="";
        if($this->params()->fromQuery('gender')){
            $gender=$this->params()->fromQuery('gender');
        }
        if($this->params()->fromQuery('month')){
            $month=$this->params()->fromQuery('month');
        }
        if($this->params()->fromQuery('range')){
            $range=$this->params()->fromQuery('range');
        }
        if($this->params()->fromQuery('age')){
            $age=$this->params()->fromQuery('age');
        }
        if($this->params()->fromQuery('fromMonth')){
            $fromMonth=$this->params()->fromQuery('fromMonth');
        }
        if($this->params()->fromQuery('toMonth')){
            $toMonth=$this->params()->fromQuery('toMonth');
        }
        if($this->params()->fromQuery('lab')){
            $labFilter=$this->params()->fromQuery('lab');
            $params['labs'] = explode(',',$labFilter);
        }
        if($this->params()->fromQuery('femaleFilter')){
            $femaleFilter=$this->params()->fromQuery('femaleFilter');
        }
        if($this->params()->fromQuery('lt')){
            $lt=$this->params()->fromQuery('lt');
        }
        if($this->params()->fromQuery('result')){
            $result=$this->params()->fromQuery('result');
        }
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $commonService = $this->getServiceLocator()->get('CommonService');
        $hubName = $sampleService->getAllHubName();
        $sampleType = $sampleService->getSampleType();
        $currentRegimen = $sampleService->getAllCurrentRegimen();
        $facilityInfo = $commonService->getSampleTestedFacilityInfo($params);
        //print_r($facilityInfo);die;
        return new ViewModel(array(
                'sampleType' => $sampleType,
                'hubName' => $hubName,
                'currentRegimen' => $currentRegimen,
                'searchMonth' => $month,
                'searchGender' => $gender,
                'searchRange' => $range,
                'fromMonth' => $fromMonth,
                'toMonth' => $toMonth,
                'labFilter' => $labFilter,
                'age' => $age,
                'femaleFilter' => $femaleFilter,
                'lt' => $lt,
                'facilityInfo' => $facilityInfo,
                'result' => $result
        ));
    }
    
    public function requisitionFormsIncompleteAction(){
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $month="";
        $labFilter = "";
        if($this->params()->fromQuery('month')){
            $month=$this->params()->fromQuery('month');
        }
        if($this->params()->fromQuery('lab')){
            $labFilter=$this->params()->fromQuery('lab');
            $params['labs'] = $labFilter;
        }
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $commonService = $this->getServiceLocator()->get('CommonService');
        //$labList = $sampleService->getAllLabName();
        $facilityInfo = $commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
            //'labList' => $labList,
            'searchMonth' => $month,
            'labFilter' => $labFilter,
            'facilityInfo' => $facilityInfo
        ));
    }
    
    public function getIncompleteSampleDetailsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getIncompleteSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getIncompleteBarSampleDetailsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getIncompleteBarSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleResultAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleResultDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params'=>$params,'result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleTestResultAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleTestedResultDetails($params);
            $sampleType = $sampleService->getSampleType();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'sampleType'=>$sampleType))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleTestResultVolumeAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleTestedResultBasedVolumeDetails($params);
            $sampleType = $sampleService->getSampleType();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'sampleType'=>$sampleType))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleTestResultGenderAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $params['gender'] = 'yes';
            $result = $sampleService->getSampleTestedResultGenderDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleTestResultAgeGroupAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getLabTurnAroundTimeAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $sampleType = $sampleService->getSampleType();
            $result = $sampleService->getLabTurnAroundTime($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'sampleType'=>$sampleType))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getRequisitionFormsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getRequisitionFormsTested($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleVolumeAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleVolume($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getFemalePatientResultAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getFemalePatientResult($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getLineOfTreatmentAction(){
        $request = $this->getRequest();
        if($request->isPost()) {
           $params = $request->getPost();
           $sampleService = $this->getServiceLocator()->get('SampleService');
           $result = $sampleService->getLineOfTreatment($params);
           $viewModel = new ViewModel();
           $viewModel->setVariables(array('result' => $result))
                       ->setTerminal(true);
           return $viewModel;
       }
    }
    
    public function getVlOutComesAction(){
        $request = $this->getRequest();
        if($request->isPost()) {
           $params = $request->getPost();
           $sampleService = $this->getServiceLocator()->get('SampleService');
           $result = $sampleService->getVlOutComes($params);
           $viewModel = new ViewModel();
           $viewModel->setVariables(array('result' => $result))
                       ->setTerminal(true);
           return $viewModel;
       }
    }
    
    public function getLabFacilitiesAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $sampleType = $sampleService->getSampleType();
            $result = $sampleService->getFacilites($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'height'=>$params['height']))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleDetailsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params'=>$params,'result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getBarSampleDetailsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getBarSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params'=>$params,'result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getLabFilterSampleDetailsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getLabFilterSampleDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function getFilterSampleDetailsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getFilterSampleDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function samplesTestedLabAction(){
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $gender="";
        $month="";
        $range="";
        $age="";
        $fromMonth="";
        $toMonth="";
        $labFilter="";
        $params['fromSrc'] = 'tested-lab';
        if($this->params()->fromQuery('gender')){
            $gender=$this->params()->fromQuery('gender');
        }
        if($this->params()->fromQuery('month')){
            $month=$this->params()->fromQuery('month');
        }
        if($this->params()->fromQuery('range')){
            $range=$this->params()->fromQuery('range');
        }
        if($this->params()->fromQuery('age')){
            $age=$this->params()->fromQuery('age');
        }
        if($this->params()->fromQuery('fromMonth')){
            $fromMonth=$this->params()->fromQuery('fromMonth');
        }
        if($this->params()->fromQuery('toMonth')){
            $toMonth=$this->params()->fromQuery('toMonth');
        }
        if($this->params()->fromQuery('lab')){
            $labFilter = $this->params()->fromQuery('lab');
            $params['labNames'] = explode(',',$labFilter);
        }
        
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $commonService = $this->getServiceLocator()->get('commonService');
        $hubName = $sampleService->getAllHubName();
        $sampleType = $sampleService->getSampleType();
        $currentRegimen = $sampleService->getAllCurrentRegimen();
        $facilityInfo = $commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
                'sampleType' => $sampleType,
                'hubName' => $hubName,
                'currentRegimen' => $currentRegimen,
                'searchMonth' => $month,
                'searchGender' => $gender,
                'searchRange' => $range,
                'fromMonth' => $fromMonth,
                'toMonth' => $toMonth,
                'labFilter' => $labFilter,
                'age' => $age,
                'facilityInfo' => $facilityInfo
        ));
    }
    
    public function getLabSampleDetailsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getLabSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getLabBarSampleDetailsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getLabBarSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function sampleVolumeAction(){
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $fromMonth = "";
        $toMonth = "";
        $labFilter = "";
        $sampleStatus = "";
        $params['fromSrc'] = 'sample-volume';
        if($this->params()->fromQuery('fromMonth')){
            $fromMonth=$this->params()->fromQuery('fromMonth');
        }
        if($this->params()->fromQuery('toMonth')){
            $toMonth=$this->params()->fromQuery('toMonth');
        }
        if($this->params()->fromQuery('lab')){
            $labFilter = $this->params()->fromQuery('lab');
            $params['labCodes'] = explode(',',$labFilter);
        }
        if($this->params()->fromQuery('result')){
            $sampleStatus=$this->params()->fromQuery('result');
        }
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $commonService = $this->getServiceLocator()->get('CommonService');
        $hubName = $sampleService->getAllHubName();
        $sampleType = $sampleService->getSampleType();
        $currentRegimen = $sampleService->getAllCurrentRegimen();
        $facilityInfo = $commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
            'sampleType' => $sampleType,
            'hubName' => $hubName,
            'currentRegimen' => $currentRegimen,
            'fromMonth' => $fromMonth,
            'toMonth' => $toMonth,
            'labFilter' => $labFilter,
            'sampleStatus' => $sampleStatus,
            'facilityInfo' => $facilityInfo
        ));
    }
    
    public function exportSampleResultExcelAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $file=$sampleService->generateSampleResultExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' =>$file))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function exportLabTestedSampleExcelAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $file=$sampleService->generateLabTestedSampleExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' =>$file))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function samplesTestedTurnAroundTimeAction(){
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $gender="";
        $month="";
        $range="";
        $age="";
        $fromMonth="";
        $toMonth="";
        $labFilter="";
        if($this->params()->fromQuery('gender')){
            $gender=$this->params()->fromQuery('gender');
        }
        if($this->params()->fromQuery('month')){
            $month=$this->params()->fromQuery('month');
        }
        if($this->params()->fromQuery('range')){
            $range=$this->params()->fromQuery('range');
        }
        if($this->params()->fromQuery('age')){
            $age=$this->params()->fromQuery('age');
        }
        if($this->params()->fromQuery('fromMonth')){
            $fromMonth=$this->params()->fromQuery('fromMonth');
        }
        if($this->params()->fromQuery('toMonth')){
            $toMonth=$this->params()->fromQuery('toMonth');
        }
        if($this->params()->fromQuery('lab')){
            $labFilter=$this->params()->fromQuery('lab');
            $params['labs'] = explode(',',$labFilter);
        }
        
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $commonService = $this->getServiceLocator()->get('CommonService');
        $hubName = $sampleService->getAllHubName();
        $sampleType = $sampleService->getSampleType();
        $currentRegimen = $sampleService->getAllCurrentRegimen();
        $facilityInfo = $commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
                'sampleType' => $sampleType,
                'hubName' => $hubName,
                'currentRegimen' => $currentRegimen,
                'searchMonth' => $month,
                'searchGender' => $gender,
                'searchRange' => $range,
                'fromMonth' => $fromMonth,
                'toMonth' => $toMonth,
                'labFilter' => $labFilter,
                'age' => $age,
                'facilityInfo' => $facilityInfo
        ));
    }
    
    public function getBarSampleTatAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getBarSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params'=>$params,'result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getPieSampleTatAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params'=>$params,'result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getFilterSampleTatAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getFilterSampleTatDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function exportSampleTestedResultTatExcelAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $file=$sampleService->generateLabTestedSampleTatExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' =>$file))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getFacilitiesGeolocationAction() {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $fromDate="";
        $toDate="";
        $labFilter="";
        if($this->params()->fromQuery('fromDate')){
            $fromDate=$this->params()->fromQuery('fromDate');
        }
        if($this->params()->fromQuery('toDate')){
            $toDate=$this->params()->fromQuery('toDate');
        }
        if($this->params()->fromQuery('lab')){
          $labFilter=$this->params()->fromQuery('lab');  
        }
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $labName = $sampleService->getAllLabName();
        if(trim($fromDate)!='' && trim($toDate)!=''){
           return new ViewModel(array('fromMonth'=>date('M-Y',strtotime($fromDate)),'toMonth'=>date('M-Y',strtotime($toDate)),'labFilter'=>$labFilter,'labName'=>$labName));
        }else{
            return $this->redirect()->toUrl("/labs/dashboard");
        }
    }
    
    public function getLocationInfoAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $commonService = $this->getServiceLocator()->get('CommonService');
            $result=$commonService->getSampleTestedLocationInfo($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' =>$result))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function sampleResultAwaitedAction() {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $frmSource="";
        $labFilter="";
        if($this->params()->fromQuery('src')){
            $frmSource = $this->params()->fromQuery('src');
        }if($this->params()->fromQuery('lab')){
            $labFilter=$this->params()->fromQuery('lab');
            $params['labs'] = explode(',',$labFilter);
        }
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $commonService = $this->getServiceLocator()->get('CommonService');
        $hubName = $sampleService->getAllHubName();
        $sampleType = $sampleService->getSampleType();
        $currentRegimen = $sampleService->getAllCurrentRegimen();
        $facilityInfo = $commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
            'frmSource' => $frmSource,
            'labFilter' => $labFilter,
            'sampleType' => $sampleType,
            'hubName' => $hubName,
            'currentRegimen' => $currentRegimen,
            'facilityInfo' => $facilityInfo
        ));
    }
    
    public function getProvinceBarSampleResultAwaitedDetailsAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result=$sampleService->getProvinceBarSampleResultAwaitedDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' =>$result))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getDistrictBarSampleResultAwaitedDetailsAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result=$sampleService->getDistrictBarSampleResultAwaitedDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' =>$result))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getClinicBarSampleResultAwaitedDetailsAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result=$sampleService->getClinicBarSampleResultAwaitedDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' =>$result))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getFacilityBarSampleResultAwaitedDetailsAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result=$sampleService->getFacilityBarSampleResultAwaitedDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' =>$result))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getFilterSampleResultAwaitedDetailsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getFilterSampleResultAwaitedDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function exportResultsAwaitedSampleAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $file=$sampleService->generateResultsAwaitedSampleExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' =>$file))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleTestResultPregnantAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleTestedResultPregnantPatientDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleTestResultBreastfeedingAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleTestedResultBreastfeedingPatientDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
}