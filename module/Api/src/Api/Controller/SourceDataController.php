<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;

class SourceDataController extends AbstractRestfulController
{
    use JsonResponseTrait;

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
        return $this->jsonResponse($response);
    }
}
