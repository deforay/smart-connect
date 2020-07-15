<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class VlsmEidController extends AbstractRestfulController
{
    public function create($params) {
        $service = $this->getServiceLocator()->get('EidSampleService');
        $response =$service->saveFileFromVlsmAPI();
        return new JsonModel($response);
    }
}