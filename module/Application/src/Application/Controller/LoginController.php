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
            if ($params["csrf_token"] != $_SESSION["csrf_token"]) {
                // Reset token
                unset($_SESSION["csrf_token"]);
                $container = new Container('alert');
                $container->alertMsg = 'Could not process your login request. Please try again.';
                return '/login';
            }
            $url = $this->userService->login($params);
            return $this->redirect()->toRoute($url);
        }
        if (isset($logincontainer->userId) && $logincontainer->userId != "") {
            return $this->redirect()->toRoute("summary");
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
            return $this->redirect()->toRoute($url);
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
