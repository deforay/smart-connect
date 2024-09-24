<?php

namespace Eid\Service;

use Exception;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Session\Container;
use Application\Service\CommonService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class EidSummaryService
{

    public $sm = null;
    protected $translator = null;

    public function __construct($sm)
    {
        $this->sm = $sm;
        $this->translator = $this->sm->get('translator');
    }


    public function fetchSummaryTabDetails($params)
    {
        $eidSampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $eidSampleDb->getSummaryTabDetails($params);
    }

    public function getKeySummaryIndicatorsDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchKeySummaryIndicatorsDetails($params);
    }

    public function getSamplesReceivedBarChartDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchSamplesReceivedBarChartDetails($params);
    }

    public function getAllSamplesReceivedByDistrict($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchAllSamplesReceivedByDistrict($parameters);
    }
    public function getAllSamplesReceivedByProvince($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchAllSamplesReceivedByProvince($parameters);
    }

    public function getAllSamplesReceivedByFacility($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchAllSamplesReceivedByFacility($parameters);
    }

    public function getPositiveRateBarChartDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchPositiveRateBarChartDetails($params);
    }

    public function getAllPositiveRateByDistrict($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchAllPositiveRateByDistrict($parameters);
    }

    public function getAllPositiveRateByProvince($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchAllPositiveRateByProvince($parameters);
    }

    public function getAllPositiveRateByFacility($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->fetchAllPositiveRateByFacility($parameters);
    }

    public function getSamplesRejectedBarChartDetails($params)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchSamplesRejectedBarChartDetails($params);
    }

    public function getAllSamplesRejectedByDistrict($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fetchAllSamplesRejectedByDistrict($parameters);
    }

    public function getAllSamplesRejectedByFacility($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fecthAllSamplesRejectedByFacility($parameters);
    }
    public function getAllSamplesRejectedByProvince($parameters)
    {
        $sampleDb = $this->sm->get('EidSampleTable');
        return $sampleDb->fecthAllSamplesRejectedByProvince($parameters);
    }

    public function getEidOutcomesDetails($params)
    {
        $eidSampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $eidSampleDb->fetchEidOutcomesDetails($params);
    }

    public function getEidOutcomesByAgeDetails($params)
    {
        $eidSampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $eidSampleDb->fetchEidOutcomesByAgeDetails($params);
    }

    public function getTATDetails($params)
    {
        $eidSampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $eidSampleDb->fetchTATDetails($params);
    }

    public function getEidOutcomesByProvinceDetails($params)
    {
        $eidSampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $eidSampleDb->fetchEidOutcomesByProvinceDetails($params);
    }

    public function exportIndicatorResultExcel($params)
    {
        $queryContainer = new Container('query');
        if (property_exists($queryContainer, 'indicatorSummaryQuery') && $queryContainer->indicatorSummaryQuery !== null) {
            try {
                $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                $sql = new Sql($dbAdapter);
                $sQueryStr = $sql->buildSqlString($queryContainer->indicatorSummaryQuery);
                $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                if (isset($sResult) && !empty($sResult)) {
                    $excel = new Spreadsheet();


                    $sheet = $excel->getActiveSheet();
                    $output = [];
                    $keySummaryIndicators = [];
                    $j = 1;

                    foreach ($sResult as $row) {
                        $keySummaryIndicators['sample'][$this->translator->translate('Samples Received')]['month'][$j] = (isset($row["total_samples_received"])) ? $row["total_samples_received"] : 0;
                        $keySummaryIndicators['sample'][$this->translator->translate('Samples Tested')]['month'][$j] = (isset($row["total_samples_tested"])) ? $row["total_samples_tested"] : 0;
                        $keySummaryIndicators['sample'][$this->translator->translate('Samples Rejected')]['month'][$j] = (isset($row["total_samples_rejected"])) ? $row["total_samples_rejected"] : 0;
                        $keySummaryIndicators['sample'][$this->translator->translate('Valid Tested')]['month'][$j]  = $valid = (isset($row["total_samples_tested"])) ? $row["total_samples_tested"] - $row["total_samples_rejected"] : 0;;
                        $keySummaryIndicators['sample'][$this->translator->translate('No. of Positive')]['month'][$j] = (isset($row["total_positive_samples"])) ? $row["total_positive_samples"] : 0;
                        $keySummaryIndicators['sample'][$this->translator->translate('Positive %') . ' (%)']['month'][$j] = ($valid > 0) ? round((($row["total_positive_samples"] / $valid) * 100), 2) . '' : '0';
                        $keySummaryIndicators['sample'][$this->translator->translate('Rejection %') . ' (%)']['month'][$j] = (isset($row["total_samples_rejected"]) && $row["total_samples_rejected"] > 0 && $row["total_samples_received"] > 0) ? round((($row["total_samples_rejected"] / ($row["total_samples_tested"] + $row["total_samples_rejected"])) * 100), 2) . '' : '0';
                        $keySummaryIndicators['month'][$j] = $row['monthyear'];
                        $j++;
                    }

                    $sheet->setCellValue('A1', html_entity_decode($this->translator->translate('Months'), ENT_QUOTES, 'UTF-8'));
                    foreach ($keySummaryIndicators['month'] as $key => $month) {
                        $colNo = $key + 1;
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($month));
                    }


                    foreach ($keySummaryIndicators['sample'] as $key => $indicators) {
                        $row = [];
                        $row[] = $key;
                        foreach ($indicators['month'] as $months) {
                            $row[] = $months;
                        }
                        $output[] = $row;
                    }


                    $currentRow = 2;
                    foreach ($output as $rowData) {
                        $colNo = 1;
                        foreach ($rowData as $field => $value) {
                            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $currentRow, html_entity_decode($value ?? "", ENT_QUOTES, 'UTF-8'));
                            $colNo++;
                        }
                        $currentRow++;
                    }

                    $writer = IOFactory::createWriter($excel, 'Xlsx');
                    $filename = 'EID-SUMMARY-KEY-INDICATORS-' . date('d-M-Y-H-i-s') . '.xlsx';
                    $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                    return $filename;
                } else {
                    return "";
                }
            } catch (Exception $exc) {
                error_log("SUMMARY-INDICATORS-RESULT-REPORT--" . $exc->getMessage());
                return "";
            }
        } else {
            return "";
        }
    }

    public function exportPositiveRateByFacility($params, $dashTable = 'dash_form_eid')
    {

        $queryContainer = new Container('query');
        $translator = $this->sm->get('translator');
        // To set te session table
        $loginContainer = new Container('credo');
        if (property_exists($loginContainer, 'EidSampleTable') && $loginContainer->EidSampleTable !== null && $loginContainer->EidSampleTable != "") {
            $dashTable = $loginContainer->EidSampleTable;
        }
        $common = new CommonService();

        if (!property_exists($queryContainer, 'fetchAllPositiveRateByFacility') || $queryContainer->fetchAllPositiveRateByFacility === null) {

            $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
            $sql = new Sql($dbAdapter);
            $queryContainer->fetchAllPositiveRateByFacility = $sql->select()->from(array('vl' => $dashTable))
                ->columns(
                    array(
                        'eid_id',
                        'facility_id',
                        'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                        'result',
                        "total_samples_received" => new Expression("(COUNT(*))"),
                        "total_samples_valid" => new Expression("(SUM(CASE WHEN (((vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL'))) THEN 1 ELSE 0 END))"),
                        "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                        "total_positive_samples" => new Expression("SUM(CASE WHEN ((vl.result like 'positive' OR vl.result like 'Positive' )) THEN 1 ELSE 0 END)"),
                        "total_negative_samples" => new Expression("SUM(CASE WHEN ((vl.result like 'negative' OR vl.result like 'Negative')) THEN 1 ELSE 0 END)"),
                        "positive_rate" => new Expression("ROUND(((SUM(CASE WHEN ((vl.result like 'positive' OR vl.result like 'Positive' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
                ->join(array('f_d_l_dp' => 'geographical_divisions'), 'f_d_l_dp.geo_id=f.facility_state_id', array('province' => 'geo_name'))
                ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
                ->group('vl.facility_id');
        }



        try {
            $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
            $sql = new Sql($dbAdapter);
            $sQueryStr = $sql->buildSqlString($queryContainer->fetchAllSuppressionRateByFacility);
            $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($sResult) && !empty($sResult)) {
                $excel = new Spreadsheet();

                $sheet = $excel->getActiveSheet();
                $output = [];
                foreach ($sResult as $aRow) {

                    $row = [];
                    $row[] = ucwords($aRow['facility_name']);
                    $row[] = ucwords($aRow['province']);
                    $row[] = ucwords($aRow['district']);
                    $row[] = $aRow['total_samples_valid'];
                    $row[] = $aRow['total_positive_samples'];
                    $row[] = $aRow['total_negative_samples'];
                    $row[] = ($aRow['total_samples_rejected'] > 0 && $aRow['total_samples_received'] > 0) ? round((($aRow['total_samples_rejected'] / $aRow['total_samples_received']) * 100), 2) : '';
                    $row[] = ($aRow['total_samples_valid'] > 0 && $aRow['total_positive_samples'] > 0) ? round((($aRow['total_positive_samples'] / $aRow['total_samples_valid']) * 100), 2) : '';
                    $output[] = $row;
                }


                $sheet->setCellValue('A1', html_entity_decode($this->translator->translate('Facility'), ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('B1', html_entity_decode($this->translator->translate('Province'), ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('C1', html_entity_decode($this->translator->translate('District/County'), ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('D1', html_entity_decode($this->translator->translate('Valid Results'), ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('E1', html_entity_decode($this->translator->translate('Positive Results'), ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('F1', html_entity_decode($this->translator->translate('Negative Results'), ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('G1', html_entity_decode($this->translator->translate('Samples Rejected in %'), ENT_QUOTES, 'UTF-8'));
                $sheet->setCellValue('H1', html_entity_decode($this->translator->translate('Positive Rate in %'), ENT_QUOTES, 'UTF-8'));



                $currentRow = 2;
                foreach ($output as $rowData) {
                    $colNo = 0;
                    foreach ($rowData as $field => $value) {
                        if (!isset($value)) {
                            $value = "";
                        }
                        // if ($colNo > 5) {
                        //     break;
                        // }
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $currentRow, html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                        $colNo++;
                    }
                    $currentRow++;
                }

                $writer = IOFactory::createWriter($excel, 'Xlsx');
                $filename = 'EID-Facility-Wise-Positive-Rate-' . date('d-M-Y-H-i-s') . '.xlsx';
                $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                return $filename;
            } else {
                return "";
            }
        } catch (Exception $exc) {
            error_log("EID-Facility-Wise-Positive-Rate-" . $exc->getMessage());
            return "";
        }
    }
}
