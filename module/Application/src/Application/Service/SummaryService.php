<?php

namespace Application\Service;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Sql\Expression;
use Application\Service\CommonService;
use PHPExcel;

class SummaryService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }
    
        
    public function fetchSummaryTabDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getSummaryTabDetails($params);
    }
    
    public function getAllSamplesReceivedByDistrict($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSamplesReceivedByDistrict($params);
    }
    
    public function getAllSamplesReceivedByFacility($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSamplesReceivedByFacility($params);
    }
    
    public function getSamplesReceivedGraphDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSamplesReceivedGraphDetails($params);
    }
}