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

        $types = (!isset($params['testType']) || empty($params['testType'])) ? ["vl", "eid", "covid19"] : (array) $params['testType'];
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
                    'totalReceived' => new Expression("SUM(CASE WHEN sample_collection_date IS NOT NULL AND DATE(sample_collection_date) NOT IN ('1970-01-01', '0000-00-00') THEN 1 ELSE 0 END)"),
                    'totalTested' => new Expression("SUM(CASE WHEN sample_tested_datetime IS NOT NULL AND DATE(sample_tested_datetime) NOT IN ('1970-01-01', '0000-00-00') THEN 1 ELSE 0 END)"),
                    'totalRejected' => new Expression("SUM(CASE WHEN (reason_for_sample_rejection IS NOT NULL AND reason_for_sample_rejection != '' AND reason_for_sample_rejection != 0) OR (is_sample_rejected LIKE 'yes') AND (sample_collection_date IS NOT NULL AND DATE(sample_collection_date) NOT IN ('1970-01-01', '0000-00-00')) THEN 1 ELSE 0 END)"),
                    'totalPending' => new Expression("SUM(CASE WHEN (sample_collection_date IS NOT NULL AND DATE(sample_collection_date) NOT IN ('1970-01-01', '0000-00-00')) AND (is_sample_rejected LIKE 'yes' OR result IS NULL OR result = '' OR result_status IN (2,4,5,10)) THEN 1 ELSE 0 END)")
                ])
                ->join(['f' => 'facility_details'], "$type.lab_id = f.facility_id", ['facility_name']);

            // Add POC join if needed
            if (!empty($params['flag']) && $params['flag'] == 'poc') {
                $select->join(['icm' => 'instrument_machines'], "$type.import_machine_name = icm.config_machine_id");
            }

            // Apply where conditions
            if (!empty($whereConditions)) {
                foreach ($whereConditions as $condition) {
                    $select->where($condition);
                }
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
            SUM(t.gender) AS 'Sex Missing',
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
                $rawDate = $rRow[$r . 'Date'] ?? null;
                if (empty($rawDate)) {
                    continue;
                }
                $displayDate = date("d-M-Y", strtotime($rawDate));
                $finalResult[$r][] = array(array('total' => $rRow['total']), 'date' => $displayDate, $r . 'Date' => $displayDate, $r . 'Total' => $totalSum += $rRow['total']);
            }
        }
        return ['quickStats' => $finalResult['quickStats'] ?? null, 'scResult' => $finalResult['received'] ?? null, 'stResult' => $finalResult['tested'] ?? null, 'srResult' => $finalResult['rejected'] ?? null];
    }

    public function getFacilityPerformanceData($params)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $loginContainer = new Container('credo');
        $mappedFacilities = $loginContainer->mappedFacilities ?? null;

        $dimension = $params['dimension'] ?? 'testing_lab';
        // When dimension=health_facility we group by facility_id (the requesting site); otherwise by lab_id.
        $facilityColumn = ($dimension === 'health_facility') ? 'facility_id' : 'lab_id';
        $types = (!isset($params['testType']) || empty($params['testType'])) ? ["vl", "eid", "covid19"] : (array) $params['testType'];
        $mode = $params['mode'] ?? 'backlog';
        $topN = isset($params['topN']) ? (int) $params['topN'] : 10;

        $whereConditions = [];

        if (isset($params['collectionDate']) && !empty($params['collectionDate'])) {
            [$from, $to] = CommonService::convertDateRange($params['collectionDate']);
            $whereConditions[] = new WhereExpression("DATE(sample_collection_date) BETWEEN ? AND ?", [$from, $to]);
        }

        if (isset($params['testedDate']) && !empty($params['testedDate'])) {
            [$from, $to] = CommonService::convertDateRange($params['testedDate']);
            $whereConditions[] = new WhereExpression("DATE(sample_tested_datetime) BETWEEN ? AND ?", [$from, $to]);
        }

        if (isset($params['provinceName']) && !empty($params['provinceName'])) {
            $whereConditions[] = new WhereExpression("facility_state_id IN (?)", [implode(",", $params['provinceName'])]);
        }

        if (isset($params['districtName']) && !empty($params['districtName'])) {
            $whereConditions[] = new WhereExpression("facility_district_id IN (?)", [implode(",", $params['districtName'])]);
        }

        if (isset($params['clinicId']) && !empty($params['clinicId'])) {
            $whereConditions[] = new WhereExpression("facility_id IN (?)", [implode(",", $params['clinicId'])]);
        }

        if (isset($params['labId']) && !empty($params['labId'])) {
            $whereConditions[] = new WhereExpression("lab_id IN (?)", [implode(",", $params['labId'])]);
        }

        if (!empty($params['flag']) && $params['flag'] == 'poc') {
            $whereConditions[] = new WhereExpression("icm.poc_device = ?", ['yes']);
        }

        if (!empty($mappedFacilities)) {
            $whereConditions[] = new WhereExpression("lab_id IN (?)", [implode(", ", $mappedFacilities)]);
        }

        $unionQueries = [];
        foreach ($types as $type) {
            $tableAlias = "t";
            $facilityAlias = ($dimension === 'health_facility') ? 'hf' : 'lb';

            $select = $sql->select()
                ->from([$tableAlias => "dash_form_$type"])
                ->columns([
                    'facility_id' => new Expression("$tableAlias.$facilityColumn"),
                    'facility_name' => new Expression("$facilityAlias.facility_name"),
                    'received_count' => new Expression("SUM(CASE WHEN $tableAlias.sample_collection_date IS NOT NULL AND DATE($tableAlias.sample_collection_date) NOT IN ('1970-01-01', '0000-00-00') THEN 1 ELSE 0 END)"),
                    'tested_count' => new Expression("SUM(CASE WHEN $tableAlias.sample_tested_datetime IS NOT NULL AND DATE($tableAlias.sample_tested_datetime) NOT IN ('1970-01-01', '0000-00-00') THEN 1 ELSE 0 END)"),
                    'rejected_count' => new Expression("SUM(CASE WHEN (($tableAlias.reason_for_sample_rejection IS NOT NULL AND $tableAlias.reason_for_sample_rejection != '' AND $tableAlias.reason_for_sample_rejection != 0) OR ($tableAlias.is_sample_rejected LIKE 'yes')) AND ($tableAlias.sample_collection_date IS NOT NULL AND DATE($tableAlias.sample_collection_date) NOT IN ('1970-01-01', '0000-00-00')) THEN 1 ELSE 0 END)")
                ])
                ->join([$facilityAlias => 'facility_details'], "$tableAlias.$facilityColumn = $facilityAlias.facility_id", []);

            if (!empty($params['flag']) && $params['flag'] == 'poc') {
                $select->join(['icm' => 'instrument_machines'], "$tableAlias.import_machine_name = icm.config_machine_id", []);
            }

            if (!empty($whereConditions)) {
                foreach ($whereConditions as $condition) {
                    $select->where($condition);
                }
            }

            $select->where(["$tableAlias.$facilityColumn IS NOT NULL"]);
            $select->group(["$tableAlias.$facilityColumn", "$facilityAlias.facility_name"]);
            $unionQueries[] = $sql->buildSqlString($select);
        }

        if (empty($unionQueries)) {
            return [
                'totals' => [
                    'total_received' => 0,
                    'total_tested' => 0,
                    'total_rejected' => 0,
                    'total_pending' => 0,
                    'overall_pct_tested' => 0
                ],
                'perFacility' => []
            ];
        }

        $finalQuery = "SELECT facility_id, facility_name,
                            SUM(received_count) AS received_count,
                            SUM(tested_count) AS tested_count,
                            SUM(rejected_count) AS rejected_count
                        FROM (" . implode(" UNION ALL ", $unionQueries) . ") AS t
                        GROUP BY facility_id, facility_name";

        $rows = $this->adapter->query($finalQuery, Adapter::QUERY_MODE_EXECUTE)->toArray();

        $perFacility = [];
        $totalReceived = $totalTested = $totalRejected = 0;
        foreach ($rows as $row) {
            $received = (int) ($row['received_count'] ?? 0);
            $tested = (int) ($row['tested_count'] ?? 0);
            $rejected = (int) ($row['rejected_count'] ?? 0);
            $pending = max(0, $received - $tested - $rejected);
            $pctTested = ($received > 0) ? round(($tested * 100) / $received, 2) : 0;

            $perFacility[] = [
                'id' => $row['facility_id'],
                'name' => $row['facility_name'],
                'received' => $received,
                'tested' => $tested,
                'rejected' => $rejected,
                'pending' => $pending,
                'pct_tested' => $pctTested
            ];

            $totalReceived += $received;
            $totalTested += $tested;
            $totalRejected += $rejected;
        }

        $totalPending = max(0, $totalReceived - $totalTested - $totalRejected);
        $overallPctTested = ($totalReceived > 0) ? round(($totalTested * 100) / $totalReceived, 2) : 0;

        // Compute Others aggregation for the active mode + topN to send to UI.
        $eligible = $perFacility;
        if ($mode === 'pct_tested') {
            $eligible = array_filter($perFacility, function ($item) {
                return ($item['received'] ?? 0) > 0;
            });
            usort($eligible, function ($a, $b) {
                return ($a['pct_tested'] <=> $b['pct_tested']);
            });
        } elseif ($mode === 'volume') {
            usort($eligible, function ($a, $b) {
                return ($b['received'] <=> $a['received']);
            });
        } else {
            usort($eligible, function ($a, $b) {
                return ($b['pending'] <=> $a['pending']);
            });
        }
        $others = [
            'received' => 0,
            'tested' => 0,
            'rejected' => 0,
            'pending' => 0,
            'pct_tested' => 0
        ];
        if (count($eligible) > $topN) {
            $remainder = array_slice($eligible, $topN);
            foreach ($remainder as $item) {
                $others['received'] += $item['received'] ?? 0;
                $others['tested'] += $item['tested'] ?? 0;
                $others['rejected'] += $item['rejected'] ?? 0;
                $others['pending'] += $item['pending'] ?? 0;
            }
            $others['pending'] = max(0, $others['pending']);
            $others['pct_tested'] = ($others['received'] > 0) ? round(($others['tested'] * 100) / $others['received'], 2) : 0;
        }

        return [
            'totals' => [
                'total_received' => $totalReceived,
                'total_tested' => $totalTested,
                'total_rejected' => $totalRejected,
                'total_pending' => $totalPending,
                'overall_pct_tested' => $overallPctTested
            ],
            'perFacility' => $perFacility,
            'others' => $others
        ];
    }
}
