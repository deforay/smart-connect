<?php
namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class CronController extends AbstractActionController{

    private $sampleService = null;

    public function __construct($sampleService)
    {
        $this->sampleService = $sampleService;
    }

    public function indexAction(){
       
    }
    
    public function importVlAction(){
        return false;
    }

    public function generateBackupAction(){
        //$sampleService = $this->getServiceLocator()->get('SampleService');
        $this->sampleService->generateBackup();
    }    
    
    
}

