<?php

namespace Application\Service;

use Zend\Session\Container;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Sql\Expression;
use Application\Service\CommonService;
use PHPExcel;

class SummaryService {

    public $sm = null;

    public function __construct($sm) {
        $this->sm = $sm;
    }

    public function getServiceManager() {
        return $this->sm;
    }
        
    public function fetchSummaryTabDetails(){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->getSummaryTabDetails();
    }
    
    public function getKeySummaryIndicatorsDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchKeySummaryIndicatorsDetails($params);
    }
    
    public function getSamplesReceivedBarChartDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSamplesReceivedBarChartDetails($params);
    }
    
    public function getAllSamplesReceivedByDistrict($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSamplesReceivedByDistrict($parameters);
    }
    public function getAllSamplesReceivedByProvince($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSamplesReceivedByProvince($parameters);
    }
    
    public function getAllSamplesReceivedByFacility($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSamplesReceivedByFacility($parameters);
    }
    
    public function getSuppressionRateBarChartDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchSuppressionRateBarChartDetails($params);
    }
    
    public function getAllSuppressionRateByDistrict($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSuppressionRateByDistrict($parameters);
    }

    public function getAllSuppressionRateByProvince($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSuppressionRateByProvince($parameters);
    }
    
    public function getAllSuppressionRateByFacility($parameters){
        $sampleDb = $this->sm->get('SampleTable');
        return $sampleDb->fetchAllSuppressionRateByFacility($parameters);
    }
    
    public function getSamplesRejectedBarChartDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchSamplesRejectedBarChartDetails($params);
    }
    
    public function getAllSamplesRejectedByDistrict($parameters){
       $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchAllSamplesRejectedByDistrict($parameters); 
    }
    
    public function getAllSamplesRejectedByFacility($parameters){
       $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fecthAllSamplesRejectedByFacility($parameters); 
    }
    public function getAllSamplesRejectedByProvince($parameters){
        $sampleDb = $this->sm->get('SampleTable');
       return $sampleDb->fecthAllSamplesRejectedByProvince($parameters); 
     }
    
    public function getRegimenGroupBarChartDetails($params){
        $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchRegimenGroupBarChartDetails($params);
    }
    
    public function getRegimenGroupSamplesDetails($parameters){
        $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchRegimenGroupSamplesDetails($parameters);
    }
    
    public function getAllLineOfTreatmentDetails(){
        $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchAllLineOfTreatmentDetails();
    }
    
    public function getAllCollapsibleLineOfTreatmentDetails(){
        $sampleDb = $this->sm->get('SampleTable');
      return $sampleDb->fetchAllCollapsibleLineOfTreatmentDetails();
    }

    public function exportIndicatorResultExcel($params){
        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        $common = new CommonService();
        if(isset($queryContainer->indicatorSummaryQuery)){
            try{
                $dbAdapter = $this->sm->get('Zend\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->getSqlStringForSqlObject($queryContainer->indicatorSummaryQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if(isset($sResult) && count($sResult)>0){
                    $excel = new PHPExcel();
                    $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
                    $cacheSettings = array('memoryCacheSize' => '80MB');
                    \PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
                    $sheet = $excel->getActiveSheet();
                    $output = array();
                    $keySummaryIndicators = array();
                    $j = 0;
                    foreach($sResult as $row){
                        $keySummaryIndicators['sample']['Samples Received']['month'][$j] = (isset($row["total_samples_received"]))?$row["total_samples_received"]:0;
                        $keySummaryIndicators['sample']['Samples Tested']['month'][$j] = (isset($row["total_samples_tested"]))?$row["total_samples_tested"]:0;
                        $keySummaryIndicators['sample']['Samples Rejected']['month'][$j] = (isset($row["total_samples_rejected"]))? $row["total_samples_rejected"]:0;
                        $keySummaryIndicators['sample']['Valid Tested']['month'][$j]  = $valid = (isset($row["total_samples_tested"]))? $row["total_samples_tested"] - $row["total_samples_rejected"] :0;;
                        $keySummaryIndicators['sample']['Samples Suppressed']['month'][$j] = (isset($row["total_suppressed_samples"]))?$row["total_suppressed_samples"]:0;
                        $keySummaryIndicators['sample']['Suppression Rate']['month'][$j] = ($valid > 0) ? round((($row["total_suppressed_samples"]/$valid)*100),2).' %':'0';
                        $keySummaryIndicators['sample']['Rejection Rate']['month'][$j] = (isset($row["total_samples_rejected"]) && $row["total_samples_rejected"] >0 && $row["total_samples_received"] > 0)?round((($row["total_samples_rejected"]/($row["total_samples_tested"] + $row["total_samples_rejected"]))*100),2).' %':'0';
                        $keySummaryIndicators['month'][$j] = $row['monthyear'];                
                        $j++;
                    }
                    
                    $styleArray = array(
                        'font' => array(
                            'bold' => true,
                        ),
                        'alignment' => array(
                            'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PHPExcel_Style_Border::BORDER_THIN,
                            ),
                        )
                    );
                    $borderStyle = array(
                        'alignment' => array(
                            'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
                        ),
                        'borders' => array(
                            'outline' => array(
                                'style' => \PHPExcel_Style_Border::BORDER_THIN,
                            ),
                        )
                    );
                    $eRow=0;
                    $sheet->setCellValue('A1', html_entity_decode($translator->translate('Months'), ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                    foreach($keySummaryIndicators['month'] as $key=>$month){ 
                        $colNo=$key+1;
                        $currentRow=1;
                        $sheet->getCellByColumnAndRow($colNo,$currentRow)->setValueExplicit(html_entity_decode($month, ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                        $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                        $sheet->getStyle($cellName . $currentRow)->applyFromArray($styleArray);
                    }

                    
                    foreach ($keySummaryIndicators['sample'] as $key=>$indicators) {
                        $row = array();
                        $row[]=$translator->translate($key);
                        foreach($indicators['month'] as $months){
                            $row[]=$months;
                        }
                        $output[] = $row;
                    }
                    
                  
                    $currentRow = 2;
                    foreach ($output as $rowData) {
                        $colNo = 0;
                        foreach ($rowData as $field => $value) {
                            if (!isset($value)) {
                                $value = "";
                            }
                            
                            if (is_numeric($value)) {
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_NUMERIC);
                            }else{
                                $sheet->getCellByColumnAndRow($colNo, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PHPExcel_Cell_DataType::TYPE_STRING);
                            }
                            $cellName = $sheet->getCellByColumnAndRow($colNo, $currentRow)->getColumn();
                            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle);
                            $sheet->getDefaultRowDimension()->setRowHeight(20);
                            $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
                            $sheet->getStyleByColumnAndRow($colNo, $currentRow)->getAlignment()->setWrapText(true);
                            $colNo++;
                        }
                      $currentRow++;
                    }
                    $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel5');
                    $filename = 'SUMMARY-INDICATORS-RESULT-REPORT--' . date('d-M-Y-H-i-s') . '.xls';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                }else{
                    return "";
                }
             }catch (Exception $exc) {
                error_log("SUMMARY-INDICATORS-RESULT-REPORT--" . $exc->getMessage());
                error_log($exc->getTraceAsString());
                return "";
             }  
        }else{
            return "";
        }
        
    }
}