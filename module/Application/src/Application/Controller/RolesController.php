<?php

namespace Application\Controller;


use Laminas\View\Model\ViewModel;
use Application\Service\CommonService;

class RolesController extends AbstractAppController
{

    private $roleService = null;

    public function __construct($roleService, \Application\Model\Acl $acl)
    {
        $this->setAcl($acl);
        $this->roleService = $roleService;
    }
    public function indexAction()
    {
        if (!$this->hasPrivilege('index')) {
            return $this->forbidden();
        }
        $this->layout()->setVariable('activeTab', 'roles');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->roleService->getAllRolesDetails($params);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }

    public function addAction()
    {
        if (!$this->hasPrivilege('add')) {
            return $this->forbidden();
        }
        $this->layout()->setVariable('activeTab', 'roles');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
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
        if (!$this->hasPrivilege('edit')) {
            return $this->forbidden();
        }
        $this->layout()->setVariable('activeTab', 'roles');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->roleService->updateRoles($params);
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
