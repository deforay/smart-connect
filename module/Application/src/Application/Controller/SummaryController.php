<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class SummaryController extends AbstractActionController{

    public function indexAction(){
        $this->layout()->setVariable('activeTab', 'summary-dashboard');
        return $this->_redirect()->toUrl('/summary/dashboard'); 
    }

    public function dashboardAction(){
        $this->layout()->setVariable('activeTab', 'summary-dashboard');
        $sampleService = $this->getServiceLocator()->get('SummaryService');
        $summaryTabResult = $sampleService->fetchSummaryTabDetails();    
        $keySummaryIndicatorsResult = $sampleService->getKeySummaryIndicatorsDetails();    
        return new ViewModel(array(
                    'summaryTabInfo' => $summaryTabResult,
                    'keySummaryIndicators' => $keySummaryIndicatorsResult
        ));
    }
    
    public function samplesReceivedDistrictAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SummaryService');
            $result = $sampleService->getAllSamplesReceivedByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function samplesReceivedFacilityAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SummaryService');
            $result = $sampleService->getAllSamplesReceivedByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function samplesReceivedGraphAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SummaryService');
            $result = $sampleService->getSamplesReceivedGraphDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                                    'result' => $result
                                    ))->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function suppressionRateDistrictAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SummaryService');
            $result = $sampleService->getAllSuppressionRateByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function suppressionRateFacilityAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SummaryService');
            $result = $sampleService->getAllSuppressionRateByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function suppressionRateGraphAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SummaryService');
            $result = $sampleService->getSuppressionRateGraphDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                                    'result' => $result
                                    ))->setTerminal(true);
            return $viewModel;
        }
    }

    
}