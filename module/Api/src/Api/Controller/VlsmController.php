<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Laminas\Json\Json;
use Zend\Debug\Debug;

class VlsmController extends AbstractRestfulController
{
    public function create($params) {
        $service = $this->getServiceLocator()->get('SampleService');
        $response =$service->saveFileFromVlsmAPI($params);
        return new JsonModel($response);
    }
}