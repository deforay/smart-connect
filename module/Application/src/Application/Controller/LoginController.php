<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;

class LoginController extends AbstractActionController
{

    public function indexAction()
    {
        $logincontainer = new Container('credo');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $userService = $this->getServiceLocator()->get('UserService');
            $url = $userService->login($params);
            return $this->redirect()->toUrl($url);
        }
        if (isset($logincontainer->userId) && $logincontainer->userId != "") {
            return $this->redirect()->toUrl("/devices/status");
        } else {
            $vm = new ViewModel();
            $vm->setTerminal(true);
            return $vm;
        }
    }

    public function logoutAction() {
        $sessionLogin = new Container('credo');
        $sessionLogin->getManager()->getStorage()->clear();
        return $this->redirect()->toRoute("login");
    }    


}

