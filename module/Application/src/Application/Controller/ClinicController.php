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
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');          
        return new ViewModel(array(
                'sampleType' => $sampleType,
                'clinicName' => $clinicName,
            ));
    }
    
    public function overallViralLoadAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getOverAllLoadStatus($params);
            $chartResult = $sampleService->getChartOverAllLoadStatus($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'chartResult'=>$chartResult))
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
}

