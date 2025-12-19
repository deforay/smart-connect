<?php

namespace Application\Controller;

use Laminas\Config\Config;

use Laminas\View\Model\ViewModel;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;

class UserLoginHistoryController extends AbstractActionController
{


    private $userLoginHistoryService = null;

    public function __construct($userLoginHistoryService)
    {
        $this->userLoginHistoryService = $userLoginHistoryService;
    }


    public function indexAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->userLoginHistoryService->getAllDetails($params);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }
}
