<?php

namespace Application\Controller;


use Laminas\View\Model\ViewModel;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;

class StatusController extends AbstractActionController
{

    private \Application\Service\CommonService $commonService;

    public function __construct($commonService)
    {
        $this->commonService = $commonService;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'status');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $result = $this->commonService->getAllDashApiReceiverStatsByGrid($parameters);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
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
