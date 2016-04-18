<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class ClinicController extends AbstractActionController
{

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');          
        return new ViewModel();
    }

    public function dashboardAction()
    {
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');          
        return new ViewModel();
    }


}

