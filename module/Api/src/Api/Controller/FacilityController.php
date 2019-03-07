<?php

namespace Api\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Json\Json;

class FacilityController extends AbstractRestfulController
{
    public function create($params) {
        $facilityService = $this->getServiceLocator()->get('FacilityService');
        $response =$facilityService->getAllFacilitiesInApi($params);
        return new JsonModel($response);
    }
}   

