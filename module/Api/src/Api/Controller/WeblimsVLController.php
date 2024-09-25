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
        $phpInput = file_get_contents('php://input');
        $response = $this->sampleService->saveWeblimsVLAPI($phpInput);
        return new JsonModel($response);
    }
}
