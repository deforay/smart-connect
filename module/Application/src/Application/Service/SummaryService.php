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
        
    public function fetchSummaryTabDetails(){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getSummaryTabDetails();
    }
    
    public function getKeySummaryIndicatorsDetails(){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchKeySummaryIndicatorsDetails();
    }
    
    public function getSamplesReceivedBarChartDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSamplesReceivedBarChartDetails($params);
    }
    
    public function getAllSamplesReceivedByDistrict($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSamplesReceivedByDistrict($parameters);
    }
    public function getAllSamplesReceivedByProvince($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSamplesReceivedByProvince($parameters);
    }
    
    public function getAllSamplesReceivedByFacility($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSamplesReceivedByFacility($parameters);
    }
    
    public function getSuppressionRateBarChartDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSuppressionRateBarChartDetails($params);
    }
    
    public function getAllSuppressionRateByDistrict($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSuppressionRateByDistrict($parameters);
    }

    public function getAllSuppressionRateByProvince($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSuppressionRateByProvince($parameters);
    }
    
    public function getAllSuppressionRateByFacility($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSuppressionRateByFacility($parameters);
    }
    
    public function getSamplesRejectedBarChartDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchSamplesRejectedBarChartDetails($params);
    }
    
    public function getAllSamplesRejectedByDistrict($parameters){
       $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchAllSamplesRejectedByDistrict($parameters); 
    }
    
    public function getAllSamplesRejectedByFacility($parameters){
       $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fecthAllSamplesRejectedByFacility($parameters); 
    }
    public function getAllSamplesRejectedByProvince($parameters){
        $sampleDb = $this->sm->get('SampleTable');
       return $sampleDb->fecthAllSamplesRejectedByProvince($parameters); 
     }
    
    public function getRegimenGroupBarChartDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchRegimenGroupBarChartDetails($params);
    }
    
    public function getRegimenGroupSamplesDetails($parameters){
        $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchRegimenGroupSamplesDetails($parameters);
    }
    
    public function getAllLineOfTreatmentDetails(){
        $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchAllLineOfTreatmentDetails();
    }
    
    public function getAllCollapsibleLineOfTreatmentDetails(){
        $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchAllCollapsibleLineOfTreatmentDetails();
    }
}