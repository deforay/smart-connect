<?php

namespace Application\Model;

use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\AbstractTableGateway;
use \Application\Service\CommonService;
use Laminas\Json\Expr;

class DashTrackApiRequestsTable extends AbstractTableGateway
{

    public $table = 'dash_track_api_requests';
    public $adapter;

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function add($params)
    {
        return $this->insert($params);
    }

    public function fetchAllDashTrackApiRequestsByGrid($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

         $aColumns = array('transaction_id', 'number_of_records', 'request_type', 'test_type', "api_url", "DATE_FORMAT(requested_on,'%d-%b-%Y')");
         $orderColumns = array('transaction_id', 'number_of_records', 'request_type', 'test_type', 'api_url', 'requested_on');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('a' => "dash_track_api_requests"));

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }
        $common = new CommonService();
        [$startDate, $endDate] = $common->convertDateRange($_POST['dateRange'] ?? '');

        if (isset($_POST['dateRange']) && trim($_POST['dateRange']) != '') {
            $sWhere[] = ' DATE(a.requested_on) >= "' . $startDate . '" AND DATE(a.requested_on) <= "' . $endDate . '"';
        }

        if (isset($_POST['syncedType']) && trim($_POST['syncedType']) != '') {
            $sWhere[] = ' a.request_type like "' . $_POST['syncedType'] . '"';
        }
        if (isset($_POST['testType']) && trim($_POST['testType']) != '') {
            $sWhere[] = ' a.test_type like "' . $_POST['testType'] . '"';
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        // Get the string of the Sql, instead of the Select-instance
        $sQueryStr = $sql->buildSqlString($sQuery);
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
            $row = [];
            $row[] = $aRow['transaction_id'];
            $row[] = $aRow['number_of_records'];
            $row[] = str_replace("-", " ", ($aRow['request_type']));
            $row[] = strtoupper($aRow['test_type']);
            $row[] = $aRow['api_url'];
            $row[] = $common->humanReadableDateFormat($aRow['requested_on'], true);
            $row[] = '<a href="javascript:void(0);" class="btn btn-success btn-xs" style="margin-right: 2px;" title="Result" onclick="showModal(\'show-params.php?id=' . base64_encode($aRow['api_track_id']) . '\',1200,720);"> Show Params</a>';
            
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function addApiTracking($transactionId, $user, $numberOfRecords, $requestType, $testType, $url = null, $requestData = null, $responseData = null, $format = null, $labId = null)
    {
        try {
            $common = new CommonService();
            $requestData = $common->toJSON($requestData);
            $responseData = $common->toJSON($responseData);

            $folderPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api';
            if (!empty($requestData) && $requestData != '[]') {
                $common->makeDirectory($folderPath . DIRECTORY_SEPARATOR . 'requests');
                $common->zipJson($requestData, "$folderPath/requests/$transactionId.json");
            }
            if (!empty($responseData) && $responseData != '[]') {
                $common->makeDirectory($folderPath . DIRECTORY_SEPARATOR . 'responses');
                $common->zipJson($responseData, "$folderPath/responses/$transactionId.json");
            }

            $data = [
                'transaction_id' => $transactionId ?? null,
                'requested_by' => $user ?? 'system',
                'requested_on' => $common->getDateTime(),
                'number_of_records' => $numberOfRecords ?? 0,
                'request_type' => $requestType ?? null,
                'test_type' => $testType ?? null,
                'api_url' => $url ?? null,
                'facility_id' => $labId ?? null,
                'data_format' => $format ?? null
            ];
            if (!empty($requestData) && $requestData != '[]') {
                $data['api_params'] = '/uploads/requests/' . $transactionId . '.json';
                $data['request_data'] = '/uploads/requests/' . $transactionId . '.json';
            }
            if (!empty($responseData) && $responseData != '[]') {
                $data['response_data'] = '/uploads/responses/' . $transactionId . '.json';
            }
            return $this->insert($data);
        } catch (Exception $exc) {
            error_log($exc->getMessage());
            error_log($this->db->getLastError());
            error_log($exc->getTraceAsString());
            return 0;
        }
    }
}
