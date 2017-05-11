<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class LaboratoryController extends AbstractActionController
{

    public function indexAction()
    {
        return new ViewModel();
    }

    public function dashboardAction()
    {
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $sampleType = $sampleService->getSampleType();
        $labName = $sampleService->getAllLabName();
        $this->layout()->setVariable('activeTab', 'labs-dashboard');          
        return new ViewModel(array(
                    'sampleType' => $sampleType,
                    'labName' => $labName,
                ));
    }

    public function samplesAccessionAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');          
        return new ViewModel();
    }
    
    public function samplesWaitingAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');                
        return new ViewModel();
    }
    
    
    public function samplesRejectedAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');                 
        return new ViewModel();
    }
    
    public function samplesTestedAction()
    {   
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
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
        }
        
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $labName = $sampleService->getAllLabName();
        $clinicName = $sampleService->getAllClinicName();
        $hubName = $sampleService->getAllHubName();
        $sampleType = $sampleService->getSampleType();
        $currentRegimen = $sampleService->getAllCurrentRegimen();
        return new ViewModel(array(
                'sampleType' => $sampleType,
                'labName' => $labName,
                'clinicName' => $clinicName,
                'hubName' => $hubName,
                'currentRegimen' => $currentRegimen,
                'searchMonth' => $month,
                'searchGender' => $gender,
                'searchRange' => $range,
                'fromMonth' => $fromMonth,
                'toMonth' => $toMonth,
                'labFilter' => $labFilter,
                'age' => $age
        ));
    }
    
    public function requisitionFormsIncompleteAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');          
        return new ViewModel();
    }
    public function getSampleResultAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleResultDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAction()
    {
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
    public function getSampleTestResultVolumeAction()
    {
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
    public function getSampleTestResultGenderAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $params['gender'] = 'yes';
            $result = $sampleService->getSampleTestedResultGenderDetails($params);
            $sampleType = $sampleService->getSampleType();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'sampleType'=>$sampleType))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $params['age'] = 'yes';
            $result = $sampleService->getSampleTestedResultAgeDetails($params);
            $sampleType = $sampleService->getSampleType();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'sampleType'=>$sampleType))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getRequisitionFormsAction()
    {
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
    public function getSampleVolumeAction()
    {
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
    public function getLabTurnAroundTimeAction()
    {
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
    public function getLabFacilitiesAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $sampleType = $sampleService->getSampleType();
            $result = $sampleService->getFacilites($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result,'sampleType'=>$sampleType))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getSampleDetailsAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getBarSampleDetailsAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getFilterSampleDetailsAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getFilterSampleDetails($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function samplesTestedLabAction()
    {   
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
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
        }
        
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $labName = $sampleService->getAllLabName();
        $clinicName = $sampleService->getAllClinicName();
        $hubName = $sampleService->getAllHubName();
        $sampleType = $sampleService->getSampleType();
        $currentRegimen = $sampleService->getAllCurrentRegimen();
        return new ViewModel(array(
                'sampleType' => $sampleType,
                'labName' => $labName,
                'clinicName' => $clinicName,
                'hubName' => $hubName,
                'currentRegimen' => $currentRegimen,
                'searchMonth' => $month,
                'searchGender' => $gender,
                'searchRange' => $range,
                'fromMonth' => $fromMonth,
                'toMonth' => $toMonth,
                'labFilter' => $labFilter,
                'age' => $age
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
}

