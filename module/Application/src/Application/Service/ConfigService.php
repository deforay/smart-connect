<?php
namespace Application\Service;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;

class ConfigService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }
    
    public function getAllConfig($params){
        $configDb = $this->sm->get('GlobalTable');
        return $configDb->fetchAllConfig($params);
    }
    
    public function getAllGlobalConfig(){
        $globalDb = $this->sm->get('GlobalTable');
        return $globalDb->fetchAllGlobalConfig();        
    }
    
    public function updateConfig($params){
        $adapter = $this->sm->get('Zend\Db\Adapter\Adapter')->getDriver()->getConnection();
        $adapter->beginTransaction();
        try {
            $db = $this->sm->get('GlobalTable');
            $result = $db->updateConfigDetails($params);
            $adapter->commit();
            $alertContainer = new Container('alert');
            $alertContainer->alertMsg = 'Config details updated successfully';
        }
        catch (Exception $exc) {
            $adapter->rollBack();
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }
    
    public function fetchGlobalValue($globalName){
        $db = $this->sm->get('GlobalTable');
        return $db->getGlobalValue($globalName);
    }
}
?>

