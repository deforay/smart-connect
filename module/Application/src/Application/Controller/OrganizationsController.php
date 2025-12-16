<?php

namespace Application\Controller;

use Laminas\View\Model\ViewModel;
use Application\Service\UserService;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;

class OrganizationsController extends AbstractActionController
{

    public \Application\Service\OrganizationService $organizationService;
    public CommonService $commonService;
    public UserService $userService;

    public function __construct($organizationService, $commonService, $userService)
    {
        $this->organizationService = $organizationService;
        $this->commonService = $commonService;
        $this->userService = $userService;
    }

    public function indexAction()
    {

        $organizations = $this->organizationService->fetchOrganizations();
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'organizations');

        return new ViewModel(array('organizations' => $organizations));
    }

    public function addAction()
    {
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'organizations');

        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $result = $this->organizationService->addOrganization($params);
            return $this->redirect()->toRoute('organizations');
        }

        $orgTypes = $this->organizationService->fetchOrganizationTypes();
        $countries = $this->commonService->getAllCountries();

        return new ViewModel(array('orgTypes' => $orgTypes, 'countries' => $countries));
    }

    public function editAction()
    {
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'organizations');

        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $result = $this->organizationService->updateOrganization($params);
            return $this->redirect()->toRoute('organizations');
        } else {
            $orgId = ($this->params()->fromRoute('id'));
            $org = $this->organizationService->getOrganization($orgId);
            if ($org == false) {
                return $this->redirect()->toRoute('organizations');
            } else {
                $orgTypes = $this->organizationService->fetchOrganizationTypes();
                $countries = $this->commonService->getAllCountries();

                return new ViewModel(array('org' => $org, 'orgTypes' => $orgTypes, 'countries' => $countries));
            }
        }
    }


    public function mapAction()
    {
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'users');


        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $result = $this->organizationService->mapOrganizationToUsers($params);
            return $this->redirect()->toRoute('organizations');
        } else {
            $orgId = ($this->params()->fromRoute('id'));
            $org = $this->organizationService->getOrganization($orgId);
            if ($org == false) {
                return $this->redirect()->toRoute('organizations');
            } else {
                $users = $this->userService->fetchUsers();
                $map = $this->organizationService->fetchOrganizationMap($orgId);
                return new ViewModel(array('users' => $users, 'org' => $org, 'map' => $map));
            }
        }
    }
}
