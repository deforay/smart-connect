<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Zend\Debug\Debug;

class WeblimsVLController extends AbstractRestfulController
{
    public function create($params) {
        $service = $this->getServiceLocator()->get('SampleService');
        $response =$service->saveWeblimsVLAPI($params);
        return new JsonModel($response);
    }
}