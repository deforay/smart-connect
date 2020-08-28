<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class SourceDataController extends AbstractRestfulController
{

    private $sampleService = null;

    public function __construct($sampleService)
    {
        $this->sampleService = $sampleService;
    }

    public function getList()
    {
        exit('Nothing to see here');
    }

    public function create($params)
    {

        if (isset($params['token']) && $params['token'] != '') {
            $response = $this->sampleService->getSourceData($params);
        } else {
            $response['status'] = '422';
            $response['result'] = 'Invalid or Missing Query Params';
        }
        return new JsonModel($response);
    }
}
