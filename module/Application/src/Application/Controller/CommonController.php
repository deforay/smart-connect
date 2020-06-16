<?php

namespace Application\Controller;

use Laminas\Session\Container;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class CommonController extends AbstractActionController{
    public function indexAction(){
        $result = "";
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $common = $this->getServiceLocator()->get('CommonService');
            $result = $common->checkFieldValidations($params);
        }
        $viewModel = new ViewModel();
        $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
        return $viewModel;
    }
    
    public function multipleFieldValidationAction(){
        $result = "";
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $common = $this->getServiceLocator()->get('CommonService');
            $result = $common->checkMultipleFieldValidations($params);
        }
        $viewModel = new ViewModel();
        $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
        return $viewModel;
    }
    
    public function clearCacheAction(){
        $result = "";
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $common = $this->getServiceLocator()->get('CommonService');
            $result = $common->clearAllCache();
        }
        $viewModel = new ViewModel();
        $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
        return $viewModel;
    }
    
    public function setSessionAction(){
        $logincontainer = new Container('credo');
        $logincontainer->sampleTable="";
        unset($logincontainer->sampleTable);
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            if($params['sessionType'] == "current"){
                $logincontainer->sampleTable = 'dash_vl_request_form_current';
            }else{
                $logincontainer->sampleTable = 'dash_vl_request_form';
            }
        }
        $viewModel = new ViewModel();
        $viewModel->setVariables(array('result' => $logincontainer->sampleTable))->setTerminal(true);
        return $viewModel;
    }

}

