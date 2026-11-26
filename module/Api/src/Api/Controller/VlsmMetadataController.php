<?php

namespace Api\Controller;

use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractRestfulController;

class VlsmMetadataController extends AbstractRestfulController
{
    use JsonResponseTrait;

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
        $response = $this->commonService->saveVlsmMetadataFromAPI($params);
        return $this->jsonResponse($response);
    }
}
