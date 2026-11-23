<?php

namespace Application\Controller;

use Application\Model\Acl;
use Application\Service\LandingPage;
use Application\Session\Container;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    private Acl $acl;

    public function __construct(Acl $acl)
    {
        $this->acl = $acl;
    }

    /**
     * The home page sends a user to Snapshot when their role may open it, and
     * otherwise to their landing page. It always sent everyone to Snapshot,
     * which answered 403 to clinic users, whose role has no Snapshot privilege.
     */
    public function indexAction()
    {
        $session = new Container('credo');
        $target = LandingPage::for($this->acl, $session->roleCode, $session->role, ['snapshot']);

        // No page this role may open. /login ends the session and says why.
        if ($target === null) {
            return $this->redirect()->toRoute('login');
        }

        return $this->redirect()->toRoute($target['route'], $target['params']);
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
