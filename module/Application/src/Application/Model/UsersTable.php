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
            if($logincontainer->role == 1 || $logincontainer->role == 2){
               return '/labs/dashboard';
            }else if($logincontainer->role == 3){
                return '/clinics/dashboard';
            }else if($logincontainer->role == 4){
                return '/hubs/dashboard';
            }
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
        if(isset($params['email']) && trim($params['email'])!="" && trim($params['password'])!=""){
            $newData=array('user_name'=>$params['username'],
                        'email'=>$params['email'],
                        'mobile'=>$params['mobile'],
                        'password'=>$params['password'],
                        'role'=>$params['role'],
                        //'created_by'=>$credoContainer->userId,
                        //'created_on'=> new Expression('NOW()'),
                        'status'=>'active'
                    );
        
            $this->insert($newData);
            return $this->lastInsertValue;
        }
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
        $userId=base64_decode($params['userId']);
        if(trim($params['userId'])!=""){
            $data=array('user_name'=>$params['username'],
                       'email'=>$params['email'],
                       'mobile'=>$params['mobile'],
                       'role'=>$params['role'],
                       //'created_by'=>$credoContainer->userId,
                       //'created_on'=> new Expression('NOW()'),
                       'status'=>$params['status']
                       );
            if(trim($params['password'])!=""){
                $data['password']=$params['password'];
            }
            $this->update($data,array('user_id' => $userId));
            return $userId;
        }
    }    
    
    public function fetchAllUsers($parameters) {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('user_name','role_name','email','mobile');

        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */

        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ( $parameters['sSortDir_' . $i] ) . ",";
                }
            }
            $sOrder = substr_replace($sOrder, "", -1);
        }

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */

        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        /*
         * SQL queries
         * Get data to display
         */
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('u'=>'dash_users'))
                ->join(array('r' => 'dash_user_roles'), "u.role=r.role_id", array('role_name'));
                
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery); // Get the string of the Sql, instead of the Select-instance 
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->getSqlStringForSqlObject($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iTotal = $this->select()->count();
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        
        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = ucwords($aRow['user_name']);
            $row[] = ucfirst($aRow['role_name']);
            $row[] = $aRow['email'];
            $row[] = $aRow['mobile'];
            
            $row[] = '<a href="./edit/' . base64_encode($aRow['user_id']) . '" class="btn green" style="margin-right: 2px;" title="Edit"><i class="fa fa-pencil"> Edit</i></a>';
            
            $output['aaData'][] = $row;
        }
        return $output;
    }
    
}
