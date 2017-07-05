<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class UsersController extends AbstractActionController
{

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'users');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $userService = $this->getServiceLocator()->get('UserService');
            $result = $userService->getAllUsers($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
        
      
    }

    public function addAction(){
        $this->layout()->setVariable('activeTab', 'users');
        $userService = $this->getServiceLocator()->get('UserService');
        
        if($this->getRequest()->isPost()){
            $params=$this->getRequest()->getPost();
            $result=$userService->addUser($params);
            return $this->_redirect()->toRoute('users');
        }        
        
        $roles = $userService->fetchRoles();
        return new ViewModel(array('roles' => $roles));
    }

    public function editAction()
    {
        $this->layout()->setVariable('activeTab', 'users');
        $userService = $this->getServiceLocator()->get('UserService');
        $commonService = $this->getServiceLocator()->get('CommonService');
        if($this->getRequest()->isPost()){
            $params=$this->getRequest()->getPost();
            $result=$userService->updateUser($params);
            return $this->_redirect()->toRoute('users');
        }else{
            $userId = base64_decode($this->params()->fromRoute('id'));
            $user = $userService->getUser($userId);
            if($user == false){
                return $this->_redirect()->toRoute('users'); 
            }else{
                $params = array();
                $facilities = array();
                $roles = $userService->fetchRoles();
                if($user->role!= null && trim($user->role)!= '' && $user->role > 1){
                   $params['role']=$user->role;
                   $facilities = $commonService->getRoleFacilities($params);
                }
                return new ViewModel(array('user'=>$user,'roles' => $roles,'facilities'=>$facilities));
            }   
        }
    }
    public function mapAction()
    {
        $this->layout()->setVariable('activeTab', 'admin');    
        $this->layout()->setVariable('activeMenu', 'users');
        $userService = $this->getServiceLocator()->get('UserService');
        $orgService = $this->getServiceLocator()->get('OrganizationService');

        
        if($this->getRequest()->isPost()){
            $params=$this->getRequest()->getPost();
            $result=$userService->mapUserOrganizations($params);
            return $this->_redirect()->toRoute('users');
        }else{
            $userId = ($this->params()->fromRoute('id'));
            $user = $userService->getUser($userId);
            if($user == false){
                return $this->_redirect()->toRoute('users'); 
            }else{
                $orgs = $orgService->fetchOrganizations();
                $map = $userService->fetchUserOrganizations($userId);
                return new ViewModel(array('user'=>$user,'facilities' => $orgs,'map'=>$map));
            }   
        }
    }
    
    public function getRoleFacilitiesAction(){
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $commonService = $this->getServiceLocator()->get('CommonService');
            $result = $commonService->getRoleFacilities($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params'=>$params,'result' => $result))
                        ->setTerminal(true);
            return $viewModel;
        }
    }
}

