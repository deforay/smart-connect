<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\Container;

class OrganizationsController extends AbstractActionController
{

    public function indexAction()
    {
        $orgService = $this->getServiceLocator()->get('OrganizationService');
        $organizations = $orgService->fetchOrganizations();
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'organizations');

        return new ViewModel(array('organizations' => $organizations));
    }

    public function addAction()
    {
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'organizations');
        $orgService = $this->getServiceLocator()->get('OrganizationService');
        $commonService = $this->getServiceLocator()->get('CommonService');
        
        if($this->getRequest()->isPost()){
            $params=$this->getRequest()->getPost();
            $result=$orgService->addOrganization($params);
            return $this->_redirect()->toRoute('organizations');
        }        
        
        $orgTypes = $orgService->fetchOrganizationTypes();
        $countries = $commonService->getAllCountries();
        
        return new ViewModel(array('orgTypes' => $orgTypes,'countries' => $countries));
    }

    public function editAction()
    {
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'organizations');
        $orgService = $this->getServiceLocator()->get('OrganizationService');
        $commonService = $this->getServiceLocator()->get('CommonService');
        
        if($this->getRequest()->isPost()){
            $params=$this->getRequest()->getPost();
            $result=$orgService->updateOrganization($params);
            return $this->_redirect()->toRoute('organizations');
        }else{
            $orgId = ($this->params()->fromRoute('id'));
            $org = $orgService->getOrganization($orgId);
            if($org == false){
                return $this->_redirect()->toRoute('organizations'); 
            }else{
                $orgTypes = $orgService->fetchOrganizationTypes();
                $countries = $commonService->getAllCountries();
        
                return new ViewModel(array('org'=>$org,'orgTypes' => $orgTypes,'countries' => $countries));
            }
            
        }       
        
    }


}

