<?php

namespace Api\Controller;

use Laminas\View\Model\JsonModel;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractRestfulController;

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
        // Ensure to parse raw input data if $_POST and $_FILES are empty
        CommonService::parseMultipartFormData(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-covid19");
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
