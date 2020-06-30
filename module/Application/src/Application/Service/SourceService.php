<?php

namespace Application\Service;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Session\Container;


class SourceService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }

    public function addSource($params) {
        $adapter = $this->sm->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
        $adapter->beginTransaction();
        try {
            $sourceDb = $this->sm->get('SourceTable');
            $result = $sourceDb->addSourceDetails($params);
            if ($result > 0) {
                $adapter->commit();
                $container = new Container('alert');
                $container->alertMsg = 'Source details added successfully';
            }
        } catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function getAllSource($params) {
        $sourceDb = $this->sm->get('SourceTable');
        //$acl = $this->sm->get('AppAcl');
        return $sourceDb->fetchAllSource($params);
    }

    public function updateSource($params) {
        $adapter = $this->sm->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
        $adapter->beginTransaction();
        try {
            $sourceDb = $this->sm->get('SourceTable');
            $result = $sourceDb->updateSourceDetails($params);
            if ($result > 0) {
                $adapter->commit();
                $container = new Container('alert');
                $container->alertMsg = 'Source details updated successfully';
            }
        } catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function getSource($id) {
        $sourceDb = $this->sm->get('SourceTable');
        return $sourceDb->getSourceDetails($id);
    }

    public function getAllActiveSource() {
        $sourceDb = $this->sm->get('SourceTable');
        return $sourceDb->fetchAllActiveSource();
    }
}
?>
