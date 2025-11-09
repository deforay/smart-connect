<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;

class FacilityController extends AbstractRestfulController
{
    use JsonResponseTrait;

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
        return $this->jsonResponse($response);
    }
}
