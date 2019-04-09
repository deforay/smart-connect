<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class CronController extends AbstractActionController{

    public function indexAction(){
       
    }
    
    public function importVlAction(){
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $sampleService->importSampleResultFile();
    }

    public function generateBackupAction(){
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $sampleService->generateBackup();
    }    
    
    
}

