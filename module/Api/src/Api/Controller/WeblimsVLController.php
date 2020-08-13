<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class WeblimsVLController extends AbstractRestfulController
{
    public function create($params) {

        $response = array(
            'status'    => 'success',
            'message'   => 'API Connected',
        );
        return new JsonModel($response);
    }
}