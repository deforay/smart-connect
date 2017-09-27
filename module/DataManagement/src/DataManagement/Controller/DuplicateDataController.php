<?php

namespace DataManagement\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class DuplicateDataController extends AbstractActionController{

    public function indexAction(){
        $this->layout()->setVariable('activeTab', 'duplicate-data');
        $request = $this->getRequest();
        if ($request->isPost()){
            $parameters = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $sampleService->getAllSamples($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function removeAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $response=$sampleService->removeDuplicateSampleRows($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('response' =>$response))
                      ->setTerminal(true);
            return $viewModel;
        }
    }

}

