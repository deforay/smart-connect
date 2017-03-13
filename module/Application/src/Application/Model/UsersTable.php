<?php

namespace Application\Model;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Expression;
use Zend\Db\TableGateway\AbstractTableGateway;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Countries
 *
 * @author amit
 */
class UsersTable extends AbstractTableGateway {

    protected $table = 'dash_users';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
    
    public function login($params) {
        
        $username = $params['email'];
        $password = $params['password'];

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('u' => 'dash_users'))
                ->join(array('r' => 'dash_user_roles'), 'u.role=r.role_id')
                ->where(array('email' => $username, 'password' => $password));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        
        $container = new Container('alert');
        $logincontainer = new Container('credo');
        if (count($rResult) > 0) {
            $logincontainer->userId = $rResult[0]["user_id"];
            $logincontainer->name = $rResult[0]["user_name"];
            $logincontainer->mobile = $rResult[0]["mobile"];
            $logincontainer->role = $rResult[0]["role"];
            $logincontainer->email = $rResult[0]["email"];
            //$logincontainer->accessType = $rResult[0]["access_type"];
            $container->alertMsg = '';
            //die('home');
            return '/labs/dashboard';
        } else {
            $container->alertMsg = 'Please check your login credentials';
            //die('login');
            return '/login';
        }
    }
    
    public function fetchUsers(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('u' => 'dash_users'))
                ->join(array('r' => 'dash_user_roles'), 'u.role=r.role_id');
        
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $rResult;
    
    }
    
    public function addUser($params){
        $credoContainer = new Container('credo');
        $newData=array('username'=>$params['username'],
                       'email'=>$params['email'],
                       'mobile'=>$params['mobile'],
                       'password'=>$params['password'],
                       'role'=>$params['role'],
                       'created_by'=>$credoContainer->userId,
                       'created_on'=> new Expression('NOW()'),
                       'status'=>'active'
                       );
        
        //\Zend\Debug\Debug::dump($newData);die;
        
        $this->insert($newData);
        return $this->lastInsertValue;
    }    
    
    public function getUser($userId){
        $dbAdapter = $this->adapter;

        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('u' => 'dash_users'))
        ->join(array('r' => 'dash_user_roles'), 'u.role=r.role_id')
                      ->where("user_id= $userId");
        
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);

        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if(count($rResult) > 0){
            return $rResult[0];
        }else{
            return false;    
        }
        
    }    
    
    public function updateUser($params){
        $credoContainer = new Container('credo');
        $userId = $credoContainer->userId;        
        $data=array('username'=>$params['username'],
                       'email'=>$params['email'],
                       'mobile'=>$params['mobile'],
                       'password'=>$params['password'],
                       'role'=>$params['role'],
                       'created_by'=>$credoContainer->userId,
                       'created_on'=> new Expression('NOW()'),
                       'status'=>$params['status']
                       );
        return $this->update($data,array('user_id' => $params['userId']));
    }    
    
}
