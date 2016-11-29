<?php

namespace Application\Service;

use Zend\Session\Container;

class SampleService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }
    public function UploadSampleResultFile($params) {
        $sampleDb = $this->sm->get('SourceTable');
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $allowedExtensions = array('xls', 'xlsx', 'csv');
            $fileName = $_FILES['importFile']['name'];
            $ranNumber = str_pad(rand(0, pow(10, 6)-1), 6, '0', STR_PAD_LEFT);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileName =$ranNumber.".".$extension;
            
            if (!file_exists(UPLOAD_PATH) && !is_dir(UPLOAD_PATH)) {
                mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "uploads");
            }
            if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "vl-sample-result") && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "vl-sample-result")) {
                mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . "vl-sample-result");
            }
            
            if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR ."vl-sample-result" . DIRECTORY_SEPARATOR . $fileName)) {
                if (move_uploaded_file($_FILES['importFile']['tmp_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR ."vl-sample-result" . DIRECTORY_SEPARATOR . $fileName)) {
                    $objPHPExcel = \PHPExcel_IOFactory::load(UPLOAD_PATH . DIRECTORY_SEPARATOR ."vl-sample-result" . DIRECTORY_SEPARATOR . $fileName);
                    $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
                    $count = count($sheetData);
                    $common = new \Application\Service\CommonService();
                    for ($i = 2; $i <= $count; ++$i) {
                        if(trim($sheetData[$i]['A']) != '' && trim($sheetData[$i]['B']) != '') {
                            $sampleCode = trim($sheetData[$i]['A']);
                            $data = array('sample_code'=>$sampleCode,
                                          'vl_instance_id'=>trim($sheetData[$i]['B']),
                                          'source'=>$params['sourceName'],
                                          'gender'=>trim($sheetData[$i]['C']),
                                          'age_in_yrs'=>trim($sheetData[$i]['D']),
                                          'sample_collection_date'=>trim($sheetData[$i]['Q']),
                                          'lab_tested_date'=>trim($sheetData[$i]['S']),
                                          'log_value'=>trim($sheetData[$i]['T']),
                                          'absolute_value'=>trim($sheetData[$i]['U']),
                                          'text_value'=>trim($sheetData[$i]['V']),
                                          'absolute_decimal_value'=>trim($sheetData[$i]['W']),
                                          'result'=>trim($sheetData[$i]['X']),
                                          );
                            //check existing sample code
                            $sampleCode = $this->checkSampleCode($sampleCode);
                            if(count($sampleCode)>0){
                                $sampleDb->updateSampleData();
                            }
                            $facilityData = $this->checkFacilityDetails($sampleCode);
                        }
                    }
                }
            }
    }
    public function checkSampleCode($sampleCode)
    {
        $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from('samples')->where(array('sample_code' => $sampleCode));
        $sQueryStr = $sql->getSqlStringForSqlObject($sQuery);
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        return $sResult;
    }
}