<?php

declare(strict_types=1);

namespace Application\Service {
    // CLI fixtures are not HTTP uploads. Keep the production parser and cleanup path.
    function move_uploaded_file(string $from, string $to): bool
    {
        return rename($from, $to);
    }
}

namespace {
    require dirname(__DIR__) . '/vendor/autoload.php';

    use Application\Service\CommonService;
    use Laminas\Db\Adapter\Adapter;
    use Laminas\Db\ResultSet\ResultSetInterface;

    final class MetadataTrace
    {
        public array $events = [];
        public bool $existing = false;
        public ?string $failTable = null;
        public bool $failFacilityRow = false;
        public bool $unwrittenFacility = false;
        public ?string $localTimestamp = null;
        public array $templates = [];
    }

    final class MetadataAdapter extends Adapter
    {
        public function __construct(private MetadataTrace $trace)
        {
            parent::__construct(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
        }

        public function query($sql, $parametersOrQueryMode = self::QUERY_MODE_PREPARE, ?ResultSetInterface $resultPrototype = null)
        {
            $this->trace->events[] = ['sql', $sql];
            return new class($this->trace->existing) {
                public function __construct(private bool $existing) {}
                public function current(): array|false { return $this->existing ? ['id' => 1] : false; }
                public function getGeneratedValue(): int { return 1; }
            };
        }
    }

    final class MetadataGateway
    {
        public function __construct(private string $name, private MetadataTrace $trace) {}
        public function __call(string $method, array $args): int
        {
            $this->trace->events[] = [$this->name, $method, $args];
            if ($this->trace->failTable === $this->name) {
                throw new RuntimeException('Simulated table failure');
            }
            if ($this->trace->failFacilityRow && $this->name === 'FacilityTableWithoutCache' && $args[0]['facility_id'] === 1) {
                throw new RuntimeException('Simulated facility failure');
            }
            return $this->trace->unwrittenFacility && $this->name === 'FacilityTableWithoutCache' ? 0 : 1;
        }
    }

    final class MetadataServices
    {
        private MetadataAdapter $adapter;
        public function __construct(private MetadataTrace $trace) { $this->adapter = new MetadataAdapter($trace); }
        public function get(string $name): Adapter|MetadataGateway
        {
            return $name === 'Laminas\Db\Adapter\Adapter' ? $this->adapter : new MetadataGateway($name, $this->trace);
        }
    }

    final class MetadataService extends CommonService
    {
        public function __construct(private MetadataTrace $trace) { parent::__construct(new MetadataServices($trace)); }
        public function getTableFieldsAsArray(string $tableName, array $unwantedColumns = []): array
        {
            $this->trace->events[] = ['template', $tableName, $unwantedColumns];
            return array_diff_key($this->trace->templates[$tableName], array_flip($unwantedColumns));
        }
        public function getLastModifiedDateTime($tableName, $modifiedDateTimeColName = 'updated_datetime', $condition = null)
        {
            $this->trace->events[] = ['timestamp', $tableName, $modifiedDateTimeColName];
            return $this->trace->localTimestamp;
        }
        public function checkFacilityStateDistrictDetails($location, $parent)
        {
            $this->trace->events[] = ['location', $location, $parent];
            return $this->trace->existing ? ['geo_id' => 7] : false;
        }
    }

    function verifyMetadata(bool $condition, string $message): void
    {
        if (!$condition) { throw new RuntimeException($message); }
    }

    $root = sys_get_temp_dir() . '/metadata-sync-' . bin2hex(random_bytes(8));
    mkdir($root, 0700);
    mkdir($root . '/vlsm-reference', 0700);
    define('TEMP_UPLOAD_PATH', $root);
    $columns = [
        'geographical_divisions' => ['geo_id', 'geo_parent', 'geo_name', 'geo_code', 'geo_status', 'updated_datetime'],
        'facility_details' => ['facility_id', 'facility_state', 'facility_district', 'facility_state_id', 'facility_district_id', 'data_sync'],
        'r_vl_test_reasons' => ['test_reason_id', 'test_reason_name'],
        'r_covid19_test_reasons' => ['test_reason_id', 'test_reason_name'],
        'r_vl_art_regimen' => ['art_id', 'art_code', 'data_sync'],
        'r_vl_sample_rejection_reasons' => ['rejection_reason_id', 'rejection_reason_name', 'data_sync'],
        'r_eid_sample_rejection_reasons' => ['rejection_reason_id', 'rejection_reason_name'],
        'r_covid19_sample_rejection_reasons' => ['rejection_reason_id', 'rejection_reason_name'],
        'instrument_machines' => ['config_machine_id', 'config_machine_name'],
        'instruments' => ['instrument_id', 'supported_tests', 'approved_by'],
        'r_vl_sample_type' => ['sample_id', 'sample_name'],
        'r_eid_sample_type' => ['sample_id', 'sample_name'],
        'r_covid19_sample_type' => ['sample_id', 'sample_name'],
        'r_covid19_comorbidities' => ['comorbidity_id', 'comorbidity_name'],
        'r_covid19_symptoms' => ['symptom_id', 'symptom_name'],
        'r_hepatitis_sample_rejection_reasons' => ['rejection_reason_id', 'rejection_reason_name'],
        'r_hepatitis_rick_factors' => ['riskfactor_id', 'riskfactor_name'],
        'r_hepatitis_test_reasons' => ['test_reason_id', 'test_reason_name'],
        'r_hepatitis_results' => ['result_id', 'result'],
        'r_hepatitis_sample_type' => ['sample_id', 'sample_name'],
    ];
    $payload = [];
    $templates = [];
    foreach ($columns as $table => $names) {
        $row = array_fill_keys($names, 'Example');
        foreach ($names as $name) {
            if (str_ends_with($name, '_id') || $name === 'geo_parent') { $row[$name] = 1; }
        }
        $row['updated_datetime'] = '2026-01-01 00:00:00';
        if ($table === 'instruments') { $row['supported_tests'] = ['vl', 'eid']; $row['approved_by'] = ['id' => 2]; }
        $templates[$table] = array_fill_keys(array_keys($row), null);
        $row['sender_only_column'] = 'ignored';
        $payload[$table] = ['lastModifiedTime' => '2026-01-02 00:00:00', 'tableData' => [$row]];
    }

    $traces = [];
    try {
        foreach (['insert', 'update', 'older', 'equal', 'missing-local', 'missing-remote', 'force', 'failure', 'facility-failure', 'alias', 'both-aliases', 'unknown', 'empty', 'empty-tables', 'blank-locations', 'named-locations', 'unwritten-facility'] as $scenario) {
            $data = $payload;
            $trace = new MetadataTrace();
            $trace->templates = $templates;
            $trace->existing = $scenario === 'update';
            $trace->localTimestamp = '2026-01-01 00:00:00';
            if (in_array($scenario, ['older', 'force'], true)) { $trace->localTimestamp = '2026-01-03 00:00:00'; }
            if ($scenario === 'equal') { $trace->localTimestamp = '2026-01-02 00:00:00'; }
            if ($scenario === 'missing-local') { $trace->localTimestamp = null; }
            if ($scenario === 'missing-remote') {
                foreach ($data as &$table) { unset($table['lastModifiedTime']); }
                unset($table);
            }
            if ($scenario === 'force') { $data['forceSync'] = true; }
            if ($scenario === 'failure') { $trace->failTable = 'TestReasonTable'; }
            if ($scenario === 'facility-failure') {
                $trace->failFacilityRow = true;
                $second = $data['facility_details']['tableData'][0];
                $second['facility_id'] = 2;
                $data['facility_details']['tableData'][] = $second;
            }
            if (in_array($scenario, ['alias', 'both-aliases'], true)) {
                $data['r_hepatitis_risk_factors'] = $data['r_hepatitis_rick_factors'];
                if ($scenario === 'alias') { unset($data['r_hepatitis_rick_factors']); }
            }
            if ($scenario === 'unknown') { $data['unhandled_table'] = ['tableData' => [['id' => 1]]]; }
            if ($scenario === 'empty') { $data = []; }
            if ($scenario === 'empty-tables') {
                foreach ($data as &$table) { $table['tableData'] = []; }
                unset($table);
            }
            if (in_array($scenario, ['blank-locations', 'named-locations'], true)) {
                $facility = &$data['facility_details']['tableData'][0];
                unset($facility['facility_state_id'], $facility['facility_district_id']);
                if ($scenario === 'blank-locations') {
                    $facility['facility_state'] = '';
                    $facility['facility_district'] = '';
                }
                unset($facility);
            }
            if ($scenario === 'unwritten-facility') { $trace->unwrittenFacility = true; }

            file_put_contents($root . '/upload.json', json_encode(['timestamp' => ['timestamp' => 1], 'data' => $data]));
            $_FILES['referenceFile'] = ['name' => 'reference.json', 'tmp_name' => $root . '/upload.json'];
            $result = (new MetadataService($trace))->saveVlsmMetadataFromAPI([]);
            $partial = in_array($scenario, ['failure', 'facility-failure', 'unknown', 'both-aliases', 'unwritten-facility'], true);
            verifyMetadata($result['status'] === ($partial ? 'partial' : 'success'), 'Incorrect status for ' . $scenario);
            if (in_array($scenario, ['older', 'equal', 'empty'], true)) {
                verifyMetadata(!array_filter($trace->events, fn ($event) => $event[0] !== 'timestamp'), 'Skipped data caused writes');
            }
            if ($scenario === 'insert') {
                $templateTables = array_column(array_filter($trace->events, fn ($event) => $event[0] === 'template'), 1);
                verifyMetadata(count($templateTables) === 19, 'Reference tables were skipped');
                verifyMetadata($trace->events[0] === ['timestamp', 'geographical_divisions', 'updated_datetime'], 'Geography must sync first');
                verifyMetadata(!str_contains(json_encode($trace->events), 'sender_only_column'), 'Unexpected sender fields reached storage');
                $sqlEvents = array_column(array_filter($trace->events, fn ($event) => $event[0] === 'sql'), 1);
                verifyMetadata(count(array_filter($sqlEvents, fn ($sql) => str_contains($sql, 'ON DUPLICATE KEY UPDATE'))) === 3, 'SQL upsert tables changed');
            }
            if ($scenario === 'update') {
                $updates = array_filter($trace->events, fn ($event) => ($event[1] ?? null) === 'update');
                verifyMetadata(count($updates) === 13, 'Legacy lookup updates changed');
            }
            if ($scenario === 'force') {
                verifyMetadata(!array_filter($trace->events, fn ($event) => $event[0] === 'timestamp'), 'Force sync must bypass timestamps');
                verifyMetadata(in_array(['sql', 'TRUNCATE TABLE `facility_details`'], $trace->events, true), 'Force sync must replace facilities');
            }
            if ($scenario === 'unwritten-facility') {
                verifyMetadata(str_contains($result['errors']['facility_details'], 'Rows total: 1, inserted: 0, errors: 0'), 'Unwritten facility was not reported');
            }
            if ($scenario === 'failure') {
                verifyMetadata(isset($result['errors']['r_vl_test_reasons']), 'Failed table was not reported');
                verifyMetadata(count($trace->events) > 50, 'Failure stopped subsequent tables');
            }
            if ($scenario === 'facility-failure') {
                verifyMetadata(str_contains($result['errors']['facility_details'], 'Rows total: 2, inserted: 1, errors: 1'), 'Facility row accounting changed');
            }
            verifyMetadata(glob($root . '/vlsm-reference/*') === [], 'Uploaded fixture was not removed');
            $traces[$scenario] = ['response' => $result, 'events' => $trace->events];
        }
        if (isset($argv[1])) { file_put_contents($argv[1], json_encode($traces, JSON_PRETTY_PRINT)); }
        echo "Metadata synchronization checks passed for 17 scenarios across 20 tables.\n";
    } finally {
        foreach (glob($root . '/vlsm-reference/*') as $file) { unlink($file); }
        if (is_file($root . '/upload.json')) { unlink($root . '/upload.json'); }
        rmdir($root . '/vlsm-reference');
        rmdir($root);
    }
}
