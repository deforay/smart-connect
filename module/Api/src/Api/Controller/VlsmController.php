<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class VlsmController extends AbstractRestfulController
{

    private $sampleService = null;

    public function __construct($sampleService)
    {
        $this->sampleService = $sampleService;
    }

    public function getList()
    {
        exit('Nothing to see here');
    }

    public function create($params)
    {
        $response = $this->sampleService->saveFileFromVlsmAPI();
        return new JsonModel($response);
    }
}
