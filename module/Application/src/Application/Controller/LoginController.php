<?php

namespace Application\Controller;

use Laminas\Session\Container;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class LoginController extends AbstractActionController
{

    private $userService = null;
    private $configService = null;

    public function __construct($userService, $configService)
    {
        $this->userService = $userService;
        $this->configService = $configService;
    }


    public function indexAction()
    {
        $logincontainer = new Container('credo');

        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $url = $this->userService->login($params);
            return $this->redirect()->toUrl($url);
        }
        if (isset($logincontainer->userId) && $logincontainer->userId != "") {
            return $this->redirect()->toUrl("summary/dashboard");
        } else {
            $config = $this->configService->getAllGlobalConfig();
            $vm = new ViewModel();
            $vm->setVariables(array('config' => $config))
                ->setTerminal(true);
            return $vm;
        }
    }

    public function otpAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $url = $this->userService->otp($params);
            return $this->redirect()->toUrl($url);
        }

        $config = $this->configService->getAllGlobalConfig();
        $vm = new ViewModel();
        $vm->setVariables(array('config' => $config))
            ->setTerminal(true);
        return $vm;
    }

    public function logoutAction()
    {
        $sessionLogin = new Container('credo');
        $sessionLogin->getManager()->getStorage()->clear();
        return $this->redirect()->toRoute("login");
    }
}
