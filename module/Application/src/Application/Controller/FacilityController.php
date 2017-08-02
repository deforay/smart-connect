<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class FacilityController extends AbstractActionController
{
    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'facility');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $facilityService = $this->getServiceLocator()->get('FacilityService');
            $result = $facilityService->getAllFacility($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function addAction(){
        $this->layout()->setVariable('activeTab', 'facility');
        $facilityService = $this->getServiceLocator()->get('FacilityService');
        if($this->getRequest()->isPost()){
            $params=$this->getRequest()->getPost();
            $result=$facilityService->addFacility($params);
            return $this->_redirect()->toRoute('facility');
        }else{
            $facilityType = $facilityService->fetchFacilityType();
            $facilityLocation = $facilityService->fetchLocationDetails();
            return new ViewModel(array('facilityType' => $facilityType,'facilityLocation'=>$facilityLocation));
        }
    }

    public function editAction()
    {
        $this->layout()->setVariable('activeTab', 'facility');
        $facilityService = $this->getServiceLocator()->get('FacilityService');
        if($this->getRequest()->isPost()){
            $params=$this->getRequest()->getPost();
            $result=$facilityService->updateFacility($params);
            return $this->_redirect()->toRoute('facility');
        }else{
            $facilityId = base64_decode($this->params()->fromRoute('id'));
            $facility = $facilityService->getFacility($facilityId);
            $facilityType = $facilityService->fetchFacilityType();
            $facilityLocation = $facilityService->fetchLocationDetails();
            if($facility == false){
                return $this->_redirect()->toRoute('facility'); 
            }else{
                return new ViewModel(array('facility'=>$facility,'facilityType' => $facilityType,'facilityLocation'=>$facilityLocation));
            }
        }
    }
    public function getDistrictListAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $commonService = $this->getServiceLocator()->get('FacilityService');
            $result = $commonService->getDistrictList($params['state']);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
}