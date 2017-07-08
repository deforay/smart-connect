<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class ClinicController extends AbstractActionController
{

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');          
        return new ViewModel();
    }

    public function dashboardAction()
    {
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $sampleType = $sampleService->getSampleType();
        $clinicName = $sampleService->getAllClinicName();
        $testReasonName = $sampleService->getAllTestReasonName();
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');          
        return new ViewModel(array(
                'sampleType' => $sampleType,
                'clinicName' => $clinicName,
                'testReason' => $testReasonName,
            ));
    }
    
    public function overallViralLoadAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $chartResult = $sampleService->getChartOverAllLoadStatus($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('chartResult'=>$chartResult))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function testResultAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getAllTestResults($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function getSampleTestResultAction()
    {
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
    public function getSampleTestReasonAction()
    {
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
    
    public function getOverAllLoadStatusAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getOverAllLoadStatus($params);
            return $this->getResponse()->setContent(Json::encode($result));
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
    public function testResultViewAction()
    {
        $testResultId = base64_decode($this->params()->fromRoute('id'));
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $configService = $this->getServiceLocator()->get('ConfigService');
        $params['id'] = $testResultId;
        $sampleResult = $sampleService->getSampleInfo($params);
        $config=$configService->getAllGlobalConfig();
        return new ViewModel(array(
                'result' => $sampleResult,'config'=>$config
            ));
    }
    
}

