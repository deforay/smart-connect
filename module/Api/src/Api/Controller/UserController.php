<?php

namespace Api\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class UserController extends AbstractRestfulController
{
    private $userService = null;

    public function __construct($userService)
    {
        $this->userService = $userService;
    }

    public function getList()
    {
        exit('Nothing to see here');
    }

    public function create($params)
    {
        if (
            isset($params['userName']) && $params['userName'] != '' &&
            isset($params['password']) && $params['password'] != ''
        ) {
            $response = $this->userService->userLoginApi($params);
        } else {
            $response['status'] = '403';
            $response['message'] = 'Invalid or Missing Query Params';
        }

        return new JsonModel($response);
    }
}
