<?php

namespace Application\Service;

use Zend\Session\Container;

class SourceService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }
    public function getAllSources() {
        $db = $this->sm->get('SourceTable');
        return $db->fetchAllSources();
    }
}