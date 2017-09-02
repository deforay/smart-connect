<?php

namespace Application\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Sql\Sql;
use Application\Service\CommonService;

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
class GlobalTable extends AbstractTableGateway {

    protected $table = 'dash_global_config';
    public $sm = null;

    public function __construct(Adapter $adapter, $sm=null) {
        $this->adapter = $adapter;
        $this->sm = $sm;
    }
    
    public function getGlobalValue($globalName) {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('dash_global_config')->where(array('name' => $globalName));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $configValues = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $configValues[0]['value'];
    }
    
    public function fetchAllConfig($parameters) {
        $common = new CommonService($this->sm);
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */
        $aColumns = array('display_name', 'value');
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
        $sQuery = $sql->select()->from('dash_global_config')
                                ->where(array('status'=>'active'));
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
            "iTotalRecords" => $iFilteredTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $currentVal = $aRow['value'];
            if($aRow['display_name'] == 'Language'){
              $currentVal = $this->fetchLocaleDetailsById('display_name',$aRow['value']);
            }
           $row = array();
            $row[] = ucwords($common->translate($aRow['display_name']));
            $row[] = ucwords($currentVal);
            $output['aaData'][] = $row;
        }
       return $output;
    }
    
    public function fetchAllGlobalConfig() {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('dash_global_config');
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $configValues = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $size = sizeof($configValues);
        $arr = array();
        // now we create an associative array so that we can easily create view variables
        for ($i = 0; $i < $size; $i++) {
            $arr[$configValues[$i]['name']] = $configValues[$i]['value'];
        }
        
        // using assign to automatically create view variables
        // the column names will now become view variables
        return $arr;
    }
    
    public function updateConfigDetails($params) {
        $updateRes = 0;
        $common=new CommonService();
        //for logo deletion
        if(isset($params['removedLogoImage']) && trim($params['removedLogoImage']) != "" && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $params['removedLogoImage'])){
            unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $params['removedLogoImage']);
            $this->update(array('value'=>''),array('name'=>'logo'));
        }
        //for logo updation
        if(isset($_FILES['logo']['name']) && $_FILES['logo']['name']!= ""){
            if(!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo")) {
                mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo");
            }
            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['logo']['name'], PATHINFO_EXTENSION));
            $string = $common->generateRandomString(6).".";
            $imageName = "logo".$string.$extension;
            if (move_uploaded_file($_FILES["logo"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $imageName)) {
                $this->update(array('value'=>$imageName),array('name'=>'logo'));
            }
        }
        //for non-logo field updation
        foreach ($params as $fieldName => $fieldValue) {
            if($fieldName!= 'removedLogoImage'){
	       $updateRes = $this->update(array('value' => $fieldValue), array('name' => $fieldName));
            }
        }
      return $updateRes;
    }
    
    public function fetchActiveLocales(){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $localeQuery = $sql->select()->from(array('locale' => 'locale_details'));
        $loclaeQueryStr = $sql->getSqlStringForSqlObject($localeQuery);
       return $dbAdapter->query($loclaeQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }
    
    public function fetchLocaleDetailsById($column,$localeId){
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $localeQuery = $sql->select()->from(array('locale' => 'locale_details'))
                                     ->columns(array($column))
                                     ->where(array('locale.locale_id'=>$localeId));
        $loclaeQueryStr = $sql->getSqlStringForSqlObject($localeQuery);
        $localeResult = $dbAdapter->query($loclaeQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
       return $localeResult->$column;
    }
}
