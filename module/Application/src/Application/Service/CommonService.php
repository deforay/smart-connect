<?php

namespace Application\Service;

use Zend\Session\Container;

class CommonService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }

    
    public function getAllCountries() {
        $db = $this->sm->get('CountriesTable');
        return $db->getAllCountries();
    }
    
   
}