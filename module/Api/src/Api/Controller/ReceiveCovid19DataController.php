<?php

namespace Api\Controller;

use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;

class ReceiveCovid19DataController extends AbstractRestfulController
{
    use JsonResponseTrait;

    private $covid19FormService = null;

    public function __construct($covid19FormService)
    {
        $this->covid19FormService = $covid19FormService;
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
        // \Zend\Debug\Debug::dump("hi");die;
        $params = file_get_contents('php://input');
        $response = $this->covid19FormService->saveCovid19DataFromAPI($params);
        return $this->jsonResponse($response);
    }
}
