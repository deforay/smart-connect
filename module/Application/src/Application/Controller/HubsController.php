<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class HubsController extends AbstractActionController
{

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'hubs-dashboard');          
        return new ViewModel();
    }

    public function dashboardAction()
    {
        $this->layout()->setVariable('activeTab', 'hubs-dashboard');          
        return new ViewModel();
    }


}

