<?php

namespace DataManagement\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class ExportController extends AbstractActionController{

    public function indexAction(){
        $this->layout()->setVariable('activeTab', 'export-data');
        
    }
    
    public function generateAction(){
        $request = $this->getRequest();
        if ($request->isPost()){
            $parameters = $request->getPost();
            $commonService = $this->getServiceLocator()->get('CommonService');
            $result = $commonService->addBackupGeneration($parameters);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                    ->setTerminal(true);
            return $viewModel;            
        }
    }

}

