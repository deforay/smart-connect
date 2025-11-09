<?php

namespace Api\Controller;

use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractRestfulController;

class VlsmEidController extends AbstractRestfulController
{
    use JsonResponseTrait;

    public \Eid\Service\EidSampleService $eidSampleService;

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
        // Ensure to parse raw input data if $_POST and $_FILES are empty
        CommonService::parseMultipartFormData(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-eid");

        if (!isset($params['api-version'])) {
            $params['api-version'] = 'v1';
        }
        if ($params['api-version'] == 'v1') {
            $response = $this->eidSampleService->saveFileFromVlsmAPIV1();
        } elseif ($params['api-version'] == 'v2') {
            $response = $this->eidSampleService->saveFileFromVlsmAPIV2();
        }


        return $this->jsonResponse($response);
    }
}
