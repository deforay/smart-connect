<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class FacilityController extends AbstractRestfulController
{
    private $facilityService = null;

    public function __construct($facilityService)
    {
        $this->facilityService = $facilityService;
    }

    public function getList()
    {
        exit('Nothing to see here');
    }
    public function create($params)
    {
        $response = $this->facilityService->getAllFacilitiesInApi($params);
        return new JsonModel($response);
    }
}
