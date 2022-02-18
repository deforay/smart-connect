<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class StatusController extends AbstractActionController
{

    private $commonService = null;

    public function __construct($commonService)
    {
        $this->commonService = $commonService;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'status');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $result = $this->commonService->getAllDashApiReceiverStatsByGrid($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function labAction()
    {
        $this->layout()->setVariable('activeTab', 'status');
        $statusId = base64_decode($this->params()->fromRoute('id'));
        if ($statusId == false) {
            return $this->redirect()->toRoute('users');
        } else {
            $statusDetails = $this->commonService->getStatusDetails($statusId);
            return new ViewModel(array('result' => $statusDetails));
        }
    }
}
