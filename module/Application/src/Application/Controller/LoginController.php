<?php

namespace Application\Controller;

use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;

class LoginController extends AbstractActionController
{

    private \Application\Service\UserService $userService;
    private \Application\Service\ConfigService $configService;

    public function __construct($userService, $configService)
    {
        $this->userService = $userService;
        $this->configService = $configService;
    }


    public function indexAction()
    {
        $loginContainer = new Container('credo');


        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {

            $params = $request->getPost();
            if ($params["CSRF_TOKEN"] != $_SESSION["CSRF_TOKEN"]) {
                // Reset token and create a new one
                CommonService::generateCSRF(true);
                $container = new Container('alert');
                $container->alertMsg = 'Could not process your login request. Please try again.';
                return '/login';
            }
            $url = $this->userService->login($params);
            return $this->redirect()->toRoute($url);
        }
        if (property_exists($loginContainer, 'userId') && $loginContainer->userId !== null && $loginContainer->userId != "") {
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
        /** @var \Laminas\Http\Request $request */
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
