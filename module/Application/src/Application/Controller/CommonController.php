<?php

namespace Application\Controller;

use Laminas\Session\Container;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Zend\Debug\Debug;

class CommonController extends AbstractActionController
{

    private $sampleService = null;
    private $configService = null;

    public function __construct($commonService, $configService)
    {
        $this->configService = $configService;
        $this->commonService = $commonService;
    }

    public function indexAction()
    {
        $result = "";
        /** @var \Laminas\Http\Request $request */
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

    public function multipleFieldValidationAction()
    {
        $result = "";
        /** @var \Laminas\Http\Request $request */
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

    public function clearCacheAction()
    {
        $result = "";
        /** @var \Laminas\Http\Request $request */
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

    public function setSessionAction()
    {
        $loginContainer = new Container('credo');
        //$loginContainer->sampleTable = "";
        unset($loginContainer->sampleTable);
        unset($loginContainer->eidSampleTable);
        unset($loginContainer->covid19SampleTable);
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            if ($params['sessionType'] == "current") {
                $loginContainer->useCurrentTables = true;
                $loginContainer->sampleTable = 'dash_form_vl_current';
                $loginContainer->eidSampleTable = 'dash_form_eid_current';
                $loginContainer->covid19SampleTable = 'dash_form_covid19_current';
            } else {
                $loginContainer->useCurrentTables = false;
                $loginContainer->sampleTable = 'dash_form_vl';
                $loginContainer->eidSampleTable = 'dash_form_eid';
                $loginContainer->covid19SampleTable = 'dash_form_covid19';
            }
        }
        $viewModel = new ViewModel();
        $viewModel->setVariables(array('result' => true))->setTerminal(true);
        return $viewModel;
    }

    public function getDistrictListAction()
    {
        /** @var \Laminas\Http\Request $request */
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
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            
            $params = $request->getPost();
            $viewModel = new ViewModel();
            $params['facilityType'] = isset($params['facilityType']) ? $params['facilityType'] : 1;
            $res = $this->commonService->getFacilityList($params['districtName'], $params['facilityType']);
            $viewModel->setVariables(array('result' => $res))->setTerminal(true);
            return $viewModel;
        }
    }
}
