<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class FacilityController extends AbstractActionController
{

    private $facilityService = null;

    public function __construct($facilityService)
    {
        $this->facilityService = $facilityService;
    }
    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'facility');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->facilityService->getAllFacility($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function addAction()
    {
        $this->layout()->setVariable('activeTab', 'facility');
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $result = $this->facilityService->addFacility($params);
            return $this->redirect()->toRoute('facility');
        } else {
            $facilityType = $this->facilityService->fetchFacilityType();
            $facilityLocation = $this->facilityService->fetchLocationDetails();
            return new ViewModel(array('facilityType' => $facilityType, 'facilityLocation' => $facilityLocation));
        }
    }

    public function editAction()
    {
        $this->layout()->setVariable('activeTab', 'facility');
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $result = $this->facilityService->updateFacility($params);
            return $this->redirect()->toRoute('facility');
        } else {
            $facilityId = base64_decode($this->params()->fromRoute('id'));
            $facility = $this->facilityService->getFacility($facilityId);
            $facilityType = $this->facilityService->fetchFacilityType();
            $facilityLocation = $this->facilityService->fetchLocationDetails();
            if ($facility == false) {
                return $this->redirect()->toRoute('facility');
            } else {
                return new ViewModel(array('facility' => $facility, 'facilityType' => $facilityType, 'facilityLocation' => $facilityLocation));
            }
        }
    }
    public function getDistrictListAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->facilityService->getDistrictList($params['state']);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }
}
