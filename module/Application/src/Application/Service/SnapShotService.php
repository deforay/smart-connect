<?php

namespace Application\Service;

use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Application\Service\CommonService;
use Laminas\Db\Sql\Predicate\Expression as WhereExpression;

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
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $loginContainer = new Container('credo');
        $mappedFacilities = $loginContainer->mappedFacilities ?? null;

        $types = $params['testType'] ?? ["vl", "eid", "covid19"];
        $testTypeQuery = [];
        $whereConditions = [];

        // Collection Date
        if (isset($params['collectionDate']) && !empty($params['collectionDate'])) {
            [$from, $to] = CommonService::convertDateRange($params['collectionDate']);
            $whereConditions[] = new WhereExpression("DATE(sample_collection_date) BETWEEN ? AND ?", [$from, $to]);
        }

        // Tested Date
        if (isset($params['testedDate']) && !empty($params['testedDate'])) {
            [$from, $to] = CommonService::convertDateRange($params['testedDate']);
            $whereConditions[] = new WhereExpression("DATE(sample_tested_datetime) BETWEEN ? AND ?", [$from, $to]);
        }

        // Province Name
        if (isset($params['provinceName']) && !empty($params['provinceName'])) {
            $whereConditions[] = new WhereExpression("facility_state_id IN (?)", [implode(",", $params['provinceName'])]);
        }

        // District Name
        if (isset($params['districtName']) && !empty($params['districtName'])) {
            $whereConditions[] = new WhereExpression("facility_district_id IN (?)", [implode(",", $params['districtName'])]);
        }

        // Clinic ID
        if (isset($params['clinicId']) && !empty($params['clinicId'])) {
            $whereConditions[] = new WhereExpression("facility_id IN (?)", [implode(",", $params['clinicId'])]);
        }

        // Lab ID
        if (isset($params['labId']) && !empty($params['labId'])) {
            $whereConditions[] = new WhereExpression("lab_id IN (?)", [implode(",", $params['labId'])]);
        }

        // POC Flag
        if (!empty($params['flag']) && $params['flag'] == 'poc') {
            $whereConditions[] = new WhereExpression("icm.poc_device = ?", ['yes']);
        }

        // Mapped Facilities
        if (!empty($mappedFacilities)) {
            $whereConditions[] = new WhereExpression("lab_id IN (?)", [implode(", ", $mappedFacilities)]);
        }

        foreach ($types as $type) {
            $select = $sql->select()
                ->from(["$type" => "dash_form_$type"])
                ->columns([
                    'reg' => new Expression("COUNT(*)"),
                    'totalReceived' => new Expression("SUM(CASE WHEN sample_collection_date IS NOT NULL AND sample_collection_date != '' AND DATE(sample_collection_date) NOT IN ('1970-01-01', '0000-00-00') THEN 1 ELSE 0 END)"),
                    'totalTested' => new Expression("SUM(CASE WHEN sample_tested_datetime IS NOT NULL AND sample_tested_datetime != '' AND DATE(sample_tested_datetime) NOT IN ('1970-01-01', '0000-00-00') THEN 1 ELSE 0 END)"),
                    'totalRejected' => new Expression("SUM(CASE WHEN (reason_for_sample_rejection IS NOT NULL AND reason_for_sample_rejection != '' AND reason_for_sample_rejection != 0) OR (is_sample_rejected LIKE 'yes') AND (sample_collection_date IS NOT NULL AND sample_collection_date != '' AND DATE(sample_collection_date) NOT IN ('1970-01-01', '0000-00-00')) THEN 1 ELSE 0 END)"),
                    'totalPending' => new Expression("SUM(CASE WHEN (sample_collection_date IS NOT NULL AND sample_collection_date != '' AND DATE(sample_collection_date) NOT IN ('1970-01-01', '0000-00-00')) AND (is_sample_rejected LIKE 'yes' OR result IS NULL OR result = '' OR result_status IN (2,4,5,10)) THEN 1 ELSE 0 END)")
                ])
                ->join(['f' => 'facility_details'], "$type.lab_id = f.facility_id", ['facility_name']);

            // Add POC join if needed
            if (!empty($params['flag']) && $params['flag'] == 'poc') {
                $select->join(['icm' => 'instrument_machines'], "$type.import_machine_name = icm.config_machine_id");
            }

            // Apply where conditions
            foreach ($whereConditions as $condition) {
                $select->where($condition);
            }

            // Group by and order by
            $select->group('f.facility_id');

            $testTypeQuery[] = $select->getSqlString($dbAdapter->getPlatform());
        }

        // Combine all queries
        $testTypeQuery = implode(" UNION ALL ", $testTypeQuery);

        // Final query to get aggregated results
        $finalQuery = "SELECT t.facility_name AS clinicName,
                            SUM(t.reg) AS total,
                            SUM(t.totalReceived) AS totalReceived,
                            SUM(t.totalTested) AS totalTested,
                            SUM(t.totalRejected) AS totalRejected,
                            SUM(t.totalPending) AS totalPending
                        FROM ($testTypeQuery) t
                        GROUP BY clinicName
                        ORDER BY total DESC";

        return $this->adapter->query($finalQuery, Adapter::QUERY_MODE_EXECUTE)->toArray();
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
