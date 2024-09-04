<?php

namespace Api\Controller;

use Application\Service\CommonService;
use Laminas\View\Model\JsonModel;
use Application\Service\SampleService;
use Laminas\Mvc\Controller\AbstractRestfulController;

class VlsmController extends AbstractRestfulController
{

    public SampleService $sampleService;

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

        // Ensure to parse raw input data if $_POST and $_FILES are empty
        CommonService::parseMultipartFormData();

        if (!isset($params['api-version'])) {
            $params['api-version'] = 'v1';
        }
        if ($params['api-version'] == 'v1') {
            $response = $this->sampleService->saveFileFromVlsmAPIV1();
        } elseif ($params['api-version'] == 'v2') {
            $response = $this->sampleService->saveFileFromVlsmAPIV2();
        }

        return new JsonModel($response);
    }
}
