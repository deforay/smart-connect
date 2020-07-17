<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Zend\Debug\Debug;
use \Covid19\Service\Covid19FormService;

class VlsmCovidController extends AbstractRestfulController
{
    public function create($params) {
        // Debug::dump($_FILES);die;
        $service = $this->getServiceLocator()->get('Covid19FormService');
        $response =$service->saveFileFromVlsmAPI();
        return new JsonModel($response);
    }
}