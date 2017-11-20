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
        $summaryService = $this->getServiceLocator()->get('SummaryService');
        $summaryTabResult = $summaryService->fetchSummaryTabDetails();    
        $keySummaryIndicatorsResult = $summaryService->getKeySummaryIndicatorsDetails();    
        return new ViewModel(array(
                    'summaryTabInfo' => $summaryTabResult,
                    'keySummaryIndicators' => $keySummaryIndicatorsResult
        ));
    }
    
    public function samplesReceivedDistrictAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getAllSamplesReceivedByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function samplesReceivedFacilityAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getAllSamplesReceivedByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function samplesReceivedGraphAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getSamplesReceivedGraphDetails($params);
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
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getAllSuppressionRateByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function suppressionRateFacilityAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getAllSuppressionRateByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function suppressionRateGraphAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getSuppressionRateGraphDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                                    'result' => $result
                                    ))->setTerminal(true);
            return $viewModel;
        }
    }

    public function samplesRejectedDistrictAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getAllSamplesRejectedByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function samplesRejectedFacilityAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getAllSamplesRejectedByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function getSamplesRejectedBarChartAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getSamplesRejectedBarChartDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                                    'result' => $result
                                    ))->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getRegimenGroupDetailsAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getRegimenGroupSamplesDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function getRegimenGroupBarChartAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $summaryService->getRegimenGroupBarChartDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                                    'result' => $result
                                    ))->setTerminal(true);
            return $viewModel;
        }
    }
}