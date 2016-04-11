<?php

namespace Application\Service;

use Zend\Session\Container;

class UserService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }
    
    public function login($params) {
        $db = $this->sm->get('UsersTable');
        return $db->login($params);
    }
    public function fetchUsers() {
        $db = $this->sm->get('UsersTable');
        return $db->fetchUsers();
    }
    
    public function fetchRoles() {
        $db = $this->sm->get('RolesTable');
        return $db->fetchRoles();
    }
    
    public function getUser($userId) {
        $db = $this->sm->get('UsersTable');
        return $db->getUser($userId);
    }
    
    public function addUser($params) {
        $db = $this->sm->get('UsersTable');
        return $db->addUser($params);
    }
    
    public function updateUser($params) {
        $db = $this->sm->get('UsersTable');
        return $db->updateUser($params);
    }
   
}