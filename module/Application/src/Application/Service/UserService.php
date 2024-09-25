<?php

namespace Application\Service;

use Exception;
use Laminas\Session\Container;

class UserService
{

    public $sm = null;
    private \Application\Model\UsersTable $usersTable;

    public function __construct($sm, $usersTable)
    {
        $this->sm = $sm;
        $this->usersTable = $usersTable;
    }

    public function login($params)
    {
        return $this->usersTable->login($params);
    }
    public function otp($params)
    {
        $dataInterfaceLogin = new Container('dataInterfaceLogin');
        $loginParams = array('email' => $dataInterfaceLogin->email, 'password' => $dataInterfaceLogin->password);
        return $this->usersTable->login($loginParams, $params['otp']);
    }
    public function fetchUsers()
    {
        return $this->usersTable->fetchUsers();
    }

    public function fetchRoles()
    {
        $db = $this->sm->get('RolesTable');
        return $db->fetchRoles();
    }

    public function getUser($userId)
    {
        return $this->usersTable->getUser($userId);
    }

    public function addUser($params)
    {
        $adapter = $this->sm->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
        $adapter->beginTransaction();
        try {
            $result = $this->usersTable->addUser($params);
            if ($result > 0) {
                $adapter->commit();
                $alertContainer = new Container('alert');
                $alertContainer->alertMsg = 'User details added successfully';
            }
        } catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function updateUser($params)
    {
        $adapter = $this->sm->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
        $adapter->beginTransaction();
        try {
            $result = $this->usersTable->updateUser($params);
            if ($result > 0) {
                $adapter->commit();
                $alertContainer = new Container('alert');
                $alertContainer->alertMsg = 'User details updated successfully';
            }
        } catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function fetchUserOrganizations($userId)
    {
        $db = $this->sm->get('UserOrganizationsMapTable');
        return $db->fetchOrganizations($userId);
    }

    public function mapUserOrganizations($params)
    {
        $db = $this->sm->get('UserOrganizationsMapTable');
        return $db->mapUserOrganizations($params);
    }

    public function getAllUsers($parameters)
    {
        return $this->usersTable->fetchAllUsers($parameters);
    }

    public function userLoginApi($params)
    {
        return $this->usersTable->userLoginDetailsApi($params);
    }
}
