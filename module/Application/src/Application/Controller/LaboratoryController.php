<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

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
        return new ViewModel();
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

}

