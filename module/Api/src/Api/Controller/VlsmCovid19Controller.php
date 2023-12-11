<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class VlsmCovid19Controller extends AbstractRestfulController
{
    private $covid19SampleService = null;

    public function __construct($covid19SampleService)
    {
        $this->covid19SampleService = $covid19SampleService;
    }

    public function getList()
    {
        exit('Nothing to see here');
    }
    public function create($params)
    {
        if (!isset($params['api-version'])) {
            $params['api-version'] = 'v1';
        }
        if ($params['api-version'] == 'v1') {
            $response = $this->covid19SampleService->saveFileFromVlsmAPIV1();
        } elseif ($params['api-version'] == 'v2') {
            $response = $this->covid19SampleService->saveFileFromVlsmAPIV2();
        }


        return new JsonModel($response); 
        
    }
}
