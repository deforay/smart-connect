<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class ReceiveEidDataController extends AbstractRestfulController
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
        // \Zend\Debug\Debug::dump($params);die;
        $params = file_get_contents('php://input');
        $response = $this->sampleService->saveEidDataFromAPI($params);
        return new JsonModel($response);
    }
}
