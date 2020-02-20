<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class SummaryController extends AbstractActionController{


    private $sampleService = null;
    private $summaryService = null;

    public function __construct($summaryService,$sampleService)
    {
        $this->summaryService = $summaryService;
        $this->sampleService = $sampleService;
    }

    public function indexAction(){
        $this->layout()->setVariable('activeTab', 'summary-dashboard');
        return $this->_redirect()->toUrl('/summary/dashboard'); 
    }
    
    public function dashboardAction(){
        
        if($this->params()->fromQuery('f') && $this->params()->fromQuery('t')){
            $params['fromDate']=$this->params()->fromQuery('f');
            $params['toDate']=$this->params()->fromQuery('t');
        }else{
            $params['fromDate']  = date('Y-m', strtotime('+1 month', strtotime('-12 month')));
            $params['toDate']  =date('Y-m');
        }      
        $this->layout()->setVariable('activeTab', 'summary-dashboard');
        // $summaryService = $this->getServiceLocator()->get('SummaryService');
        $summaryTabResult = $this->summaryService->fetchSummaryTabDetails($params); 
        $allLineofTreatmentResult = $this->summaryService->getAllLineOfTreatmentDetails($params);
        $allCollapsibleLineofTreatmentResult = $this->summaryService->getAllCollapsibleLineOfTreatmentDetails($params);
        
        /* District, Province and Facility */
        // $sampleService = $this->getServiceLocator()->get('SampleService');        
        $clinicName = $this->sampleService->getAllClinicName();        
        $provinceName = $this->sampleService->getAllProvinceList();
        $districtName = $this->sampleService->getAllDistrictList();
        /* Ends Here*/   
        
        return new ViewModel(array(
            'summaryTabInfo' => $summaryTabResult,
            'allLineofTreatmentInfo' => $allLineofTreatmentResult,
            'allCollapsibleLineofTreatmentResult'=>$allCollapsibleLineofTreatmentResult,
            'startDate' => $params['fromDate'],
            'endDate' => $params['toDate'],
            'clinicName' => $clinicName,
            'provinceName'=>$provinceName,
            'districtName'=>$districtName
        ));
    }
    
    public function getSamplesReceivedBarChartAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getSamplesReceivedBarChartDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                                    'result' => $result
                                    ))->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function samplesReceivedDistrictAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getAllSamplesReceivedByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    public function samplesReceivedProvinceAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getAllSamplesReceivedByProvince($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function samplesReceivedFacilityAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getAllSamplesReceivedByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function getSuppressionRateBarChartAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getSuppressionRateBarChartDetails($params);
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
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getAllSuppressionRateByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    public function suppressionRateProvinceAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getAllSuppressionRateByProvince($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function suppressionRateFacilityAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getAllSuppressionRateByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function getSamplesRejectedBarChartAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getSamplesRejectedBarChartDetails($params);
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
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getAllSamplesRejectedByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function samplesRejectedFacilityAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getAllSamplesRejectedByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    public function samplesRejectedProvinceAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getAllSamplesRejectedByProvince($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function getRegimenGroupBarChartAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getRegimenGroupBarChartDetails($params);
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
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $result = $this->summaryService->getRegimenGroupSamplesDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function getIndicatorsAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $keySummaryIndicatorsResult = $this->summaryService->getKeySummaryIndicatorsDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                            'keySummaryIndicators' => $keySummaryIndicatorsResult,
                        ))->setTerminal(true);
            return $viewModel;
        }
    }

    public function exportIndicatorResultExcelAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $file=$this->summaryService->exportIndicatorResultExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' =>$file))
                      ->setTerminal(true);
            return $viewModel;
        }
    }

    public function exportSuppressionRateByFacilityAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $summaryService = $this->getServiceLocator()->get('SummaryService');
            $file=$this->summaryService->exportSuppressionRateByFacility($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' =>$file))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
}