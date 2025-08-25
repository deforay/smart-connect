<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;


class UsersController extends AbstractActionController
{

    private $userService = null;
    private \Application\Service\CommonService $commonService;
    private $orgService = null;

    public function __construct($userService, $commonService, $orgService)
    {
        $this->userService = $userService;
        $this->commonService = $commonService;
        $this->orgService = $orgService;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'users');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->userService->getAllUsers($params);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }

    public function addAction()
    {
        $this->layout()->setVariable('activeTab', 'users');


        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $result = $this->userService->addUser($params);
            return $this->redirect()->toRoute('users');
        }

        $roles = $this->userService->fetchRoles();
        return new ViewModel(array('roles' => $roles));
    }

    public function editAction()
    {
        $this->layout()->setVariable('activeTab', 'users');


        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $result = $this->userService->updateUser($params);
            return $this->redirect()->toRoute('users');
        } else {
            $userId = base64_decode($this->params()->fromRoute('id'));
            $user = $this->userService->getUser($userId);
            if ($user == false) {
                return $this->redirect()->toRoute('users');
            } else {
                $params = [];
                $facilities = [];
                $roles = $this->userService->fetchRoles();
                if ($user->role != null && trim($user->role) != '' && $user->role > 1) {
                    $params['role'] = $user->role;
                    $facilities = $this->commonService->getRoleFacilities($params);
                }
                return new ViewModel(array('user' => $user, 'roles' => $roles, 'facilities' => $facilities));
            }
        }
    }
    public function mapAction()
    {
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'users');




        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $result = $this->userService->mapUserOrganizations($params);
            return $this->redirect()->toRoute('users');
        } else {
            $userId = ($this->params()->fromRoute('id'));
            $user = $this->userService->getUser($userId);
            if ($user == false) {
                return $this->redirect()->toRoute('users');
            } else {
                $orgs = $this->orgService->fetchOrganizations();
                $map = $this->userService->fetchUserOrganizations($userId);
                return new ViewModel(array('user' => $user, 'facilities' => $orgs, 'map' => $map));
            }
        }
    }

    public function getRoleFacilitiesAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->commonService->getRoleFacilities($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }
}
