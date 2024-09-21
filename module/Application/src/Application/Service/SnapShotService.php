<?php

namespace Application\Service;

use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Application\Service\CommonService;

class SnapShotService
{

    public $sm;
    public CommonService $commonService;
    public Adapter $adapter;
    protected $translator = null;
    public function __construct($sm, $commonService, $dbAdapter)
    {
        $this->sm = $sm;
        $this->commonService = $commonService;
        $this->adapter = $dbAdapter;
        $this->translator = $this->sm->get('translator');
    }
    public function getSnapshotData($params)
    {

        $loginContainer = new Container('credo');
        $mappedFacilities = $loginContainer->mappedFacilities ?? null;
        $testTypeQuery = [];
        $where = [];

        if (isset($params['collectionDate']) && !empty($params['collectionDate'])) {
            [$from, $to] = CommonService::convertDateRange($params['collectionDate']);
            $where[] = " DATE(sample_collection_date) BETWEEN '$from' AND '$to' ";
        }
        if (isset($params['testedDate']) && !empty($params['testedDate'])) {
            [$from, $to] = CommonService::convertDateRange($params['testedDate']);
            $where[] = " DATE(sample_tested_datetime) BETWEEN '$from' AND '$to' ";
        }
        if (isset($params['provinceName']) && !empty($params['provinceName'])) {
            $where[] = " facility_state_id IN(" . implode(",", $params['provinceName']) . ") ";
        }
        if (isset($params['districtName']) && !empty($params['districtName'])) {
            $where[] = " facility_district_id IN(" . implode(",", $params['districtName']) . ") ";
        }
        if (isset($params['clinicId']) && !empty($params['clinicId'])) {
            $where[] = " facility_id IN(" . implode(",", $params['clinicId']) . ") ";
        }
        if (isset($params['labId']) && !empty($params['labId'])) {
            $where[] = " lab_id IN(" . implode(",", $params['labId']) . ") ";
        }
        if (!empty($params['flag']) && $params['flag'] == 'poc') {
            $where[] = " icm.poc_device = 'yes'";
        }
        if (isset($mappedFacilities) && !empty($mappedFacilities)) {
            $where[] = " lab_id IN (" . implode(', ', $mappedFacilities) . ")";
        }
        if (isset($where) && !empty($where)) {
            $whereQuery = " WHERE " . implode(" AND ", $where);
        }
        $types = ["vl", "eid", "tb", "covid19", "hepatitis"];
        if (isset($params['testType']) && !empty($params['testType'])) {
            $types = $params['testType'];
        }
        if (isset($types) && !empty($types)) {
            foreach ($types as $type) {
                $q = " SELECT count(*) AS reg,
                SUM(CASE WHEN
                    (sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00')
                        THEN 1 ELSE 0 END) AS 'totalReceived',

                SUM(CASE WHEN
                    (
                        sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00'
                    ) THEN 1 ELSE 0 END
                ) AS 'totalTested',

                SUM(CASE WHEN
                    (
                        (reason_for_sample_rejection IS NOT NULL AND reason_for_sample_rejection !='' AND reason_for_sample_rejection!= 0) OR (is_sample_rejected like 'yes')
                        AND
                        (sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00')
                    ) THEN 1 ELSE 0 END
                ) AS 'totalRejected',

                SUM(CASE WHEN
                    (
                        (sample_collection_date IS NOT NULL AND sample_collection_date NOT LIKE '' AND DATE(sample_collection_date) NOT LIKE '0000:00:00' AND DATE(sample_collection_date) !='1970-01-01')
                        AND
                        (is_sample_rejected like 'yes' OR result IS NULL OR result LIKE '' OR result_status IN(2,4,5,10))
                    ) THEN 1 ELSE 0 END
                ) AS 'totalPending',
                facility_name FROM dash_form_$type AS $type INNER JOIN facility_details as f ON $type lab_id = f.facility_id ";

                if (!empty($params['flag']) && $params['flag'] == 'poc') {
                    $q .= " INNER JOIN instrument_machines as icm ON $type.import_machine_name = icm.config_machine_id ";
                }
                $q .= "$whereQuery GROUP BY f.facility_id ";
                $testTypeQuery[] = $q;
            }
        }


        $testTypeQuery = implode(" UNION ALL ", $testTypeQuery);
        $sql = "SELECT t.facility_name AS clinicName,
                        SUM(t.reg) AS total,
                        SUM(t.totalReceived) AS totalReceived,
                        SUM(t.totalTested) AS totalTested,
                        SUM(t.totalRejected) AS totalRejected,
                        SUM(t.totalPending) AS totalPending
                            FROM ($testTypeQuery) t GROUP BY clinicName ORDER BY total DESC";
        return $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE)->toArray();
    }

    public function getSnapshotQuickStatsDetails($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $globalDb = $this->sm->get('GlobalTable');
        $loginContainer = new Container('credo');
        $mappedFacilities = $loginContainer->mappedFacilities ?? null;

        $samplesWaitingFromLastXMonths = $globalDb->getGlobalValue('sample_waiting_month_range');
        $age['vl'] = "patient_age_in_years";
        $age['eid'] = "child_age";
        $age['covid19'] = "patient_age";
        $age['hepatitis'] = "patient_age";
        $age['tb'] = "patient_age";
        $query = [];
        //get received data
        $types = array("vl", "eid", "tb", "covid19", "hepatitis");
        if (isset($params['testType']) && !empty($params['testType'])) {
            $types = $params['testType'];
        }
        if (isset($types) && !empty($types)) {
            foreach ($types as $type) {
                $gender = ($type == 'eid') ? 'child_gender' : 'patient_gender';
                $quickStatsquery = $sql->select()->from(array("vl" => "dash_form_" . $type))
                    ->columns(
                        array(
                            $this->translator->translate("total") => new Expression('COUNT(*)'),

                            $this->translator->translate("recevied") => new Expression("SUM(CASE WHEN (sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00') THEN 1 ELSE 0 END)"),

                            $this->translator->translate("tested") => new Expression("SUM(CASE WHEN (
                                (sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')
                            ) THEN 1 ELSE 0 END)"),

                            $this->translator->translate("gender") => new Expression("SUM(CASE WHEN ((" . $gender . " IS NULL OR " . $gender . " ='' OR " . $gender . " ='unreported' OR " . $gender . " ='Unreported')) THEN 1 ELSE 0 END)"),

                            $this->translator->translate("age") => new Expression("SUM(CASE WHEN ((" . $age[$type] . " IS NULL OR " . $age[$type] . " ='' OR " . $age[$type] . " ='Unreported'  OR " . $age[$type] . " ='unreported')) THEN 1 ELSE 0 END)"),

                            $this->translator->translate("less6") => new Expression("SUM(CASE WHEN ((sample_collection_date < DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH)) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END)"),

                            $this->translator->translate("greater6") => new Expression("SUM(CASE WHEN ((sample_collection_date > DATE_SUB(NOW(), INTERVAL $samplesWaitingFromLastXMonths MONTH)) AND (reason_for_sample_rejection is NULL or reason_for_sample_rejection ='' or reason_for_sample_rejection = 0)) THEN 1 ELSE 0 END)")
                        )
                    );
                // recevied data
                $receivedQuery = $sql->select()->from(array("vl" => "dash_form_" . $type))
                    ->columns(array('total' => new Expression("SUM(
                        CASE WHEN(
                            sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'
                        ) THEN 1 ELSE 0 END
                    )"), 'receivedDate' => new Expression('DATE(sample_collection_date)')))
                    // ->where("sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00'")
                    ->group(array("receivedDate"));
                //tested data
                $testedQuery = $sql->select()->from(array("vl" => "dash_form_" . $type))
                    ->columns(array('total' => new Expression("SUM(
                        CASE WHEN(
                            (sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')
                        ) THEN 1 ELSE 0 END
                    )"), 'testedDate' => new Expression('DATE(sample_tested_datetime)')))
                    // ->where("(is_sample_rejected IS NULL AND is_sample_rejected = '' AND is_sample_rejected like 'no') AND (sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')")
                    ->group(array("testedDate"));
                //get rejected data
                $rejectedQuery = $sql->select()->from(array("vl" => "dash_form_" . $type))
                    ->columns(array('total' => new Expression("SUM(
                    CASE WHEN (
                        (is_sample_rejected like 'yes' OR result_status IN(4))
                        AND
                        (sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00')
                    ) THEN 1 ELSE 0 END
                )"), 'rejectedDate' => new Expression('DATE(sample_collection_date)')))
                    // ->where("(is_sample_rejected like 'yes' OR result IS NULL OR result LIKE '' OR result_status IN(2,4,5,10)) AND (sample_collection_date is not null AND sample_collection_date not like '' AND DATE(sample_collection_date) !='1970-01-01' AND DATE(sample_collection_date) !='0000-00-00')")
                    ->group(array("rejectedDate"));

                if (isset($params['collectionDate']) && !empty($params['collectionDate'])) {
                    $date = explode(" to ", $params['collectionDate']);
                    $quickStatsquery = $quickStatsquery->where(array("DATE(sample_collection_date) >='" . $this->commonService->isoDateFormat($date[0]) . "'", "DATE(sample_collection_date) <='" . $this->commonService->isoDateFormat($date[1]) . "'"));
                    $receivedQuery = $receivedQuery->where(array("DATE(sample_collection_date) >='" . $this->commonService->isoDateFormat($date[0]) . "'", "DATE(sample_collection_date) <='" . $this->commonService->isoDateFormat($date[1]) . "'"));
                    $testedQuery = $testedQuery->where(array("DATE(sample_collection_date) >='" . $this->commonService->isoDateFormat($date[0]) . "'", "DATE(sample_collection_date) <='" . $this->commonService->isoDateFormat($date[1]) . "'"));
                    $rejectedQuery = $rejectedQuery->where(array("DATE(sample_collection_date) >='" . $this->commonService->isoDateFormat($date[0]) . "'", "DATE(sample_collection_date) <='" . $this->commonService->isoDateFormat($date[1]) . "'"));
                }
                if (isset($params['testedDate']) && !empty($params['testedDate'])) {
                    $date = explode(" to ", $params['testedDate']);
                    $quickStatsquery = $quickStatsquery->where(array("DATE(sample_tested_datetime) >='" . $this->commonService->isoDateFormat($date[0]) . "'", "DATE(sample_tested_datetime) <='" . $this->commonService->isoDateFormat($date[1]) . "'"));
                    $receivedQuery = $receivedQuery->where(array("DATE(sample_tested_datetime) >='" . $this->commonService->isoDateFormat($date[0]) . "'", "DATE(sample_tested_datetime) <='" . $this->commonService->isoDateFormat($date[1]) . "'"));
                    $testedQuery = $testedQuery->where(array("DATE(sample_tested_datetime) >='" . $this->commonService->isoDateFormat($date[0]) . "'", "DATE(sample_tested_datetime) <='" . $this->commonService->isoDateFormat($date[1]) . "'"));
                    $rejectedQuery = $rejectedQuery->where(array("DATE(sample_tested_datetime) >='" . $this->commonService->isoDateFormat($date[0]) . "'", "DATE(sample_tested_datetime) <='" . $this->commonService->isoDateFormat($date[1]) . "'"));
                }
                if (!empty($params['flag']) && $params['flag'] == 'poc') {
                    $quickStatsquery = $quickStatsquery->join(array('icm' => 'instrument_machines'), 'icm.config_machine_id = vl.import_machine_name', array('poc_device'))->where(array('icm.poc_device' => 'yes'));
                    $receivedQuery = $receivedQuery->join(array('icm' => 'instrument_machines'), 'icm.config_machine_id = vl.import_machine_name', array('poc_device'))->where(array('icm.poc_device' => 'yes'));
                    $testedQuery = $testedQuery->join(array('icm' => 'instrument_machines'), 'icm.config_machine_id = vl.import_machine_name', array('poc_device'))->where(array('icm.poc_device' => 'yes'));
                    $rejectedQuery = $rejectedQuery->join(array('icm' => 'instrument_machines'), 'icm.config_machine_id = vl.import_machine_name', array('poc_device'))->where(array('icm.poc_device' => 'yes'));
                }
                if (isset($mappedFacilities) && !empty($mappedFacilities)) {
                    $quickStatsquery = $quickStatsquery->where(array("lab_id IN ('" . implode('", "', $mappedFacilities) . "')"));
                    $receivedQuery = $receivedQuery->where(array("lab_id IN ('" . implode('", "', $mappedFacilities) . "')"));
                    $testedQuery = $testedQuery->where(array("lab_id IN ('" . implode('", "', $mappedFacilities) . "')"));
                    $rejectedQuery = $rejectedQuery->where(array("lab_id IN ('" . implode('", "', $mappedFacilities) . "')"));
                }
                $query['quickStats'][] = $sql->buildSqlString($quickStatsquery);
                $query['received'][] = $sql->buildSqlString($receivedQuery);
                $query['tested'][] = $sql->buildSqlString($testedQuery);
                $query['rejected'][] = $sql->buildSqlString($rejectedQuery);
            }
        }
        $quickStatsQuery = implode(" UNION ALL ", $query['quickStats']);
        $sql = "SELECT SUM(t.recevied) AS 'Total Samples',
            SUM(t.tested) AS 'Samples Tested',
            SUM(t.gender) AS 'Gender Missing',
            SUM(t.age) AS 'Age Missing',
            SUM(t.less6) AS 'Results Not Available (< 6 months)',
            SUM(t.greater6) AS 'Results Not Available (> 6 months)' ";
        $sql .= " FROM ($quickStatsQuery) t ";

        $finalResult['quickStats'] = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE)->current();

        foreach (['received', 'tested', 'rejected'] as $r) {
            $sql = "SELECT SUM(t.total) AS total, t." . $r . "Date ";
            $sql .= " FROM (
                    " . implode(" UNION ALL ", $query[$r]) . "
            ) t GROUP BY " . $r . "Date ORDER BY " . $r . "Date DESC";
            $result = $this->adapter->query($sql, Adapter::QUERY_MODE_EXECUTE)->toArray();
            $totalSum = 0;
            foreach ($result as $rRow) {
                $displayDate = date("d-M-Y", strtotime($rRow[$r . 'Date']));
                $finalResult[$r][] = array(array('total' => $rRow['total']), 'date' => $displayDate, $r . 'Date' => $displayDate, $r . 'Total' => $totalSum += $rRow['total']);
            }
        }
        return ['quickStats' => $finalResult['quickStats'] ?? null, 'scResult' => $finalResult['received'] ?? null, 'stResult' => $finalResult['tested'] ?? null, 'srResult' => $finalResult['rejected'] ?? null];
    }
}
