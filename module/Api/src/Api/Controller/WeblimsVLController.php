<?php

namespace Api\Controller;

use Laminas\Http\Response;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractRestfulController;

class WeblimsVLController extends AbstractRestfulController
{
    use JsonResponseTrait;

    private $sampleService = null;

    public function __construct($sampleService)
    {
        $this->sampleService = $sampleService;
    }

    public function getList()
    {

        return $this->jsonResponse(array(
            'status'    => 'fail',
            'message'   => 'Invalid Request',
        ), Response::STATUS_CODE_400);
    }
    public function create($params)
    {
        $phpInput = file_get_contents('php://input');
        $response = $this->sampleService->saveWeblimsVLAPI($phpInput);
        return $this->jsonResponse($response);
    }
}
