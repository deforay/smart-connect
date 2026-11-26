<?php

namespace Application\Controller;

use Laminas\Config\Config;

use Laminas\View\Model\ViewModel;
use Application\Service\CommonService;

class UserLoginHistoryController extends AbstractAppController
{


    private $userLoginHistoryService = null;

    public function __construct($userLoginHistoryService, \Application\Model\Acl $acl)
    {
        $this->setAcl($acl);
        $this->userLoginHistoryService = $userLoginHistoryService;
    }


    public function indexAction()
    {
        if (!$this->hasPrivilege('index')) {
            return $this->forbidden();
        }
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->userLoginHistoryService->getAllDetails($params);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }
}
