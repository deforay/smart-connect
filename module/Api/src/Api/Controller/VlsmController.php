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

        if(!isset($params['api-version'])){
            $params['api-version'] = 'v1';
        }
        if( $params['api-version'] == 'v1'){
            $response = $this->sampleService->saveFileFromVlsmAPIV1();
        }else if( $params['api-version'] == 'v2'){
            $response = $this->sampleService->saveFileFromVlsmAPIV2();
        }
        
        return new JsonModel($response);
    }
}
