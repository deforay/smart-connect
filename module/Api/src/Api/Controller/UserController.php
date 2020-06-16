<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Laminas\Json\Json;

class UserController extends AbstractRestfulController
{
    public function create($params) {
        if(isset($params['userName']) && $params['userName']!='' && 
            isset($params['password']) && $params['password']!=''){
            $userService = $this->getServiceLocator()->get('UserService');
            $response =$userService->userLoginApi($params);
        }else{
            $response['status'] = '403';
            $response['message'] = 'Invalid or Missing Query Params';
        }

        return new JsonModel($response);
    }
}   

