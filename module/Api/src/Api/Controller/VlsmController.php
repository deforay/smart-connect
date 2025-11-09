<?php

namespace Api\Controller;

use Application\Service\CommonService;
use Application\Service\SampleService;
use Laminas\Mvc\Controller\AbstractRestfulController;

class VlsmController extends AbstractRestfulController
{
    use JsonResponseTrait;

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
        CommonService::parseMultipartFormData(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-vl");

        $response = $this->sampleService->saveFileFromVlsmAPIV2();

        return $this->jsonResponse($response);
    }
}
