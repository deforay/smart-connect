<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class ClinicController extends AbstractActionController{

    public function indexAction(){
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');          
        return $this->_redirect()->toUrl('/clinics/dashboard'); 
    }

    public function dashboardAction(){
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $sampleType = $sampleService->getSampleType();
        $clinicName = $sampleService->getAllClinicName();
        $testReasonName = $sampleService->getAllTestReasonName();          
        return new ViewModel(array(
                'sampleType' => $sampleType,
                'clinicName' => $clinicName,
                'testReason' => $testReasonName,
            ));
    }
    
    public function getOverallViralLoadStatusAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $chartResult = $sampleService->getOverallViralLoadStatus($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('chartResult'=>$chartResult))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getViralLoadStatusBasedOnAgeAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $chartResult = $sampleService->getViralLoadStatusBasedOnAge($params);
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
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->fetchSampleTestedReason($params);
            $testReasonName = $sampleService->getAllTestReasonName();
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
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getClinicSampleTestedResults($params);
            $sampleType = $sampleService->getSampleType();
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
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getAllTestResults($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function generateResultPdfAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $configService = $this->getServiceLocator()->get('ConfigService');
            $sampleResult=$sampleService->getSampleInfo($params);
            $config=$configService->getAllGlobalConfig();
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
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $file=$sampleService->generateResultExcel($params);
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
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $sampleResult = $sampleService->getSampleInfo($params);
        return new ViewModel(array(
                'result' => $sampleResult
            ));
    }
    
}