<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class ReceiveVlDataController extends AbstractRestfulController
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
        // print_r("Prasath");die;
        // \Zend\Debug\Debug::dump("hi");die;
        $params = file_get_contents('php://input');
        $response = $this->sampleService->saveVLDataFromAPI($params);
        return new JsonModel($response);
    }
}
