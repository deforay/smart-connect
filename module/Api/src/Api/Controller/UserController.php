<?php

namespace Api\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Json\Json;

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

