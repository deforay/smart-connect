<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Application\Service\CommonService;


class UsersTable extends AbstractTableGateway
{

    protected $table = 'dash_users';
    public $sm = null;
    public array $config;
    public $useCurrentSampleTable = null;
    public CommonService $commonService;
    protected $adapter;
    public function __construct(Adapter $adapter, $sm = null, $commonService = null)
    {
        $this->adapter = $adapter;
        $this->sm = $sm;
        $this->commonService = $commonService;
        $this->config = $this->sm->get('Config');
        $this->useCurrentSampleTable = $this->config['defaults']['use-current-sample-table'];
    }

    public function login($params, $otp = null)
    {
        $username = $params['email'];
        $password = $params['password'];

        //echo $username; echo $password; echo $otp; die;
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('u' => 'dash_users'))
            ->join(array('r' => 'dash_user_roles'), 'u.role=r.role_id')
            ->where(array('email' => $username, 'password' => $password));

        if (isset($otp) && $otp != null && $otp != '') {
            $sQuery = $sQuery->where(array('otp' => $otp));
        }

        $sQueryStr = $sql->buildSqlString($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $container = new Container('alert');
        $loginContainer = new Container('credo');
        if (!empty($rResult)) {

            date_default_timezone_set(isset($this->config['defaults']['time-zone']) ? $this->config['defaults']['time-zone'] : 'UTC');
            // Let us flush the file cache
            $cacheExpiryInMins = isset($this->config['defaults']['cache-expiry']) ? $this->config['defaults']['cache-expiry'] : 120;
            clearstatcache();

            $cacheDirLastModified = filemtime(realpath(APPLICATION_PATH . "/../data/cache"));
            $cacheExpiryInMins = strtotime("-$cacheExpiryInMins mins");

            if ($cacheDirLastModified > strtotime("-$cacheExpiryInMins mins")) {
                $this->commonService->clearAllCache();
            }


            $facilities_id = [];
            $facilities_name = [];
            $facilities_code = [];
            $provinces = [];
            $districts = [];
            $mapQuery = $sql->select()->from(array('u_f_map' => 'dash_user_facility_map'))
                ->join(array('f' => 'facility_details'), 'f.facility_id=u_f_map.facility_id', array('facility_name', 'facility_code', 'facility_state', 'facility_district'))
                ->where(array('u_f_map.user_id' => $rResult[0]["user_id"]));
            $mapQueryStr = $sql->buildSqlString($mapQuery);
            $mapResult = $dbAdapter->query($mapQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($mapResult) && count($mapResult) > 0) {
                foreach ($mapResult as $facilities) {
                    $facilities_id[] = $facilities['facility_id'];
                    $facilities_name[] = $facilities['facility_name'];
                    $facilities_code[] = $facilities['facility_code'];
                    //set provinces
                    if ($facilities['facility_state'] != null && trim($facilities['facility_state']) != '' && !in_array($facilities['facility_state'], $provinces)) {
                        $provinces[] = $facilities['facility_state'];
                    }
                    //set districts
                    if ($facilities['facility_district'] != null && trim($facilities['facility_district']) != '' && !in_array($facilities['facility_district'], $districts)) {
                        $districts[] = $facilities['facility_district'];
                    }
                }
            } else {
                //set 0 by default
                $facilities_id = [];
                $facilities_name = [];
                $facilities_code = [];
                $provinces = [];
                $districts = [];
            }
            $loginContainer->userId = $rResult[0]["user_id"];
            $loginContainer->username = $rResult[0]["user_name"];
            $loginContainer->mobile = $rResult[0]["mobile"];
            $loginContainer->role = $rResult[0]["role"];
            $loginContainer->roleCode = $rResult[0]["role_code"];
            $loginContainer->email = $rResult[0]["email"];
            //$loginContainer->accessType = $rResult[0]["access_type"];
            $loginContainer->mappedFacilities = $facilities_id;
            $loginContainer->mappedFacilitiesName = $facilities_name;
            $loginContainer->mappedFacilitiesCode = $facilities_code;
            $loginContainer->provinces = $provinces;
            $loginContainer->districts = $districts;
            $container->alertMsg = '';
            if ($this->useCurrentSampleTable == true) {
                $loginContainer->showCurrentTablesToggle = true;
                $loginContainer->useCurrentTables = $this->useCurrentSampleTable;
                $loginContainer->sampleTable = 'dash_form_vl_current';
                $loginContainer->eidSampleTable = 'dash_form_eid_current';
                $loginContainer->covid19SampleTable = 'dash_form_covid19_current';
            } else {
                $loginContainer->sampleTable = 'dash_form_vl';
                $loginContainer->eidSampleTable = 'dash_form_eid';
                $loginContainer->covid19SampleTable = 'dash_form_covid19';
            }


            if ($otp == null && $loginContainer->role == 7) {
                // Let us ensure this person cannot login till they enter OTP.
                // so we will clear the login session
                $loginContainer->getManager()->getStorage()->clear('credo');
                $dataInterfaceLogin = new Container('dataInterfaceLogin');
                $dataInterfaceLogin->email = $rResult[0]["email"];
                $dataInterfaceLogin->password = $rResult[0]["password"];
                return 'login-otp';
            } elseif ($otp != null && $loginContainer->role == 7) {
                return 'data-management-export';
            } elseif ($loginContainer->role == 3) {
                return 'clinics';
            } elseif ($loginContainer->role == 2) {
                return 'laboratory';
            } else {
                return 'summary';
            }
            //die('home');




        } else {
            $container = new Container('alert');
            $container->alertMsg = 'Please check your login credentials';
            return 'login';
        }
    }

    public function fetchUsers()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('u' => 'dash_users'))
            ->join(array('r' => 'dash_user_roles'), 'u.role=r.role_id');
        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function addUser($params)
    {
        $userId = 0;
        if (isset($params['email']) && trim($params['email']) != "" && trim($params['password']) != "") {
            $newData = array(
                'user_name' => $params['username'],
                'email' => $params['email'],
                'mobile' => $params['mobile'],
                'password' => $params['password'],
                'role' => $params['role'],
                //'created_by'=>$credoContainer->userId,
                //'created_on'=> new Expression('NOW()'),
                'status' => 'active'
            );
            $this->insert($newData);
            $userId = $this->lastInsertValue;
            if ($userId > 0 && (isset($params['facility']) && count($params['facility']) > 0)) {
                $dbAdapter = $this->adapter;
                $userFacilityMapDb = new UserFacilityMapTable($dbAdapter);
                $counter = count($params['facility']);
                for ($f = 0; $f < $counter; $f++) {
                    $userFacilityMapDb->insert(array('user_id' => $userId, 'facility_id' => $params['facility'][$f]));
                }
            }
        }
        return $userId;
    }

    public function getUser($userId)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('u' => 'dash_users'))
            ->join(array('r' => 'dash_user_roles'), 'u.role=r.role_id')
            ->where("user_id= $userId");
        $sQueryStr = $sql->buildSqlString($sQuery);
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if ($rResult) {
            $userFacilityMapQuery = $sql->select()->from(array('u_f_map' => 'dash_user_facility_map'))
                ->columns(array('facility_id'))
                ->where("u_f_map.user_id= $userId");
            $userFacilityMapStr = $sql->buildSqlString($userFacilityMapQuery);
            $rResult['facilities'] = $dbAdapter->query($userFacilityMapStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            return $rResult;
        } else {
            return false;
        }
    }

    public function updateUser($params)
    {
        $credoContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $userFacilityMapDb = new UserFacilityMapTable($dbAdapter);
        $userId = base64_decode($params['userId']);
        if (trim($params['userId']) != "") {
            $mapQuery = $sql->select()->from(array('u_f_map' => 'dash_user_facility_map'))
                ->where(array('u_f_map.user_id' => $userId));
            $mapQueryStr = $sql->buildSqlString($mapQuery);
            $mapResult = $dbAdapter->query($mapQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($mapResult) && count($mapResult) > 0) {
                $userFacilityMapDb->delete(array('user_id' => $userId));
            }
            $data = array(
                'user_name' => $params['username'],
                'email' => $params['email'],
                'mobile' => $params['mobile'],
                'role' => $params['role'],
                //'created_by'=>$credoContainer->userId,
                //'created_on'=> new Expression('NOW()'),
                'status' => $params['status']
            );
            if (trim($params['password']) != "") {
                $data['password'] = $params['password'];
            }
            $this->update($data, array('user_id' => $userId));
            //remove user-facility map

            //update user-facility map
            if (isset($params['facility']) && count($params['facility']) > 0) {
                $counter = count($params['facility']);
                for ($f = 0; $f < $counter; $f++) {
                    $userFacilityMapDb->insert(array('user_id' => $userId, 'facility_id' => $params['facility'][$f]));
                }
            }
            return $userId;
        }
    }

    public function fetchAllUsers($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('user_name', 'role_name', 'email', 'mobile');

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
            for ($i = 0; $i < (int) $parameters['iSortingCols']; $i++) {
                if ($parameters['bSortable_' . (int) $parameters['iSortCol_' . $i]] == "true") {
                    $sOrder .= $aColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }
        /* Individual column filtering */
        $counter = count($aColumns);

        /* Individual column filtering */
        for ($i = 0; $i < $counter; $i++) {
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
        $sQuery = $sql->select()->from(array('u' => 'dash_users'))
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

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iTotal = $this->select()->count();
        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        $buttText = $this->commonService->translate('Edit');
        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = ucwords($aRow['user_name']);
            $row[] = ucfirst($aRow['role_name']);
            $row[] = $aRow['email'];
            $row[] = $aRow['mobile'];
            $row[] = '<a href="./edit/' . base64_encode($aRow['user_id']) . '" class="btn green" style="margin-right: 2px;" title="' . $buttText . '"><i class="fa fa-pencil"> ' . $buttText . '</i></a>';

            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function userLoginDetailsApi($params)
    {
        if (trim($params['userName']) != "" && trim($params['password']) != "") {
            $username = $params['userName'];
            $password = $params['password'];
            $dbAdapter = $this->adapter;
            $sql = new Sql($dbAdapter);

            $sQuery = $sql->select()->from(array('u' => 'dash_users'))
                ->join(array('r' => 'dash_user_roles'), 'u.role=r.role_id', array('role_code'))
                ->where(array('email' => $username, 'password' => $password, 'u.status' => 'active', 'role' => '6'));
            $sQueryStr = $sql->buildSqlString($sQuery);
            $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
            if ($rResult != "") {
                if (trim($rResult['api_token']) == '') {
                    $token = $this->generateApiToken();
                    $data = array('api_token' => $token);
                    $this->update($data, array('user_id' => $rResult['user_id']));
                }
                $query = $sql->select()->from(array('u' => 'dash_users'))
                    ->columns(array('api_token'))
                    ->where(array('user_id' => $rResult['user_id']));
                $queryStr = $sql->buildSqlString($query);
                $dResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if ($dResult != "") {
                    $response['status'] = '200';
                    $response['token'] = $dResult['api_token'];
                }
            } else {
                $response['status'] = '403';
                $response['message'] = 'Invalid or Missing Query Params';
            }
        } else {
            $response['status'] = '403';
            $response['message'] = 'Invalid or Missing Query Params';
        }
        return $response;
    }

    public function generateApiToken()
    {
        //$token = bin2hex(random_bytes(32));
        $token = bin2hex(openssl_random_pseudo_bytes(32));
        return $this->checkUserApiToken($token);
    }

    public function checkUserApiToken($token)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('u' => 'dash_users'))
            ->where(array('api_token' => $token));
        $sQueryStr = $sql->buildSqlString($sQuery);
        $result = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if ($result != "") {
            $this->generateApiToken();
        } else {
            return $token;
        }
    }

    public function checkExistUser($name)
    {
        $userInfo = $this->select(array('user_name LIKE "' . $name . '%"'))->current();
        if ($userInfo) {
            return $userInfo['user_id'];
        } else {
            $this->insert(array(
                'user_name' => $name,
                'role'      => 9999,
                'status'    => 'inactive'
            ));
            return $this->lastInsertValue;
        }
    }
}
