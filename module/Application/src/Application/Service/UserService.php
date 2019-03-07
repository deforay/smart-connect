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
        $adapter = $this->sm->get('Zend\Db\Adapter\Adapter')->getDriver()->getConnection();
        $adapter->beginTransaction();
        try {
            $db = $this->sm->get('UsersTable');
            $result = $db->addUser($params);
            if($result>0){
             $adapter->commit();
             $alertContainer = new Container('alert');
             $alertContainer->alertMsg = 'User details added successfully';
            }
        }
        catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }
    
    public function updateUser($params) {
        $adapter = $this->sm->get('Zend\Db\Adapter\Adapter')->getDriver()->getConnection();
        $adapter->beginTransaction();
        try {
            $db = $this->sm->get('UsersTable');
            $result = $db->updateUser($params);
            if($result>0){
             $adapter->commit();
             $alertContainer = new Container('alert');
             $alertContainer->alertMsg = 'User details updated successfully';
            }
        }
        catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }
   
    public function fetchUserOrganizations($userId) {
        $db = $this->sm->get('UserOrganizationsMapTable');
        return $db->fetchOrganizations($userId);
    }
   
    public function mapUserOrganizations($params) {
        $db = $this->sm->get('UserOrganizationsMapTable');
        return $db->mapUserOrganizations($params);
    }
    
    public function getAllUsers($parameters){
        $db = $this->sm->get('UsersTable');
        return $db->fetchAllUsers($parameters);
    }

    public function userLoginApi($params) {
        $db = $this->sm->get('UsersTable');
        return $db->userLoginDetailsApi($params);
    }
}