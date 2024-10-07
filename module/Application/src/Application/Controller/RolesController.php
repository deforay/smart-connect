<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class RolesController extends AbstractActionController
{

    private $roleService = null;

    public function __construct($roleService)
    {
        $this->roleService = $roleService;
    }
    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'roles');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->roleService->getAllRolesDetails($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function addAction()
    {
        $this->layout()->setVariable('activeTab', 'roles');
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $result = $this->roleService->addRoles($params);
            return $this->redirect()->toRoute('roles');
        } else {
            $rolesResult = $this->roleService->getAllRoles();
            return new ViewModel([
                'rolesresult' => $rolesResult,
            ]);
        }
    }

    public function editAction()
    {
        $this->layout()->setVariable('activeTab', 'roles');
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $result = $this->roleService->updateRoles($params);
            return $this->redirect()->toRoute('roles');
        } else {
            $id = base64_decode($this->params()->fromRoute('id'));
            $result = $this->roleService->getRole($id);
            $rolesResult = $this->roleService->getAllRoles(); //privileges
            $config = $this->roleService->getPrivilegesMap($id);
            return new ViewModel([
                'result' => $result,
                'rolesresult' => $rolesResult,
                'resourceResult' => $config,
            ]);
        }
    }
}