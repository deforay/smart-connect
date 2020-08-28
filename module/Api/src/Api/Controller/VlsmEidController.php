<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class VlsmEidController extends AbstractRestfulController
{
    private $eidSampleService = null;

    public function __construct($eidSampleService)
    {
        $this->eidSampleService = $eidSampleService;
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
            $response = $this->eidSampleService->saveFileFromVlsmAPIV1();
        } else if ($params['api-version'] == 'v2') {
            $response = $this->eidSampleService->saveFileFromVlsmAPIV2();
        }


        return new JsonModel($response);
    }
}
