<?php

namespace Api\Controller;

use Laminas\View\Model\JsonModel;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractRestfulController;

class VlsmMetadataController extends AbstractRestfulController
{

    private CommonService $commonService;

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
        // Ensure to parse raw input data if $_POST and $_FILES are empty
        CommonService::parseMultipartFormData();

        $response = $this->commonService->saveVlsmMetadataFromAPI($params);
        return new JsonModel($response);
    }
}
