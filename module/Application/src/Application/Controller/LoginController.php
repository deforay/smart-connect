<?php

namespace Application\Controller;

use Laminas\Session\Container;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class LoginController extends AbstractActionController{

    public function indexAction(){
        $logincontainer = new Container('credo');
        $configService = $this->getServiceLocator()->get('ConfigService');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $userService = $this->getServiceLocator()->get('UserService');
            $url = $userService->login($params);
            return $this->redirect()->toUrl($url);
        }
        if (isset($logincontainer->userId) && $logincontainer->userId != "") {
            return $this->redirect()->toUrl("summary/dashboard");
        } else {
            $config=$configService->getAllGlobalConfig();
            $vm = new ViewModel();
            $vm->setVariables(array('config'=>$config))
               ->setTerminal(true);
            return $vm;
        }
    }

    public function otpAction(){
        $logincontainer = new Container('credo');

        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $userService = $this->getServiceLocator()->get('UserService');
            $url = $userService->otp($params);
            return $this->redirect()->toUrl($url);
        }

        $configService = $this->getServiceLocator()->get('ConfigService');        
        $config=$configService->getAllGlobalConfig();
        $vm = new ViewModel();
        $vm->setVariables(array('config'=>$config))
           ->setTerminal(true);
        return $vm;        
    }

    public function logoutAction() {
        $sessionLogin = new Container('credo');
        $sessionLogin->getManager()->getStorage()->clear();
        return $this->redirect()->toRoute("login");
    }    


}

