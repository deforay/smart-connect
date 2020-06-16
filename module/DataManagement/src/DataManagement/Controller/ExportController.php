<?php

namespace DataManagement\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

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

