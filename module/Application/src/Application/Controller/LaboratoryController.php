<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class LaboratoryController extends AbstractActionController
{

    public function indexAction()
    {
        return new ViewModel();
    }

    public function dashboardAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');          
        return new ViewModel();
    }

    public function samplesAccessionAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');          
        return new ViewModel();
    }
    
    public function samplesWaitingAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');                
        return new ViewModel();
    }
    
    
    public function samplesRejectedAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');                 
        return new ViewModel();
    }
    
    public function samplesTestedAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');               
        return new ViewModel();
    }
    
    public function requisitionFormsIncompleteAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');          
        return new ViewModel();
    }


}

