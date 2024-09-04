<?php

namespace Api\Controller;

use Laminas\View\Model\JsonModel;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractRestfulController;

class WeblimsVLController extends AbstractRestfulController
{

    private $sampleService = null;

    public function __construct($sampleService)
    {
        $this->sampleService = $sampleService;
    }

    public function getList()
    {

        return array(
            'status'    => 'fail',
            'message'   => 'Invalid Request',
        );
    }
    public function create($params)
    {
        // Ensure to parse raw input data if $_POST and $_FILES are empty
        CommonService::parseMultipartFormData();

        $params = file_get_contents('php://input');
        $response = $this->sampleService->saveWeblimsVLAPI($params);
        return new JsonModel($response);
    }
}
