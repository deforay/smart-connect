<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
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
}
