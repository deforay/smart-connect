<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Application\Service\CommonService;


class RolesTable extends AbstractTableGateway
{

    protected $table = 'dash_user_roles';
    public $sm = null;
    public $adapter;
    public CommonService $commonService;

    public function __construct(Adapter $adapter, $commonService, $sm = null)
    {
        $this->adapter = $adapter;
        $this->commonService = $commonService;
        $this->sm = $sm;
    }

    public function addRolesDetails($params)
    {
        if (trim($params['roleName'] != '')) {
            $rolesdata = array(
                'role_name' => $params['roleName'],
                'role_code' => $params['roleCode'],
                'status' => $params['status'],
            );
            
            $this->insert($rolesdata);
            return $this->lastInsertValue;
        }

    }

    public function updateRolesDetails($params)
    {
        if (trim($params['roleName']) != "" && trim($params['roleId']) != "") {
            $roleId = base64_decode($params['roleId']);
            $rolesdata = array(
                'role_name' => $params['roleName'],
                'role_code' => $params['roleCode'],
                'status' => $params['status']
            );
            $this->update($rolesdata, array('role_id=' . $roleId));
            return $roleId;
        }
    }

    public function fetchAllRoles()
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($this->adapter);
        $resourceQuery = $sql->select()->from('resources')->order('display_name');
        $resourceQueryStr = $sql->buildSqlString($resourceQuery); // Get the string of the Sql, instead of the Select-instance
        $resourceResult = $dbAdapter->query($resourceQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $n = count($resourceResult);
        for ($i = 0; $i < $n; $i++) {
            $privilageQuery = $sql->select()->from('privileges')->where(array('resource_id' => $resourceResult[$i]['resource_id']))->order('display_name');
            $privilageQueryStr = $sql->buildSqlString($privilageQuery); // Get the string of the Sql, instead of the Select-instance
            $resourceResult[$i]['privileges'] = $dbAdapter->query($privilageQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        return $resourceResult;
    }

    public function fetchAllRoleDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('role_name', 'role_code', 'status');

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
        $sQuery = $sql->select()->from(array('r' => 'dash_user_roles'));

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
            $row[] = $aRow['role_name'];
            $row[] = ucwords($aRow['role_code']);
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="edit/' . base64_encode($aRow['role_id']) . '" class="btn green" style="margin-right: 2px;" title="' . $buttText . '"><i class="fa fa-pencil"> ' . $buttText . '</i></a>';
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function getRolesDetails($id)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('dash_user_roles')->where(array('role_id' => $id));
        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
    }

    public function mapRolesPrivileges($params)
    {
        try {           
            $roleCode = $params['roleCode'];
            $sql = new Sql($this->adapter);
            $dbAdapter = $this->adapter;

            // Get or insert role    
            $query = $sql->select()->from('dash_user_roles')->where(array('role_code' => $roleCode));
            $roleQueryStr = $sql->buildSqlString($query); // Get the string of the Sql, instead of the Select-instance
            $role = $dbAdapter->query($roleQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $roleId = $role[0]['role_id'];

            $mapQuery = $sql->select()->from('roles_privileges_map')->where(array('role_id' => $roleId));
            $roleMapCheckQueryStr = $sql->buildSqlString($mapQuery);
            $roleMapCheck = $dbAdapter->query($roleMapCheckQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            if (!empty($roleMapCheck)) {
                //  delete records before insert
                $delete = $sql->delete('roles_privileges_map');
                $delete->where(array('role_id' => $roleId));
                // Execute the delete statement
                $statement = $sql->prepareStatementForSqlObject($delete);
                $statement->execute();
            }
            
            foreach ($params['resource'] as $resourceName => $privileges) {
                foreach ($privileges as $key => $privilege) {
                    if ($privilege === 'allow') {
                        $query = $sql->select()->from('privileges')->where(array('resource_id' => $resourceName, 'privilege_name' => $key));
                        $privilegeQueryStr = $sql->buildSqlString($query); // Get the string of the Sql, instead of the Select-instance
                        $privilegeRes = $dbAdapter->query($privilegeQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $privilegeId = $privilegeRes[0]['privilege_id'];

                        $sql = new Sql($dbAdapter);
                       
                        $insert = $sql->insert('roles_privileges_map');
                        $data = array(
                            'role_id' => $roleId,
                            'privilege_id' => $privilegeId
                        );
                        $insert->values($data);
                        $statement = $sql->prepareStatementForSqlObject($insert);
                        $result = $statement->execute();
                    }
                }
            }
        } catch (\Exception $exc) {
      
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
        }
    }

    public function fetchPrivilegesMapByRoleId($roleId) {
        $sql = new Sql($this->adapter);
        $dbAdapter = $this->adapter;
        $rolePrivmaps = [];
        if($roleId != '') {
            $query = $sql->select()->from('roles_privileges_map')->where(array('role_id' => $roleId));
            $privMapQueryStr = $sql->buildSqlString($query); // Get the string of the Sql, instead of the Select-instance
            $rolePrivmaps = $dbAdapter->query($privMapQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        return $rolePrivmaps;
    }
}
