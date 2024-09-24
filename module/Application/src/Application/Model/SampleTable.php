<?php

namespace Application\Model;

use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Application\Service\CommonService;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Predicate\Expression as WhereExpression;

class SampleTable extends AbstractTableGateway
{

    protected $table = 'dash_form_vl';
    protected $sm = null;
    protected array $config;
    protected $dbsId = null;
    protected $plasmaId = null;
    protected $mappedFacilities = null;
    protected $translator = null;
    protected $adapter;
    protected CommonService $commonService;

    public function __construct(Adapter $adapter, $sm = null, $mappedFacilities = null, $table = null, $commonService = null)
    {
        $this->adapter = $adapter;
        $this->sm = $sm;
        if ($table != null && !empty($table)) {
            $this->table = $table;
        }
        $this->config = $this->sm->get('Config');
        $this->translator = $this->sm->get('translator');
        $this->dbsId = $this->config['defaults']['dbsId'];
        $this->plasmaId = $this->config['defaults']['plasmaId'];
        $this->mappedFacilities = $mappedFacilities;
        $this->commonService = $commonService;
    }

    public function fetchQuickStats($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        //        $query = "SELECT count(*) as 'Total',
        //		SUM(CASE
        //            WHEN patient_gender IS NULL OR patient_gender ='' THEN 0
        //            ELSE 1
        //            END) as GenderMissing,
        //		SUM(CASE
        //            WHEN patient_age_in_years IS NULL OR patient_age_in_years ='' THEN 0
        //            ELSE 1
        //            END) as AgeMissing,
        //        SUM(CASE
        //            WHEN (result is NULL OR result ='') AND (sample_collection_date > DATE_SUB(NOW(), INTERVAL 6 MONTH) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='')) THEN 1
        //            ELSE 0
        //            END) as ResultWaiting
        //           FROM " . $this->table . "  as vl";


        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');

        $query = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    $this->translator->translate("Total Samples") => new Expression('COUNT(*)'),
                    $this->translator->translate("Samples Tested") => new Expression("SUM(CASE
                                                                                WHEN (((vl.vl_result_category is NOT NULL AND vl.vl_result_category !='') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))) THEN 1
                                                                                ELSE 0
                                                                                END)"),
                    $this->translator->translate("Gender Missing") => new Expression("SUM(CASE
                                                                                    WHEN ((patient_gender IS NULL OR patient_gender ='' OR patient_gender ='unreported' OR patient_gender ='Unreported')) THEN 1
                                                                                    ELSE 0
                                                                                    END)"),
                    $this->translator->translate("Age Missing") => new Expression("SUM(CASE
                                                                                WHEN ((patient_age_in_years IS NULL OR patient_age_in_years ='' OR patient_age_in_years ='Unreported'  OR patient_age_in_years ='unreported')) THEN 1
                                                                                ELSE 0
                                                                                END)"),
                    $this->translator->translate("Results Not Available (< 6 months)") => new Expression("SUM(CASE
                                                                                                                                WHEN ((vl.vl_result_category is NULL OR vl.vl_result_category ='') AND (sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH)) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or reason_for_sample_rejection = 0)) THEN 1
                                                                                                                                ELSE 0
                                                                                                                                END)"),
                    $this->translator->translate("Results Not Available (> 6 months)") => new Expression("SUM(CASE
                                                                                                                                WHEN ((vl.vl_result_category is NULL OR vl.vl_result_category ='') AND (sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH)) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or reason_for_sample_rejection = 0)) THEN 1
                                                                                                                                ELSE 0
                                                                                                                                END)")
                )
            );
        //$query = $query->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')");
        if ($loginContainer->role != 1) {
            $query = $query->where('vl.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        $queryStr = $sql->buildSqlString($query);
        //echo $queryStr;die;
        //$result = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $result = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        return $result[0];
    }

    //start lab dashboard details
    public function fetchSampleResultDetails($params)
    {
        $loginContainer = new Container('credo');
        $quickStats = $this->fetchQuickStats($params);
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        $waitingTotal = 0;
        $receivedTotal = 0;
        $testedTotal = 0;
        $rejectedTotal = 0;
        $waitingResult = [];
        $receivedResult = [];
        $tResult = [];
        $rejectedResult = [];
        if (trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        } else {
            $timestamp = time();
            $qDates = [];
            for ($i = 0; $i < 28; $i++) {
                $qDates[] = "'" . date('Y-m-d', $timestamp) . "'";
                $timestamp -= 24 * 3600;
            }
            $qDates = implode(",", $qDates);
        }

        //get received data
        $receivedQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('total' => new Expression('COUNT(*)'), 'receivedDate' => new Expression('DATE(sample_collection_date)')))
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
            ->group(array("receivedDate"));
        if ($loginContainer->role != 1) {
            $receivedQuery = $receivedQuery->where('vl.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        if (trim($params['daterange']) != '') {
            if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                $receivedQuery = $receivedQuery->where(array("DATE(vl.sample_collection_date) <='$splitDate[1]'", "DATE(vl.sample_collection_date) >='$splitDate[0]'"));
            }
        } else {
            $receivedQuery = $receivedQuery->where("DATE(sample_collection_date) IN ($qDates)");
        }
        $cQueryStr = $sql->buildSqlString($receivedQuery);
        //echo $cQueryStr;die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $this->commonService->cacheQuery($cQueryStr, $dbAdapter);

        //var_dump($receivedResult);die;
        $recTotal = 0;
        foreach ($rResult as $rRow) {
            $displayDate = \Application\Service\CommonService::humanReadableDateFormat($rRow['receivedDate']);
            $receivedResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'receivedDate' => $displayDate, 'receivedTotal' => $recTotal += $rRow['total']);
        }

        //tested data
        $testedQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('total' => new Expression('COUNT(*)'), 'testedDate' => new Expression('DATE(sample_tested_datetime)')))
            ->where("((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))")
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
            ->group(array("testedDate"));
        if ($loginContainer->role != 1) {
            $testedQuery = $testedQuery->where('vl.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        if (trim($params['daterange']) != '') {
            if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                $testedQuery = $testedQuery->where(array("DATE(vl.sample_tested_datetime) <='$splitDate[1]'", "DATE(vl.sample_tested_datetime) >='$splitDate[0]'"));
            }
        } else {
            $testedQuery = $testedQuery->where("DATE(sample_tested_datetime) IN ($qDates)");
        }
        $cQueryStr = $sql->buildSqlString($testedQuery);
        //echo $cQueryStr;//die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $this->commonService->cacheQuery($cQueryStr, $dbAdapter);

        //var_dump($receivedResult);die;
        $testedTotal = 0;
        foreach ($rResult as $rRow) {
            $displayDate = \Application\Service\CommonService::humanReadableDateFormat($rRow['testedDate']);
            $tResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'testedDate' => $displayDate, 'testedTotal' => $testedTotal += $rRow['total']);
        }

        //get rejected data
        $rejectedQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('total' => new Expression('COUNT(*)'), 'rejectDate' => new Expression('DATE(sample_collection_date)')))
            ->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection!= 0")
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
            ->group(array("rejectDate"));
        if ($loginContainer->role != 1) {
            $rejectedQuery = $rejectedQuery->where('vl.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
        }
        if (trim($params['daterange']) != '') {
            if (trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
                $rejectedQuery = $rejectedQuery->where(array("DATE(vl.sample_collection_date) <='$splitDate[1]'", "DATE(vl.sample_collection_date) >='$splitDate[0]'"));
            }
        } else {
            $rejectedQuery = $rejectedQuery->where("DATE(sample_collection_date) IN ($qDates)");
        }
        $cQueryStr = $sql->buildSqlString($rejectedQuery);
        //echo $cQueryStr;die;
        //$rResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $rResult = $this->commonService->cacheQuery($cQueryStr, $dbAdapter);
        $rejTotal = 0;
        foreach ($rResult as $rRow) {
            $displayDate = CommonService::humanReadableDateFormat($rRow['rejectDate']);
            $rejectedResult[] = array(array('total' => $rRow['total']), 'date' => $displayDate, 'rejectDate' => $displayDate, 'rejectTotal' => $rejTotal += $rRow['total']);
        }
        return array('quickStats' => $quickStats, 'scResult' => $receivedResult, 'stResult' => $tResult, 'srResult' => $rejectedResult);
    }

    //get sample tested result details
    public function fetchSamplesTested($params)
    {
        $loginContainer = new Container('credo');
        $result = [];
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));


            $facilityIdList = null;

            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_type = 2 AND f.status="active"');
                $fQuery = $fQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            } elseif (!empty($this->mappedFacilities)) {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    //->where('f.facility_type = 2 AND f.status="active"')
                    ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            }


            $specimenTypes = null;
            if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                $rsQuery = $sql->select()->from(array('rs' => 'r_vl_sample_type'))->columns(array('sample_id'));
                $rsQuery = $rsQuery->where('rs.sample_id="' . base64_decode(trim($params['sampleType'])) . '"');
                $rsQueryStr = $sql->buildSqlString($rsQuery);
                //$sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $specimenTypesResult = $this->commonService->cacheQuery($rsQueryStr, $dbAdapter);
                $specimenTypes = array_column($specimenTypesResult, 'sample_id');
            }

            $queryStr = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "total" => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                        "GreaterThan1000" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%')) THEN 1 ELSE 0 END)"),
                        "LesserThan1000" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                        "total_samples_valid" => new Expression("(SUM(CASE WHEN (((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL'))) THEN 1 ELSE 0 END))"),
                        //"total_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)")
                        //"TND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)"),
                        //new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)")
                    )
                );

            if ($specimenTypes != null) {
                $queryStr = $queryStr->where('vl.specimen_type IN ("' . implode('", "', $specimenTypes) . '")');
            }
            if ($facilityIdList != null) {
                $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date not like '')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");

            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));
            $queryStr = $queryStr->order('sample_collection_date ASC');
            $queryStr = $sql->buildSqlString($queryStr);
            //error_log($queryStr);
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["monthDate"] === null) {
                    continue;
                }
                $result['sampleName']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["GreaterThan1000"])) ? $sRow["GreaterThan1000"] : 0;
                //$result['sampleName']['VL Not Detected'][$j] = $sRow["TND"];
                $result['sampleName']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["LesserThan1000"])) ? $sRow["LesserThan1000"] : 0;
                //$result['sampleName']['suppression'][$j]  = (($sRow["total_samples_valid"]) > 0) ? round(100 *($sRow["total_samples_valid"]/$sRow['LesserThan1000'])) : 0;
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }
    public function fetchSampleTestReasonBarChartDetails($params)
    {
        $loginContainer = new Container('credo');
        $result = [];
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $rsQuery = $sql->select()->from(array('tr' => 'r_vl_test_reasons'));
            if (isset($params['testedReason']) && trim($params['testedReason']) != '') {
                $rsQuery = $rsQuery->where('tr.test_reason_id="' . base64_decode(trim($params['testedReason'])) . '"');
            }
            $rsQueryStr = $sql->buildSqlString($rsQuery);
            $sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            //$sampleTypeResult = $this->commonService->cacheQuery($rsQueryStr,$dbAdapter);

            $sampleTestReasonId = array_column($sampleTypeResult, 'test_reason_id');
            $sampleTestedReason = implode(',', $sampleTestReasonId);

            $queryStr = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "total" => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                        "GreaterThan1000" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "LesserThan1000" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                        //"TND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)"),
                        //new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)")
                    )
                );
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $queryStr = $queryStr->where('vl.lab_id IN (' . $params['facilityId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $queryStr = $queryStr->where('vl.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
            }
            $queryStr = $queryStr->where("
                        (sample_collection_date is not null AND sample_collection_date not like '')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(sample_collection_date) <= '" . $endMonth . "'
                        AND vl.reason_for_vl_testing IN ($sampleTestedReason)");

            $queryStr = $queryStr->group(array(new Expression('MONTH(sample_collection_date)')));
            $queryStr = $queryStr->order('sample_collection_date ASC');
            $queryStr = $sql->buildSqlString($queryStr);
            //error_log($queryStr);
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["monthDate"] == null) {
                    continue;
                }
                $result['sampleTestedReason']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["GreaterThan1000"])) ? $sRow["GreaterThan1000"] : 0;
                $result['sampleTestedReason']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["LesserThan1000"])) ? $sRow["LesserThan1000"] : 0;
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    public function getSamplesTestedPerLab($params)
    {
        $loginContainer = new Container('credo');
        $result = [];
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));

            $facilityIdList = null;

            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    ->where('f.facility_type = 2 AND f.status="active"');
                $fQuery = $fQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            } elseif (!empty($this->mappedFacilities)) {
                $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                    //->where('f.facility_type = 2 AND f.status="active"')
                    ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
                $fQueryStr = $sql->buildSqlString($fQuery);
                $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $facilityIdList = array_column($facilityResult, 'facility_id');
            }


            $specimenTypes = null;
            if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                $rsQuery = $sql->select()->from(array('rs' => 'r_vl_sample_type'))->columns(array('sample_id'));
                $rsQuery = $rsQuery->where('rs.sample_id="' . base64_decode(trim($params['sampleType'])) . '"');
                $rsQueryStr = $sql->buildSqlString($rsQuery);
                //$sampleTypeResult = $dbAdapter->query($rsQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $specimenTypesResult = $this->commonService->cacheQuery($rsQueryStr, $dbAdapter);
                $specimenTypes = array_column($specimenTypesResult, 'sample_id');
            }



            $query = $sql->select()->from(array('vl' => $this->table))

                ->columns(
                    array(
                        "total" => new Expression("SUM(CASE WHEN (
                                                        (vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)
                                                        OR
                                                        (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%')
                                                        ) THEN 1 ELSE 0 END)"),
                        "GreaterThan1000" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "LesserThan1000" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array('facility_name'))
                ->where(array("vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'", "vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'"))

                ->order('total DESC')
                ->group('vl.lab_id');

            if ($specimenTypes != null) {
                $query = $query->where('vl.specimen_type IN ("' . implode('", "', $specimenTypes) . '")');
            }
            if ($facilityIdList != null) {
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }

            $queryStr = $sql->buildSqlString($query);
            // echo $queryStr;die;
            $testResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $j = 0;
            foreach ($testResult as $data) {
                $result['sampleName']['VL (>= 1000 cp/ml)'][$j] = $data['GreaterThan1000'];
                $result['sampleName']['VL (< 1000 cp/ml)'][$j] = $data['LesserThan1000'];
                $result['lab'][$j] = $data['facility_name'];
                $j++;
            }
        }
        return $result;
    }

    //get sample tested result details
    public function fetchSampleTestedResultGenderDetails($params)
    {
        $loginContainer = new Container('credo');
        $result = [];
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $j = 0;
            $lessTotal = 0;
            $greaterTotal = 0;
            $notTargetTotal = 0;
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "total" => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),

                        "MGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender != '' and vl.patient_gender !='unreported' and vl.patient_gender in('m','Male','M','MALE') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "MLesserThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender != '' and vl.patient_gender !='unreported' and vl.patient_gender in('m','Male','M','MALE') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                        //"MTND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' and vl.patient_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),

                        "FGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender != '' and vl.patient_gender !='unreported' and vl.patient_gender in('f','Female','F','FEMALE') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "FLesserThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender != '' and vl.patient_gender !='unreported' and vl.patient_gender in('f','Female','F','FEMALE') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                        //"FTND" => new Expression("SUM(CASE WHEN vl.result='Target Not Detected' and vl.patient_gender in('f','Female','F','FEMALE') THEN 1 ELSE 0 END)"),

                        "OGreaterThan1000" => new Expression("SUM(CASE WHEN ((vl.patient_gender IS NULL or vl.patient_gender = '' or vl.patient_gender = 'Not Recorded' or vl.patient_gender = 'not recorded') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "OLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.patient_gender IS NULL or vl.patient_gender ='NULL' or vl.patient_gender = '' or vl.patient_gender = 'Not Recorded' or vl.patient_gender = 'not recorded' or vl.patient_gender = 'Unreported' or vl.patient_gender = 'unreported') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                        //"OTND" => new Expression("SUM(CASE WHEN (vl.result='Target Not Detected' and vl.patient_gender NOT in('m','Male','M','MALE','f','Female','F','FEMALE')) THEN 1 ELSE 0 END)")
                    )
                );
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $query = $query->where('vl.lab_id IN (' . $params['facilityId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
            }
            $query = $query->where("
                        (vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(sample_collection_date) <= '" . $endMonth . "' ");

            $query = $query->group(array(new Expression('MONTH(sample_collection_date)')));
            $query = $query->order('sample_collection_date ASC');
            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["monthDate"] == null) {
                    continue;
                }
                $result['M']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["MGreaterThan1000"])) ? $sRow["MGreaterThan1000"] : 0;
                //$result['M']['VL Not Detected'][$j] = $sRow["MTND"];
                $result['M']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["MLesserThan1000"])) ? $sRow["MLesserThan1000"] : 0;

                $result['F']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["FGreaterThan1000"])) ? $sRow["FGreaterThan1000"] : 0;
                //$result['F']['VL Not Detected'][$j] = $sRow["FTND"];
                $result['F']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["FLesserThan1000"])) ? $sRow["FLesserThan1000"] : 0;

                $result['Not Specified']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["OGreaterThan1000"])) ? $sRow["OGreaterThan1000"] : 0;
                //$result['Not Specified']['VL Not Detected'][$j] = $sRow["OTND"];
                $result['Not Specified']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["OLesserThan1000"])) ? $sRow["OLesserThan1000"] : 0;

                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    // TAT FUNCTION
    public function fetchLabTurnAroundTime($params)
    {

        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];
        $skipDays = isset($this->config['defaults']['tat-skipdays']) ? $this->config['defaults']['tat-skipdays'] : 365;


        $facilityIdList = null;

        // FILTER :: Checking if the facility filter is set
        // else if the user is mapped to one or more facilities

        if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                ->where('f.facility_type = 2 AND f.status="active"');
            $fQuery = $fQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $facilityIdList = array_column($facilityResult, 'facility_id');
        } elseif (!empty($this->mappedFacilities)) {
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))->columns(array('facility_id'))
                //->where('f.facility_type = 2 AND f.status="active"')
                ->where('f.facility_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $facilityIdList = array_column($facilityResult, 'facility_id');
        }

        // FILTER :: Checking if the date range filter is set (which should be always set)

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $monthyear = date("Y-m-d");
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            if (strtotime($startMonth) >= strtotime($monthyear)) {
                $startMonth = $endMonth = date("Y-m-01", strtotime("-2 months"));
            } elseif (strtotime($endMonth) >= strtotime($monthyear)) {
                //$endMonth = date("Y-m-t", strtotime("-2 months"));
            }


            // $startMonth = date("Y-m", strtotime(trim($startMonth))) . "-01";
            // $endMonth = date("Y-m", strtotime(trim($endMonth))) . "-31";

            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    [
                        // "total_samples_collected" => new Expression('COUNT(*)'),
                        // "month" => new Expression("MONTH(result_approved_datetime)"),
                        // "year" => new Expression("YEAR(result_approved_datetime)"),
                        // "AvgDiff" => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                        // "monthDate" => new Expression("DATE_FORMAT(DATE(result_approved_datetime), '%b-%Y')"),
                        // "total_samples_pending" => new Expression("(SUM(CASE WHEN ((vl.vl_result_category IS NULL OR vl.vl_result_category = '' OR vl.vl_result_category = 'NULL') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),

                        "totalSamples" => new Expression('COUNT(vl_sample_id)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(vl.sample_tested_datetime), '%b-%Y')"),
                        //"daydiff" => new Expression('AVG(ABS(TIMESTAMPDIFF(DAY,sample_tested_datetime,sample_collection_date)))'),
                        "AvgTestedDiff" => new Expression('CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_tested_datetime,vl.sample_collection_date))) AS DECIMAL (10,2))'),
                        "AvgReceivedDiff" => new Expression('CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_received_at_lab_datetime,vl.sample_collection_date))) AS DECIMAL (10,2))'),
                        "AvgReceivedTested" => new Expression('CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_tested_datetime,vl.sample_received_at_lab_datetime))) AS DECIMAL (10,2))'),
                        "AvgReceivedPrinted" => new Expression('CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.result_printed_datetime,vl.sample_collection_date))) AS DECIMAL (10,2))')
                    ]
                );
            // $query = $query->where(
            //     "(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) not like '1970-01-01' AND DATE(vl.sample_collection_date) not like '0000-00-00')
            //     AND (vl.sample_tested_datetime is not null AND vl.sample_tested_datetime not like '' AND DATE(vl.sample_tested_datetime) not like '1970-01-01' AND DATE(vl.sample_tested_datetime) not like '0000-00-00')"
            // );
            $query = $query->where("DATE(vl.sample_tested_datetime) BETWEEN '$startMonth' AND '$endMonth'");
            $skipDays = (isset($skipDays) && $skipDays > 0) ? $skipDays : 365;
            $query = $query->where("
                                (DATEDIFF(sample_tested_datetime,sample_collection_date) < '$skipDays' AND
                                DATEDIFF(sample_tested_datetime,sample_collection_date) >= 0)");
            // $query = $query->where('
            //         (DATEDIFF(result_printed_datetime,sample_collection_date) < ' . $skipDays . ' AND
            //         DATEDIFF(result_printed_datetime,sample_collection_date) >= 0)');

            if ($facilityIdList != null) {
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $facilityIdList) . '")');
            }
            $query = $query->group('monthDate');
            // $query = $query->group(array(new Expression('YEAR(vl.result_approved_datetime)')));
            // $query = $query->group(array(new Expression('MONTH(vl.result_approved_datetime)')));
            // $query = $query->order(array(new Expression('DATE(vl.result_approved_datetime) ASC')));
            $query = $query->order('sample_tested_datetime ASC');
            $queryStr = $sql->buildSqlString($query);
            //error_log($queryStr);
            // echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;

            foreach ($sampleResult as $sRow) {
                /* $result['all'][$j] = (isset($sRow["AvgDiff"]) && $sRow["AvgDiff"] != NULL && $sRow["AvgDiff"] > 0) ? round($sRow["AvgDiff"], 2) : null;
                //$result['lab'][$j] = (isset($labsubQueryResult[0]["labCount"]) && $labsubQueryResult[0]["labCount"] != NULL && $labsubQueryResult[0]["labCount"] > 0) ? round($labsubQueryResult[0]["labCount"],2) : 0;
                $result['sample']['Samples Collected'][$j] = (isset($sRow['total_samples_collected']) && $sRow['total_samples_collected'] != NULL) ? $sRow['total_samples_collected'] : null;
                $result['sample']['Results Not Available'][$j] = (isset($sRow['total_samples_pending']) && $sRow['total_samples_pending'] != NULL) ? $sRow['total_samples_pending'] : null;
                $result['date'][$j] = $sRow["monthDate"];
                $j++; */
                if ($sRow["monthDate"] == null) {
                    continue;
                }

                $result['totalSamples'][$j] = (isset($sRow["totalSamples"]) && $sRow["totalSamples"] > 0 && $sRow["totalSamples"] != null) ? $sRow["totalSamples"] : 'null';
                $result['sampleTestedDiff'][$j] = (isset($sRow["AvgTestedDiff"]) && $sRow["AvgTestedDiff"] > 0 && $sRow["AvgTestedDiff"] != null) ? round($sRow["AvgTestedDiff"], 2) : 'null';
                $result['sampleReceivedDiff'][$j] = (isset($sRow["AvgReceivedDiff"]) && $sRow["AvgReceivedDiff"] > 0 && $sRow["AvgReceivedDiff"] != null) ? round($sRow["AvgReceivedDiff"], 2) : 'null';
                $result['sampleReceivedTested'][$j] = (isset($sRow["AvgReceivedTested"]) && $sRow["AvgReceivedTested"] > 0 && $sRow["AvgReceivedTested"] != null) ? round($sRow["AvgReceivedTested"], 2) : 'null';
                $result['sampleCollectedPrinted'][$j] = (isset($sRow["AvgReceivedPrinted"]) && $sRow["AvgReceivedPrinted"] > 0 && $sRow["AvgReceivedPrinted"] != null) ? round($sRow["AvgReceivedPrinted"], 2) : 'null';
                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    public function fetchSampleTestedResultAgeGroupDetails($params)
    {
        $loginContainer = new Container('credo');
        $result = [];
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $j = 0;
            $lessTotal = 0;
            $greaterTotal = 0;
            $notTargetTotal = 0;
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),

                        "AgeLt2VLGt1000" => new Expression("SUM(CASE WHEN ((vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2) and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "AgeLt2VLLt1000" => new Expression("SUM(CASE WHEN ((vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2) and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                        "AgeGte2Lte5VLGt1000" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 2 and patient_age_in_years <= 5) and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "AgeGte2Lte5VLLt1000" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 2 and patient_age_in_years <= 5) and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                        "AgeGte6Lte14VLGt1000" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 6 and patient_age_in_years <= 14) and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "AgeGte6Lte14VLLt1000" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 6 and patient_age_in_years <= 14) and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                        "AgeGte15Lte49VLGt1000" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 15 and patient_age_in_years <= 49) and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "AgeGte15Lte49VLLt1000" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 15 and patient_age_in_years <= 49) and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                        "AgeGt50VLGt1000" => new Expression("SUM(CASE WHEN ((patient_age_in_years > 50) and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "AgeGt50VLLt1000" => new Expression("SUM(CASE WHEN ((patient_age_in_years > 50) and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                        "AgeUnknownVLGt1000" => new Expression("SUM(CASE WHEN ((vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "AgeUnknownVLLt1000" => new Expression("SUM(CASE WHEN ((vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                    )
                );
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $query = $query->where('vl.lab_id IN (' . $params['facilityId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
            }
            $query = $query->where("
                        (sample_collection_date is not null AND sample_collection_date not like '')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(sample_collection_date) <= '" . $endMonth . "' ");

            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $where = '';
                $counter = count($age);
                for ($a = 0; $a < $counter; $a++) {
                    if (trim($where) != '') {
                        $where .= ' OR ';
                    }
                    if ($age[$a] == '<2') {
                        $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                    } elseif ($age[$a] == '2to5') {
                        $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    } elseif ($age[$a] == '6to14') {
                        $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    } elseif ($age[$a] == '15to49') {
                        $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    } elseif ($age[$a] == '>=50') {
                        $where .= "(vl.patient_age_in_years >= 50)";
                    } elseif ($age[$a] == 'unknown') {
                        $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                    }
                }
                $where = '(' . $where . ')';
                $query = $query->where($where);
            }
            $query = $query->group(array(new Expression('MONTH(sample_collection_date)')));
            $query = $query->order('sample_collection_date ASC');
            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["monthDate"] == null) {
                    continue;
                }
                $result['Age < 2']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["AgeLt2VLGt1000"])) ? $sRow["AgeLt2VLGt1000"] : 0;
                $result['Age 2-5']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["AgeGte2Lte5VLGt1000"])) ? $sRow["AgeGte2Lte5VLGt1000"] : 0;
                $result['Age 6-14']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["AgeGte6Lte14VLGt1000"])) ? $sRow["AgeGte6Lte14VLGt1000"] : 0;
                $result['Age 15-49']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["AgeGte15Lte49VLGt1000"])) ? $sRow["AgeGte15Lte49VLGt1000"] : 0;
                $result['Age > 50']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["AgeGt50VLGt1000"])) ? $sRow["AgeGt50VLGt1000"] : 0;
                $result['Age Unknown']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["AgeUnknownVLGt1000"])) ? $sRow["AgeUnknownVLGt1000"] : 0;

                $result['Age < 2']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["AgeLt2VLLt1000"])) ? $sRow["AgeLt2VLLt1000"] : 0;
                $result['Age 2-5']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["AgeGte2Lte5VLLt1000"])) ? $sRow["AgeGte2Lte5VLLt1000"] : 0;
                $result['Age 6-14']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["AgeGte6Lte14VLLt1000"])) ? $sRow["AgeGte6Lte14VLLt1000"] : 0;
                $result['Age 15-49']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["AgeGte15Lte49VLLt1000"])) ? $sRow["AgeGte15Lte49VLLt1000"] : 0;
                $result['Age > 50']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["AgeGt50VLLt1000"])) ? $sRow["AgeGt50VLLt1000"] : 0;
                $result['Age Unknown']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["AgeUnknownVLLt1000"])) ? $sRow["AgeUnknownVLLt1000"] : 0;

                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    public function fetchSampleTestedResultPregnantPatientDetails($params)
    {
        $loginContainer = new Container('credo');
        $result = [];
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $j = 0;
            $lessTotal = 0;
            $greaterTotal = 0;
            $notTargetTotal = 0;
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                        "greaterThan1000" => new Expression("SUM(CASE WHEN (vl.is_patient_pregnant in('yes','Yes','YES') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "lesserThan1000" => new Expression("SUM(CASE WHEN (vl.is_patient_pregnant in('yes','Yes','YES') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                    )
                );
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $query = $query->where('vl.lab_id IN (' . $params['facilityId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
            }
            $query = $query->where("
                        DATE(sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(sample_collection_date) <= '" . $endMonth . "' ");

            $query = $query->group(array(new Expression('MONTH(sample_collection_date)')));
            $query = $query->order('sample_collection_date ASC');
            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["monthDate"] == null) {
                    continue;
                }
                $result['sampleName']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["greaterThan1000"])) ? $sRow["greaterThan1000"] : null;
                $result['sampleName']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["lesserThan1000"])) ? $sRow["lesserThan1000"] : null;

                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    public function fetchSampleTestedResultBreastfeedingPatientDetails($params)
    {
        $loginContainer = new Container('credo');
        $result = [];
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $j = 0;
            $lessTotal = 0;
            $greaterTotal = 0;
            $notTargetTotal = 0;
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                        "greaterThan1000" => new Expression("SUM(CASE WHEN (vl.is_patient_breastfeeding in('yes','Yes','YES') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "lesserThan1000" => new Expression("SUM(CASE WHEN (vl.is_patient_breastfeeding in('yes','Yes','YES') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                    )
                );
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $query = $query->where('vl.lab_id IN (' . $params['facilityId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
            }
            $query = $query->where("
                        DATE(sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(sample_collection_date) <= '" . $endMonth . "' ");

            $query = $query->group(array(new Expression('MONTH(sample_collection_date)')));
            $query = $query->order('sample_collection_date ASC');
            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["monthDate"] == null) {
                    continue;
                }
                $result['sampleName']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["greaterThan1000"])) ? $sRow["greaterThan1000"] : null;
                $result['sampleName']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["lesserThan1000"])) ? $sRow["lesserThan1000"] : null;

                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    public function getRequisitionFormsTested($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $incompleteQuery = "(vl.patient_art_no IS NULL OR vl.patient_art_no='' OR vl.patient_age_in_years IS NULL OR vl.patient_age_in_years ='' OR vl.patient_gender IS NULL OR vl.patient_gender='' OR vl.current_regimen IS NOT NULL OR vl.current_regimen !='')";
            $completeQuery = "vl.patient_art_no IS NOT NULL AND vl.patient_art_no !='' AND vl.patient_age_in_years IS NOT NULL AND vl.patient_age_in_years !='' AND vl.patient_gender IS NOT NULL AND vl.patient_gender !='' AND vl.current_regimen IS NOT NULL AND vl.current_regimen !=''";
            if (isset($params['formFields']) && trim($params['formFields']) != '') {
                $formFields = explode(',', $params['formFields']);
                $incompleteQuery = '';
                $completeQuery = '';
                $counter = count($formFields);
                for ($f = 0; $f < $counter; $f++) {
                    if (trim($formFields[$f]) != '') {
                        $incompleteQuery .= 'vl.' . $formFields[$f] . ' IS NULL OR vl.' . $formFields[$f] . '=""';
                        $completeQuery .= 'vl.' . $formFields[$f] . ' IS NOT NULL AND vl.' . $formFields[$f] . '!=""';
                        if ((count($formFields) - $f) > 1) {
                            $incompleteQuery .= ' OR ';
                            $completeQuery .= ' AND ';
                        }
                    }
                }
            }
            $i = 0;
            $completeResultCount = 0;
            $inCompleteResultCount = 0;
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "total" => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),

                        "CompletedForms" => new Expression("SUM(CASE WHEN ($completeQuery) THEN 1 ELSE 0 END)"),
                        "IncompleteForms" => new Expression("SUM(CASE WHEN ($incompleteQuery) THEN 1 ELSE 0 END)"),

                    )
                );
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $query = $query->where('vl.lab_id IN (' . $params['facilityId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $this->mappedFacilities) . '")');
            }
            $query = $query->where("
                        (sample_collection_date is not null AND sample_collection_date not like '')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(sample_collection_date) <= '" . $endMonth . "' ");

            $query = $query->group(array(new Expression('MONTH(sample_collection_date)')));
            $query = $query->order('sample_collection_date ASC');
            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            if (isset($sampleResult) && count($sampleResult) > 0) {
                foreach ($sampleResult as $sRow) {
                    if ($sRow["monthDate"] == null) {
                        continue;
                    }
                    $result['Complete'][$j] = (isset($sRow["CompletedForms"])) ? (int) $sRow["CompletedForms"] : null;
                    $result['Incomplete'][$j] = (isset($sRow["IncompleteForms"])) ? (int) $sRow["IncompleteForms"] : null;
                    $completionRate = 100 * ($result['Complete'][$j] / ($result['Complete'][$j] + $result['Incomplete'][$j]));
                    $result['CompletionRate'][$j] = ($completionRate > 0) ? round($completionRate, 2) : 0;
                    $result['date'][$j] = $sRow["monthDate"];
                    $j++;
                }
            }
        }
        return $result;
    }

    public function getSampleVolume($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))
                ->where('f.facility_type = 2 AND f.status="active"');
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $fQuery = $fQuery->where('f.facility_id IN (' . $params['facilityId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($facilityResult) && count($facilityResult) > 0) {
                $i = 0;
                foreach ($facilityResult as $facility) {
                    $countQuery = $sql->select()->from(array('vl' => $this->table))->columns(array('total' => new Expression('COUNT(*)')))
                        ->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"))
                        ->where('vl.lab_id="' . $facility['facility_id'] . '"');
                    if (isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested') {
                        $countQuery = $countQuery->where("((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))");
                    } elseif (isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                        $countQuery = $countQuery->where("(vl.vl_result_category IS NULL OR vl.vl_result_category = '' OR vl.vl_result_category = 'NULL') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)");
                    } elseif (isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                        $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
                    }
                    $cQueryStr = $sql->buildSqlString($countQuery);
                    $countResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result[$i][0] = $countResult['total'] ?? 0;
                    $result[$i][1] = ucwords($facility['facility_name']);
                    $result[$i][2] = $facility['facility_code'];
                    $i++;
                }
            }
        }
        return $result;
    }

    //get female result
    public function getFemalePatientResult($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $femaleTestResult = [];

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        //"total" => new Expression("SUM(CASE WHEN (patient_gender != '' AND patient_gender IS NOT NULL AND (patient_gender ='f' OR patient_gender ='female' OR patient_gender='F' OR patient_gender='FEMALE')) THEN 1 ELSE 0 END)"),
                        "Breastfeeding" => new Expression("SUM(CASE WHEN ((is_patient_breastfeeding ='yes' OR is_patient_breastfeeding ='Yes' OR is_patient_breastfeeding ='YES')) THEN 1 ELSE 0 END)"),
                        "Not_Breastfeeding" => new Expression("SUM(CASE WHEN ((is_patient_breastfeeding ='no' OR is_patient_breastfeeding ='No' OR  is_patient_breastfeeding ='NO')) THEN 1 ELSE 0 END)"),
                        "Breastfeeding_Unknown" => new Expression("SUM(CASE WHEN (is_patient_breastfeeding !='no' AND is_patient_breastfeeding !='No' AND  is_patient_breastfeeding !='NO' AND is_patient_breastfeeding !='yes' AND is_patient_breastfeeding !='Yes' AND  is_patient_breastfeeding !='YES') THEN 1 ELSE 0 END)"),
                        "Pregnant" => new Expression("SUM(CASE WHEN ((is_patient_pregnant ='yes' OR is_patient_pregnant ='Yes' OR  is_patient_pregnant ='YES')) THEN 1 ELSE 0 END)"),
                        "Not_Pregnant" => new Expression("SUM(CASE WHEN ((is_patient_pregnant ='no' OR is_patient_pregnant ='No' OR  is_patient_pregnant ='NO')) THEN 1 ELSE 0 END)"),
                        "Pregnant_Unknown" => new Expression("SUM(CASE WHEN (is_patient_pregnant !='no' AND is_patient_pregnant !='No' AND  is_patient_pregnant !='NO' AND is_patient_pregnant !='yes' AND is_patient_pregnant !='Yes' AND  is_patient_pregnant !='YES') THEN 1 ELSE 0 END)"),
                    )
                );
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $query = $query->where('vl.lab_id IN (' . $params['facilityId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            $query = $query->where("
                        (sample_collection_date is not null AND sample_collection_date not like '')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(sample_collection_date) <= '" . $endMonth . "' AND (patient_gender='f' || patient_gender='F' || patient_gender='Female' || patient_gender='FEMALE')");

            $queryStr = $sql->buildSqlString($query);
            $femaleTestResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        }
        return $femaleTestResult;
    }

    //get Line of tratment result
    public function getLineOfTreatment($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $lineOfTreatmentResult = [];

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "Line_Of_Treatment_1" => new Expression("SUM(CASE WHEN (line_of_treatment = 1) THEN 1 ELSE 0 END)"),
                        "Line_Of_Treatment_2" => new Expression("SUM(CASE WHEN (line_of_treatment = 2) THEN 1 ELSE 0 END)"),
                        "Line_Of_Treatment_3" => new Expression("SUM(CASE WHEN (line_of_treatment = 3) THEN 1 ELSE 0 END)"),
                        "Not_Specified" => new Expression("SUM(CASE WHEN ((line_of_treatment IS NULL OR line_of_treatment= '' OR line_of_treatment = 0)) THEN 1 ELSE 0 END)"),
                    )
                );
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $query = $query->where('vl.lab_id IN (' . $params['facilityId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $query = $query->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            $query = $query->where("
                        (sample_collection_date is not null AND sample_collection_date not like '')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");

            $queryStr = $sql->buildSqlString($query);
            $lineOfTreatmentResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        }
        return $lineOfTreatmentResult;
    }

    public function fetchFacilites($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $lResult = [];

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $lQuery = $sql->select()->from(array('vl' => $this->table))->columns(array('lab_id', 'labCount' => new \Laminas\Db\Sql\Expression("COUNT(vl.lab_id)")))
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array('facility_name', 'latitude', 'longitude'))
                ->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"))
                ->group('vl.lab_id');
            if (isset($params['facilityId']) && trim($params['facilityId']) != '') {
                $lQuery = $lQuery->where('vl.lab_id IN (' . $params['facilityId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $lQuery = $lQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            $lQueryStr = $sql->buildSqlString($lQuery);
            $lResult = $dbAdapter->query($lQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($lResult) && count($lResult) > 0) {
                $i = 0;
                foreach ($lResult as $lab) {
                    if ($lab['lab_id'] != NULL && trim($lab['lab_id']) != '' && $lab['lab_id'] != 0) {
                        $lcQuery = $sql->select()->from(array('vl' => $this->table))
                            ->columns(array('facility_id', 'clinicCount' => new \Laminas\Db\Sql\Expression("COUNT(vl.facility_id)")))
                            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name', 'latitude', 'longitude'))
                            ->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"))
                            ->where(array("vl.lab_id" => $lab['lab_id'], 'f.facility_type' => '1'))
                            ->group('vl.facility_id');
                        $lcQueryStr = $sql->buildSqlString($lcQuery);
                        $lResult[$i]['clinic'] = $dbAdapter->query($lcQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                        $i++;
                    }
                }
            }
        }
        return $lResult;
    }

    public function fetchIncompleteSampleDetails($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        $i = 0;
        $j = 1;
        $k = 2;
        $l = 3;
        $result[$i]['field'] = 'Patient ART Number';
        $result[$j]['field'] = 'Current Regimen';
        $result[$k]['field'] = 'Patient Age in Years';
        $result[$l]['field'] = 'Patient Gender';
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
        }

        $inCompleteQuery = $sql->select()->from(array('vl' => $this->table))->columns(array('total' => new Expression('COUNT(*)')))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array(), 'left');
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $inCompleteQuery = $inCompleteQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $inCompleteQuery = $inCompleteQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $inCompleteQuery = $inCompleteQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
        }
        if (isset($params['lab']) && trim($params['lab']) != '') {
            $inCompleteQuery = $inCompleteQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $inCompleteQuery = $inCompleteQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        if (isset($params['gender']) && $params['gender'] == 'F') {
            $inCompleteQuery = $inCompleteQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        } elseif (isset($params['gender']) && $params['gender'] == 'M') {
            $inCompleteQuery = $inCompleteQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $inCompleteQuery = $inCompleteQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
        }
        if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
            $inCompleteQuery = $inCompleteQuery->where("vl.is_patient_pregnant = 'yes'");
        } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
            $inCompleteQuery = $inCompleteQuery->where("vl.is_patient_pregnant = 'no'");
        } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
            $inCompleteQuery = $inCompleteQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
        }
        if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
            $inCompleteQuery = $inCompleteQuery->where("vl.is_patient_breastfeeding = 'yes'");
        } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
            $inCompleteQuery = $inCompleteQuery->where("vl.is_patient_breastfeeding = 'no'");
        } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
            $inCompleteQuery = $inCompleteQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
        }
        $incQueryStr = $sql->buildSqlString($inCompleteQuery);
        //echo $incQueryStr;die;
        $artInCompleteResult = $dbAdapter->query($incQueryStr . " AND (vl.patient_art_no IS NULL OR vl.patient_art_no ='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $currentRegimenInCompleteResult = $dbAdapter->query($incQueryStr . " AND (vl.current_regimen IS NULL OR vl.current_regimen ='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $ageInYearsInCompleteResult = $dbAdapter->query($incQueryStr . " AND (vl.patient_age_in_years IS NULL OR vl.patient_age_in_years ='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $patientGenderInCompleteResult = $dbAdapter->query($incQueryStr . " AND (vl.patient_gender IS NULL OR vl.patient_gender ='')", $dbAdapter::QUERY_MODE_EXECUTE)->current();
        $result[$i]['total'] = $artInCompleteResult['total'] ?? 0;
        $result[$j]['total'] = $currentRegimenInCompleteResult['total'] ?? 0;
        $result[$k]['total'] = $ageInYearsInCompleteResult['total'] ?? 0;
        $result[$l]['total'] = $patientGenderInCompleteResult['total'] ?? 0;
        return $result;
    }

    public function fetchIncompleteBarSampleDetails($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
        }
        $fQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('vl' => $this->table), 'vl.lab_id=f.facility_id', array('lab_id', 'specimen_type', 'result'))
            ->where('vl.lab_id !=0')
            ->group('f.facility_id');
        if (isset($params['lab']) && trim($params['lab']) != '') {
            $fQuery = $fQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $fQuery = $fQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $fQueryStr = $sql->buildSqlString($fQuery);
        //echo $fQueryStr;die;
        $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if (isset($facilityResult) && count($facilityResult) > 0) {

            $facilityIdList = array_column($facilityResult, 'facility_id');

            $countQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        'total' => new Expression('COUNT(*)'),
                        "complete" => new Expression("SUM(CASE WHEN (( vl.patient_art_no IS NOT NULL AND vl.patient_art_no !='' AND vl.current_regimen IS NOT NULL AND vl.current_regimen !='' AND vl.patient_age_in_years IS NOT NULL AND vl.patient_age_in_years !=''  AND vl.patient_gender IS NOT NULL AND vl.patient_gender != '')) THEN 1 ELSE 0 END)"),
                        "incomplete" => new Expression("SUM(CASE WHEN (( (vl.patient_art_no IS NULL OR vl.patient_art_no='' OR vl.current_regimen IS NULL OR vl.current_regimen='' OR vl.patient_age_in_years IS NULL OR vl.patient_age_in_years ='' OR vl.patient_gender IS NULL OR vl.patient_gender=''))) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array('facility_name'))
                ->where('vl.lab_id IN ("' . implode('", "', $facilityIdList) . '")')
                ->group('vl.lab_id');
            if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
            }
            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $countQuery = $countQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $countQuery = $countQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
            }
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            } elseif (isset($params['gender']) && $params['gender'] == 'M') {
                $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
            } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $countQuery = $countQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
            }
            if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
                $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
            } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
                $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
            } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
                $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
            }
            if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
                $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
            } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
                $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'");
            } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
                $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
            }
            $cQueryStr = $sql->buildSqlString($countQuery);
            $barResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $j = 0;
            foreach ($barResult as $data) {
                $result['form']['Complete'][$j] = $data['complete'];
                $result['form']['Incomplete'][$j] = $data['incomplete'];
                $result['lab'][$j] = ucwords($data['facility_name']);
                $j++;
            }
        }
        return $result;
    }

    //get vl out comes result
    public function getVlOutComes($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $vlOutComeResult = [];

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $sQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "Suppressed" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                        "Not_Suppressed" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array());
            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
            }
            if (isset($params['lab']) && trim($params['lab']) != '') {
                $sQuery = $sQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
            }
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            }
            if (isset($params['currentRegimen']) && trim($params['currentRegimen']) != '') {
                $sQuery = $sQuery->where('vl.current_regimen="' . base64_decode(trim($params['currentRegimen'])) . '"');
            }
            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='" . $params['adherence'] . "'"));
            }
            if (isset($params['age']) && is_array($params['age'])) {
                $params['age'] = implode(',', $params['age']);
            }
            if (isset($params['age']) && trim($params['age']) != '') {
                $where = '';
                $params['age'] = explode(',', $params['age']);
                $counter = count($params['age']);
                for ($a = 0; $a < $counter; $a++) {
                    if (trim($where) != '') {
                        $where .= ' OR ';
                    }
                    if ($params['age'][$a] == '<2') {
                        $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                    } elseif ($params['age'][$a] == '2to5') {
                        $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    } elseif ($params['age'][$a] == '6to14') {
                        $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    } elseif ($params['age'][$a] == '15to49') {
                        $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    } elseif ($params['age'][$a] == '>=50') {
                        $where .= "(vl.patient_age_in_years >= 50)";
                    } elseif ($params['age'][$a] == 'unknown') {
                        $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                    }
                }
                $where = '(' . $where . ')';
                $sQuery = $sQuery->where($where);
            }
            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $sQuery = $sQuery->where("vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' ");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $sQuery = $sQuery->where("vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000");
            }
            if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                $sQuery = $sQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
            }
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            } elseif (isset($params['gender']) && $params['gender'] == 'M') {
                $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
            } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $sQuery = $sQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
            }
            if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
                $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
            } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
                $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'");
            } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
                $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
            }
            if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
                $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
            } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
                $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'");
            } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
                $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
            }
            if (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == '1') {
                $sQuery = $sQuery->where("vl.line_of_treatment = '1'");
            } elseif (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == '2') {
                $sQuery = $sQuery->where("vl.line_of_treatment = '2'");
            } elseif (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == '3') {
                $sQuery = $sQuery->where("vl.line_of_treatment = '3'");
            } elseif (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == 'not_specified') {
                $sQuery = $sQuery->where("(vl.line_of_treatment IS NULL OR vl.line_of_treatment = '' OR vl.line_of_treatment = '0')");
            }
            $queryStr = $sql->buildSqlString($sQuery);
            $vlOutComeResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        }
        return $vlOutComeResult;
    }
    //end lab dashboard details

    // CLINIC DASHBOARD STUFF
    public function fetchOverallViralLoadResult($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sResult = [];

        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }

            $squery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "totalCollected" => new Expression("count(*)"),
                        "testedTotal" => new Expression("SUM(CASE WHEN ((vl.vl_result_category is NOT NULL OR vl.vl_result_category != '')) THEN 1 ELSE 0 END)"),
                        "notTestedTotal" => new Expression("SUM(CASE WHEN ((vl.vl_result_category is NULL OR vl.vl_result_category = '')) THEN 1 ELSE 0 END)"),
                        "lessThan1000" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                        "greaterThan1000" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                    )
                );
            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $squery = $sql->select()->from(array('vl' => $this->table))
                    ->columns(
                        array(
                            "testedTotal" => new Expression("SUM(CASE WHEN ((vl.vl_result_category is NOT NULL OR vl.vl_result_category != '')) THEN 1 ELSE 0 END)"),
                            "lessThan1000" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                        )
                    );
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $squery = $sql->select()->from(array('vl' => $this->table))
                    ->columns(
                        array(
                            "testedTotal" => new Expression("SUM(CASE WHEN ((vl.vl_result_category is NOT NULL OR vl.vl_result_category != '')) THEN 1 ELSE 0 END)"),
                            "greaterThan1000" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        )
                    );
            }
            $squery = $squery->where(array("DATE(vl.sample_collection_date) BETWEEN '$startDate' AND '$endDate'"));

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $squery = $squery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $squery = $squery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }

            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $squery = $squery->where('vl.specimen_type="' . base64_decode(trim($params['sampleTypeId'])) . '"');
            }
            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $squery = $squery->where(array("vl.arv_adherance_percentage ='" . $params['adherence'] . "'"));
            }
            //print_r($params['age']);die;
            $ageWhere = '';
            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $counter = count($age);
                for ($a = 0; $a < $counter; $a++) {
                    if (trim($ageWhere) != '') {
                        $ageWhere .= ' OR ';
                    }
                    if ($age[$a] == '<2') {
                        $ageWhere .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                    } elseif ($age[$a] == '2to5') {
                        $ageWhere .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    } elseif ($age[$a] == '6to14') {
                        $ageWhere .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    } elseif ($age[$a] == '15to49') {
                        $ageWhere .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    } elseif ($age[$a] == '>=50') {
                        $ageWhere .= "(vl.patient_age_in_years >= 50)";
                    } elseif ($age[$a] == 'unknown') {
                        $ageWhere .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                    }
                }
                $ageWhere = '(' . $ageWhere . ')';
                $squery = $squery->where($ageWhere);
            }

            if (isset($params['gender']) && $params['gender'] == 'F') {
                $squery = $squery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            } elseif (isset($params['gender']) && $params['gender'] == 'M') {
                $squery = $squery->where("vl.patient_gender IN ('m','male','M','MALE')");
            } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $squery = $squery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
            }
            if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
                $squery = $squery->where("vl.is_patient_pregnant = 'yes'");
            } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
                $squery = $squery->where("vl.is_patient_pregnant = 'no'");
            } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
                $squery = $squery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
            }
            if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
                $squery = $squery->where("vl.is_patient_breastfeeding = 'yes'");
            } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
                $squery = $squery->where("vl.is_patient_breastfeeding = 'no'");
            } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
                $squery = $squery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
            }

            $sQueryStr = $sql->buildSqlString($squery);
            //echo $sQueryStr;die;
            $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        }
        //var_dump($sResult);die;
        return $sResult;
    }

    public function fetchViralLoadStatusBasedOnGender($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }

            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "mTotal" => new Expression("SUM(CASE WHEN (vl.patient_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),
                        "mGreaterThanEqual1000" => new Expression("SUM(CASE WHEN (vl.patient_gender in('m','Male','M','MALE') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "mLesserThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender in('m','Male','M','MALE') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                        "fTotal" => new Expression("SUM(CASE WHEN (vl.patient_gender in('f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                        "fGreaterThanEqual1000" => new Expression("SUM(CASE WHEN (vl.patient_gender in('f','Female','F','FEMALE') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "fLesserThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender in('f','Female','F','FEMALE') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                        "nsTotal" => new Expression("SUM(CASE WHEN ((vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')) THEN 1 ELSE 0 END)"),
                        "nsGreaterThanEqual1000" => new Expression("SUM(CASE WHEN ((vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded' OR vl.patient_gender = 'Unreported' OR vl.patient_gender = 'unreported') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "nsLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded' OR vl.patient_gender = 'Unreported' OR vl.patient_gender = 'unreported') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                    )
                )
                ->where(new WhereExpression('DATE(vl.sample_collection_date) BETWEEN ? AND ?', [$startDate, $endDate]));

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $clinicIds = explode(',', $params['clinicId']);
                $query->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($clinicIds), '?')) . ')', $clinicIds));
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                if (!empty($mappedFacilities)) {
                    $query->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($mappedFacilities), '?')) . ')', $mappedFacilities));
                }
            }

            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $query->where("(vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $query->where("(vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)");
            }

            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $sampleTypeId = base64_decode(trim($params['sampleTypeId']));
                $query->where(new WhereExpression('vl.specimen_type = ?', [$sampleTypeId]));
            }

            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $ageConditions = [];
                foreach ($age as $ageGroup) {
                    switch ($ageGroup) {
                        case '<2':
                            $ageConditions[] = "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                            break;
                        case '2to5':
                            $ageConditions[] = "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                            break;
                        case '6to14':
                            $ageConditions[] = "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                            break;
                        case '15to49':
                            $ageConditions[] = "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                            break;
                        case '>=50':
                            $ageConditions[] = "(vl.patient_age_in_years >= 50)";
                            break;
                        case 'unknown':
                            $ageConditions[] = "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported')";
                            break;
                    }
                }
                if (!empty($ageConditions)) {
                    $query->where('(' . implode(' OR ', $ageConditions) . ')');
                }
            }
            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $query->where("vl.arv_adherance_percentage = ?", $params['adherence']);
                //$query = $query->where(array("vl.arv_adherance_percentage = '" . $params['adherence'] . "'"));
            }

            if (isset($params['gender'])) {
                if ($params['gender'] == 'F') {
                    $query->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                } elseif ($params['gender'] == 'M') {
                    $query->where("vl.patient_gender IN ('m','male','M','MALE')");
                } elseif ($params['gender'] == 'not_specified') {
                    $query->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                }
            }

            if (isset($params['isPregnant'])) {
                if ($params['isPregnant'] == 'yes') {
                    $query->where("vl.is_patient_pregnant = 'yes'");
                } elseif ($params['isPregnant'] == 'no') {
                    $query->where("vl.is_patient_pregnant = 'no'");
                } elseif ($params['isPregnant'] == 'unreported') {
                    $query->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
                }
            }

            if (isset($params['isBreastfeeding'])) {
                if ($params['isBreastfeeding'] == 'yes') {
                    $query->where("vl.is_patient_breastfeeding = 'yes'");
                } elseif ($params['isBreastfeeding'] == 'no') {
                    $query->where("vl.is_patient_breastfeeding = 'no'");
                } elseif ($params['isBreastfeeding'] == 'unreported') {
                    $query->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
                }
            }

            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sample) {
                $result['Total']['Male'][$j] = (isset($sample["mTotal"])) ? $sample["mTotal"] : 0;
                $result['Total']['Female'][$j] = (isset($sample["fTotal"])) ? $sample["fTotal"] : 0;
                $result['Total']['Not Specified'][$j] = (isset($sample["nsTotal"])) ? $sample["nsTotal"] : 0;
                $result['Suppressed']['Male'][$j] = (isset($sample["mLesserThan1000"])) ? $sample["mLesserThan1000"] : 0;
                $result['Suppressed']['Female'][$j] = (isset($sample["fLesserThan1000"])) ? $sample["fLesserThan1000"] : 0;
                $result['Suppressed']['Not Specified'][$j] = (isset($sample["nsLesserThan1000"])) ? $sample["nsLesserThan1000"] : 0;
                $result['Not Suppressed']['Male'][$j] = (isset($sample["mGreaterThanEqual1000"])) ? $sample["mGreaterThanEqual1000"] : 0;
                $result['Not Suppressed']['Female'][$j] = (isset($sample["fGreaterThanEqual1000"])) ? $sample["fGreaterThanEqual1000"] : 0;
                $result['Not Suppressed']['Not Specified'][$j] = (isset($sample["nsGreaterThanEqual1000"])) ? $sample["nsGreaterThanEqual1000"] : 0;
                $j++;
            }
        }


        return $result;
    }

    public function fetchSampleTestedResultBasedGenderDetails($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(

                        "total" => new Expression('COUNT(*)'),
                        "sampleCollectionDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%b-%Y')"),

                        "MGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender != '' and vl.patient_gender !='unreported' and vl.patient_gender in('m','Male','M','MALE') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "MLesserThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender != '' and vl.patient_gender !='unreported' and vl.patient_gender in('m','Male','M','MALE') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                        "FGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender != '' and vl.patient_gender !='unreported' and vl.patient_gender in('f','Female','F','FEMALE') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "FLesserThan1000" => new Expression("SUM(CASE WHEN (vl.patient_gender is not null and vl.patient_gender != '' and vl.patient_gender !='unreported' and vl.patient_gender in('f','Female','F','FEMALE') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                        "OGreaterThan1000" => new Expression("SUM(CASE WHEN ((vl.patient_gender IS NULL or vl.patient_gender = '' or vl.patient_gender = 'Not Recorded' or vl.patient_gender = 'not recorded') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "OLesserThan1000" => new Expression("SUM(CASE WHEN ((vl.patient_gender IS NULL or vl.patient_gender ='NULL' or vl.patient_gender = '' or vl.patient_gender = 'Not Recorded' or vl.patient_gender = 'not recorded' or vl.patient_gender = 'Unreported' or vl.patient_gender = 'unreported') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                    )
                )
                ->where(new WhereExpression('DATE(vl.sample_collection_date) BETWEEN ? AND ?', [$startDate, $endDate]));

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $clinicIds = explode(',', $params['clinicId']);
                $query->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($clinicIds), '?')) . ')', $clinicIds));
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                if (!empty($mappedFacilities)) {
                    $query->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($mappedFacilities), '?')) . ')', $mappedFacilities));
                }
            }

            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $query->where("(vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $query->where("(vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)");
            }

            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $sampleTypeId = base64_decode(trim($params['sampleTypeId']));
                $query->where(new WhereExpression('vl.specimen_type = ?', [$sampleTypeId]));
            }

            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $ageConditions = [];
                foreach ($age as $ageGroup) {
                    switch ($ageGroup) {
                        case '<2':
                            $ageConditions[] = "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                            break;
                        case '2to5':
                            $ageConditions[] = "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                            break;
                        case '6to14':
                            $ageConditions[] = "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                            break;
                        case '15to49':
                            $ageConditions[] = "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                            break;
                        case '>=50':
                            $ageConditions[] = "(vl.patient_age_in_years >= 50)";
                            break;
                        case 'unknown':
                            $ageConditions[] = "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported')";
                            break;
                    }
                }
                if (!empty($ageConditions)) {
                    $query->where('(' . implode(' OR ', $ageConditions) . ')');
                }
            }

            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $query->where(new WhereExpression("vl.arv_adherance_percentage = ?", $params['adherence']));
            }

            if (isset($params['gender'])) {
                if ($params['gender'] == 'F') {
                    $query->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                } elseif ($params['gender'] == 'M') {
                    $query->where("vl.patient_gender IN ('m','male','M','MALE')");
                } elseif ($params['gender'] == 'not_specified') {
                    $query->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                }
            }

            if (isset($params['isPregnant'])) {
                if ($params['isPregnant'] == 'yes') {
                    $query->where("vl.is_patient_pregnant = 'yes'");
                } elseif ($params['isPregnant'] == 'no') {
                    $query->where("vl.is_patient_pregnant = 'no'");
                } elseif ($params['isPregnant'] == 'unreported') {
                    $query->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
                }
            }

            if (isset($params['isBreastfeeding'])) {
                if ($params['isBreastfeeding'] == 'yes') {
                    $query->where("vl.is_patient_breastfeeding = 'yes'");
                } elseif ($params['isBreastfeeding'] == 'no') {
                    $query->where("vl.is_patient_breastfeeding = 'no'");
                } elseif ($params['isBreastfeeding'] == 'unreported') {
                    $query->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
                }
            }

            $query = $query->group(array(new Expression('WEEK(sample_collection_date)')));
            $query = $query->order(array(new Expression('WEEK(sample_collection_date)')));
            $queryStr = $sql->buildSqlString($query);
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["sampleCollectionDate"] == null) {
                    continue;
                }
                $result['M']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["MGreaterThan1000"])) ? $sRow["MGreaterThan1000"] : 0;
                //$result['M']['VL Not Detected'][$j] = $sRow["MTND"];
                $result['M']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["MLesserThan1000"])) ? $sRow["MLesserThan1000"] : 0;

                $result['F']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["FGreaterThan1000"])) ? $sRow["FGreaterThan1000"] : 0;
                //$result['F']['VL Not Detected'][$j] = $sRow["FTND"];
                $result['F']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["FLesserThan1000"])) ? $sRow["FLesserThan1000"] : 0;

                $result['Not Specified']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["OGreaterThan1000"])) ? $sRow["OGreaterThan1000"] : 0;
                //$result['Not Specified']['VL Not Detected'][$j] = $sRow["OTND"];
                $result['Not Specified']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["OLesserThan1000"])) ? $sRow["OLesserThan1000"] : 0;

                $result['date'][$j] = $sRow["sampleCollectionDate"];
                $j++;
            }
        }

        return $result;
    }

    public function fetchClinicSampleTestedResultAgeGroupDetails($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            if ($params['age']['from'] == 'unknown') {
                $caseQuery1 = new Expression("SUM(CASE WHEN ((vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported') and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)");
                $caseQuery2 = new Expression("SUM(CASE WHEN ((vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported') and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)");
            } else {
                $from = $params['age']['from'];
                $to = $params['age']['to'];
                $caseQuery1 = new Expression("SUM(CASE WHEN ((vl.patient_age_in_years $from AND vl.patient_age_in_years  $to) and (vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)");
                $caseQuery2 = new Expression("SUM(CASE WHEN ((vl.patient_age_in_years $from AND vl.patient_age_in_years  $to) and (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)");
            }
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%b-%Y')"),

                        "AgeLtVLGt1000" => $caseQuery1,
                        "AgeLtVLLt1000" => $caseQuery2,
                    )
                )
                ->where(new WhereExpression('DATE(vl.sample_collection_date) BETWEEN ? AND ?', [$startDate, $endDate]));

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $clinicIds = explode(',', $params['clinicId']);
                $query->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($clinicIds), '?')) . ')', $clinicIds));
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                if (!empty($mappedFacilities)) {
                    $query->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($mappedFacilities), '?')) . ')', $mappedFacilities));
                }
            }

            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $query->where("(vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $query->where("(vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)");
            }

            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $sampleTypeId = base64_decode(trim($params['sampleTypeId']));
                $query->where(new WhereExpression('vl.specimen_type = ?', [$sampleTypeId]));
            }

            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $query->where(new WhereExpression("vl.arv_adherance_percentage = ?", $params['adherence']));
            }

            if (isset($params['gender'])) {
                if ($params['gender'] == 'F') {
                    $query->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                } elseif ($params['gender'] == 'M') {
                    $query->where("vl.patient_gender IN ('m','male','M','MALE')");
                } elseif ($params['gender'] == 'not_specified') {
                    $query->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                }
            }

            if (isset($params['isPregnant'])) {
                if ($params['isPregnant'] == 'yes') {
                    $query->where("vl.is_patient_pregnant = 'yes'");
                } elseif ($params['isPregnant'] == 'no') {
                    $query->where("vl.is_patient_pregnant = 'no'");
                } elseif ($params['isPregnant'] == 'unreported') {
                    $query->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
                }
            }

            if (isset($params['isBreastfeeding'])) {
                if ($params['isBreastfeeding'] == 'yes') {
                    $query->where("vl.is_patient_breastfeeding = 'yes'");
                } elseif ($params['isBreastfeeding'] == 'no') {
                    $query->where("vl.is_patient_breastfeeding = 'no'");
                } elseif ($params['isBreastfeeding'] == 'unreported') {
                    $query->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
                }
            }

            $query = $query->group(array(new Expression('WEEK(sample_collection_date)')));
            $query = $query->order(array(new Expression('WEEK(sample_collection_date)')));
            $queryStr = $sql->buildSqlString($query);
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["monthDate"] == null) {
                    continue;
                }
                $result[$params['age']['ageName']]['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["AgeLtVLGt1000"])) ? $sRow["AgeLtVLGt1000"] : 0;

                $result[$params['age']['ageName']]['VL (< 1000 cp/ml)'][$j] = (isset($sRow["AgeLtVLLt1000"])) ? $sRow["AgeLtVLLt1000"] : 0;

                $result['date'][$j] = $sRow["monthDate"];
                $j++;
            }
        }
        return $result;
    }

    public function fetchClinicRequisitionFormsTested($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }

            $incompleteQuery = "(vl.patient_art_no IS NULL OR vl.patient_art_no='' OR vl.patient_age_in_years IS NULL OR vl.patient_age_in_years ='' OR vl.patient_gender IS NULL OR vl.patient_gender='' OR vl.current_regimen IS NOT NULL OR vl.current_regimen !='')";
            $completeQuery = "vl.patient_art_no IS NOT NULL AND vl.patient_art_no !='' AND vl.patient_age_in_years IS NOT NULL AND vl.patient_age_in_years !='' AND vl.patient_gender IS NOT NULL AND vl.patient_gender !='' AND vl.current_regimen IS NOT NULL AND vl.current_regimen !=''";
            if (isset($params['formFields']) && trim($params['formFields']) != '') {
                $formFields = explode(',', $params['formFields']);
                $incompleteQuery = '';
                $completeQuery = '';
                $counter = count($formFields);
                for ($f = 0; $f < $counter; $f++) {
                    if (trim($formFields[$f]) != '') {
                        $incompleteQuery .= 'vl.' . $formFields[$f] . ' IS NULL OR vl.' . $formFields[$f] . '=""';
                        $completeQuery .= 'vl.' . $formFields[$f] . ' IS NOT NULL AND vl.' . $formFields[$f] . '!=""';
                        if ((count($formFields) - $f) > 1) {
                            $incompleteQuery .= ' OR ';
                            $completeQuery .= ' AND ';
                        }
                    }
                }
            }
            $i = 0;
            $completeResultCount = 0;
            $inCompleteResultCount = 0;
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "total" => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%b-%Y')"),

                        "CompletedForms" => new Expression("SUM(CASE WHEN ($completeQuery) THEN 1 ELSE 0 END)"),
                        "IncompleteForms" => new Expression("SUM(CASE WHEN ($incompleteQuery) THEN 1 ELSE 0 END)"),

                    )
                )
                ->where(new WhereExpression('DATE(vl.sample_collection_date) BETWEEN ? AND ?', [$startDate, $endDate]));

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $clinicIds = explode(',', $params['clinicId']);
                $query->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($clinicIds), '?')) . ')', $clinicIds));
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                if (!empty($mappedFacilities)) {
                    $query->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($mappedFacilities), '?')) . ')', $mappedFacilities));
                }
            }

            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $query->where("(vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $query->where("(vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)");
            }

            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $sampleTypeId = base64_decode(trim($params['sampleTypeId']));
                $query->where(new WhereExpression('vl.specimen_type = ?', [$sampleTypeId]));
            }

            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $query->where(new WhereExpression("vl.arv_adherance_percentage = ?", $params['adherence']));
            }

            if (isset($params['gender'])) {
                if ($params['gender'] == 'F') {
                    $query->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                } elseif ($params['gender'] == 'M') {
                    $query->where("vl.patient_gender IN ('m','male','M','MALE')");
                } elseif ($params['gender'] == 'not_specified') {
                    $query->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                }
            }

            if (isset($params['isPregnant'])) {
                if ($params['isPregnant'] == 'yes') {
                    $query->where("vl.is_patient_pregnant = 'yes'");
                } elseif ($params['isPregnant'] == 'no') {
                    $query->where("vl.is_patient_pregnant = 'no'");
                } elseif ($params['isPregnant'] == 'unreported') {
                    $query->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
                }
            }

            if (isset($params['isBreastfeeding'])) {
                if ($params['isBreastfeeding'] == 'yes') {
                    $query->where("vl.is_patient_breastfeeding = 'yes'");
                } elseif ($params['isBreastfeeding'] == 'no') {
                    $query->where("vl.is_patient_breastfeeding = 'no'");
                } elseif ($params['isBreastfeeding'] == 'unreported') {
                    $query->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
                }
            }

            $query = $query->group(array(new Expression('WEEK(sample_collection_date)')));
            $query = $query->order(array(new Expression('WEEK(sample_collection_date)')));
            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
            $j = 0;
            if (isset($sampleResult) && count($sampleResult) > 0) {
                foreach ($sampleResult as $sRow) {
                    if ($sRow["monthDate"] == null) {
                        continue;
                    }
                    $result['Complete'][$j] = (isset($sRow["CompletedForms"])) ? (int) $sRow["CompletedForms"] : null;
                    $result['Incomplete'][$j] = (isset($sRow["IncompleteForms"])) ? (int) $sRow["IncompleteForms"] : null;
                    $completionRate = 100 * ($result['Complete'][$j] / ($result['Complete'][$j] + $result['Incomplete'][$j]));
                    $result['CompletionRate'][$j] = ($completionRate > 0) ? round($completionRate, 2) : 0;
                    $result['date'][$j] = $sRow["monthDate"];
                    $j++;
                }
            }
        }

        return $result;
    }

    public function fetchSampleTestedReason($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rResult = [];

        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            $rQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(array('total' => new Expression('COUNT(*)'), 'monthDate' => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%M-%Y')")))
                ->join(array('tr' => 'r_vl_test_reasons'), 'tr.test_reason_id=vl.reason_for_vl_testing', array('test_reason_name'))
                ->where(new WhereExpression('DATE(vl.sample_collection_date) BETWEEN ? AND ?', [$startDate, $endDate]))
                //->where('vl.facility_id !=0')
                //->where('vl.reason_for_vl_testing="'.$reason['test_reason_id'].'"');
                ->group('tr.test_reason_id');

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $clinicIds = explode(',', $params['clinicId']);
                $rQuery->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($clinicIds), '?')) . ')', $clinicIds));
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                if (!empty($mappedFacilities)) {
                    $rQuery->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($mappedFacilities), '?')) . ')', $mappedFacilities));
                }
            }

            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $rQuery->where("(vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $rQuery->where("(vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000)");
            }

            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $sampleTypeId = base64_decode(trim($params['sampleTypeId']));
                $rQuery->where(new WhereExpression('vl.specimen_type = ?', [$sampleTypeId]));
            }

            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $ageConditions = [];
                foreach ($age as $ageGroup) {
                    switch ($ageGroup) {
                        case '<2':
                            $ageConditions[] = "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                            break;
                        case '2to5':
                            $ageConditions[] = "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                            break;
                        case '6to14':
                            $ageConditions[] = "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                            break;
                        case '15to49':
                            $ageConditions[] = "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                            break;
                        case '>=50':
                            $ageConditions[] = "(vl.patient_age_in_years >= 50)";
                            break;
                        case 'unknown':
                            $ageConditions[] = "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported')";
                            break;
                    }
                }
                if (!empty($ageConditions)) {
                    $rQuery->where('(' . implode(' OR ', $ageConditions) . ')');
                }
            }

            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $rQuery->where(new WhereExpression("vl.arv_adherance_percentage = ?", $params['adherence']));
            }
            if (isset($params['gender'])) {
                if ($params['gender'] == 'F') {
                    $rQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                } elseif ($params['gender'] == 'M') {
                    $rQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                } elseif ($params['gender'] == 'not_specified') {
                    $rQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                }
            }

            if (isset($params['isPregnant'])) {
                if ($params['isPregnant'] == 'yes') {
                    $rQuery->where("vl.is_patient_pregnant = 'yes'");
                } elseif ($params['isPregnant'] == 'no') {
                    $rQuery->where("vl.is_patient_pregnant = 'no'");
                } elseif ($params['isPregnant'] == 'unreported') {
                    $rQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
                }
            }

            if (isset($params['isBreastfeeding'])) {
                if ($params['isBreastfeeding'] == 'yes') {
                    $rQuery->where("vl.is_patient_breastfeeding = 'yes'");
                } elseif ($params['isBreastfeeding'] == 'no') {
                    $rQuery->where("vl.is_patient_breastfeeding = 'no'");
                } elseif ($params['isBreastfeeding'] == 'unreported') {
                    $rQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
                }
            }

            if (isset($params['testReason']) && trim($params['testReason']) != '') {
                $rQuery->where(new WhereExpression("vl.reason_for_vl_testing ='" . base64_decode($params['testReason']) . "'"));
            }

            $rQueryStr = $sql->buildSqlString($rQuery);
            //echo $rQueryStr;die;
            //$qResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $qResult = $this->commonService->cacheQuery($rQueryStr, $dbAdapter);
            $j = 0;
            foreach ($qResult as $r) {
                $rResult[$r['test_reason_name']][$j]['total'] = (isset($r['total'])) ? (int) $r['total'] : 0;
                $rResult['date'][$j] = $r['monthDate'];
                $j++;
            }
        }
        return $rResult;
    }
    //end clinic details

    //get distinict date
    public function getDistinctDate($endDate, $startDate)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $squery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array(new Expression('DISTINCT YEAR(sample_collection_date) as year,MONTH(sample_collection_date) as month,DAY(sample_collection_date) as day')))
            //->where('vl.lab_id !=0')
            ->order('month ASC')->order('day ASC');
        if (isset($startDate) && trim($endDate) != '') {
            if (trim($startDate) !== trim($endDate)) {
                $squery = $squery->where(array("vl.sample_collection_date <='" . $endDate . " 23:59:59" . "'", "vl.sample_collection_date >='" . $startDate . " 00:00:00" . "'"));
            } else {
                $fromMonth = date("Y-m", strtotime(trim($startDate)));
                $month = strtotime($fromMonth);
                $m = date('m', $month);
                $year = date('Y', $month);
                $squery = $squery->where("Month(sample_collection_date)='" . $m . "' AND Year(sample_collection_date)='" . $year . "'");
            }
        }
        $sQueryStr = $sql->buildSqlString($squery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function fetchAllTestResults($parameters)
    {
        $loginContainer = new Container('credo');
        $queryContainer = new Container('query');
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('sample_code', 'facility_name', 'DATE_FORMAT(sample_collection_date,"%d-%b-%Y")', 'rejection_reason_name', 'DATE_FORMAT(sample_tested_datetime,"%d-%b-%Y")', 'result');
        $orderColumns = array('sample_code', 'facility_name', 'sample_collection_date', 'rejection_reason_name', 'sample_tested_datetime', 'result');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $startDate = '';
        $endDate = '';
        if (isset($parameters['sampleCollectionDate']) && trim($parameters['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $parameters['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
        }

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('vl_sample_id', 'sample_code', 'vl_result_category', 'result_value_absolute_decimal', 'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'), 'specimen_type', 'sampleTestingDate' => new Expression('DATE(sample_tested_datetime)'), 'result_value_log', 'result_value_absolute', 'result_value_text', 'result'))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'), 'left')
            ->join(array('r_r_r' => 'r_vl_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection', array('rejection_reason_name'), 'left');
        //->where(array('f.facility_type'=>'1'));
        if (isset($parameters['sampleCollectionDate']) && trim($parameters['sampleCollectionDate']) != '') {
            //$sQuery = $sQuery->where(array("vl.sample_collection_date <='" . $endDate . " 23:59:59" . "'", "vl.sample_collection_date >='" . $startDate . " 00:00:00" . "'"));
            $sQuery = $sQuery->where(array("DATE(vl.sample_collection_date) >='$startDate'", "DATE(vl.sample_collection_date) <='$endDate'"));
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $sQuery = $sQuery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        if (isset($parameters['testResult']) && $parameters['testResult'] == '<1000') {
            $sQuery = $sQuery->where("(vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )");
        } elseif (isset($parameters['testResult']) && $parameters['testResult'] == '>=1000') {
            $sQuery = $sQuery->where("vl.vl_result_category like 'not suppressed%' OR vl.vl_result_category like 'Not Suppressed%' or vl.result_value_absolute_decimal >= 1000");
        }
        if (isset($parameters['sampleTypeId']) && trim($parameters['sampleTypeId']) != '') {
            $sQuery = $sQuery->where('vl.specimen_type="' . base64_decode(trim($parameters['sampleTypeId'])) . '"');
        }
        //print_r($parameters['age']);die;
        if (isset($parameters['age']) && trim($parameters['age']) != '') {
            $age = explode(',', $parameters['age']);
            $where = '';
            $counter = count($age);
            for ($a = 0; $a < $counter; $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                } elseif ($age[$a] == '2to5') {
                    $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                } elseif ($age[$a] == '6to14') {
                    $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                } elseif ($age[$a] == '15to49') {
                    $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                } elseif ($age[$a] == '>=50') {
                    $where .= "(vl.patient_age_in_years >= 50)";
                } elseif ($age[$a] == 'unknown') {
                    $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported')";
                }
            }
            $where = '(' . $where . ')';
            $sQuery = $sQuery->where($where);
        }
        if (isset($parameters['adherence']) && trim($parameters['adherence']) != '') {
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='" . $parameters['adherence'] . "'"));
        }
        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        } elseif (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        } elseif (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded' OR vl.patient_gender = 'unreported' OR vl.patient_gender = 'Unreported')");
        }
        if (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'yes') {
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
        } elseif (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'no') {
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'");
        } elseif (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'unreported') {
            $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
        }
        if (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'yes') {
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
        } elseif (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'no') {
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'");
        } elseif (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'unreported') {
            $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
        }
        if (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'result') {
            $sQuery = $sQuery->where("(vl.vl_result_category is NOT NULL AND vl.vl_result_category !='' AND vl.vl_result_category !='Rejected')");
        } elseif (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'noresult') {
            $sQuery = $sQuery->where("(vl.vl_result_category is NULL OR vl.vl_result_category ='')");
        } elseif (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'rejected') {
            $sQuery = $sQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0 OR vl.vl_result_category = 'Rejected'");
        }
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }
        if (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'result') {
            $hQuery = '';
            $hQuery = clone $sQuery;
            $hQuery->join(array('pat' => 'patients'), 'pat.patient_code=vl.patient_art_no', array('patient_first_name', 'patient_middle_name', 'patient_last_name'), 'left')
                ->join(array('st' => 'r_vl_sample_type'), 'st.sample_id=vl.specimen_type', array('sample_name'), 'left')
                ->join(array('lds' => 'geographical_divisions'), 'lds.geo_id=f.facility_state_id', array('facilityState' => 'geo_name'), 'left')
                ->join(array('ldd' => 'geographical_divisions'), 'ldd.geo_id=f.facility_district_id', array('facilityDistrict' => 'geo_name'), 'left')
                ->where('vl_result_category="Not Suppressed"');
            $queryContainer->highVlSampleQuery = $hQuery;
        }
        $queryContainer->resultQuery = $sQuery;
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('vl_sample_id'))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('r_r_r' => 'r_vl_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection', array('rejection_reason_name'), 'left');
        //->where(array('f.facility_type'=>'1'));
        if ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $iQuery = $iQuery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        if (isset($parameters['sampleCollectionDate']) && trim($parameters['sampleCollectionDate']) != '') {
            $sQuery = $sQuery->where(array("DATE(vl.sample_collection_date) >='$startDate'", "DATE(vl.sample_collection_date) <='$endDate'"));
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );


        $viewText = $this->commonService->translate('View');
        $pdfText = $this->commonService->translate('PDF');
        foreach ($rResult as $aRow) {
            $row = [];
            $sampleCollectionDate = '';
            if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != NULL && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                $sampleCollectionDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['sampleCollectionDate']);
            }
            $sampleTestedDate = '';
            if (isset($aRow['sampleTestingDate']) && $aRow['sampleTestingDate'] != NULL && trim($aRow['sampleTestingDate']) != "" && $aRow['sampleTestingDate'] != '0000-00-00') {
                $sampleTestedDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['sampleTestingDate']);
            }
            $pdfButtCss = ($aRow['result'] == null || trim($aRow['result']) == "") ? 'display:none' : '';
            $row[] = $aRow['sample_code'];
            $row[] = ucwords($aRow['facility_name']);
            $row[] = $sampleCollectionDate;
            $row[] = (isset($aRow['rejection_reason_name'])) ? ucwords($aRow['rejection_reason_name']) : '';
            $row[] = $sampleTestedDate;
            $row[] = $aRow['result'];
            $row[] = '<a href="/clinics/test-result-view/' . base64_encode($aRow['vl_sample_id']) . '" class="btn btn-primary btn-xs" target="_blank">' . $viewText . '</a>&nbsp;&nbsp;<a href="javascript:void(0);" class="btn btn-danger btn-xs" style="' . $pdfButtCss . '" onclick="generateResultPDF(' . $aRow['vl_sample_id'] . ');">' . $pdfText . '</a>';
            $output['aaData'][] = $row;
        }

        return $output;
    }

    //get sample tested result details
    public function fetchClinicSampleTestedResults($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];



        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            $query = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        //"total" => new Expression('COUNT(*)'),
                        "day" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%d-%b-%Y')"),

                        "DBSGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.specimen_type=$this->dbsId AND (vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "DBSLesserThan1000" => new Expression("SUM(CASE WHEN (vl.specimen_type=$this->dbsId AND (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),

                        "OGreaterThan1000" => new Expression("SUM(CASE WHEN (vl.specimen_type!=$this->dbsId AND (vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                        "OLesserThan1000" => new Expression("SUM(CASE WHEN (vl.specimen_type!=$this->dbsId AND (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                    )
                )
                ->where(new WhereExpression('DATE(vl.sample_collection_date) BETWEEN ? AND ?', [$startDate, $endDate]));

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $clinicIds = explode(',', $params['clinicId']);
                $query->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($clinicIds), '?')) . ')', $clinicIds));
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $query->where(new WhereExpression('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")'));
            }

            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $query->where("(vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $query->where("(vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)");
            }

            if (isset($params['frmSrc']) && $params['frmSrc'] == 'change') {
                if (isset($params['sampleType']) && $params['sampleType'] == 'dbs') {
                    $query->where(new WhereExpression("vl.specimen_type = $this->dbsId"));
                } elseif (isset($params['sampleType']) && $params['sampleType'] == 'others') {
                    $query->where(new WhereExpression("vl.specimen_type != $this->dbsId"));
                }
            } elseif (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $sampleTypeId = base64_decode(trim($params['sampleTypeId']));
                $query->where(new WhereExpression('vl.specimen_type = ?', [$sampleTypeId]));
            }

            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $ageConditions = [];
                foreach ($age as $ageGroup) {
                    switch ($ageGroup) {
                        case '<2':
                            $ageConditions[] = "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                            break;
                        case '2to5':
                            $ageConditions[] = "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                            break;
                        case '6to14':
                            $ageConditions[] = "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                            break;
                        case '15to49':
                            $ageConditions[] = "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                            break;
                        case '>=50':
                            $ageConditions[] = "(vl.patient_age_in_years >= 50)";
                            break;
                        case 'unknown':
                            $ageConditions[] = "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported')";
                            break;
                    }
                }
                if (!empty($ageConditions)) {
                    $query->where('(' . implode(' OR ', $ageConditions) . ')');
                }
            }

            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $query->where(new WhereExpression("vl.arv_adherance_percentage = ?", $params['adherence']));
            }

            if (isset($params['gender'])) {
                if ($params['gender'] == 'F') {
                    $query->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                } elseif ($params['gender'] == 'M') {
                    $query->where("vl.patient_gender IN ('m','male','M','MALE')");
                } elseif ($params['gender'] == 'not_specified') {
                    $query->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                }
            }

            if (isset($params['isPregnant'])) {
                if ($params['isPregnant'] == 'yes') {
                    $query->where("vl.is_patient_pregnant = 'yes'");
                } elseif ($params['isPregnant'] == 'no') {
                    $query->where("vl.is_patient_pregnant = 'no'");
                } elseif ($params['isPregnant'] == 'unreported') {
                    $query->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
                }
            }

            if (isset($params['isBreastfeeding'])) {
                if ($params['isBreastfeeding'] == 'yes') {
                    $query->where("vl.is_patient_breastfeeding = 'yes'");
                } elseif ($params['isBreastfeeding'] == 'no') {
                    $query->where("vl.is_patient_breastfeeding = 'no'");
                } elseif ($params['isBreastfeeding'] == 'unreported') {
                    $query->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
                }
            }

            $query = $query->group(array(new Expression('WEEK(sample_collection_date)')));
            $query = $query->order('sample_collection_date ASC');
            $queryStr = $sql->buildSqlString($query);
            //echo $queryStr;die;
            $sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $j = 0;
            foreach ($sampleResult as $sRow) {
                if ($sRow["day"] == null) {
                    continue;
                }
                $result['DBS']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["DBSGreaterThan1000"])) ? $sRow["DBSGreaterThan1000"] : 0;
                $result['DBS']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["DBSLesserThan1000"])) ? $sRow["DBSLesserThan1000"] : 0;
                $result['Others']['VL (>= 1000 cp/ml)'][$j] = (isset($sRow["OGreaterThan1000"])) ? $sRow["OGreaterThan1000"] : 0;
                $result['Others']['VL (< 1000 cp/ml)'][$j] = (isset($sRow["OLesserThan1000"])) ? $sRow["OLesserThan1000"] : 0;
                $result['date'][$j] = $sRow["day"];
                $j++;
            }
        }
        return $result;
    }

    public function fetchSampleDetails($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $facilityQuery = $sql->select()->from(array('f' => 'facility_details'))
                ->where(array('f.facility_type' => 2));
            if (isset($params['lab']) && trim($params['lab']) != '') {
                $facilityQuery = $facilityQuery->where('f.facility_id IN (' . $params['lab'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $facilityQuery = $facilityQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            $facilityQueryStr = $sql->buildSqlString($facilityQuery);
            $facilityResult = $dbAdapter->query($facilityQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($facilityResult) && count($facilityResult) > 0) {

                $facilityIdList = array_column($facilityResult, 'facility_id');

                $countQuery = $sql->select()->from(array('vl' => $this->table))->columns(array('total' => new Expression('COUNT(*)')))
                    ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array('facility_name', 'facility_code'))
                    ->where('vl.lab_id IN ("' . implode('", "', $facilityIdList) . '")')
                    ->group('vl.lab_id');

                if (!isset($params['fromSrc'])) {
                    $countQuery = $countQuery->where('(vl.vl_result_category IS NOT NULL AND vl.vl_result_category!= "")');
                }
                if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                    $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
                }
                if (isset($params['provinces']) && trim($params['provinces']) != '') {
                    $countQuery = $countQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
                }
                if (isset($params['districts']) && trim($params['districts']) != '') {
                    $countQuery = $countQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
                }
                if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                    $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
                }
                if (isset($params['currentRegimen']) && trim($params['currentRegimen']) != '') {
                    $countQuery = $countQuery->where('vl.current_regimen="' . base64_decode(trim($params['currentRegimen'])) . '"');
                }
                if (isset($params['adherence']) && trim($params['adherence']) != '') {
                    $countQuery = $countQuery->where(array("vl.arv_adherance_percentage ='" . $params['adherence'] . "'"));
                }
                //print_r($params['age']);die;
                if (isset($params['age']) && trim($params['age']) != '') {
                    $age = explode(',', $params['age']);
                    $where = '';
                    $counter = count($age);
                    for ($a = 0; $a < $counter; $a++) {
                        if (trim($where) != '') {
                            $where .= ' OR ';
                        }
                        if ($age[$a] == '<2') {
                            $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                        } elseif ($age[$a] == '2to5') {
                            $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                        } elseif ($age[$a] == '6to14') {
                            $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                        } elseif ($age[$a] == '15to49') {
                            $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                        } elseif ($age[$a] == '>=50') {
                            $where .= "(vl.patient_age_in_years >= 50)";
                        } elseif ($age[$a] == 'unknown') {
                            $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                        }
                    }
                    $where = '(' . $where . ')';
                    $countQuery = $countQuery->where($where);
                }
                if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                    $countQuery = $countQuery->where("(vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )");
                } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                    $countQuery = $countQuery->where("(vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)");
                }
                if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                    $countQuery = $countQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
                }
                if (isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested') {
                    $countQuery = $countQuery->where("((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))");
                } elseif (isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                    $countQuery = $countQuery->where("(vl.vl_result_category IS NULL OR vl.vl_result_category = '' OR vl.vl_result_category = 'NULL') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)");
                } elseif (isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                    $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
                }
                if (isset($params['gender']) && $params['gender'] == 'F') {
                    $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                } elseif (isset($params['gender']) && $params['gender'] == 'M') {
                    $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
                    $countQuery = $countQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                }
                if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
                } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
                } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
                    $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
                }
                if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
                } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'");
                } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
                    $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
                }
                if (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == '1') {
                    $countQuery = $countQuery->where("vl.line_of_treatment = '1'");
                } elseif (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == '2') {
                    $countQuery = $countQuery->where("vl.line_of_treatment = '2'");
                } elseif (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == '3') {
                    $countQuery = $countQuery->where("vl.line_of_treatment = '3'");
                } elseif (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == 'not_specified') {
                    $countQuery = $countQuery->where("(vl.line_of_treatment IS NULL OR vl.line_of_treatment = '' OR vl.line_of_treatment = '0')");
                }
                $cQueryStr = $sql->buildSqlString($countQuery);
                //echo $cQueryStr;die;
                $countResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                $i = 0;
                foreach ($countResult as $data) {
                    $result[$i][0] = $data['total'];
                    $result[$i][1] = ucwords($data['facility_name']);
                    $result[$i][2] = $data['facility_code'];
                    $i++;
                }
            }
        }
        return $result;
    }

    public function fetchBarSampleDetails($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $fQuery = $sql->select()->from(array('f' => 'facility_details'))
                ->where(array('f.facility_type' => 2));
            if (isset($params['lab']) && trim($params['lab']) != '') {
                $fQuery = $fQuery->where('f.facility_id IN (' . $params['lab'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $fQuery = $fQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            $fQueryStr = $sql->buildSqlString($fQuery);
            $facilityResult = $dbAdapter->query($fQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($facilityResult) && count($facilityResult) > 0) {

                $facilityIdList = array_column($facilityResult, 'facility_id');

                $countQuery = $sql->select()->from(array('vl' => $this->table))
                    ->columns(
                        array(
                            'total' => new Expression('COUNT(*)'),
                            "suppressed" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%')) THEN 1 ELSE 0 END)"),
                            "not_suppressed" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                            "rejected" => new Expression("SUM(CASE WHEN ((vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END)"),
                        )
                    )
                    ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array('facility_name'))
                    ->where('vl.lab_id IN ("' . implode('", "', $facilityIdList) . '")')
                    ->group('vl.lab_id');

                if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                    $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
                }
                if (isset($params['provinces']) && trim($params['provinces']) != '') {
                    $countQuery = $countQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
                }
                if (isset($params['districts']) && trim($params['districts']) != '') {
                    $countQuery = $countQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
                }
                if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                    $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
                }
                if (isset($params['currentRegimen']) && trim($params['currentRegimen']) != '') {
                    $countQuery = $countQuery->where('vl.current_regimen="' . base64_decode(trim($params['currentRegimen'])) . '"');
                }
                if (isset($params['adherence']) && trim($params['adherence']) != '') {
                    $countQuery = $countQuery->where(array("vl.arv_adherance_percentage ='" . $params['adherence'] . "'"));
                }
                //print_r($params['age']);die;
                if (isset($params['age']) && trim($params['age']) != '') {
                    $age = explode(',', $params['age']);
                    $where = '';
                    $counter = count($age);
                    for ($a = 0; $a < $counter; $a++) {
                        if (trim($where) != '') {
                            $where .= ' OR ';
                        }
                        if ($age[$a] == '<2') {
                            $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                        } elseif ($age[$a] == '2to5') {
                            $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                        } elseif ($age[$a] == '6to14') {
                            $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                        } elseif ($age[$a] == '15to49') {
                            $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                        } elseif ($age[$a] == '>=50') {
                            $where .= "(vl.patient_age_in_years >= 50)";
                        } elseif ($age[$a] == 'unknown') {
                            $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                        }
                    }
                    $where = '(' . $where . ')';
                    $countQuery = $countQuery->where($where);
                }
                if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                    $countQuery = $countQuery->where("(vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )");
                } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                    $countQuery = $countQuery->where("(vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)");
                }
                if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                    $countQuery = $countQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
                }
                if (isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_tested') {
                    $countQuery = $countQuery->where("((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))");
                } elseif (isset($params['sampleStatus']) && $params['sampleStatus'] == 'samples_not_tested') {
                    $countQuery = $countQuery->where("(vl.vl_result_category IS NULL OR vl.vl_result_category = '' OR vl.vl_result_category = 'NULL') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)");
                } elseif (isset($params['sampleStatus']) && $params['sampleStatus'] == 'sample_rejected') {
                    $countQuery = $countQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
                }
                if (isset($params['gender']) && $params['gender'] == 'F') {
                    $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                } elseif (isset($params['gender']) && $params['gender'] == 'M') {
                    $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
                    $countQuery = $countQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                }
                if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
                } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
                    $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
                } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
                    $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
                }
                if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
                } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
                    $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'");
                } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
                    $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
                }
                if (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == '1') {
                    $countQuery = $countQuery->where("vl.line_of_treatment = '1'");
                } elseif (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == '2') {
                    $countQuery = $countQuery->where("vl.line_of_treatment = '2'");
                } elseif (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == '3') {
                    $countQuery = $countQuery->where("vl.line_of_treatment = '3'");
                } elseif (isset($params['lineOfTreatment']) && $params['lineOfTreatment'] == 'not_specified') {
                    $countQuery = $countQuery->where("(vl.line_of_treatment IS NULL OR vl.line_of_treatment = '' OR vl.line_of_treatment = '0')");
                }
                $cQueryStr = $sql->buildSqlString($countQuery);
                $barChartResult = $dbAdapter->query($cQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

                $j = 0;
                foreach ($barChartResult as $data) {
                    $result['sample']['Suppressed'][$j] = $data['suppressed'];
                    $result['sample']['Not Suppressed'][$j] = $data['not_suppressed'];
                    $result['sample']['Rejected'][$j] = $data['rejected'];
                    $result['lab'][$j] = ucwords($data['facility_name']);
                    $j++;
                }
            }
        }
        return $result;
    }

    public function fetchLabSampleDetails($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];



        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = date("Y-m", strtotime(trim($params['fromDate']))) . "-01";
            //$endMonth = date("Y-m", strtotime(trim($params['toDate']))) . "-31";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $sQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "DBS" => new Expression("SUM(CASE WHEN ((vl.specimen_type=$this->dbsId AND (vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))) THEN 1 ELSE 0 END)"),
                        "Others" => new Expression("SUM(CASE WHEN ((vl.specimen_type!=$this->dbsId AND (vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array(), 'left')
                ->where(array("DATE(vl.sample_collection_date) <='$endMonth'", "DATE(vl.sample_collection_date) >='$startMonth'"));
            if (isset($params['lab']) && trim($params['lab']) != '') {
                $sQuery = $sQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
            }
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            }

            if (isset($params['currentRegimen']) && trim($params['currentRegimen']) != '') {
                $sQuery = $sQuery->where('vl.current_regimen="' . base64_decode(trim($params['currentRegimen'])) . '"');
            }

            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='" . $params['adherence'] . "'"));
            }

            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $where = '';
                $counter = count($age);
                for ($a = 0; $a < $counter; $a++) {
                    if (trim($where) != '') {
                        $where .= ' OR ';
                    }
                    if ($age[$a] == '<2') {
                        $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                    } elseif ($age[$a] == '2to5') {
                        $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    } elseif ($age[$a] == '6to14') {
                        $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    } elseif ($age[$a] == '15to49') {
                        $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    } elseif ($age[$a] == '>=50') {
                        $where .= "(vl.patient_age_in_years >= 50)";
                    } elseif ($age[$a] == 'unknown') {
                        $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                    }
                }
                $where = '(' . $where . ')';
                $sQuery = $sQuery->where($where);
            }
            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $sQuery = $sQuery->where("(vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $sQuery = $sQuery->where("vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            }
            if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                $sQuery = $sQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
            }
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            } elseif (isset($params['gender']) && $params['gender'] == 'M') {
                $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
            } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $sQuery = $sQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
            }
            if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
                $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
            } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
                $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'");
            } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
                $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
            }
            if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
                $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
            } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
                $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'");
            } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
                $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
            }
            $sQuery = $sQuery->group(array(new Expression('DATE(sample_collection_date)')));
            $sQuery = $sQuery->order('sample_collection_date ASC');

            $sQuery = $sql->buildSqlString($sQuery);
            $sampleResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $result['DBS'] = 0;
            $result['Others'] = 0;
            foreach ($sampleResult as $count) {
                $result['DBS'] += (isset($count['DBS'])) ? $count['DBS'] : 0;
                $result['Others'] += (isset($count['Others'])) ? $count['Others'] : 0;
            }
        }
        return $result;
    }

    public function fetchLabBarSampleDetails($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));

            $sQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        'samples' => new Expression('COUNT(*)'),
                        "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                        "GreaterThan1000" => new Expression("SUM(CASE WHEN (((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1 ELSE 0 END)"),
                        "LesserThan1000" => new Expression("SUM(CASE WHEN (( vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1 ELSE 0 END)"),
                    )
                )
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array(), 'left')
                //->where("Month(sample_collection_date)='".$month."' AND Year(sample_collection_date)='".$year."'")
            ;

            $sQuery = $sQuery->where(
                "
                                        (sample_collection_date is not null AND sample_collection_date not like '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'"
            );

            if (isset($params['lab']) && trim($params['lab']) != '') {
                $sQuery = $sQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            if (isset($params['provinces']) && trim($params['provinces']) != '') {
                $sQuery = $sQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
            }
            if (isset($params['districts']) && trim($params['districts']) != '') {
                $sQuery = $sQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
            }
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            }
            if (isset($params['currentRegimen']) && trim($params['currentRegimen']) != '') {
                $sQuery = $sQuery->where('vl.current_regimen="' . base64_decode(trim($params['currentRegimen'])) . '"');
            }
            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='" . $params['adherence'] . "'"));
            }
            //print_r($params['age']);die;
            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $where = '';
                $counter = count($age);
                for ($a = 0; $a < $counter; $a++) {
                    if (trim($where) != '') {
                        $where .= ' OR ';
                    }
                    if ($age[$a] == '<2') {
                        $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                    } elseif ($age[$a] == '2to5') {
                        $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                    } elseif ($age[$a] == '6to14') {
                        $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                    } elseif ($age[$a] == '15to49') {
                        $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                    } elseif ($age[$a] == '>=50') {
                        $where .= "(vl.patient_age_in_years >= 50)";
                    } elseif ($age[$a] == 'unknown') {
                        $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                    }
                }
                $where = '(' . $where . ')';
                $sQuery = $sQuery->where($where);
            }
            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $sQuery = $sQuery->where("(vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $sQuery = $sQuery->where("vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            }
            if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
                $sQuery = $sQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
            }
            if (isset($params['gender']) && $params['gender'] == 'F') {
                $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            } elseif (isset($params['gender']) && $params['gender'] == 'M') {
                $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
            } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
                $sQuery = $sQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
            }
            if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
                $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
            } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
                $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'");
            } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
                $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
            }
            if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
                $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
            } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
                $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'");
            } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
                $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
            }
            $sQuery = $sQuery->group(array(new Expression('MONTH(sample_collection_date)')));
            $sQuery = $sQuery->order('sample_collection_date ASC');
            $sQueryStr = $sql->buildSqlString($sQuery);
            $barChartResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            $j = 0;
            foreach ($barChartResult as $data) {
                $result['rslt']['VL (< 1000 cp/ml)'][$j] = $data['LesserThan1000'];
                $result['rslt']['VL (>= 1000 cp/ml)'][$j] = $data['GreaterThan1000'];
                $result['date'][$j] = $data['monthDate'];
                $j++;
            }


            // $j = 0;
            // while($start <= $end){
            //     $month = date('m', $start);$year = date('Y', $start);$monthYearFormat = date("M-Y", $start);
            //     $sQuery = $sql->select()->from(array('vl'=>$this->table))->columns(array('samples' => new Expression('COUNT(*)')))
            //                             ->join(array('f'=>'facility_details'),'f.facility_id=vl.lab_id',array(),'left')
            //                             ->where("Month(sample_collection_date)='".$month."' AND Year(sample_collection_date)='".$year."'");
            //     if(isset($params['lab']) && trim($params['lab'])!= ''){
            //         $sQuery = $sQuery->where('vl.lab_id IN ('.$params['lab'].')');
            //     }else{
            //         if($loginContainer->role!= 1){
            //             $mappedFacilities = (isset($loginContainer->mappedFacilities) && count($loginContainer->mappedFacilities) >0)?$loginContainer->mappedFacilities:[];
            //             $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
            //         }
            //     }
            //     if(isset($params['provinces']) && trim($params['provinces'])!= ''){
            //         $sQuery = $sQuery->where('f.facility_state_id IN (' . $params['provinces'].')');
            //     }
            //     if(isset($params['districts']) && trim($params['districts'])!= ''){
            //         $sQuery = $sQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
            //     }
            //     if(isset($params['clinicId']) && trim($params['clinicId'])!= ''){
            //         $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            //     }
            //     if(isset($params['currentRegimen']) && trim($params['currentRegimen'])!=''){
            //         $sQuery = $sQuery->where('vl.current_regimen="'.base64_decode(trim($params['currentRegimen'])).'"');
            //     }
            //     if(isset($params['adherence']) && trim($params['adherence'])!=''){
            //         $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='".$params['adherence']."'"));
            //     }
            //     //print_r($params['age']);die;
            //     if(isset($params['age']) && trim($params['age'])!= ''){
            //         $age = explode(',',$params['age']);
            //         $where = '';
            //         for($a=0;$a<count($age);$a++){
            //             if(trim($where)!= ''){ $where.= ' OR '; }
            //             if($age[$a] == '<2'){
            //               $where.= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
            //             }else if($age[$a] == '2to5') {
            //               $where.= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
            //             }else if($age[$a] == '6to14') {
            //               $where.= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
            //             }else if($age[$a] == '15to49') {
            //               $where.= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
            //             }else if($age[$a] == '>=50'){
            //               $where.= "(vl.patient_age_in_years >= 50)";
            //             }else if($age[$a] == 'unknown'){
            //               $where.= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
            //             }
            //         }
            //       $where = '('.$where.')';
            //       $sQuery = $sQuery->where($where);
            //     }
            //     if(isset($params['testResult']) && $params['testResult'] == '<1000'){
            //         $sQuery = $sQuery->where("(vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            //     }else if(isset($params['testResult']) && $params['testResult'] == '>=1000') {
            //         $sQuery = $sQuery->where("vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            //     }
            //     if(isset($params['sampleType']) && trim($params['sampleType'])!=''){
            //         $sQuery = $sQuery->where('vl.specimen_type="'.base64_decode(trim($params['sampleType'])).'"');
            //     }
            //     if(isset($params['gender']) && $params['gender']=='F'){
            //         $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
            //     }else if(isset($params['gender']) && $params['gender']=='M'){
            //         $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
            //     }else if(isset($params['gender']) && $params['gender']=='not_specified'){
            //         $sQuery = $sQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
            //     }
            //     if(isset($params['isPregnant']) && $params['isPregnant']=='yes'){
            //         $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
            //     }else if(isset($params['isPregnant']) && $params['isPregnant']=='no'){
            //         $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'");
            //     }else if(isset($params['isPregnant']) && $params['isPregnant']=='unreported'){
            //         $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
            //     }
            //     if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='yes'){
            //         $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
            //     }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='no'){
            //         $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'");
            //     }else if(isset($params['isBreastfeeding']) && $params['isBreastfeeding']=='unreported'){
            //         $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
            //     }
            //     $sQueryStr = $sql->buildSqlString($sQuery);
            //     //echo $sQueryStr;die;
            //     $lessResult = $dbAdapter->query($sQueryStr." AND (vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
            //     $result['rslt']['VL (< 1000 cp/ml)'][$j] = (isset($lessResult->samples))?$lessResult->samples:0;

            //     $greaterResult = $dbAdapter->query($sQueryStr." AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
            //     $result['rslt']['VL (>= 1000 cp/ml)'][$j] = (isset($greaterResult->samples))?$greaterResult->samples:0;

            //     //$notTargetResult = $dbAdapter->query($sQueryStr." AND 'vl.result'='Target Not Detected'", $dbAdapter::QUERY_MODE_EXECUTE)->current();
            //     //$result['rslt']['VL Not Detected'][$j] = $notTargetResult->samples;
            //     $result['date'][$j] = $monthYearFormat;
            //     $start = strtotime("+1 month", $start);
            //   $j++;
            // }
        }
        return $result;
    }

    public function fetchLabFilterSampleDetails($parameters)
    {
        $loginContainer = new Container('credo');
        $queryContainer = new Container('query');

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('DATE_FORMAT(sample_collection_date,"%d-%b-%Y")', 'sample_name', 'facility_name');
        $orderColumns = array('sample_collection_date', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'sample_name', 'facility_name');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array(
                'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                "total_samples_received" => new Expression("(COUNT(*))"),
                "total_samples_tested" => new Expression("(SUM(CASE WHEN (((vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                "total_samples_pending" => new Expression("(SUM(CASE WHEN ((vl.result IS NULL OR vl.result = '' OR vl.result = 'NULL' OR sample_tested_datetime is null OR sample_tested_datetime like '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                "suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl' or vl.result like '%baixo%' or vl.result like 'Negative' or vl.result like 'NEGAT' or vl.result like 'Indeterminado' or vl.result like 'NON DETECTEE' or vl.result like '<40' or vl.result like '< 40' or vl.result like '<20' or vl.result like'< 20') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)"),
                "not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)"),
                "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)")
            ))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array(), 'left')
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND f.facility_type = 1")
            ->group(new Expression('DATE(sample_collection_date)'))
            ->group('vl.specimen_type')
            ->group('vl.facility_id');
        //filter start
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
        }
        if (isset($parameters['lab']) && trim($parameters['lab']) != '') {
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['lab'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        if (isset($parameters['provinces']) && trim($parameters['provinces']) != '') {
            $sQuery = $sQuery->where('l.facility_state IN (' . $parameters['provinces'] . ')');
        }
        if (isset($parameters['districts']) && trim($parameters['districts']) != '') {
            $sQuery = $sQuery->where('l.facility_district IN (' . $parameters['districts'] . ')');
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
        }
        if (isset($parameters['currentRegimen']) && trim($parameters['currentRegimen']) != '') {
            $sQuery = $sQuery->where('vl.current_regimen="' . base64_decode(trim($parameters['currentRegimen'])) . '"');
        }
        if (isset($parameters['adherence']) && trim($parameters['adherence']) != '') {
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='" . $parameters['adherence'] . "'"));
        }
        if (isset($parameters['age']) && trim($parameters['age']) != '') {
            $age = explode(',', $parameters['age']);
            $where = '';
            $counter = count($age);
            for ($a = 0; $a < $counter; $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                } elseif ($age[$a] == '2to5') {
                    $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                } elseif ($age[$a] == '6to14') {
                    $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                } elseif ($age[$a] == '15to49') {
                    $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                } elseif ($age[$a] == '>=50') {
                    $where .= "(vl.patient_age_in_years >= 50)";
                } elseif ($age[$a] == 'unknown') {
                    $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                }
            }
            $where = '(' . $where . ')';
            $sQuery = $sQuery->where($where);
        }
        if (isset($parameters['testResult']) && $parameters['testResult'] == '<1000') {
            $sQuery = $sQuery->where("(vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
        } elseif (isset($parameters['testResult']) && $parameters['testResult'] == '>=1000') {
            $sQuery = $sQuery->where("vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
        }
        if (isset($parameters['sampleType']) && trim($parameters['sampleType']) != '') {
            $sQuery = $sQuery->where('vl.specimen_type="' . base64_decode(trim($parameters['sampleType'])) . '"');
        }
        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        } elseif (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        } elseif (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
        }
        if (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'yes') {
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
        } elseif (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'no') {
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'");
        } elseif (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'unreported') {
            $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
        }
        if (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'yes') {
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
        } elseif (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'no') {
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'");
        } elseif (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'unreported') {
            $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
        }
        //filter end
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }
        $queryContainer->labTestedSampleQuery = $sQuery;
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
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array(

                "total_samples_received" => new Expression("(COUNT(*))")
            ))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array(), 'left')
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND f.facility_type = 1")
            ->group(new Expression('DATE(sample_collection_date)'))
            ->group('vl.specimen_type')
            ->group('vl.facility_id');
        if ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = [];
            $sampleCollectionDate = '';
            if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != null && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                $sampleCollectionDate = \Application\Service\CommonService::humanReadableDateFormat($aRow['sampleCollectionDate']);
            }
            $row[] = $sampleCollectionDate;
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['suppressed_samples'];
            $row[] = $aRow['not_suppressed_samples'];
            $row[] = $aRow['rejected_samples'];
            $row[] = ucwords($aRow['sample_name']);
            $row[] = ucwords($aRow['facility_name']);
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchFilterSampleDetails($parameters)
    {
        $loginContainer = new Container('credo');
        $queryContainer = new Container('query');

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('facility_name', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id');
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
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
        }
        $sQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('vl' => $this->table), 'vl.lab_id=f.facility_id', array(
                "total_samples_received" => new Expression("(COUNT(*))"),
                "total_samples_tested" => new Expression("(SUM(CASE WHEN (((vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                "total_samples_pending" => new Expression("(SUM(CASE WHEN ((vl.result IS NULL OR vl.result = '' OR vl.result = 'NULL' OR sample_tested_datetime is null OR sample_tested_datetime like '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                "suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl' or vl.result like '%baixo%' or vl.result like 'Negative' or vl.result like 'NEGAT' or vl.result like 'Indeterminado' or vl.result like 'NON DETECTEE' or vl.result like '<40' or vl.result like '< 40' or vl.result like '<20' or vl.result like'< 20') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)"),
                "not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)"),
                "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)")
            ))
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND vl.lab_id !=0")
            ->group('vl.lab_id');
        if (isset($parameters['provinces']) && trim($parameters['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state_id IN (' . $parameters['provinces'] . ')');
        }
        if (isset($parameters['districts']) && trim($parameters['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district_id IN (' . $parameters['districts'] . ')');
        }
        if (isset($parameters['lab']) && trim($parameters['lab']) != '') {
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['lab'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $startMonth . " 00:00:00" . "'", "vl.sample_collection_date <='" . $endMonth . " 23:59:59" . "'"));
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
        }
        if (isset($parameters['currentRegimen']) && trim($parameters['currentRegimen']) != '') {
            $sQuery = $sQuery->where('vl.current_regimen="' . base64_decode(trim($parameters['currentRegimen'])) . '"');
        }
        if (isset($parameters['adherence']) && trim($parameters['adherence']) != '') {
            $sQuery = $sQuery->where(array("vl.arv_adherance_percentage ='" . $parameters['adherence'] . "'"));
        }
        if (isset($parameters['age']) && trim($parameters['age']) != '') {
            $where = '';
            $parameters['age'] = explode(',', $parameters['age']);
            $counter = count($parameters['age']);
            for ($a = 0; $a < $counter; $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($parameters['age'][$a] == '<2') {
                    $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                } elseif ($parameters['age'][$a] == '2to5') {
                    $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                } elseif ($parameters['age'][$a] == '6to14') {
                    $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                } elseif ($parameters['age'][$a] == '15to49') {
                    $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                } elseif ($parameters['age'][$a] == '>=50') {
                    $where .= "(vl.patient_age_in_years >= 50)";
                } elseif ($parameters['age'][$a] == 'unknown') {
                    $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                }
            }
            $where = '(' . $where . ')';
            $sQuery = $sQuery->where($where);
        }
        if (isset($parameters['testResult']) && $parameters['testResult'] == '<1000') {
            $sQuery = $sQuery->where("(vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
        } elseif (isset($parameters['testResult']) && $parameters['testResult'] == '>=1000') {
            $sQuery = $sQuery->where("vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
        }
        if (isset($parameters['sampleType']) && trim($parameters['sampleType']) != '') {
            $sQuery = $sQuery->where('vl.specimen_type="' . base64_decode(trim($parameters['sampleType'])) . '"');
        }
        if (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'sample_tested') {
            $sQuery = $sQuery->where("((vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))");
        } elseif (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'samples_not_tested') {
            $sQuery = $sQuery->where("(vl.result IS NULL OR vl.result = '' OR vl.result = 'NULL' OR sample_tested_datetime is null OR sample_tested_datetime like '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)");
        } elseif (isset($parameters['sampleStatus']) && $parameters['sampleStatus'] == 'sample_rejected') {
            $sQuery = $sQuery->where("vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0");
        }
        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        } elseif (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        } elseif (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
        }
        if (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'yes') {
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
        } elseif (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'no') {
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'");
        } elseif (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'unreported') {
            $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
        }
        if (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'yes') {
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
        } elseif (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'no') {
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'");
        } elseif (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'unreported') {
            $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
        }
        if (isset($parameters['lineOfTreatment']) && $parameters['lineOfTreatment'] == '1') {
            $sQuery = $sQuery->where("vl.line_of_treatment = '1'");
        } elseif (isset($parameters['lineOfTreatment']) && $parameters['lineOfTreatment'] == '2') {
            $sQuery = $sQuery->where("vl.line_of_treatment = '2'");
        } elseif (isset($parameters['lineOfTreatment']) && $parameters['lineOfTreatment'] == '3') {
            $sQuery = $sQuery->where("vl.line_of_treatment = '3'");
        } elseif (isset($parameters['lineOfTreatment']) && $parameters['lineOfTreatment'] == 'not_specified') {
            $sQuery = $sQuery->where("(vl.line_of_treatment IS NULL OR vl.line_of_treatment = '' OR vl.line_of_treatment = '0')");
        }
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }
        $queryContainer->sampleResultQuery = $sQuery;
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
        $iQuery = $sql->select()->from(array('f' => 'facility_details'))
            ->join(array('vl' => $this->table), 'vl.lab_id=f.facility_id', array(
                "total_samples_received" => new Expression("(COUNT(*))")
            ))
            ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00' AND vl.lab_id !=0")
            ->group('vl.lab_id');
        if ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        //print_r($parameters);die;
        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = ucwords($aRow['facility_name']);
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['suppressed_samples'];
            $row[] = $aRow['not_suppressed_samples'];
            $row[] = $aRow['rejected_samples'];
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchFilterSampleTatDetails($parameters)
    {
        $loginContainer = new Container('credo');
        $queryContainer = new Container('query');

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array("DATE_FORMAT(sample_collection_date,'%b-%Y')");
        $orderColumns = array('sample_collection_date', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id', 'vl_sample_id');
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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = date("Y-m", strtotime(trim($parameters['fromDate'])));
            $endMonth = date("Y-m", strtotime(trim($parameters['toDate'])));
        }
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                    "total_samples_pending" => new Expression("(SUM(CASE WHEN (sample_tested_datetime is null OR sample_tested_datetime like '' OR DATE(sample_tested_datetime) ='1970-01-01' OR DATE(sample_tested_datetime) ='0000-00-00' OR DATE(sample_tested_datetime) ='0') THEN 1 ELSE 0 END))"),
                    "suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)"),
                    "not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                    "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                    "AvgDiff" => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_tested_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array(), 'left');
        if (isset($parameters['provinces']) && trim($parameters['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state_id IN (' . $parameters['provinces'] . ')');
        }
        if (isset($parameters['districts']) && trim($parameters['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district_id IN (' . $parameters['districts'] . ')');
        }
        if (isset($parameters['lab']) && trim($parameters['lab']) != '') {
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['lab'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = (!empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : array();
            $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
        }
        if (isset($parameters['sampleType']) && trim($parameters['sampleType']) != '') {
            $sQuery = $sQuery->where('vl.specimen_type="' . base64_decode(trim($parameters['sampleType'])) . '"');
        }
        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        } elseif (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        } elseif (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("vl.patient_gender NOT IN ('f','female','F','FEMALE','m','male','M','MALE')");
        }
        if (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'yes') {
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
        } elseif (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'no') {
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'");
        } elseif (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'unreported') {
            $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
        }
        if (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'yes') {
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
        } elseif (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'no') {
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'");
        } elseif (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'unreported') {
            $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
        }
        $sQuery = $sQuery->where("
                (sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) not like '1970-01-01' AND DATE(sample_collection_date) not like '0000-00-00')
                AND (sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) not like '1970-01-01' AND DATE(sample_tested_datetime) not like '0000-00-00')
                AND DATE(sample_collection_date) >= '" . $startMonth . "-01'
                AND DATE(sample_collection_date) <= '" . $endMonth . "-31'
                AND vl.result IS NOT NULL
                AND vl.result != ''
                AND vl.result != 'NULL'");

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
        $sQuery = $sQuery->group(array(new Expression('MONTH(sample_collection_date)')));
        $sQuery = $sQuery->order('sample_collection_date ASC');

        $queryContainer->sampleResultTestedTATQuery = $sQuery;
        $queryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $queryStr;die;
        $rResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(

                    "total_samples_received" => new Expression("(COUNT(*))")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array(), 'left');
        if ($loginContainer->role != 1) {
            $mappedFacilities = (!empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : array();
            $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', array_values(array_filter($mappedFacilities))) . '")');
        }
        $iQuery = $iQuery->group(array(new Expression('MONTH(sample_collection_date)')));
        $iQuery = $iQuery->order('sample_collection_date ASC');
        $iQueryStr = $sql->buildSqlString($iQuery);
        //error_log($iQueryStr);die;
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);
        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['monthDate'];
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['suppressed_samples'];
            $row[] = $aRow['not_suppressed_samples'];
            $row[] = $aRow['rejected_samples'];
            $row[] = (isset($aRow['AvgDiff'])) ? round($aRow['AvgDiff'], 2) : 0;
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchProvinceWiseResultAwaitedDrillDown($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }

        $p = 0;

        $countQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((vl.vl_result_category is NULL OR vl.vl_result_category = '')
                                                    AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0))) THEN 1
                                                            ELSE 0
                                                        END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array())
            ->join(array('p' => 'geographical_divisions'), 'p.geo_id=f.facility_state_id', array('province_name' => 'geo_name', 'geo_id'), 'left')
            ->group('p.geo_id');
        if (isset($params['lab']) && trim($params['lab']) != '') {
            $countQuery = $countQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $countQuery = $countQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $countQuery = $countQuery->where('p.geo_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $countQuery = $countQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
            $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "vl.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } elseif (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
            $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
        } elseif (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
            $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
        }
        if (isset($params['currentRegimen']) && trim($params['currentRegimen']) != '') {
            $countQuery = $countQuery->where('vl.current_regimen="' . base64_decode(trim($params['currentRegimen'])) . '"');
        }
        //print_r($params['age']);die;
        if (isset($params['age']) && trim($params['age']) != '') {
            $age = explode(',', $params['age']);
            $where = '';
            $counter = count($age);
            for ($a = 0; $a < $counter; $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                } elseif ($age[$a] == '2to5') {
                    $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                } elseif ($age[$a] == '6to14') {
                    $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                } elseif ($age[$a] == '15to49') {
                    $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                } elseif ($age[$a] == '>=50') {
                    $where .= "(vl.patient_age_in_years >= 50)";
                } elseif ($age[$a] == 'unknown') {
                    $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'unreported' OR vl.patient_age_in_years = 'Unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        } elseif (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded' OR vl.patient_gender = 'Unreported' OR vl.patient_gender = 'unreported')");
        }
        if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
            $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
        } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
            $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
        } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
            $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
        }
        if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
            $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
        } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
            $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'");
        } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
            $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
        }
        $countQueryStr = $sql->buildSqlString($countQuery);
        //echo $countQueryStr;die;
        $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($countResult as $row) {
            $result['province'][$p] = ($row['province_name'] != null && trim($row['province_name']) != '') ? ($row['province_name']) : 'Not Specified';
            $result['sample']['Results Not Available'][$p] = (isset($row['total'])) ? $row['total'] : 0;
            $p++;
        }
        return $result;
    }
    public function fetchDistrictWiseResultAwaitedDrillDown($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }

        $p = 0;

        $countQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((vl.vl_result_category is NULL OR vl.vl_result_category = '')
                                                    AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0))) THEN 1
                                                            ELSE 0
                                                        END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array())
            ->join(array('d' => 'geographical_divisions'), 'd.geo_id=f.facility_district_id', array('district_name' => 'geo_name', 'geo_id'), 'left')
            ->order('total DESC')
            ->group('d.geo_id');
        if (isset($params['lab']) && trim($params['lab']) != '') {
            $countQuery = $countQuery->where('vl.lab_id IN (' . $params['lab'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $countQuery = $countQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $countQuery = $countQuery->where('p.geo_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $countQuery = $countQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
            $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "vl.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } elseif (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
            $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
        } elseif (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
            $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
        }
        if (isset($params['currentRegimen']) && trim($params['currentRegimen']) != '') {
            $countQuery = $countQuery->where('vl.current_regimen="' . base64_decode(trim($params['currentRegimen'])) . '"');
        }
        //print_r($params['age']);die;
        if (isset($params['age']) && trim($params['age']) != '') {
            $age = explode(',', $params['age']);
            $where = '';
            $counter = count($age);
            for ($a = 0; $a < $counter; $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                } elseif ($age[$a] == '2to5') {
                    $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                } elseif ($age[$a] == '6to14') {
                    $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                } elseif ($age[$a] == '15to49') {
                    $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                } elseif ($age[$a] == '>=50') {
                    $where .= "(vl.patient_age_in_years >= 50)";
                } elseif ($age[$a] == 'unknown') {
                    $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'unreported' OR vl.patient_age_in_years = 'Unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        } elseif (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded' OR vl.patient_gender = 'Unreported' OR vl.patient_gender = 'unreported')");
        }
        if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
            $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
        } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
            $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
        } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
            $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
        }
        if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
            $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
        } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
            $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'");
        } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
            $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
        }
        $countQueryStr = $sql->buildSqlString($countQuery);
        //echo $countQueryStr;die;
        $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($countResult as $row) {
            $result['district'][$p] = ($row['district_name'] != null && trim($row['district_name']) != '') ? ($row['district_name']) : 'Not Specified';
            $result['sample']['Results Not Available'][$p] = (isset($row['total'])) ? $row['total'] : 0;
            $p++;
        }
        return $result;
    }

    public function fetchLabWiseResultAwaitedDrillDown($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }

        $l = 0;

        $countQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((vl.vl_result_category is NULL OR vl.vl_result_category ='') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0))) THEN 1
                                                                                 ELSE 0
                                                                                 END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.lab_id', array('lab_name' => 'facility_name'))
            ->order('total DESC')
            ->group(array('vl.lab_id'));

        if (isset($params['lab']) && trim($params['lab']) != '') {
            $countQuery = $countQuery->where('f.facility_id IN (' . $params['lab'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $countQuery = $countQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $countQuery = $countQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $countQuery = $countQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
            $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "vl.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } elseif (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
            $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
        } elseif (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
            $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
        }
        if (isset($params['currentRegimen']) && trim($params['currentRegimen']) != '') {
            $countQuery = $countQuery->where('vl.current_regimen="' . base64_decode(trim($params['currentRegimen'])) . '"');
        }
        //print_r($params['age']);die;
        if (isset($params['age']) && trim($params['age']) != '') {
            $age = explode(',', $params['age']);
            $where = '';
            $counter = count($age);
            for ($a = 0; $a < $counter; $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                } elseif ($age[$a] == '2to5') {
                    $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                } elseif ($age[$a] == '6to14') {
                    $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                } elseif ($age[$a] == '15to49') {
                    $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                } elseif ($age[$a] == '>=50') {
                    $where .= "(vl.patient_age_in_years >= 50)";
                } elseif ($age[$a] == 'unknown') {
                    $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        } elseif (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded' OR vl.patient_gender = 'unreported' OR vl.patient_gender = 'Unreported')");
        }
        if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
            $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
        } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
            $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
        } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
            $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
        }
        if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
            $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
        } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
            $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'");
        } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
            $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
        }
        $countQueryStr = $sql->buildSqlString($countQuery);
        //echo $countQueryStr;die;
        $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($countResult as $lab) {
            $result['lab'][$l] = ucwords($lab['lab_name']);
            $result['sample']['Results Not Available'][$l] = (isset($lab['total'])) ? $lab['total'] : 0;
            $l++;
        }

        return $result;
    }

    public function fetchClinicWiseResultAwaitedDrillDown($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];

        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        if (isset($params['daterange']) && trim($params['daterange']) != '') {
            $splitDate = explode('to', $params['daterange']);
        }

        $l = 0;

        $countQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array("total" => new Expression("SUM(CASE WHEN (((vl.vl_result_category is NULL OR vl.vl_result_category ='') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0))) THEN 1
                                                                                 ELSE 0
                                                                                 END)"))
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('clinic_name' => 'facility_name'))
            ->order('total DESC')
            ->group(array('vl.facility_id'));

        if (isset($params['lab']) && trim($params['lab']) != '') {
            $countQuery = $countQuery->where('f.facility_id IN (' . $params['lab'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $countQuery = $countQuery->where('f.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $countQuery = $countQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $countQuery = $countQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
            $countQuery = $countQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
        }
        if (isset($params['daterange']) && trim($params['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $countQuery = $countQuery->where(array("vl.sample_collection_date >='" . trim($splitDate[0]) . " 00:00:00" . "'", "vl.sample_collection_date <='" . trim($splitDate[1]) . " 23:59:59" . "'"));
        } elseif (isset($params['frmSource']) && trim($params['frmSource']) == '<') {
            $countQuery = $countQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
        } elseif (isset($params['frmSource']) && trim($params['frmSource']) == '>') {
            $countQuery = $countQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
        }
        if (isset($params['currentRegimen']) && trim($params['currentRegimen']) != '') {
            $countQuery = $countQuery->where('vl.current_regimen="' . base64_decode(trim($params['currentRegimen'])) . '"');
        }
        //print_r($params['age']);die;
        if (isset($params['age']) && trim($params['age']) != '') {
            $age = explode(',', $params['age']);
            $where = '';
            $counter = count($age);
            for ($a = 0; $a < $counter; $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($age[$a] == '<2') {
                    $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                } elseif ($age[$a] == '2to5') {
                    $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                } elseif ($age[$a] == '6to14') {
                    $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                } elseif ($age[$a] == '15to49') {
                    $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                } elseif ($age[$a] == '>=50') {
                    $where .= "(vl.patient_age_in_years >= 50)";
                } elseif ($age[$a] == 'unknown') {
                    $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported')";
                }
            }
            $where = '(' . $where . ')';
            $countQuery = $countQuery->where($where);
        }
        if (isset($params['sampleType']) && trim($params['sampleType']) != '') {
            $countQuery = $countQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleType'])) . '"');
        }

        if (isset($params['gender']) && $params['gender'] == 'F') {
            $countQuery = $countQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        } elseif (isset($params['gender']) && $params['gender'] == 'M') {
            $countQuery = $countQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
            $countQuery = $countQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded' OR vl.patient_gender = 'unreported' OR vl.patient_gender = 'Unreported')");
        }
        if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
            $countQuery = $countQuery->where("vl.is_patient_pregnant = 'yes'");
        } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
            $countQuery = $countQuery->where("vl.is_patient_pregnant = 'no'");
        } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
            $countQuery = $countQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
        }
        if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
            $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'yes'");
        } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
            $countQuery = $countQuery->where("vl.is_patient_breastfeeding = 'no'");
        } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
            $countQuery = $countQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
        }
        $countQueryStr = $sql->buildSqlString($countQuery);
        //echo $countQueryStr;die;
        $countResult  = $dbAdapter->query($countQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);

        foreach ($countResult as $facility) {
            $result['clinic'][$l] = ucwords($facility['clinic_name']);
            $result['sample']['Results Not Available'][$l] = (isset($facility['total'])) ? $facility['total'] : null;
            $l++;
        }

        return $result;
    }


    public function fetchFilterSampleResultAwaitedDetails($parameters)
    {
        $loginContainer = new Container('credo');
        $queryContainer = new Container('query');


        $globalDb = $this->sm->get('GlobalTable');
        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('sample_code', "DATE_FORMAT(sample_collection_date,'%d-%b-%Y')", 'f.facility_code', 'f.facility_name', 'sample_name', 'l.facility_code', 'l.facility_name', "DATE_FORMAT(sample_received_at_lab_datetime,'%d-%b-%Y')");
        $orderColumns = array('sample_code', 'sample_collection_date', 'f.facility_code', 'sample_name', 'l.facility_name', 'sample_received_at_lab_datetime');
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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        if (isset($parameters['daterange']) && trim($parameters['daterange']) != '') {
            $splitDate = explode('to', $parameters['daterange']);
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('sample_code', 'collectionDate' => new Expression('DATE(sample_collection_date)'), 'receivedDate' => new Expression('DATE(sample_received_at_lab_datetime)')))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facilityName' => 'facility_name', 'facilityCode' => 'facility_code'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array('labName' => 'facility_name'), 'left')
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'), 'left')
            ->where("(vl.vl_result_category is NULL OR vl.vl_result_category ='') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0)");
        if (isset($parameters['daterange']) && trim($parameters['daterange']) != '' && trim($splitDate[0]) != '' && trim($splitDate[1]) != '') {
            $sQuery = $sQuery->where(array("vl.sample_collection_date >='" . $splitDate[0] . " 00:00:00" . "'", "vl.sample_collection_date <='" . $splitDate[1] . " 23:59:59" . "'"));
        } elseif (isset($parameters['frmSource']) && trim($parameters['frmSource']) == '<') {
            $sQuery = $sQuery->where("(vl.sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
        } elseif (isset($parameters['frmSource']) && trim($parameters['frmSource']) == '>') {
            $sQuery = $sQuery->where("(vl.sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH))");
        }
        if (isset($parameters['provinces']) && trim($parameters['provinces']) != '') {
            $sQuery = $sQuery->where('l.facility_state IN (' . $parameters['provinces'] . ')');
        }
        if (isset($parameters['districts']) && trim($parameters['districts']) != '') {
            $sQuery = $sQuery->where('l.facility_district IN (' . $parameters['districts'] . ')');
        }
        if (isset($parameters['lab']) && trim($parameters['lab']) != '') {
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['lab'] . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $sQuery = $sQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
        }
        if (isset($parameters['currentRegimen']) && trim($parameters['currentRegimen']) != '') {
            $sQuery = $sQuery->where('vl.current_regimen="' . base64_decode(trim($parameters['currentRegimen'])) . '"');
        }
        //print_r($parameters['age']);die;
        if (isset($parameters['age']) && trim($parameters['age']) != '') {
            $where = '';
            $parameters['age'] = explode(',', $parameters['age']);
            $counter = count($parameters['age']);
            for ($a = 0; $a < $counter; $a++) {
                if (trim($where) != '') {
                    $where .= ' OR ';
                }
                if ($parameters['age'][$a] == '<2') {
                    $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                } elseif ($parameters['age'][$a] == '2to5') {
                    $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                } elseif ($parameters['age'][$a] == '6to14') {
                    $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                } elseif ($parameters['age'][$a] == '15to49') {
                    $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                } elseif ($parameters['age'][$a] == '>=50') {
                    $where .= "(vl.patient_age_in_years >= 50)";
                } elseif ($parameters['age'][$a] == 'unknown') {
                    $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported')";
                }
            }
            $where = '(' . $where . ')';
            $sQuery = $sQuery->where($where);
        }
        if (isset($parameters['sampleType']) && trim($parameters['sampleType']) != '') {
            $sQuery = $sQuery->where('vl.specimen_type="' . base64_decode(trim($parameters['sampleType'])) . '"');
        }

        if (isset($parameters['gender']) && $parameters['gender'] == 'F') {
            $sQuery = $sQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
        } elseif (isset($parameters['gender']) && $parameters['gender'] == 'M') {
            $sQuery = $sQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
        } elseif (isset($parameters['gender']) && $parameters['gender'] == 'not_specified') {
            $sQuery = $sQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded' OR vl.patient_gender = 'unreported' OR vl.patient_gender = 'Unreported')");
        }
        if (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'yes') {
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'yes'");
        } elseif (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'no') {
            $sQuery = $sQuery->where("vl.is_patient_pregnant = 'no'");
        } elseif (isset($parameters['isPregnant']) && $parameters['isPregnant'] == 'unreported') {
            $sQuery = $sQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
        }
        if (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'yes') {
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'yes'");
        } elseif (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'no') {
            $sQuery = $sQuery->where("vl.is_patient_breastfeeding = 'no'");
        } elseif (isset($parameters['isBreastfeeding']) && $parameters['isBreastfeeding'] == 'unreported') {
            $sQuery = $sQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
        }

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

        $queryContainer->resultsAwaitedQuery = $sQuery;
        $queryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $queryStr;die;
        $rResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('sample_code', 'collectionDate' => new Expression('DATE(sample_collection_date)'), 'receivedDate' => new Expression('DATE(sample_received_at_lab_datetime)')))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facilityName' => 'facility_name', 'facilityCode' => 'facility_code'))
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array('labName' => 'facility_name'), 'left')
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'), 'left')
            ->where("(vl.vl_result_category is NULL OR vl.vl_result_category ='') AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or vl.reason_for_sample_rejection = 0)");
        if ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $iQuery = $iQuery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        //error_log($iQueryStr);die;
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);
        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $displayCollectionDate = CommonService::humanReadableDateFormat($aRow['collectionDate']);
            $displayReceivedDate = CommonService::humanReadableDateFormat($aRow['receivedDate']);
            $row = [];
            $row[] = $aRow['sample_code'];
            $row[] = $displayCollectionDate;
            $row[] = $aRow['facilityCode'] . ' - ' . ucwords($aRow['facilityName']);
            $row[] = (isset($aRow['sample_name'])) ? ucwords($aRow['sample_name']) : '';
            $row[] = (isset($aRow['labName'])) ? ucwords($aRow['labName']) : '';
            $row[] = $displayReceivedDate;
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function getMonthsByQuarter($quarter)
    {
        switch ($quarter) {
            case 1:
                return array('01', '02', '03');
            case 2:
                return array('04', '05', '06');
            case 3:
                return array('07', '08', '09');
            case 4:
                return array(10, 11, 12);
        }
    }

    public function getSampleStatusDataTable($parameters)
    {

        $loginContainer = new Container('credo');
        $queryContainer = new Container('query');


        //$parameters['quarter'] = 4;
        //$parameters['year'] = 2018;
        if (isset($parameters['quarter']) && $parameters['quarter'] != "") {
            $quarterArray = $this->getMonthsByQuarter($parameters['quarter']);
            $quarters = implode(",", $quarterArray);
        } else {
            $currentQuarter = ceil((date('n') - 1) / 3);
            $quarterArray = $this->getMonthsByQuarter($currentQuarter);
            $quarters = implode(",", $quarterArray);
        }

        $year = isset($parameters['year']) && $parameters['year'] != "" ? $parameters['year'] : date('Y');

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "monthyear" => new Expression("DATE_FORMAT(sample_collection_date, '%m %Y')"),
                    //"total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN (vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') THEN 1 ELSE 0 END))"),
                    //"total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_hvl_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' )) THEN 1 ELSE 0 END)"),
                    //"total_lvl_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'), 'left')
            ->join(array('l' => 'facility_details'), 'l.facility_id=vl.lab_id', array('lab_name' => 'facility_name'), 'left')
            ->join(array('f_p_l_d' => 'geographical_divisions'), 'f_p_l_d.geo_id=f.facility_state_id', array('province' => 'geo_name'), 'left')
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'), 'left')
            //->join(array('rs'=>'r_vl_sample_type'),'rs.sample_id=vl.specimen_type',array('sample_name'),'left')
            ->where(array(
                "MONTH(vl.sample_collection_date) in ($quarters)",
                "YEAR(vl.sample_collection_date) = $year"
            ))
            ->group(array(new Expression("DATE_FORMAT(sample_collection_date, '%m %Y')"), 'f.facility_id'));

        if (isset($parameters['labID']) && trim($parameters['labID']) != '') {
            $sQuery = $sQuery->where('vl.lab_id IN (' . $parameters['labID'] . ')');
        }
        if (isset($parameters['clinicId']) && trim($parameters['clinicId']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $parameters['clinicId'] . ')');
        }
        // if (isset($sWhere) && $sWhere != "") {
        //     $sQuery->where($sWhere);
        // }


        $sQuery->order(array("facility_name ASC", "sample_collection_date ASC"));

        $queryContainer->sampleStatusResultQuery = $sQuery;

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        $output = [];

        $months = [];

        foreach ($quarterArray as $m) {
            $output['months'][] = $m . " " . $year;
        }


        foreach ($rResult as $aRow) {
            $output['data'][$aRow['facility_name']]['province'] = $aRow['province'];
            $output['data'][$aRow['facility_name']]['district'] = $aRow['district'];
            $output['data'][$aRow['facility_name']]['months'][$aRow['monthyear']] = array(
                'samples_tested' => $aRow['total_samples_tested'],
                'hvl' => $aRow['total_hvl_samples']
            );
        }
        //echo "<pre>";
        //var_dump($output);die;
        return $output;
    }

    public function fetchAllSamples($parameters)
    {
        $loginContainer = new Container('credo');
        $queryContainer = new Container('query');
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('sample_code', 'DATE_FORMAT(sample_collection_date,"%d-%b-%Y")', 'batch_code', 'patient_art_no', 'patient_first_name', 'patient_last_name', 'facility_name', 'f_p_l_d.geo_name', 'f_d_l_d.geo_name', 'sample_name', 'result', 'status_name');
        $orderColumns = array('vl_sample_id', 'sample_code', 'sample_collection_date', 'batch_code', 'patient_art_no', 'patient_first_name', 'facility_name', 'f_p_l_d.geo_name', 'f_d_l_d.geo_name', 'sample_name', 'result', 'status_name');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $startDate = '';
        $endDate = '';
        if (isset($parameters['sampleCollectionDate']) && trim($parameters['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $parameters['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
        }
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('vl_sample_id', 'sample_code', 'facility_id', 'patient_first_name', 'patient_last_name', 'patient_art_no', 'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'), 'result'))
            ->join(array('rss' => 'r_sample_status'), 'rss.status_id=vl.result_status', array('status_name'))
            ->join(array('b' => 'batch_details'), 'b.batch_id=vl.sample_batch_id', array('batch_code'), 'left')
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'), 'left')
            ->join(array('f_p_l_d' => 'geographical_divisions'), 'f_p_l_d.geo_id=f.facility_state_id', array('province' => 'geo_name'), 'left')
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'), 'left')
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'), 'left')
            //->group('sample_code')
            //->group('facility_id')
            //->having('COUNT(*) > 1');
            ->where("sample_code in (select sample_code from " . $this->table . " group by sample_code,facility_id having count(*) > 1)");
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }
        $queryContainer->resultQuery = $sQuery;
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('vl_sample_id'))
            ->join(array('rss' => 'r_sample_status'), 'rss.status_id=vl.result_status', array('status_name'))
            ->join(array('b' => 'batch_details'), 'b.batch_id=vl.sample_batch_id', array('batch_code'), 'left')
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'), 'left')
            ->join(array('f_p_l_d' => 'geographical_divisions'), 'f_p_l_d.geo_id=f.facility_state_id', array('province' => 'geo_name'), 'left')
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'), 'left')
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'), 'left')
            //->group('sample_code')
            //->group('facility_id')
            //->having('COUNT(*) > 1');
            ->where('sample_code in (select sample_code from  ' . $this->table . '  group by sample_code,facility_id having count(*) > 1)');
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );


        $buttText = $this->commonService->translate('Edit');
        foreach ($rResult as $aRow) {
            $sampleCollectionDate = '';
            if (isset($aRow['sampleCollectionDate']) && $aRow['sampleCollectionDate'] != NULL && trim($aRow['sampleCollectionDate']) != "" && $aRow['sampleCollectionDate'] != '0000-00-00') {
                $sampleCollectionDate = CommonService::humanReadableDateFormat($aRow['sampleCollectionDate']);
            }
            $row = [];
            $row[] = '<input type="checkbox" name="duplicate-select[]" class="' . $aRow['sample_code'] . '" id="' . $aRow['vl_sample_id'] . '" value="' . $aRow['vl_sample_id'] . '" onchange="duplicateCheck(this);"/>';
            $row[] = $aRow['sample_code'];
            $row[] = $sampleCollectionDate;
            $row[] = (isset($aRow['batch_code'])) ? $aRow['batch_code'] : '';
            $row[] = $aRow['patient_art_no'];
            $row[] = ucwords($aRow['patient_first_name'] . ' ' . $aRow['patient_last_name']);
            $row[] = (isset($aRow['facility_name'])) ? ucwords($aRow['facility_name']) : '';
            $row[] = (isset($aRow['province'])) ? ucwords($aRow['province']) : '';
            $row[] = (isset($aRow['district'])) ? ucwords($aRow['district']) : '';
            $row[] = (isset($aRow['sample_name'])) ? ucwords($aRow['sample_name']) : '';
            $row[] = $aRow['result'];
            $row[] = ucwords($aRow['status_name']);
            $row[] = '<a href="/data-management/duplicate-data/edit/' . base64_encode($aRow['vl_sample_id']) . '" class="btn green" title="Edit">' . $buttText . '</a>';
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function removeDuplicateSampleRows($params)
    {
        $response = 0;
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $removedSamplesDb = new \Application\Model\RemovedSamplesTable($this->adapter);
        if (isset($params['rows']) && trim($params['rows']) != '') {
            $duplicateSamples = explode(',', $params['rows']);
            $counter = count($duplicateSamples);
            for ($r = 0; $r < $counter; $r++) {
                $rQuery = $sql->select()->from(array('vl' => $this->table))
                    ->where(array('vl.vl_sample_id' => $duplicateSamples[$r]));
                $rQueryStr = $sql->buildSqlString($rQuery);
                $rResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                if ($rResult) {
                    $hasInserted = $removedSamplesDb->insert($rResult);
                    if ($hasInserted !== 0) {
                        $response = $this->delete(array('vl_sample_id' => $rResult['vl_sample_id']));
                    }
                }
            }
        }
        return $response;
    }

    public function getSummaryTabDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);


        $queryStr = $sql->select()->from(array('vl' => $this->table))
            ->columns(array(
                "total_samples_received" => new Expression("COUNT(*)"),
                "total_samples_tested" => new Expression("(SUM(CASE WHEN (((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL'))) THEN 1 ELSE 0 END))"),
                "suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                //"suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                //"not_suppressed_samples" => new Expression("SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)"),
                //"not_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result >= 1000) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                "rejected_samples" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                //"rejected_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                "all_current_regimen_samples" => new Expression("SUM(CASE WHEN (vl.line_of_treatment IS NOT NULL AND vl.line_of_treatment!= '' AND vl.line_of_treatment != 0) THEN 1 ELSE 0 END)"),
                "1st_line_of_current_regimen_samples" => new Expression("SUM(CASE WHEN (vl.line_of_treatment = 1) THEN 1 ELSE 0 END)"),
            ));

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $queryStr = $queryStr->where("(sample_collection_date is not null AND sample_collection_date not like '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        //->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')");
        $queryStr = $sql->buildSqlString($queryStr);

        return $this->commonService->cacheQuery($queryStr, $dbAdapter);
    }

    public function fetchSamplesReceivedBarChartDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];


        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "monthDate" => new Expression("DATE_FORMAT(DATE(sample_collection_date), '%b-%Y')"),
                    "total_dbs" => new Expression("SUM(CASE WHEN (specimen_type=$this->dbsId) THEN 1 ELSE 0 END)"),
                    "total_plasma" => new Expression("SUM(CASE WHEN (specimen_type=$this->plasmaId) THEN 1 ELSE 0 END)"),
                    "total_others" => new Expression("SUM(CASE WHEN ((specimen_type!= $this->dbsId AND specimen_type!= $this->plasmaId) AND specimen_type IS NOT NULL AND specimen_type!= '') THEN 1 ELSE 0 END)")
                )
            )
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'))
            //->where("sample_collection_date <= NOW()")
            //->where("sample_collection_date >= DATE_ADD(Now(),interval - 12 month)")
            ->group(array(new Expression('YEAR(vl.sample_collection_date)'), new Expression('MONTH(vl.sample_collection_date)')));

        if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
            $sQuery = $sQuery->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'));
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $sQuery = $sQuery->where("(sample_collection_date is not null AND sample_collection_date not like '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $sQuery = $sQuery->order('sample_collection_date ASC');
        $queryStr = $sql->buildSqlString($sQuery);
        //echo $queryStr;die;
        //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        $j = 0;
        foreach ($sampleResult as $row) {
            $result['sampleName']['dbs'][$j] = (isset($row["total_dbs"])) ? $row["total_dbs"] : 0;
            $result['sampleName']['plasma'][$j] = (isset($row["total_plasma"])) ? $row["total_plasma"] : 0;
            $result['sampleName']['others'][$j] = (isset($row["total_others"])) ? $row["total_others"] : 0;
            $result['date'][$j] = $row['monthDate'];
            $j++;
        }

        return $result;
    }

    /* Samples Received Province*/
    public function fetchAllSamplesReceivedByProvince($parameters)
    {


        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.geo_name');
        $orderColumns = array('f_d_l_d.geo_name', 'total_samples_received', 'total_samples_tested', 'total_samples_pending', 'total_samples_rejected', 'total_dbs_percentage', 'total_plasma_percentage', 'total_others_percentage');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN ((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_pending" => new Expression("(SUM(CASE WHEN ((vl.vl_result_category IS NULL OR vl.vl_result_category = '' OR vl.vl_result_category = 'NULL') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                    "total_dbs_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (specimen_type = $this->dbsId) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                    "total_plasma_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (specimen_type = $this->plasmaId) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                    "total_others_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (specimen_type!= $this->dbsId AND specimen_type!= $this->plasmaId) THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state_id');
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
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
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state_id');

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['province'];
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['total_samples_rejected'];
            $row[] = (round($aRow['total_dbs_percentage']) > 0) ? $aRow['total_dbs_percentage'] . '%' : '';
            $row[] = (round($aRow['total_plasma_percentage']) > 0) ? $aRow['total_plasma_percentage'] . '%' : '';
            $row[] = (round($aRow['total_others_percentage']) > 0) ? $aRow['total_others_percentage'] . '%' : '';
            $output['aaData'][] = $row;
        }
        return $output;
    }

    /* Samples Received District*/
    public function fetchAllSamplesReceivedByDistrict($parameters)
    {


        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.geo_name');
        $orderColumns = array('f_d_l_d.geo_name', 'total_samples_received', 'total_samples_tested', 'total_samples_pending', 'total_samples_rejected', 'total_dbs_percentage', 'total_plasma_percentage', 'total_others_percentage');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN ((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_pending" => new Expression("(SUM(CASE WHEN ((vl.vl_result_category IS NULL OR vl.vl_result_category = '' OR vl.vl_result_category = 'NULL') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("SUM(CASE WHEN (vl.reason_for_sample_rejection !='' AND vl.reason_for_sample_rejection !='0' AND vl.reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END)"),
                    "total_dbs_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (specimen_type = $this->dbsId) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                    "total_plasma_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (specimen_type = $this->plasmaId) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                    "total_others_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (specimen_type!= $this->dbsId AND specimen_type!= $this->plasmaId) THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district_id');
        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
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
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district_id');


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['district'];
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['total_samples_rejected'];
            $row[] = (round($aRow['total_dbs_percentage']) > 0) ? $aRow['total_dbs_percentage'] . '%' : '';
            $row[] = (round($aRow['total_plasma_percentage']) > 0) ? $aRow['total_plasma_percentage'] . '%' : '';
            $row[] = (round($aRow['total_others_percentage']) > 0) ? $aRow['total_others_percentage'] . '%' : '';
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchAllSamplesReceivedByFacility($parameters)
    {



        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('facility_name', 'f_d_l_dp.geo_name', 'f_d_l_d.geo_name');
        $orderColumns = array('facility_name', 'f_d_l_dp.geo_name', 'f_d_l_d.geo_name', 'total_samples_received', 'total_samples_tested', 'total_samples_pending', 'total_samples_rejected', 'total_dbs_percentage', 'total_plasma_percentage', 'total_others_percentage');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN ((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_pending" => new Expression("(SUM(CASE WHEN ((vl.vl_result_category IS NULL OR vl.vl_result_category = '' OR vl.vl_result_category = 'NULL') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection = '' OR vl.reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_dbs_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (specimen_type = $this->dbsId) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                    "total_plasma_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (specimen_type = $this->plasmaId) THEN 1 ELSE 0 END)/COUNT(*))*100),2)"),
                    "total_others_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (specimen_type!= $this->dbsId AND specimen_type!= $this->plasmaId) THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->join(array('f_d_l_dp' => 'geographical_divisions'), 'f_d_l_dp.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array('sample_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) not like '1970-01-01' AND DATE(vl.sample_collection_date) not like '0000-00-00')")
            ->group('vl.facility_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(sample_collection_date is not null AND sample_collection_date not like '')
                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
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
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('vl_sample_id'))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array())
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array())
            ->join(array('f_d_l_dp' => 'geographical_divisions'), 'f_d_l_dp.geo_id=f.facility_state_id', array())
            ->join(array('rs' => 'r_vl_sample_type'), 'rs.sample_id=vl.specimen_type', array())
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) not like '1970-01-01' AND DATE(vl.sample_collection_date) not like '0000-00-00')")
            ->group('vl.facility_id');


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }

        $iQueryStr = $sql->buildSqlString($iQuery);



        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = "<span style='white-space:nowrap !important;' >" . ucwords($aRow['facility_name']) . "</span>";
            $row[] = ucwords($aRow['province']);
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_pending'];
            $row[] = $aRow['total_samples_rejected'];
            $row[] = (round($aRow['total_dbs_percentage']) > 0) ? $aRow['total_dbs_percentage'] . '%' : '';
            $row[] = (round($aRow['total_plasma_percentage']) > 0) ? $aRow['total_plasma_percentage'] . '%' : '';
            $row[] = (round($aRow['total_others_percentage']) > 0) ? $aRow['total_others_percentage'] . '%' : '';

            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchSuppressionRateBarChartDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];


        $sQuery = $sql->select()
            ->from(array('vl' => $this->table))
            ->columns(
                array(
                    "monthyear" => new Expression("DATE_FORMAT(sample_collection_date, '%b %y')"),
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL'))) THEN 1 ELSE 0 END))"),
                    "total_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)")
                )
            )
            //->where("sample_collection_date <= NOW()")
            //->where("sample_collection_date >= DATE_ADD(Now(),interval - 12 month)")
            ->group(array(new Expression('YEAR(sample_collection_date)'), new Expression('MONTH(sample_collection_date)')));

        if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
            $sQuery = $sQuery->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'));
        }

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
        }

        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $sQuery = $sQuery->where("(sample_collection_date is not null AND sample_collection_date not like '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $sQuery = $sQuery->order('sample_collection_date ASC');
        $queryStr = $sql->buildSqlString($sQuery);
        //echo $queryStr;die;
        //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        $j = 0;
        foreach ($sampleResult as $row) {
            $result['valid_results'][$j] = (isset($row["total_samples_valid"])) ? $row["total_samples_valid"] : 0;
            $result['suppression_rate'][$j] = ($row["total_suppressed_samples"] > 0 && $row["total_samples_valid"] > 0) ? round((($row["total_suppressed_samples"] / $row["total_samples_valid"]) * 100), 2) : null;
            $result['date'][$j] = $row['monthyear'];
            $j++;
        }
        return $result;
    }

    public function fetchAllSuppressionRateByProvince($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.geo_name');
        $orderColumns = array('f_d_l_d.geo_name', 'total_samples_valid', 'total_suppressed_samples', 'total_not_suppressed_samples', 'total_samples_rejected', 'suppression_rate');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL'))) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                    "total_not_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%')) THEN 1 ELSE 0 END)"),
                    //"total_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                    "suppression_rate" => new Expression("ROUND(((SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }


        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
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
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_state_id');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = ucwords($aRow['province']);
            $row[] = $aRow['total_samples_valid'];
            $row[] = $aRow['total_suppressed_samples'];
            $row[] = $aRow['total_not_suppressed_samples'];
            $row[] = ($aRow['total_samples_rejected'] > 0 && $aRow['total_samples_received'] > 0) ? round((($aRow['total_samples_rejected'] / $aRow['total_samples_received']) * 100), 2) . '%' : '';
            $row[] = ($aRow['total_samples_valid'] > 0 && $aRow['total_suppressed_samples'] > 0) ? round((($aRow['total_suppressed_samples'] / $aRow['total_samples_valid']) * 100), 2) . '%' : '';

            $output['aaData'][] = $row;
        }
        return $output;
    }
    public function fetchAllSuppressionRateByDistrict($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.geo_name');
        $orderColumns = array('f_d_l_d.geo_name', 'total_samples_valid', 'total_suppressed_samples', 'total_not_suppressed_samples', 'total_samples_rejected', 'suppression_rate');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL'))) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                    "total_not_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                    //"total_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                    "suppression_rate" => new Expression("ROUND(((SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
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
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district_id');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['total_samples_valid'];
            $row[] = $aRow['total_suppressed_samples'];
            $row[] = $aRow['total_not_suppressed_samples'];
            $row[] = ($aRow['total_samples_rejected'] > 0 && $aRow['total_samples_received'] > 0) ? round((($aRow['total_samples_rejected'] / $aRow['total_samples_received']) * 100), 2) . '%' : '';
            $row[] = ($aRow['total_samples_valid'] > 0 && $aRow['total_suppressed_samples'] > 0) ? round((($aRow['total_suppressed_samples'] / $aRow['total_samples_valid']) * 100), 2) . '%' : '';

            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchAllSuppressionRateByFacility($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */

        $queryContainer = new Container('query');

        $aColumns = array('facility_name', 'f_d_l_dp.geo_name', 'f_d_l_d.geo_name');
        $orderColumns = array('f_d_l_d.geo_name', 'f_d_l_dp.geo_name', 'f_d_l_d.geo_name', 'total_samples_valid', 'total_suppressed_samples', 'total_not_suppressed_samples', 'total_samples_rejected', 'suppression_rate');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id',
                    'facility_id',
                    'sampleCollectionDate' => new Expression('DATE(sample_collection_date)'),
                    'result',
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL'))) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                    "total_not_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'not%' OR vl.vl_result_category like 'Not%' or vl.result_value_absolute_decimal >= 1000)) THEN 1 ELSE 0 END)"),
                    //"total_suppressed_samples_percentage" => new Expression("TRUNCATE(((SUM(CASE WHEN (vl.result < 1000 or vl.result='Target Not Detected') THEN 1 ELSE 0 END)/COUNT(*))*100),2)")
                    "suppression_rate" => new Expression("ROUND(((SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END))/(SUM(CASE WHEN (((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL'))) THEN 1 ELSE 0 END)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'geographical_divisions'), 'f_d_l_dp.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('vl.facility_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery->order($sOrder);
        }

        $queryContainer->fetchAllSuppressionRateByFacility = $sQuery;

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery->limit($sLimit);
            $sQuery->offset($sOffset);
        }

        $sQueryStr = $sql->buildSqlString($sQuery); // Get the string of the Sql, instead of the Select-instance
        //echo $sQueryStr;die;
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    'vl_sample_id'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'geographical_divisions'), 'f_d_l_dp.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('vl.facility_id');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = "<span style='white-space:nowrap !important;' >" . ucwords($aRow['facility_name']) . "</span>";
            $row[] = ucwords($aRow['province']);
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['total_samples_valid'];
            $row[] = $aRow['total_suppressed_samples'];
            $row[] = $aRow['total_not_suppressed_samples'];
            $row[] = ($aRow['total_samples_rejected'] > 0 && $aRow['total_samples_received'] > 0) ? round((($aRow['total_samples_rejected'] / $aRow['total_samples_received']) * 100), 2) . '%' : '';
            $row[] = ($aRow['total_samples_valid'] > 0 && $aRow['total_suppressed_samples'] > 0) ? round((($aRow['total_suppressed_samples'] / $aRow['total_samples_valid']) * 100), 2) . '%' : '';

            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchSamplesRejectedBarChartDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $mostRejectionReasons = [];
        $mostRejectionQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array('rejections' => new Expression('COUNT(*)')))
            ->join(array('r_r_r' => 'r_vl_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection', array('rejection_reason_id'))
            ->group('vl.reason_for_sample_rejection')
            ->order('rejections DESC')
            ->limit(4);

        if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'));
        }
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $mostRejectionQuery = $mostRejectionQuery->where('vl.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $mostRejectionQuery = $mostRejectionQuery->where("(sample_collection_date is not null AND sample_collection_date not like '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }
        $mostRejectionQueryStr = $sql->buildSqlString($mostRejectionQuery);
        $mostRejectionResult = $dbAdapter->query($mostRejectionQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        if (isset($mostRejectionResult) && count($mostRejectionResult) > 0) {
            foreach ($mostRejectionResult as $rejectionReason) {
                $mostRejectionReasons[] = $rejectionReason['rejection_reason_id'];
            }
            $mostRejectionReasons[] = 0;
        }
        $result = [];

        $start = strtotime($params['fromDate']);
        $end = strtotime($params['toDate']);

        $j = 0;
        while ($start <= $end) {
            $month = date('m', $start);
            $year = date('Y', $start);
            $monthYearFormat = date("M-Y", $start);
            $counter = count($mostRejectionReasons);
            for ($m = 0; $m < $counter; $m++) {
                $rejectionQuery = $sql->select()->from(array('vl' => $this->table))
                    ->columns(array('rejections' => new Expression('COUNT(*)')))
                    ->join(array('r_r_r' => 'r_vl_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection', array('rejection_reason_name'))
                    ->where("MONTH(sample_collection_date)='" . $month . "' AND Year(sample_collection_date)='" . $year . "'");


                if (trim($params['provinces']) != '' || trim($params['districts']) != '' || trim($params['clinics']) != '') {
                    $rejectionQuery = $rejectionQuery->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'));
                }
                if (isset($params['provinces']) && trim($params['provinces']) != '') {
                    $rejectionQuery = $rejectionQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
                }
                if (isset($params['districts']) && trim($params['districts']) != '') {
                    $rejectionQuery = $rejectionQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
                }
                if (isset($params['clinics']) && trim($params['clinics']) != '') {
                    $rejectionQuery = $rejectionQuery->where('vl.facility_id IN (' . $params['clinics'] . ')');
                }
                if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
                    $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
                    $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
                    $rejectionQuery = $rejectionQuery->where("(sample_collection_date is not null AND sample_collection_date not like '')
                                                AND DATE(sample_collection_date) >= '" . $startMonth . "'
                                                AND DATE(sample_collection_date) <= '" . $endMonth . "'");
                }
                if ($mostRejectionReasons[$m] == 0) {
                    $rejectionQuery = $rejectionQuery->where('vl.reason_for_sample_rejection is not null and vl.reason_for_sample_rejection!= "" and vl.reason_for_sample_rejection NOT IN("' . implode('", "', $mostRejectionReasons) . '")');
                } else {
                    $rejectionQuery = $rejectionQuery->where('vl.reason_for_sample_rejection = "' . $mostRejectionReasons[$m] . '"');
                }
                $rejectionQueryStr = $sql->buildSqlString($rejectionQuery);
                $rejectionResult = $this->commonService->cacheQuery($rejectionQueryStr, $dbAdapter);
                $rejectionReasonName = ($mostRejectionReasons[$m] == 0) ? 'Others' : ucwords($rejectionResult[0]['rejection_reason_name']);
                $result['rejection'][$rejectionReasonName][$j] = (isset($rejectionResult[0]['rejections'])) ? $rejectionResult[0]['rejections'] : 0;
                $result['date'][$j] = $monthYearFormat;
            }
            $start = strtotime("+1 month", $start);
            $j++;
        }
        return $result;
    }

    public function fetchAllSamplesRejectedByDistrict($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.geo_name');
        $orderColumns = array('f_d_l_d.geo_name', 'total_samples_received', 'total_samples_rejected', 'rejection_rate');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)'),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "rejection_rate" => new Expression("ROUND(((SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))/(COUNT(*)))*100,2)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
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
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)')
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district_id');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_rejected'];
            $row[] = ($aRow['rejection_rate']);
            $output['aaData'][] = $row;
        }
        return $output;
    }
    public function fecthAllSamplesRejectedByProvince($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f_d_l_d.geo_name');
        $orderColumns = array('f_d_l_d.geo_name', 'total_samples_received', 'total_samples_rejected', 'rejection_rate');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)'),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "rejection_rate" => new Expression("ROUND(((SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))/(COUNT(*)))*100,2)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
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
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)')
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_district_id');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = ucwords($aRow['province']);
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_rejected'];
            $row[] = ($aRow['rejection_rate']);
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fecthAllSamplesRejectedByFacility($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('f.facility_name', 'f_d_l_dp.geo_name', 'f_d_l_d.geo_name');
        $orderColumns = array('f_d_l_dp.geo_name', 'f_d_l_d.geo_name', 'total_samples_received', 'total_samples_rejected', 'rejection_rate');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)'),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "rejection_rate" => new Expression("ROUND(((SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))/(COUNT(*)))*100,2)")
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'geographical_divisions'), 'f_d_l_dp.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
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
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "total_samples_received" => new Expression('COUNT(*)')
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('f_d_l_dp' => 'geographical_divisions'), 'f_d_l_dp.geo_id=f.facility_state_id', array('province' => 'geo_name'))
            ->join(array('f_d_l_d' => 'geographical_divisions'), 'f_d_l_d.geo_id=f.facility_district_id', array('district' => 'geo_name'))
            ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')")
            ->group('f.facility_id');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = "<span style='white-space:nowrap !important;' >" . ucwords($aRow['facility_name']) . "</span>";
            $row[] = ucwords($aRow['province']);
            $row[] = ucwords($aRow['district']);
            $row[] = $aRow['total_samples_received'];
            $row[] = ($aRow['rejection_rate']);
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchRegimenGroupBarChartDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);



        $validQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "current_regimen",
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') THEN 1 ELSE 0 END))")
                )
            )
            //->where('vl.line_of_treatment >= 1')
            ->group('vl.current_regimen');


        $suppressedQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(array(
                "current_regimen",
                "total_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
            ))
            //->where('vl.line_of_treatment >= 1')
            ->group('vl.current_regimen');


        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "current_regimen",
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "suppression_rate" => new Expression("ROUND(((total_suppressed_samples)/(total_samples_valid))*100,2)"),
                )
            )

            ->join(array('valid' => $validQuery), 'valid.current_regimen=vl.current_regimen', array('total_samples_valid'))
            ->join(array('suppressed' => $suppressedQuery), 'suppressed.current_regimen=vl.current_regimen', array('total_suppressed_samples'))
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name', 'facility_state', 'facility_district'), 'left')

            //->where('vl.line_of_treatment >= 1')
            ->group('vl.current_regimen')
            ->order('total_samples_valid DESC')
            ->limit(20);
        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $sQuery = $sQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $sQuery = $sQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
        }

        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $sQuery = $sQuery->where("(sample_collection_date is not null AND sample_collection_date not like '')
                                            AND DATE(sample_collection_date) >= '" . $startMonth . "'
                                            AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $sQuery = $sQuery->where('vl.facility_id IN (' . $params['clinics'] . ')');
        }
        $queryStr = $sql->buildSqlString($sQuery);
        //echo $queryStr;die;
        //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $sampleResult = $this->commonService->cacheQuery($queryStr, $dbAdapter);
        //die;
        $j = 0;
        $result = [];
        foreach ($sampleResult as $aRow) {
            $result['valid_results'][$j] = $aRow['total_samples_valid'];
            $result['suppression_rate'][$j] = ($aRow['total_samples_valid'] > 0) ? $aRow['suppression_rate'] : null;
            $result['current_regimen'][$j] = $aRow["current_regimen"];
            $j++;
        }
        return $result;
    }

    public function fetchRegimenGroupSamplesDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
        $aColumns = array('current_regimen');
        $orderColumns = array('current_regimen', 'total_samples_received', 'total_samples_tested', 'total_samples_valid', 'total_suppressed_samples', 'suppression_rate', 'percentage_of_samples');

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
                    $sOrder .= $orderColumns[(int) $parameters['iSortCol_' . $i]] . " " . ($parameters['sSortDir_' . $i]) . ",";
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
        $totalSamples = $parameters['t_received'];

        $validQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "current_regimen",
                    "total_samples_valid" => new Expression("(SUM(CASE WHEN (vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') THEN 1 ELSE 0 END))")
                )
            )
            //->where('vl.line_of_treatment >= 1')
            ->group('vl.current_regimen');


        $suppressedQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "current_regimen",
                    "total_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                )
            )
            //->where('vl.line_of_treatment >= 1')
            ->group('vl.current_regimen');

        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "total_samples" => new Expression('COUNT(vl.current_regimen)'),
                    "total_samples_received" => new Expression("(SUM(CASE WHEN (vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00') THEN 1 ELSE 0 END))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN (((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') OR (vl.reason_for_sample_rejection IS NOT NULL AND vl.reason_for_sample_rejection != '' AND vl.reason_for_sample_rejection != 0))) THEN 1 ELSE 0 END))"),
                    //"total_samples_valid" => new Expression("(SUM(CASE WHEN ((vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') AND (sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    //"total_suppressed_samples" => new Expression("(SUM(CASE WHEN (vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' ) THEN 1 ELSE 0 END))"),
                    "suppression_rate" => new Expression("ROUND(((total_suppressed_samples)/(total_samples_valid))*100,2)"),
                    "percentage_of_samples" => new Expression("ROUND((COUNT(*)/$totalSamples)*100,2)"),
                    'current_regimen'
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('valid' => $validQuery), 'valid.current_regimen=vl.current_regimen', array('total_samples_valid'))
            ->join(array('suppressed' => $suppressedQuery), 'suppressed.current_regimen=vl.current_regimen', array('total_suppressed_samples'))
            //->where(array('vl.line_of_treatment  >= 1'))
            ->group('vl.current_regimen');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery->where($sWhere);
        }

        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $sQuery = $sQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
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
        $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        /* Data set length after filtering */
        $sQuery->reset('limit');
        $sQuery->reset('offset');
        $fQuery = $sql->buildSqlString($sQuery);
        $aResultFilterTotal = $dbAdapter->query($fQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $iQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "total_samples" => new Expression('COUNT(*)')
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_name'))
            ->join(array('valid' => $validQuery), 'valid.current_regimen=vl.current_regimen', array('total_samples_valid'))
            ->join(array('suppressed' => $suppressedQuery), 'suppressed.current_regimen=vl.current_regimen', array('total_suppressed_samples'))
            //->where(array('vl.line_of_treatment'=>1))
            ->group('vl.current_regimen');
        if (trim($parameters['fromDate']) != '' && trim($parameters['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $parameters['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $parameters['toDate']) . date('-t', strtotime($parameters['toDate']));
            $iQuery = $iQuery
                ->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $iQueryStr = $sql->buildSqlString($iQuery);
        $iResult = $dbAdapter->query($iQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $iTotal = count($iResult);

        $output = array(
            "sEcho" => (int) $parameters['sEcho'],
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['current_regimen'];
            $row[] = $aRow['total_samples_received'];
            $row[] = $aRow['total_samples_tested'];
            $row[] = $aRow['total_samples_valid'];
            $row[] = $aRow['total_suppressed_samples'];
            $row[] = ($aRow['suppression_rate']);
            $row[] = ($aRow['percentage_of_samples']);
            $output['aaData'][] = $row;
        }
        return $output;
    }

    public function fetchAllLineOfTreatmentDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "1stLineofSuppressed" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND vl.line_of_treatment = 1) THEN 1 ELSE 0 END))"),
                    "1stLineofNotSuppressed" => new Expression("(SUM(CASE WHEN (vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND vl.line_of_treatment = 1) THEN 1 ELSE 0 END))"),
                    "2ndLineofSuppressed" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND vl.line_of_treatment = 2) THEN 1 ELSE 0 END))"),
                    "2ndLineofNotSuppressed" => new Expression("(SUM(CASE WHEN (vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND vl.line_of_treatment = 2) THEN 1 ELSE 0 END))"),
                    "otherLineofSuppressed" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND (vl.line_of_treatment!= 1 AND vl.line_of_treatment!= 2)) THEN 1 ELSE 0 END))"),
                    "otherLineofNotSuppressed" => new Expression("(SUM(CASE WHEN (vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00' AND (vl.line_of_treatment!= 1 AND vl.line_of_treatment!= 2)) THEN 1 ELSE 0 END))")
                )
            );
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $sQuery = $sQuery->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $queryStr = $sql->buildSqlString($sQuery);
        //echo $queryStr;die;
        //$sampleResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $this->commonService->cacheQuery($queryStr, $dbAdapter);
    }

    public function fetchAllCollapsibleLineOfTreatmentDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        $sQuery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "current_regimen",
                    "validResults" => new Expression("(SUM(CASE WHEN ((vl.result IS NOT NULL AND vl.result != '' AND vl.result != 'NULL' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample') AND (sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1 ELSE 0 END))"),
                    "totalSuppressed" => new Expression("(SUM(CASE WHEN ((vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))"),
                    "totalNotSuppressed" => new Expression("(SUM(CASE WHEN (vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1 ELSE 0 END))")
                )
            );
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $sQuery = $sQuery->where("(vl.sample_collection_date is not null AND vl.sample_collection_date not like '')
                                        AND DATE(vl.sample_collection_date) >= '" . $startMonth . "'
                                        AND DATE(vl.sample_collection_date) <= '" . $endMonth . "'");
        }
        $queryStr = $sql->buildSqlString($sQuery);
        //lineofTreatmentResult = $dbAdapter->query($queryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        $adult1stLineofTreatmentResult = $this->commonService->cacheQuery($queryStr . " AND line_of_treatment = 1 AND (patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years >= 18) group by current_regimen order by validResults desc limit 9", $dbAdapter);
        $adult1stLineofTreatmentOthersResult = $this->commonService->cacheQuery($queryStr . " AND line_of_treatment = 1 AND (patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years >= 18) group by current_regimen order by validResults desc limit 10,18446744073709551615", $dbAdapter);

        $paeds1stLineofTreatmentResult = $this->commonService->cacheQuery($queryStr . " AND line_of_treatment = 1 AND (patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years < 18) group by current_regimen order by validResults desc limit 9", $dbAdapter);
        $paeds1stLineofTreatmentOthersResult = $this->commonService->cacheQuery($queryStr . " AND line_of_treatment = 1 AND (patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years < 18) group by current_regimen order by validResults desc limit 10,18446744073709551615", $dbAdapter);

        $adult2ndLineofTreatmentResult = $this->commonService->cacheQuery($queryStr . " AND line_of_treatment = 2 AND (patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years >= 18) group by current_regimen order by validResults desc limit 9", $dbAdapter);
        $adult2ndLineofTreatmentOthersResult = $this->commonService->cacheQuery($queryStr . " AND line_of_treatment = 2 AND patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years >= 18 group by current_regimen order by validResults desc limit 10,18446744073709551615", $dbAdapter);

        $paeds2ndLineofTreatmentResult = $this->commonService->cacheQuery($queryStr . " AND line_of_treatment = 2 AND (patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years < 18) group by current_regimen order by validResults desc limit 8", $dbAdapter);
        $paeds2ndLineofTreatmentOthersResult = $this->commonService->cacheQuery($queryStr . " AND line_of_treatment = 2 AND (patient_age_in_years IS NOT NULL AND patient_age_in_years!= '' AND patient_age_in_years < 18) group by current_regimen order by validResults desc limit 9,18446744073709551615", $dbAdapter);

        $otherLineofTreatmentResult = $this->commonService->cacheQuery($queryStr . " AND line_of_treatment is not null AND line_of_treatment!= '' AND line_of_treatment!= 1 AND line_of_treatment!= 2 group by current_regimen", $dbAdapter);
        return array('adult1stLineofTreatmentResult' => $adult1stLineofTreatmentResult, 'adult1stLineofTreatmentOthersResult' => $adult1stLineofTreatmentOthersResult, 'paeds1stLineofTreatmentResult' => $paeds1stLineofTreatmentResult, 'paeds1stLineofTreatmentOthersResult' => $paeds1stLineofTreatmentOthersResult, 'adult2ndLineofTreatmentResult' => $adult2ndLineofTreatmentResult, 'adult2ndLineofTreatmentOthersResult' => $adult2ndLineofTreatmentOthersResult, 'paeds2ndLineofTreatmentResult' => $paeds2ndLineofTreatmentResult, 'paeds2ndLineofTreatmentOthersResult' => $paeds2ndLineofTreatmentOthersResult, 'otherLineofTreatmentResult' => $otherLineofTreatmentResult);
    }

    public function fetchKeySummaryIndicatorsDetails($params)
    {
        $queryContainer = new Container('query');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $summaryResult = [];

        $samplesReceivedSummaryQuery = $sql->select()
            ->from(array('vl' => $this->table))
            ->columns(
                array(
                    "monthyear" => new Expression("DATE_FORMAT(sample_collection_date, '%b %y')"),
                    "total_samples_received" => new Expression("(COUNT(*))"),
                    "total_samples_tested" => new Expression("(SUM(CASE WHEN (vl.vl_result_category IS NOT NULL AND vl.vl_result_category != '' AND vl.vl_result_category != 'NULL') THEN 1 ELSE 0 END))"),
                    "total_samples_rejected" => new Expression("(SUM(CASE WHEN (reason_for_sample_rejection !='' AND reason_for_sample_rejection !='0' AND reason_for_sample_rejection IS NOT NULL) THEN 1 ELSE 0 END))"),
                    "total_suppressed_samples" => new Expression("SUM(CASE WHEN ((vl.vl_result_category like 'suppressed%' OR vl.vl_result_category like 'Suppressed%' )) THEN 1 ELSE 0 END)"),
                )
            )
            ->join(array('f' => 'facility_details'), 'f.facility_id = vl.facility_id')
            //->where("sample_collection_date <= NOW()")
            //->where("sample_collection_date >= DATE_ADD(Now(),interval - 12 month)")
            ->group(array(new Expression('YEAR(sample_collection_date)'), new Expression('MONTH(sample_collection_date)')));

        if (isset($params['provinces']) && trim($params['provinces']) != '') {
            $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery->where('f.facility_state_id IN (' . $params['provinces'] . ')');
        }
        if (isset($params['districts']) && trim($params['districts']) != '') {
            $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery->where('f.facility_district_id IN (' . $params['districts'] . ')');
        }
        if (isset($params['clinics']) && trim($params['clinics']) != '') {
            $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery->where('vl.facility_id IN (' . $params['clinics'] . ')');
        }
        if (trim($params['fromDate']) != '' && trim($params['toDate']) != '') {
            $startMonth = str_replace(' ', '-', $params['fromDate']) . "-01";
            $endMonth = str_replace(' ', '-', $params['toDate']) . date('-t', strtotime($params['toDate']));
            $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery
                ->where("(sample_collection_date is not null AND sample_collection_date not like '')
                                        AND DATE(sample_collection_date) >= '" . $startMonth . "'
                                        AND DATE(sample_collection_date) <= '" . $endMonth . "'");
        }

        $samplesReceivedSummaryQuery = $samplesReceivedSummaryQuery->order('sample_collection_date ASC');
        $queryContainer->indicatorSummaryQuery = $samplesReceivedSummaryQuery;
        $samplesReceivedSummaryCacheQuery = $sql->buildSqlString($samplesReceivedSummaryQuery);
        // die($samplesReceivedSummaryCacheQuery);
        $samplesReceivedSummaryResult = $this->commonService->cacheQuery($samplesReceivedSummaryCacheQuery, $dbAdapter);
        $j = 0;
        foreach ($samplesReceivedSummaryResult as $row) {
            $summaryResult['sample'][$this->translator->translate('Samples Received')]['month'][$j] = (isset($row["total_samples_received"])) ? $row["total_samples_received"] : 0;
            $summaryResult['sample'][$this->translator->translate('Samples Tested')]['month'][$j] = (isset($row["total_samples_tested"])) ? $row["total_samples_tested"] : 0;
            $summaryResult['sample'][$this->translator->translate('Samples Rejected')]['month'][$j] = (isset($row["total_samples_rejected"])) ? $row["total_samples_rejected"] : 0;
            $summaryResult['sample'][$this->translator->translate('Valid Tested')]['month'][$j]  = $valid = (isset($row["total_samples_tested"])) ? $row["total_samples_tested"] - $row["total_samples_rejected"] : 0;;
            $summaryResult['sample'][$this->translator->translate('Samples Suppressed')]['month'][$j] = (isset($row["total_suppressed_samples"])) ? $row["total_suppressed_samples"] : 0;
            $summaryResult['sample'][$this->translator->translate('Suppression Rate')]['month'][$j] = ($valid > 0) ? round((($row["total_suppressed_samples"] / $valid) * 100), 2) . ' %' : '0';
            $summaryResult['sample'][$this->translator->translate('Rejection Rate')]['month'][$j] = (isset($row["total_samples_rejected"]) && $row["total_samples_rejected"] > 0 && $row["total_samples_received"] > 0) ? round((($row["total_samples_rejected"] / ($row["total_samples_tested"] + $row["total_samples_rejected"])) * 100), 2) . ' %' : '0';
            $summaryResult['month'][$j] = $row['monthyear'];
            $j++;
        }
        return $summaryResult;
    }

    public function getVLTestReasonBasedOnAgeGroup($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rResult = [];

        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            $rQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "AgeLt2" => new Expression("SUM(CASE WHEN ((vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2) AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)"),
                        "AgeGte2Lte5" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 2 and patient_age_in_years <= 5) AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)"),
                        "AgeGte6Lte14" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 6 and patient_age_in_years <= 14) AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)"),
                        "AgeGte15Lte49" => new Expression("SUM(CASE WHEN ((patient_age_in_years >= 15 and patient_age_in_years <= 49) AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)"),
                        "AgeGt50" => new Expression("SUM(CASE WHEN (patient_age_in_years > 50 AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)"),
                        "AgeUnknown" => new Expression("SUM(CASE WHEN ((patient_age_in_years IS NULL OR patient_age_in_years = '' OR patient_age_in_years = 0) AND (reason_for_vl_testing IS NOT NULL AND reason_for_vl_testing != '' AND reason_for_vl_testing != 0)) THEN 1 ELSE 0 END)")
                    )
                )
                ->join(array('tr' => 'r_vl_test_reasons'), 'tr.test_reason_id=vl.reason_for_vl_testing', array('test_reason_name'))
                ->where(new WhereExpression('DATE(vl.sample_collection_date) BETWEEN ? AND ?', [$startDate, $endDate]));

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $clinicIds = explode(',', $params['clinicId']);
                $rQuery->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($clinicIds), '?')) . ')', $clinicIds));
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                if (!empty($mappedFacilities)) {
                    $rQuery->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($mappedFacilities), '?')) . ')', $mappedFacilities));
                }
            }

            if (isset($params['testReason']) && trim($params['testReason']) != '') {
                $testReasonIds = explode(',', $params['testReason']);
                $rQuery->where(new WhereExpression('vl.reason_for_vl_testing IN (' . implode(',', array_fill(0, count($testReasonIds), '?')) . ')', $testReasonIds));
            }

            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $sampleTypeId = base64_decode(trim($params['sampleTypeId']));
                $rQuery->where(new WhereExpression('vl.specimen_type = ?', [$sampleTypeId]));
            }

            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $rQuery->where(new WhereExpression("vl.arv_adherance_percentage = ?", $params['adherence']));
            }

            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $rQuery->where("(vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $rQuery->where("vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            }

            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $ageConditions = [];
                foreach ($age as $ageGroup) {
                    switch ($ageGroup) {
                        case '<2':
                            $ageConditions[] = "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                            break;
                        case '2to5':
                            $ageConditions[] = "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                            break;
                        case '6to14':
                            $ageConditions[] = "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                            break;
                        case '15to49':
                            $ageConditions[] = "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                            break;
                        case '>=50':
                            $ageConditions[] = "(vl.patient_age_in_years >= 50)";
                            break;
                        case 'unknown':
                            $ageConditions[] = "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported')";
                            break;
                    }
                }
                if (!empty($ageConditions)) {
                    $rQuery->where('(' . implode(' OR ', $ageConditions) . ')');
                }
            }

            if (isset($params['gender'])) {
                if ($params['gender'] == 'F') {
                    $rQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                } elseif ($params['gender'] == 'M') {
                    $rQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                } elseif ($params['gender'] == 'not_specified') {
                    $rQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                }
            }

            if (isset($params['isPregnant'])) {
                if ($params['isPregnant'] == 'yes') {
                    $rQuery->where("vl.is_patient_pregnant = 'yes'");
                } elseif ($params['isPregnant'] == 'no') {
                    $rQuery->where("vl.is_patient_pregnant = 'no'");
                } elseif ($params['isPregnant'] == 'unreported') {
                    $rQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
                }
            }

            if (isset($params['isBreastfeeding'])) {
                if ($params['isBreastfeeding'] == 'yes') {
                    $rQuery->where("vl.is_patient_breastfeeding = 'yes'");
                } elseif ($params['isBreastfeeding'] == 'no') {
                    $rQuery->where("vl.is_patient_breastfeeding = 'no'");
                } elseif ($params['isBreastfeeding'] == 'unreported') {
                    $rQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
                }
            }

            $rQueryStr = $sql->buildSqlString($rQuery);
            //$qResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $qResult = $this->commonService->cacheQuery($rQueryStr, $dbAdapter);
            $rResult['total']['Age < 2'][0] = (isset($qResult[0]['AgeLt2'])) ? (int) $qResult[0]['AgeLt2'] : 0;
            $rResult['total']['Age 2-5'][0] = (isset($qResult[0]['AgeGte2Lte5'])) ? (int) $qResult[0]['AgeGte2Lte5'] : 0;
            $rResult['total']['Age 6-14'][0] = (isset($qResult[0]['AgeGte6Lte14'])) ? (int) $qResult[0]['AgeGte6Lte14'] : 0;
            $rResult['total']['Age 15-49'][0] = (isset($qResult[0]['AgeGte15Lte49'])) ? (int) $qResult[0]['AgeGte15Lte49'] : 0;
            $rResult['total']['Age > 50'][0] = (isset($qResult[0]['AgeGt50'])) ? (int) $qResult[0]['AgeGt50'] : 0;
            $rResult['total']['Age Unknown'][0] = (isset($qResult[0]['AgeGt50'])) ? (int) $qResult[0]['AgeUnknown'] : 0;
        }
        return $rResult;
    }

    public function getVLTestReasonBasedOnGender($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $rResult = [];

        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            $rQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(
                    array(
                        "mTotal" => new Expression("SUM(CASE WHEN (vl.patient_gender in('m','Male','M','MALE')) THEN 1 ELSE 0 END)"),
                        "fTotal" => new Expression("SUM(CASE WHEN (vl.patient_gender in('f','Female','F','FEMALE')) THEN 1 ELSE 0 END)"),
                        "nsTotal" => new Expression("SUM(CASE WHEN ((vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')) THEN 1 ELSE 0 END)")
                    )
                )
                ->join(array('tr' => 'r_vl_test_reasons'), 'tr.test_reason_id=vl.reason_for_vl_testing', array('test_reason_name'))
                ->where(new WhereExpression('DATE(vl.sample_collection_date) BETWEEN ? AND ?', [$startDate, $endDate]));

            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $clinicIds = explode(',', $params['clinicId']);
                $rQuery->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($clinicIds), '?')) . ')', $clinicIds));
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                if (!empty($mappedFacilities)) {
                    $rQuery->where(new WhereExpression('vl.facility_id IN (' . implode(',', array_fill(0, count($mappedFacilities), '?')) . ')', $mappedFacilities));
                }
            }
            if (isset($params['testReason']) && trim($params['testReason']) != '') {
                $testReasonIds = explode(',', $params['testReason']);
                $rQuery->where(new WhereExpression('vl.reason_for_vl_testing IN (' . implode(',', array_fill(0, count($testReasonIds), '?')) . ')', $testReasonIds));
            }

            if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                $sampleTypeId = base64_decode(trim($params['sampleTypeId']));
                $rQuery->where(new WhereExpression('vl.specimen_type = ?', [$sampleTypeId]));
            }
            if (isset($params['adherence']) && trim($params['adherence']) != '') {
                $rQuery->where(new WhereExpression("vl.arv_adherance_percentage = ?", $params['adherence']));
            }

            if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                $rQuery->where("(vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                $rQuery->where("vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
            }

            if (isset($params['age']) && trim($params['age']) != '') {
                $age = explode(',', $params['age']);
                $ageConditions = [];
                foreach ($age as $ageGroup) {
                    switch ($ageGroup) {
                        case '<2':
                            $ageConditions[] = "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                            break;
                        case '2to5':
                            $ageConditions[] = "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                            break;
                        case '6to14':
                            $ageConditions[] = "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                            break;
                        case '15to49':
                            $ageConditions[] = "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                            break;
                        case '>=50':
                            $ageConditions[] = "(vl.patient_age_in_years >= 50)";
                            break;
                        case 'unknown':
                            $ageConditions[] = "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown' OR vl.patient_age_in_years = 'Unreported' OR vl.patient_age_in_years = 'unreported')";
                            break;
                    }
                }
                if (!empty($ageConditions)) {
                    $rQuery->where('(' . implode(' OR ', $ageConditions) . ')');
                }
            }

            if (isset($params['gender'])) {
                if ($params['gender'] == 'F') {
                    $rQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                } elseif ($params['gender'] == 'M') {
                    $rQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                } elseif ($params['gender'] == 'not_specified') {
                    $rQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                }
            }

            if (isset($params['isPregnant'])) {
                if ($params['isPregnant'] == 'yes') {
                    $rQuery->where("vl.is_patient_pregnant = 'yes'");
                } elseif ($params['isPregnant'] == 'no') {
                    $rQuery->where("vl.is_patient_pregnant = 'no'");
                } elseif ($params['isPregnant'] == 'unreported') {
                    $rQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported' OR vl.is_patient_pregnant = 'unreported')");
                }
            }

            if (isset($params['isBreastfeeding'])) {
                if ($params['isBreastfeeding'] == 'yes') {
                    $rQuery->where("vl.is_patient_breastfeeding = 'yes'");
                } elseif ($params['isBreastfeeding'] == 'no') {
                    $rQuery->where("vl.is_patient_breastfeeding = 'no'");
                } elseif ($params['isBreastfeeding'] == 'unreported') {
                    $rQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported' OR vl.is_patient_breastfeeding = 'unreported')");
                }
            }

            $rQueryStr = $sql->buildSqlString($rQuery);
            //$qResult = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            $qResult = $this->commonService->cacheQuery($rQueryStr, $dbAdapter);
            $rResult['total']['Male'][0] = (isset($qResult[0]['mTotal'])) ? (int) $qResult[0]['mTotal'] : 0;
            $rResult['total']['Female'][0] = (isset($qResult[0]['fTotal'])) ? (int) $qResult[0]['fTotal'] : 0;
            $rResult['total']['Other'][0] = (isset($qResult[0]['nsTotal'])) ? (int) $qResult[0]['nsTotal'] : 0;
        }
        return $rResult;
    }

    public function getVLTestReasonBasedOnClinics($params)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $result = [];
        if (isset($params['sampleCollectionDate']) && trim($params['sampleCollectionDate']) != '') {
            $s_c_date = explode("to", $params['sampleCollectionDate']);
            if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
                $startDate = trim($s_c_date[0]);
            }
            if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
                $endDate = trim($s_c_date[1]);
            }
            $clinicQuery = $sql->select()->from(array('vl' => $this->table))
                ->columns(array())
                ->join(array('tr' => 'r_vl_test_reasons'), 'tr.test_reason_id=vl.reason_for_vl_testing', array())
                ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array('facility_id', 'facility_name'))
                ->where('vl.facility_id !=0')
                ->group('vl.facility_id');
            if (isset($params['clinicId']) && trim($params['clinicId']) != '') {
                $clinicQuery = $clinicQuery->where('vl.facility_id IN (' . $params['clinicId'] . ')');
            } elseif ($loginContainer->role != 1) {
                $mappedFacilities = $loginContainer->mappedFacilities ?? [];
                $clinicQuery = $clinicQuery->where('vl.facility_id IN ("' . implode('", "', $mappedFacilities) . '")');
            }
            $clinicQueryStr = $sql->buildSqlString($clinicQuery);
            $clinicResult  = $dbAdapter->query($clinicQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (isset($clinicResult) && count($clinicResult) > 0) {
                $c = 0;
                foreach ($clinicResult as $clinic) {
                    $rQuery = $sql->select()->from(array('vl' => $this->table))
                        ->columns(array("total" => new Expression('COUNT(*)')))
                        ->join(array('tr' => 'r_vl_test_reasons'), 'tr.test_reason_id=vl.reason_for_vl_testing', array())
                        ->join(array('f' => 'facility_details'), 'f.facility_id=vl.facility_id', array())
                        ->where(array('vl.facility_id' => $clinic['facility_id']))
                        ->where(array("DATE(vl.sample_collection_date) >='$startDate'", "DATE(vl.sample_collection_date) <='$endDate'"));
                    if (isset($params['testReason']) && trim($params['testReason']) != '') {
                        $rQuery = $rQuery->where('vl.reason_for_vl_testing IN (' . $params['testReason'] . ')');
                    }
                    if (isset($params['sampleTypeId']) && $params['sampleTypeId'] != '') {
                        $rQuery = $rQuery->where('vl.specimen_type="' . base64_decode(trim($params['sampleTypeId'])) . '"');
                    }
                    if (isset($params['adherence']) && trim($params['adherence']) != '') {
                        $rQuery = $rQuery->where(array("vl.arv_adherance_percentage ='" . $params['adherence'] . "'"));
                    }
                    if (isset($params['testResult']) && $params['testResult'] == '<1000') {
                        $rQuery = $rQuery->where("(vl.result < 1000 or vl.result = 'Target Not Detected' or vl.result = 'TND' or vl.result = 'tnd' or vl.result= 'Below Detection Level' or vl.result='BDL' or vl.result='bdl' or vl.result= 'Low Detection Level' or vl.result='LDL' or vl.result='ldl') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
                    } elseif (isset($params['testResult']) && $params['testResult'] == '>=1000') {
                        $rQuery = $rQuery->where("vl.result IS NOT NULL AND vl.result!= '' AND vl.result >= 1000 AND vl.result!='Failed' AND vl.result!='failed' AND vl.result!='Fail' AND vl.result!='fail' AND vl.result!='No Sample' AND vl.result!='no sample' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'");
                    }
                    if (isset($params['age']) && trim($params['age']) != '') {
                        $age = explode(',', $params['age']);
                        $where = '';
                        $counter = count($age);
                        for ($a = 0; $a < $counter; $a++) {
                            if (trim($where) != '') {
                                $where .= ' OR ';
                            }
                            if ($age[$a] == '<2') {
                                $where .= "(vl.patient_age_in_years > 0 AND vl.patient_age_in_years < 2)";
                            } elseif ($age[$a] == '2to5') {
                                $where .= "(vl.patient_age_in_years >= 2 AND vl.patient_age_in_years <= 5)";
                            } elseif ($age[$a] == '6to14') {
                                $where .= "(vl.patient_age_in_years >= 6 AND vl.patient_age_in_years <= 14)";
                            } elseif ($age[$a] == '15to49') {
                                $where .= "(vl.patient_age_in_years >= 15 AND vl.patient_age_in_years <= 49)";
                            } elseif ($age[$a] == '>=50') {
                                $where .= "(vl.patient_age_in_years >= 50)";
                            } elseif ($age[$a] == 'unknown') {
                                $where .= "(vl.patient_age_in_years IS NULL OR vl.patient_age_in_years = '' OR vl.patient_age_in_years = 'Unknown' OR vl.patient_age_in_years = 'unknown')";
                            }
                        }
                        $where = '(' . $where . ')';
                        $rQuery = $rQuery->where($where);
                    }

                    if (isset($params['gender']) && $params['gender'] == 'F') {
                        $rQuery = $rQuery->where("vl.patient_gender IN ('f','female','F','FEMALE')");
                    } elseif (isset($params['gender']) && $params['gender'] == 'M') {
                        $rQuery = $rQuery->where("vl.patient_gender IN ('m','male','M','MALE')");
                    } elseif (isset($params['gender']) && $params['gender'] == 'not_specified') {
                        $rQuery = $rQuery->where("(vl.patient_gender IS NULL OR vl.patient_gender = '' OR vl.patient_gender ='Not Recorded' OR vl.patient_gender = 'not recorded')");
                    }
                    if (isset($params['isPregnant']) && $params['isPregnant'] == 'yes') {
                        $rQuery = $rQuery->where("vl.is_patient_pregnant = 'yes'");
                    } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'no') {
                        $rQuery = $rQuery->where("vl.is_patient_pregnant = 'no'");
                    } elseif (isset($params['isPregnant']) && $params['isPregnant'] == 'unreported') {
                        $rQuery = $rQuery->where("(vl.is_patient_pregnant IS NULL OR vl.is_patient_pregnant = '' OR vl.is_patient_pregnant = 'Unreported')");
                    }
                    if (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'yes') {
                        $rQuery = $rQuery->where("vl.is_patient_breastfeeding = 'yes'");
                    } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'no') {
                        $rQuery = $rQuery->where("vl.is_patient_breastfeeding = 'no'");
                    } elseif (isset($params['isBreastfeeding']) && $params['isBreastfeeding'] == 'unreported') {
                        $rQuery = $rQuery->where("(vl.is_patient_breastfeeding IS NULL OR vl.is_patient_breastfeeding = '' OR vl.is_patient_breastfeeding = 'Unreported')");
                    }
                    $rQueryStr = $sql->buildSqlString($rQuery);
                    $rResult  = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    $result['clinic'][$c] = addslashes($clinic['facility_name']);
                    $result['sample']['total'][$c] = $rResult['total'] ?? 0;
                    $c++;
                }
            }
        }
        return $result;
    }

    public function getSample($id)
    {
        return false;
    }
    ////////////////////////////////////////////
    /////////*** Turnaround Time Page ***///////
    ///////////////////////////////////////////

    public function getTATbyProvince($labs, $startDate, $endDate, $params = "")
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $skipDays = isset($this->config['defaults']['tat-skipdays']) ? $this->config['defaults']['tat-skipdays'] : 365;
        $squery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "Collection_Receive"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_received_at_lab_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                    "Receive_Register"    => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_received_at_lab_datetime))) AS DECIMAL (10,2))"),
                    "Register_Analysis"   => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "Analysis_Authorise"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "total"               => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                )
            )
            ->join('facility_details', 'facility_details.facility_id = vl.facility_id', array())
            ->join('geographical_divisions', 'facility_details.facility_state = geographical_divisions.geo_id')
            ->where(
                array(
                    "(sample_tested_datetime BETWEEN '$startDate' AND '$endDate')",
                    "(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) not like '1970-01-01' AND DATE(vl.sample_collection_date) not like '0000-00-00')",
                    //"facility_details.facility_state = '$provinceID'",
                )
            );

        // $squery = $squery->where("
        //                 (vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')
        //                 AND (vl.sample_registered_at_lab is not null AND vl.sample_registered_at_lab not like '' AND DATE(vl.sample_registered_at_lab) !='1970-01-01' AND DATE(vl.sample_registered_at_lab) !='0000-00-00')
        //                 AND (vl.sample_received_at_lab_datetime is not null AND vl.sample_received_at_lab_datetime not like '' AND DATE(vl.sample_received_at_lab_datetime) !='1970-01-01' AND DATE(vl.sample_received_at_lab_datetime) !='0000-00-00')
        //                 AND (vl.result_approved_datetime is not null AND vl.result_approved_datetime not like '' AND DATE(vl.result_approved_datetime) !='1970-01-01' AND DATE(vl.result_approved_datetime) !='0000-00-00')"
        //             );
        if ($skipDays > 0) {
            $squery = $squery->where('DATEDIFF(sample_received_at_lab_datetime,sample_collection_date) < ' . $skipDays . ' AND
                DATEDIFF(sample_received_at_lab_datetime,sample_collection_date) >= 0 AND

                DATEDIFF(sample_registered_at_lab,sample_received_at_lab_datetime) < ' . $skipDays . ' AND
                DATEDIFF(sample_registered_at_lab,sample_received_at_lab_datetime) >= 0 AND

                DATEDIFF(sample_tested_datetime,sample_received_at_lab_datetime) < ' . $skipDays . ' AND
                DATEDIFF(sample_tested_datetime,sample_registered_at_lab)>=0 AND

                DATEDIFF(result_approved_datetime,sample_tested_datetime) < ' . $skipDays . ' AND
                DATEDIFF(result_approved_datetime,sample_tested_datetime) >= 0');
        }

        if (isset($labs) && !empty($labs)) {
            $squery = $squery->where('vl.lab_id IN (' . implode(',', $labs) . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $squery = $squery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $squery = $squery->group(array('geo_id'));
        $sQueryStr = $sql->buildSqlString($squery);
        //echo $sQueryStr;die;
        $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
        return $sResult;
    }

    public function getTATbyDistrict($labs, $startDate, $endDate)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $skipDays = isset($this->config['defaults']['tat-skipdays']) ? $this->config['defaults']['tat-skipdays'] : 365;
        $squery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "Collection_Receive"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_received_at_lab_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                    "Receive_Register"    => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_received_at_lab_datetime))) AS DECIMAL (10,2))"),
                    "Register_Analysis"   => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "Analysis_Authorise"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "total"               => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                )
            )
            ->join('facility_details', 'facility_details.facility_id = vl.facility_id')
            ->join('geographical_divisions', 'facility_details.facility_state = geographical_divisions.geo_id')
            ->where(
                array(
                    "(sample_tested_datetime BETWEEN '$startDate' AND '$endDate')",
                    "(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) not like '1970-01-01' AND DATE(vl.sample_collection_date) not like '0000-00-00')",
                    // "facility_details.facility_district = '$districtID'"
                )
            );
        if ($skipDays > 0) {
            $squery = $squery->where("
                DATEDIFF(sample_received_at_lab_datetime,sample_collection_date) < $skipDays AND
                DATEDIFF(sample_received_at_lab_datetime,sample_collection_date)>=0 AND

                DATEDIFF(sample_registered_at_lab,sample_received_at_lab_datetime) < $skipDays AND
                DATEDIFF(sample_registered_at_lab,sample_received_at_lab_datetime)>=0 AND

                DATEDIFF(sample_tested_datetime,sample_received_at_lab_datetime) < $skipDays AND
                DATEDIFF(sample_tested_datetime,sample_registered_at_lab)>=0 AND

                DATEDIFF(result_approved_datetime,sample_tested_datetime) < $skipDays AND
                DATEDIFF(result_approved_datetime,sample_tested_datetime)>=0");
        }

        if (isset($labs) && !empty($labs)) {
            $squery = $squery->where('vl.lab_id IN (' . implode(',', $labs) . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $squery = $squery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $squery = $squery->group(array('geo_id'));
        $sQueryStr = $sql->buildSqlString($squery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function getTATbyClinic($labs, $startDate, $endDate)
    {
        $loginContainer = new Container('credo');
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $skipDays = isset($this->config['defaults']['tat-skipdays']) ? $this->config['defaults']['tat-skipdays'] : 365;
        $squery = $sql->select()->from(array('vl' => $this->table))
            ->columns(
                array(
                    "Collection_Receive"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_received_at_lab_datetime,sample_collection_date))) AS DECIMAL (10,2))"),
                    "Receive_Register"    => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_received_at_lab_datetime))) AS DECIMAL (10,2))"),
                    "Register_Analysis"   => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,sample_registered_at_lab,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "Analysis_Authorise"  => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_tested_datetime))) AS DECIMAL (10,2))"),
                    "total"               => new Expression("CAST(AVG(ABS(TIMESTAMPDIFF(DAY,result_approved_datetime,sample_collection_date))) AS DECIMAL (10,2))")
                )
            )
            ->join('facility_details', 'facility_details.facility_id = vl.facility_id')
            ->join('geographical_divisions', 'facility_details.facility_state = geographical_divisions.geo_id')
            ->where(
                array(
                    "(sample_tested_datetime BETWEEN '$startDate' AND '$endDate')",
                    "(vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) not like '1970-01-01' AND DATE(vl.sample_collection_date) not like '0000-00-00')",
                    // "vl.facility_id = '$clinicID'"
                )
            );
        if ($skipDays > 0) {
            $squery = $squery->where("
                DATEDIFF(sample_received_at_lab_datetime,sample_collection_date) < $skipDays AND
                DATEDIFF(sample_received_at_lab_datetime,sample_collection_date) >= 0 AND

                DATEDIFF(sample_registered_at_lab,sample_received_at_lab_datetime) < $skipDays AND
                DATEDIFF(sample_registered_at_lab,sample_received_at_lab_datetime)>=0 AND

                DATEDIFF(sample_tested_datetime,sample_received_at_lab_datetime) < $skipDays AND
                DATEDIFF(sample_tested_datetime,sample_registered_at_lab)>=0 AND

                DATEDIFF(result_approved_datetime,sample_tested_datetime) < $skipDays AND
                DATEDIFF(result_approved_datetime,sample_tested_datetime)>= 0");
        }

        if (isset($labs) && !empty($labs)) {
            $squery = $squery->where('vl.lab_id IN (' . implode(',', $labs) . ')');
        } elseif ($loginContainer->role != 1) {
            $mappedFacilities = $loginContainer->mappedFacilities ?? [];
            $squery = $squery->where('vl.lab_id IN ("' . implode('", "', $mappedFacilities) . '")');
        }
        $squery = $squery->group(array('geo_id'));
        $sQueryStr = $sql->buildSqlString($squery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }

    /////////////////////////////////////////////
    /////////*** Turnaround Time Page ***////////
    ////////////////////////////////////////////



    //api for fetch samples refer SourceData Controller
    public function fetchSourceData($params)
    {
        $result = [];
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        //check if the token is valid or not

        $uQuery = $sql->select()->from(array('vl' => 'dash_users'))
            ->where(array('api_token' => $params['token'], 'role' => 6));

        $uQueryStr = $sql->buildSqlString($uQuery);
        $uResult = $dbAdapter->query($uQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
        if (isset($uResult['user_id'])) {
            $sQuery = $sql->select()->from(array('vl' => $this->table))->columns(array('sample_code', 'sample_collection_date', 'sample_tested_datetime', 'result', 'patient_art_no'))
                ->join(array('r_r_r' => 'r_vl_sample_rejection_reasons'), 'r_r_r.rejection_reason_id=vl.reason_for_sample_rejection', array('rejection_reason_name'), 'left')
                ->join(array('rss' => 'r_sample_status'), 'rss.status_id=vl.result_status', array('status_name'), 'left');

            if (isset($params['patient_id']) && $params['patient_id'] != '') {
                $sQuery = $sQuery->where(array('patient_art_no' => $params['patient_id']));
            }
            if (isset($params['facility_id']) && $params['facility_id'] != '') {
                $sQuery = $sQuery->where(array('facility_id' => $params['facility_id']));
            }
            if (isset($params['return_results']) && $params['return_results'] == 1) {
                $sQuery = $sQuery->order('vl_sample_id DESC')
                    ->limit(1);
            }
            $sQueryStr = $sql->buildSqlString($sQuery);
            $rResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
            if (!empty($rResult)) {
                $result['status'] = '200';
                $result['result'] = $rResult;
            } else {
                $result['status'] = '200';
                $result['result'] = [];
            }
        } else {
            $result['status'] = '403';
            $result['result'] = 'API KEY INVALID';
        }
        return $result;
    }


    public function generateBackup()
    {

        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);

        $generateSql = "SELECT * FROM generate_backups where status='pending' ORDER BY id asc LIMIT 1";

        $generateResult = $dbAdapter->query($generateSql, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

        if (!empty($generateResult)) {

            $startDate = $generateResult[0]['start_date'];
            $endDate = $generateResult[0]['end_date'];

            $sQuery = "SELECT
            vl.sample_code,
            vl.patient_art_no,
            vl.patient_gender,
            vl.patient_age_in_years,
            vl.sample_collection_date,
            vl.sample_received_at_lab_datetime,
            vl.sample_registered_at_lab,
            vl.result_approved_datetime,
            vl.is_adherance_poor,
            vl.arv_adherance_percentage,
            vl.current_regimen,
            vl.date_of_initiation_of_current_regimen,
            vl.line_of_treatment,
            vl.is_sample_rejected,
            vl.sample_tested_datetime,
            vl.result_value_log,
            vl.result_value_absolute,
            vl.result_value_text,
            vl.result_value_absolute_decimal,
            vl.result,
            s.sample_name,
            s.status as sample_type_status,
            ts.status_name,
            f.facility_name,
            f.facility_code,
            f.facility_state,
            f.facility_district,
            f.facility_mobile_numbers,
            f.address,
            f.facility_hub_name,
            f.contact_person,
            f.report_email,
            f.country,
            f.longitude,
            f.latitude,
            f.facility_type,
            f.status as facility_status,
            ft.facility_type,
            lft.facility_type as labFacilityTypeName,
            l_f.facility_name as labName,
            l_f.facility_code as labCode,
            l_f.facility_state as labState,
            l_f.facility_district as labDistrict,
            l_f.facility_mobile_numbers as labPhone,
            l_f.address as labAddress,
            l_f.facility_hub_name as labHub,
            l_f.contact_person as labContactPerson,
            l_f.report_email as labReportMail,
            l_f.country as labCountry,
            l_f.longitude as labLongitude,
            l_f.latitude as labLatitude,
            l_f.facility_type as labFacilityType,
            l_f.status as labFacilityStatus,
            tr.test_reason_name,
            tr.test_reason_status,
            rsrr.rejection_reason_name,
            rsrr.rejection_reason_status
            FROM  " . $this->table . "  as vl
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
            LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
            LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
            LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing
            LEFT JOIN facility_type as ft ON ft.facility_type_id=f.facility_type
            LEFT JOIN facility_type as lft ON lft.facility_type_id=l_f.facility_type
            LEFT JOIN r_vl_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection
            WHERE sample_code is not null AND sample_code !='' ";

            $sQuery .= " AND DATE(vl.sample_collection_date) >= '$startDate' AND DATE(vl.sample_collection_date) <= '$endDate'";

            //echo $sQuery;die;
            $rResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

            //var_dump($rResult);die;

            $output = [];
            $headings = array("Sample Code", "Patient ID (ART No.)", "Gender", "Age In Years", "Clinic Name", "Clinic Code", "Clinic Phone Number", "Clinic Address", "Clinic HUB Name", "Clinic Contact Person", "Clinic Report Mail", "Clinic Country", "Clinic Longitude", "Clinic Latitude", "Sample Type", "Sample Collection Date", "LAB Name", "Lab Code", "Lab Phone Number", "Lab Address", "Lab HUB Name", "Lab Contact Person", "Lab Report Mail", "Lab Country", "Lab Longitude", "Lab Latitude", "Lab Type", "Lab Tested Date", "Log Value", "Absolute Value", "Text Value", "Absolute Decimal Value", "Result", "Testing Reason", "Sample Status", "Sample Received Datetime", "Line Of Treatment", "Sample Rejected", "Rejection Reason Name", "Rejection Reason Status", "Pregnant", "Breast Feeding", "Regimen Initiated Date", "ARV Adherance Percentage", "Is Adherance poor", "Approved Datetime", "Current Regimen", "Sample Registered Datetime");
            foreach ($rResult as $aRow) {
                $row = [];
                $row[] = $aRow['sample_code'];
                $row[] = $aRow['patient_art_no'];
                $row[] = $aRow['patient_gender'];
                $row[] = $aRow['patient_age_in_years'];
                $row[] = ($aRow['facility_name']);
                $row[] = ($aRow['facility_code']);
                $row[] = ($aRow['facility_mobile_numbers']);
                $row[] = ($aRow['address']);
                $row[] = ($aRow['facility_hub_name']);
                $row[] = ($aRow['contact_person']);
                $row[] = ($aRow['report_email']);
                $row[] = ($aRow['country']);
                $row[] = ($aRow['longitude']);
                $row[] = ($aRow['latitude']);
                $row[] = $aRow['sample_name'];
                $row[] = $aRow['sample_collection_date'];
                $row[] = ($aRow['labName']);
                $row[] = ($aRow['labCode']);
                $row[] = $aRow['labPhone'];
                $row[] = $aRow['labAddress'];
                $row[] = $aRow['labHub'];
                $row[] = ($aRow['labContactPerson']);
                $row[] = ($aRow['labReportMail']);
                $row[] = ($aRow['labCountry']);
                $row[] = ($aRow['labLongitude']);
                $row[] = ($aRow['labLatitude']);
                $row[] = ($aRow['labFacilityTypeName']);
                $row[] = $aRow['sample_tested_datetime'];
                $row[] = $aRow['result_value_log'];
                $row[] = $aRow['result_value_absolute'];
                $row[] = $aRow['result_value_text'];
                $row[] = $aRow['result_value_absolute_decimal'];
                $row[] = $aRow['result'];
                $row[] = ($aRow['test_reason_name']);
                $row[] = ($aRow['status_name']);
                $row[] = $aRow['sample_received_at_lab_datetime'];
                $row[] = $aRow['line_of_treatment'];
                $row[] = $aRow['is_sample_rejected'];
                $row[] = $aRow['rejection_reason_name'];
                $row[] = $aRow['rejection_reason_status'];
                $row[] = (isset($aRow['is_patient_pregnant']) && $aRow['is_patient_pregnant'] != null && $aRow['is_patient_pregnant'] != '') ? $aRow['is_patient_pregnant'] : 'unreported';
                $row[] = (isset($aRow['is_patient_breastfeeding']) && $aRow['is_patient_breastfeeding'] != null && $aRow['is_patient_breastfeeding'] != '') ? $aRow['is_patient_breastfeeding'] : 'unreported';

                $row[] = $aRow['date_of_initiation_of_current_regimen'];
                $row[] = $aRow['arv_adherance_percentage'];
                $row[] = $aRow['is_adherance_poor'];
                $row[] = $aRow['result_approved_datetime'];
                $row[] = $aRow['current_regimen'];
                $row[] = $aRow['sample_registered_at_lab'];
                $output[] = $row;
            }

            if (!is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'backups')) {
                mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'backups', true);
            }

            $csvFile = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'export-data-' . $startDate . '-' . $endDate . '-' . CommonService::generateRandomString(6) . '.csv';

            CommonService::generateCsv($headings, $output, $csvFile);

            return array('fileName' => $csvFile, 'backupId' => $generateResult[0]['id']);
        }
    }

    public function insertOrUpdate($arrayData)
    {
        return CommonService::upsert($this->adapter, $this->table, $arrayData);
    }
}
