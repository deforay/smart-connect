<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');
        return $this->redirect()->toRoute('summary');
        return new ViewModel();
    }

    public function samplesAccessionAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');
        return new ViewModel();
    }

    public function samplesWaitingAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');
        return new ViewModel();
    }


    public function samplesRejectedAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');
        return new ViewModel();
    }

    public function samplesTestedAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');
        return new ViewModel();
    }

    public function requisitionFormsIncompleteAction()
    {
        $this->layout()->setVariable('activeTab', 'dashboard');
        return new ViewModel();
    }
}
