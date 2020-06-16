<?php

namespace DataManagement\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

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
            $response = $sampleService->removeDuplicateSampleRows($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('response' =>$response))
                      ->setTerminal(true);
            return $viewModel;
        }
    }
    
    public function editAction(){
      $this->layout()->setVariable('activeTab', 'duplicate-data');
      $id = base64_decode($this->params()->fromRoute('id'));
      $sampleService = $this->getServiceLocator()->get('SampleService');
      $sample = $sampleService->getSample($id);
      if(isset($sample->vl_sample_id)){
        return new ViewModel(array('sample' => $sample));
      }else{
        return $this->_redirect()->toRoute('duplicate-data');
      }
    }

}

