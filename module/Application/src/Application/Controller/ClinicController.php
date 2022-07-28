<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class ClinicController extends AbstractActionController{


    private $sampleService = null;
    private $configService = null;

    public function __construct($sampleService, $configService)
    {
        $this->configService = $configService;
        $this->sampleService = $sampleService;
    }


    public function indexAction(){
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');
        return $this->redirect()->toRoute('clinics');
    }

    public function dashboardAction(){
        $this->layout()->setVariable('activeTab', 'clinics');
        // $sampleService = $this->getServiceLocator()->get('SampleService');
        $sampleType = $this->sampleService->getSampleType();
        $clinicName = $this->sampleService->getAllClinicName();
        $testReasonName = $this->sampleService->getAllTestReasonName();    
        $provinceName = $this->sampleService->getAllProvinceList();
        $districtName = $this->sampleService->getAllDistrictList();      
        return new ViewModel(array(
            'sampleType' => $sampleType,
            'clinicName' => $clinicName,
            'testReason' => $testReasonName,
            'provinceName'=>$provinceName,
            'districtName'=>$districtName
        ));
    }
    
    public function getOverallViralLoadStatusAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            
            $params = $request->getPost();

            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $chartResult = $this->sampleService->getOverallViralLoadStatus($params);
            //var_dump($chartResult);die;
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('chartResult'=>$chartResult))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getViralLoadStatusBasedOnGenderAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $chartResult = $this->sampleService->getViralLoadStatusBasedOnGender($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result'=>$chartResult))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleTestReasonAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->fetchSampleTestedReason($params);
            $testReasonName = $this->sampleService->getAllTestReasonName();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'testReason' => $testReasonName))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleTestResultAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getClinicSampleTestedResults($params);
            $sampleType = $this->sampleService->getSampleType();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'sampleType'=>$sampleType))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function testResultAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getAllTestResults($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function generateResultPdfAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            //$configService = $this->getServiceLocator()->get('ConfigService');
            $sampleResult=$this->sampleService->getSampleInfo($params);
            $config=$this->configService->getAllGlobalConfig();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('sampleResult' =>$sampleResult,'config'=>$config))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function exportResultExcelAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            if($params['cFrom']=='high'){
                $file=$this->sampleService->generateHighVlSampleResultExcel($params);
            }else{
                $file=$this->sampleService->generateResultExcel($params);
            }
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' =>$file))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function testResultViewAction(){
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');
        $params = array();
        $params['id'] = base64_decode($this->params()->fromRoute('id'));
        // $sampleService = $this->getServiceLocator()->get('SampleService');
        $sampleResult = $this->sampleService->getSampleInfo($params);
        return new ViewModel(array(
                'result' => $sampleResult
            ));
    }
    
    public function samplesTestReasonAction(){
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');
        $params = array();
        $params['clinic'] = $this->params()->fromQuery('clinic');
        $params['testReasonCode'] = $this->params()->fromQuery('r');
        $params['dateRange'] = $this->params()->fromQuery('dRange');
        $params['testResult'] = $this->params()->fromQuery('rlt');
        $params['sampleType'] = base64_decode($this->params()->fromQuery('sTyp'));
        $params['adherence'] = $this->params()->fromQuery('adhr');
        $params['age'] = $this->params()->fromQuery('age');
        $params['gender'] = $this->params()->fromQuery('gd');
        $params['isPatientPregnant'] = $this->params()->fromQuery('p');
        $params['isPatientBreastfeeding'] = $this->params()->fromQuery('bf');
        // $sampleService = $this->getServiceLocator()->get('SampleService');
        $clinics = $this->sampleService->getAllClinicName();
        $testReasons = $this->sampleService->getAllTestReasonName();
        $sampleType = $this->sampleService->getSampleType();
        return new ViewModel(array(
                'clinics' => $clinics,
                'testReasons' => $testReasons,
                'sampleType'=>$sampleType,
                'params'=>$params
            ));
    }
    
    public function getSamplesTestReasonBasedOnAgeGroupAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getVLTestReasonBasedOnAgeGroup($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result'=>$result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSamplesTestReasonBasedOnGenderAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getVLTestReasonBasedOnGender($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result'=>$result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSamplesTestReasonBasedOnClinicsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getVLTestReasonBasedOnClinics($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result'=>$result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultGenderAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getSampleTestedResultBasedGenderDetails($params);
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
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'params'=>$params))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupTwoToFiveAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'params'=>$params))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupSixToFourteenAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'params'=>$params))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupFifteenToFourtynineAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'params'=>$params))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupGreaterFiftyAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'params'=>$params))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupUnknownAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'params'=>$params))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getRequisitionFormsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getClinicRequisitionFormsTested($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
}