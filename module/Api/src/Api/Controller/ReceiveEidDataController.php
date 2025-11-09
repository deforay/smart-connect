<?php

namespace Api\Controller;

use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;

class ReceiveEidDataController extends AbstractRestfulController
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
        // \Zend\Debug\Debug::dump($params);die;
        $params = file_get_contents('php://input');
        $response = $this->sampleService->saveEidDataFromAPI($params);
        return $this->jsonResponse($response);
    }
}
