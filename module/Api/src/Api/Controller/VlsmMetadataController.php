<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class VlsmMetadataController extends AbstractRestfulController
{

    private \Application\Service\CommonService $commonService;

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
        // print_r($params);
        // die;
        $response = $this->commonService->saveVlsmMetadataFromAPI($params);
        return new JsonModel($response);
    }
}
