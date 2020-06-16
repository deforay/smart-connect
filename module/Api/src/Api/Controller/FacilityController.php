<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Laminas\Json\Json;

class FacilityController extends AbstractRestfulController
{
    public function create($params) {
        $facilityService = $this->getServiceLocator()->get('FacilityService');
        $response =$facilityService->getAllFacilitiesInApi($params);
        return new JsonModel($response);
    }
}   

