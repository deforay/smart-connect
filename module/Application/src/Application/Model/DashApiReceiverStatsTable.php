<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;
use \Application\Service\CommonService;
use Laminas\Json\Expr;

class DashApiReceiverStatsTable extends AbstractTableGateway
{

    protected $table = 'dash_api_receiver_stats';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function fetchAllDashApiReceiverStatsByGrid($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('facility_name', 'received_on');

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
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('f' => "facility_details"))->columns(array("facility_id", "labName" => "facility_name"))
            ->join(array('sync' => $this->table), "sync.lab_id=f.facility_id", array("*"), 'left')
            ->where(array("facility_type" => 2));

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($parameters['labId']) && $parameters['labId'] != "") {
            $sQuery->where(array("facility_id" => $parameters['labId']));
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }
        if (isset($parameters['type']) && $parameters['type'] == "status") {
            $sQuery->order("received_on DESC");
            $sQuery->group("f.facility_id");
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance 
        // echo $sQueryStr;die;
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
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        $xdays = 10;
        foreach ($rResult as $key => $aRow) {
            $row = array();
            $max = date("y-m-d HH:mi:ss", strtotime($aRow['received_on'] . "+" . $xdays . " days"));
            if (date("y-m-d HH:mi:ss") >= $max) {
                $row[] = "<status-indicator negative pulse></status-indicator>";
            } else {
                $row[] = "<status-indicator positive pulse></status-indicator>";
            }
            if (!isset($parameters['from']) && $parameters['from'] != "lab") {
                $row[] = "<a href='/status/lab/" . base64_encode($aRow['facility_id']) . "'>" . $aRow['labName'] . "</a>";
            }
            if (isset($aRow['received_on']) && $aRow['received_on'] != "") {
                $row[] = date("d-M-Y (h:i: a)", strtotime($aRow['received_on']));
            } else {
                $row[] = null;
            }
            if (isset($parameters['type']) && $parameters['type'] == "sync") {
                $row[] = $aRow['number_of_records_received'];
                $row[] = $aRow['number_of_records_processed'];
            }
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchStatusDetails($statusId)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('f' => "facility_details"))->columns(array("facility_id", "labName" => "facility_name"))
            ->join(array('sync' => $this->table), "sync.lab_id=f.facility_id", array("*"), 'left')
            ->where(array("facility_type" => 2, "facility_id" => $statusId, "unix_timestamp(received_on) >= now()-interval 3 month"))
            ->group(array(new Expr("DATE_FORMAT(received_on, '%m-%d')"), "lab_id"));
        $sQueryStr = $sql->buildSqlString($sQuery);
        die($sQueryStr);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function fetchLabSyncStatus($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('f' => "facility_details"))->columns(array(
            "facility_id", "labName" => "facility_name", "latest" =>
            new Expression("GREATEST(
                COALESCE(facility_attributes->>'$.lastHeartBeat', 0), 
                COALESCE(facility_attributes->>'$.dashLastResultsSync', 0), 
                COALESCE(facility_attributes->>'$.dashLastRequestSync', 0),
                COALESCE(sync.received_on, 0))"), 
            "dashLastResultsSync" => new Expression("(facility_attributes->>'$.dashLastResultsSync')"), 
            "dashLastRequestsSync" => new Expression("(facility_attributes->>'$.dashLastRequestsSync')") 
            ))
            ->join(array('sync' => $this->table), "sync.lab_id=f.facility_id", array(), 'left')
            ->group("facility_id")
            ->order(array("latest DESC"));
        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function updateAttributes($params){
        $commonService = new CommonService();
        $currentDateTime = $commonService->getDateTime();
        return $this->adapter->query("UPDATE ".$params['table']." SET form_attributes = JSON_SET(COALESCE(form_attributes, '{}'), '$.lastSync', ?) WHERE ".$params['field']." IN (?)", array($currentDateTime, $params['id']));
    }
}

