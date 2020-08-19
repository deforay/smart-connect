<?php

namespace Application\Controller;

use Laminas\Session\Container;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Zend\Debug\Debug;

class CommonController extends AbstractActionController{

    private $sampleService = null;
    private $configService = null;

    public function __construct($commonService, $configService)
    {
        $this->configService = $configService;
        $this->commonService = $commonService;
    }

    public function indexAction(){
        $result = "";
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->commonService->checkFieldValidations($params);
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
            $this->commonService->checkMultipleFieldValidations($params);
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
            $this->commonService->clearAllCache();
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

    public function getDistrictListAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $this->commonService->getDistrictList($params['provinceName'])))->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function getFacilityListAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $this->commonService->getFacilityList($params['districtName'])))->setTerminal(true);
            return $viewModel;
        }
    }

}

