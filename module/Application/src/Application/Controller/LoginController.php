<?php

namespace Application\Controller;

use Application\Session\Container;
use Laminas\View\Model\ViewModel;
use Application\Model\Acl;
use Application\Service\CommonService;
use Application\Service\LandingPage;
use Laminas\Mvc\Controller\AbstractActionController;

class LoginController extends AbstractActionController
{

    private \Application\Service\UserService $userService;
    private \Application\Service\ConfigService $configService;
    private Acl $acl;

    public function __construct($userService, $configService, Acl $acl)
    {
        $this->userService = $userService;
        $this->configService = $configService;
        $this->acl = $acl;
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
                // A string is not a response. Returning '/login' rendered the
                // form again only by accident of the view listeners.
                return $this->redirect()->toRoute('login');
            }
            $target = $this->userService->login($params);
            return $this->redirect()->toRoute($target['route'], $target['params']);
        }
        if (!empty($loginContainer->userId)) {
            $target = LandingPage::for($this->acl, $loginContainer->roleCode, $loginContainer->role);
            if ($target !== null) {
                return $this->redirect()->toRoute($target['route'], $target['params']);
            }
            // The role lost every landing page since this session began.
            // Sending it on would answer 403 and send it back here, so end the
            // session and show the form instead.
            $loginContainer->getManager()->getStorage()->clear();
            $alert = new Container('alert');
            $alert->alertMsg = 'Your role has no pages assigned to it. Please ask an administrator to grant access.';
            return $this->redirect()->toRoute('login');
        } else {
            $config = $this->configService->getAllGlobalConfig();
            $vm = new ViewModel();
            $vm->setVariables(array('config' => $config))
                ->setTerminal(true);
            return $vm;
        }
    }

    public function logoutAction()
    {
        $sessionLogin = new Container('credo');
        $sessionLogin->getManager()->getStorage()->clear();
        return $this->redirect()->toRoute("login");
    }
}
