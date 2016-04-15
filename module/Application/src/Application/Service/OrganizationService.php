<?php

namespace Application\Service;

use Zend\Session\Container;

class OrganizationService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }

    
    public function fetchOrganizations() {
        $db = $this->sm->get('OrganizationsTable');
        return $db->fetchOrganizations();
    }
    
    public function fetchOrganizationTypes() {
        $db = $this->sm->get('OrganizationTypesTable');
        return $db->fetchOrganizationTypes();
    }

    
    public function getOrganization($orgId) {
        $db = $this->sm->get('OrganizationsTable');
        return $db->getOrganization($orgId);
    }
    
    public function addOrganization($params) {
        $db = $this->sm->get('OrganizationsTable');
        return $db->addOrganization($params);
    }
    
    public function updateOrganization($params) {
        $db = $this->sm->get('OrganizationsTable');
        return $db->updateOrganization($params);
    }
    
    
    public function fetchOrganizationMap($orgId) {
        $db = $this->sm->get('UserOrganizationsMapTable');
        return $db->fetchUsers($orgId);
    }
    
    
    
    public function mapOrganizationToUsers($params) {
        $db = $this->sm->get('UserOrganizationsMapTable');
        return $db->mapOrganizationToUsers($params);
    }
    
    
   
}