<?php

namespace Application\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\AbstractTableGateway;

use Laminas\Session\Container;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Generate Backup - inititaed in DataManagement/ExportController
 *
 * @author amit
 */
class GenerateBackupTable extends AbstractTableGateway {

    protected $table = 'generate_backups';
    public $sm = null;

    public function __construct(Adapter $adapter, $sm=null) {
        $this->adapter = $adapter;
        $this->sm = $sm;
    }


    public function addBackupGeneration($params){

        $logincontainer = new Container('credo');

        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }

            $data = array(
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'requested_by' => $logincontainer->userId,
                            'requested_on' => new Expression('NOW()'),                        
                            'status' => 'pending',
                        );

            $this->insert($data);            
        }

    }


    public function completeBackup($backupId){
        $data = array(
            'completed_on' => new Expression('NOW()'),                        
            'status' => 'generated',
        );        
        $this->update($data, array("id" => $backupId));
    }


}