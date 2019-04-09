<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class DataController extends AbstractActionController
{
    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'data');
        $this->layout()->setVariable('activeMenu', 'data');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();            
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $sampleService->uploadSampleResultFile($params);
            return $this->redirect()->toRoute("import");
        }else{
            $sourceService = $this->getServiceLocator()->get('SourceService');
            $sourceResult=$sourceService->getAllActiveSource();
            if ($sourceResult) {
                return new ViewModel(array(
                    'sourceResult' => $sourceResult
                ));
            }
        }
    }
}

