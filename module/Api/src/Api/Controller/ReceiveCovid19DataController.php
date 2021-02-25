<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class ReceiveCovid19DataController extends AbstractRestfulController
{

    private $covid19FormService = null;

    public function __construct($covid19FormService)
    {
        $this->covid19FormService = $covid19FormService;
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
        // \Zend\Debug\Debug::dump("hi");die;
        $params = file_get_contents('php://input');
        $response = $this->covid19FormService->saveCovid19DataFromAPI($params);
        return new JsonModel($response);
    }
}
