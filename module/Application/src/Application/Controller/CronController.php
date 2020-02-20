<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class CronController extends AbstractActionController{

    private $sampleService = null;

    public function __construct($sampleService)
    {
        $this->sampleService = $sampleService;
    }

    public function indexAction(){
       
    }
    
    public function importVlAction(){
        //$sampleService = $this->getServiceLocator()->get('SampleService');
        $this->sampleService->importSampleResultFile();
    }

    public function generateBackupAction(){
        //$sampleService = $this->getServiceLocator()->get('SampleService');
        $this->sampleService->generateBackup();
    }    
    
    
}

