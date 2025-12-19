<?php

namespace Application\Service;

use Exception;
use Laminas\Session\Container;

class RoleService
{

    public $sm = null;

    public function __construct($sm)
    {
        $this->sm = $sm;
    }

    public function getAllRolesDetails($parameters)
    {
        $db = $this->sm->get('RolesTable');
        $acl = $this->sm->get('AppAcl');
        return $db->fetchAllRoleDetails($parameters, $acl);
    }

    public function getAllRoles() {
        $rolesDb = $this->sm->get('RolesTable');
        return $rolesDb->fetchAllRoles();
    }

    public function addRoles($params)
    {
        $adapter = $this->sm->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
        $eventLogDb = $this->sm->get('ActivityLogTable');
        $adapter->beginTransaction();
        try {
            $db = $this->sm->get('RolesTable');
            $result = $db->addRolesDetails($params);
            if ($result > 0) {
                $db->mapRolesPrivileges($params);
                $adapter->commit();
                $eventType = 'role-add';
                $action = 'added a new role ' . $params['roleName'];
                $resourceName = 'roles';
                $eventLogDb->addActivityLog($eventType, $action, $resourceName);
                $alertContainer = new Container('alert');
                $alertContainer->alertMsg = 'Role details added successfully';
            }
        } catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function updateRoles($params)
    {
        $adapter = $this->sm->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
        $eventLogDb = $this->sm->get('ActivityLogTable');
        $adapter->beginTransaction();
        try {
            $db = $this->sm->get('RolesTable');
            $result = $db->updateRolesDetails($params);
            if ($result > 0) {
                $db->mapRolesPrivileges($params);
                $adapter->commit();
                $eventType = 'role-update';
                $action = 'updated a role ' . $params['roleName'];
                $resourceName = 'roles';
                $eventLogDb->addActivityLog($eventType, $action, $resourceName);
                $alertContainer = new Container('alert');
                $alertContainer->alertMsg = 'Role details updated successfully';
            }
        } catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function getRole($roleId)
    {
        $db = $this->sm->get('RolesTable');
        return $db->getRolesDetails($roleId);
    }

    public function getPrivilegesMap($roleId) {
        $rolesDb = $this->sm->get('RolesTable');
        return $rolesDb->fetchPrivilegesMapByRoleId($roleId);
    }
}