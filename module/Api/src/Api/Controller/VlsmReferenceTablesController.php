<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class VlsmReferenceTablesController extends AbstractRestfulController
{

    private $commonService = null;

    public function __construct($commonService)
    {
        $this->commonService = $commonService;
    }

    public function getList()
    {
        exit('Nothing to see here');
    }
    public function create($params)
    {
        // print_r($params);die;
        $response = $this->commonService->saveVlsmReferenceTablesFromAPI($params);
        return new JsonModel($response);
    }
}
