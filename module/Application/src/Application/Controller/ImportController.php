<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class ImportController extends AbstractActionController
{
    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'import');
        $this->layout()->setVariable('activeMenu', 'import');
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

