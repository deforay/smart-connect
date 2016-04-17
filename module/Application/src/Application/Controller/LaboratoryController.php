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
        return new ViewModel();
    }

    public function samplesAction()
    {
        return new ViewModel();
    }


}

