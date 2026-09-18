<?php

namespace Application\Service;

use App\Log\AppLogger;
use stdClass;
use Exception;
use Throwable;
use ZipArchive;
use Traversable;
use DateInterval;
use DateTimeZone;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use DateTimeImmutable;
use JsonMachine\Items;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Insert;
use Application\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Symfony\Component\Mime\Email;
use Application\Model\TempMailTable;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Application\Model\DashApiReceiverStatsTable;
use Generator;

class CommonService
{

     public $sm = null;
     public $cache = null;
     private array $tableColumnsCache = [];
     /** @var TempMailTable|null */
     public ?TempMailTable $tempMailTable = null;

     public function __construct($sm = null, $cache = null, $tempMailTable = null)
     {
          $this->sm = $sm;
          $this->cache = $cache;
          if ($tempMailTable !== null) {
               $this->tempMailTable = $tempMailTable;
          }
     }

     /**
      * Encode data to JSON safely.
      *
      * @param mixed $data
      * @param int   $options
      * @param int   $depth
      * @return string
      * @throws \JsonException
      */
     public static function jsonEncode(mixed $data, int $options = 0, int $depth = 512): string
     {
          // Default to Laminas-like behavior (escape <, >, &, ', " and throw on error)
          $options |= JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR;

          return json_encode($data, $options, $depth);
     }

     public function startsWith($string, $startString)
     {
          $len = strlen($startString);
          return substr($string, 0, $len) === $startString;
     }

     public static function generateRandomString(int $length = 32): string
     {
          $bytes = ceil($length * 3 / 4);
          try {
               $randomBytes = random_bytes($bytes);
               $base64String = base64_encode($randomBytes);
               // Replace base64 characters with some alphanumeric characters
               $customBase64String = strtr($base64String, '+/=', 'ABC');
               return substr($customBase64String, 0, $length);
          } catch (Throwable $e) {
               throw new Exception('Failed to generate random string: ' . $e->getMessage());
          }
     }

     public static function generateCSRF($resetToken = false)
     {

          if (session_status() == PHP_SESSION_NONE) {
               session_start();
          }
          if ($resetToken || !isset($_SESSION["CSRF_TOKEN"])) {
               // Generate a new one
               $token = $_SESSION["CSRF_TOKEN"] = self::generateUUID();
          } else {
               // Reuse the existing token
               $token = $_SESSION["CSRF_TOKEN"];
          }
          return $token;
     }


     public function checkMultipleFieldValidations($params)
     {
          $adapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
          $jsonData = $params['json_data'];
          $tableName = $jsonData['tableName'];
          $sql = new Sql($adapter);
          $select = $sql->select()->from($tableName);
          foreach ($jsonData['columns'] as $val) {
               if ($val['column_value'] != "") {
                    $select->where($val['column_name'] . "=" . "'" . $val['column_value'] . "'");
               }
          }

          //edit
          if (isset($jsonData['tablePrimaryKeyValue']) && $jsonData['tablePrimaryKeyValue'] != null && $jsonData['tablePrimaryKeyValue'] != "null") {
               $select->where($jsonData['tablePrimaryKeyId'] . "!=" . $jsonData['tablePrimaryKeyValue']);
          }
          //error_log($sql);
          $statement = $sql->prepareStatementForSqlObject($select);
          $result = $statement->execute();
          return count($result);
     }


     public function checkFieldValidations($params)
     {
          $adapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
          $tableName = $params['tableName'];
          $fieldName = $params['fieldName'];
          $value = trim($params['value']);
          $fnct = $params['fnct'];
          try {
               $sql = new Sql($adapter);
               if ($fnct == '' || $fnct == 'null') {
                    $select = $sql->select()
                         ->from($tableName)
                         ->where([$fieldName => $value]);
                    $statement = $sql->prepareStatementForSqlObject($select);
                    $result = $statement->execute();
                    $data = count($result);
               } else {
                    $table = explode("##", $fnct);

                    $select = $sql->select()
                         ->from($tableName)
                         ->where(["$fieldName='$value'", $table[0] . "!=" . "'$table[1]'"]);
                    $statement = $sql->prepareStatementForSqlObject($select);
                    $result = $statement->execute();
                    $data = count($result);
               }
               return $data;
          } catch (Exception $exc) {
               error_log($exc->getMessage());
               error_log($exc->getTraceAsString());
          }
     }

     public static function verifyIfDateValid($date): bool
     {
          $date = trim($date);
          $response = false;

          if ($date === '' || 'undefined' === $date || 'null' === $date) {
               $response = false;
          } else {
               try {
                    $dateTime = new DateTimeImmutable($date);
                    $errors = DateTimeImmutable::getLastErrors();
                    if (
                         empty($dateTime) || $dateTime === false ||
                         !empty($errors['warning_count']) ||
                         !empty($errors['error_count'])
                    ) {
                         $response = false;
                    } else {
                         $response = true;
                    }
               } catch (Exception $e) {
                    $response = false;
               }
          }

          return $response;
     }

     // Returns the given date in Y-m-d format
     public static function isoDateFormat($date, $includeTime = false)
     {
          $date = trim($date);
          if (false === self::verifyIfDateValid($date)) {
               return null;
          } else {
               $format = ($includeTime !== true) ? "Y-m-d" : "Y-m-d H:i:s";
               return (new DateTimeImmutable($date))->format($format);
          }
     }


     // Returns the given date in d-M-Y format
     // (with or without time depending on the $includeTime parameter)
     public static function humanReadableDateFormat($date, $includeTime = false, $format = "d-M-Y", $withSeconds = false)
     {
          $date = trim($date);
          if (false === self::verifyIfDateValid($date)) {
               return null;
          } else {
               // Check if the format already includes time components
               $hasTimeComponent = preg_match('/[HhGgis]/', $format);

               // If the format doesn't have a time component and $includeTime is true, append the appropriate time format
               if ($includeTime && !$hasTimeComponent) {
                    $format .= $withSeconds ? ' H:i:s' : ' H:i';
               }

               return (new DateTimeImmutable($date))->format($format);
          }
     }

     public function insertTempMail($to, $subject, $message, $fromMail, $fromName, $cc, $bcc)
     {
          return $this->tempMailTable->insertTempMailDetails($to, $subject, $message, $fromMail, $fromName, $cc, $bcc);
     }

     public function sendTempMail(?MailerInterface $mailer = null): void
     {
          try {
               $config = $this->sm->get('Config');
               $mailer ??= new Mailer($this->createMailTransport($config['email']));
               $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
               $sql = new Sql($dbAdapter);
               $mailQuery = $sql->select()->from('temp_mail')
                    ->where(['status' => 'pending'])
                    ->limit(10);
               $mailQueryStr = $sql->buildSqlString($mailQuery);
               $mailResult = $dbAdapter->query($mailQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();

               foreach ($mailResult as $result) {
                    try {
                         $email = (new Email())
                              ->from($result['report_email'])
                              ->replyTo($result['report_email'])
                              ->priority(Email::PRIORITY_HIGH)
                              ->subject($result['subject'])
                              ->text('Sending emails is fun again!')
                              ->html($result['text_message']);

                         foreach (['to_mail' => 'addTo', 'cc' => 'addCc', 'bcc' => 'addBcc'] as $field => $method) {
                              foreach (explode(',', $result[$field] ?? '') as $recipient) {
                                   $recipient = trim($recipient);
                                   if ($recipient !== '') {
                                        $email->$method($recipient);
                                   }
                              }
                         }

                         // Keep failures pending so the next scheduled run can retry them.
                         // Crunz prevents overlapping scheduled runs of this worker.
                         $mailer->send($email);
                         $this->tempMailTable->deleteTempMail($result['id']);
                    } catch (Throwable $e) {
                         // One malformed message or failed delivery must not stop this batch.
                         error_log('Queued mail ' . $result['id'] . ' failed: ' . $e->getMessage());
                    }
               }
          } catch (Throwable $e) {
               error_log('Queued mail batch failed: ' . $e->getMessage());
          }
     }

     private function createMailTransport(array $emailConfig): EsmtpTransport
     {
          $config = $emailConfig['config'];
          $port = (int) $config['port'];
          $implicitTls = strtolower((string) ($config['ssl'] ?? '')) === 'ssl' || $port === 465;
          $transport = new EsmtpTransport($emailConfig['host'], $port, $implicitTls);
          $transport->setUsername($config['username']);
          $transport->setPassword($config['password']);
          // STARTTLS is opportunistic by default. Never send credentials or mail without TLS.
          $transport->setRequireTls(true);

          return $transport;
     }

     public function removeDirectory($dirname)
     {
          // Sanity check
          if (!is_readable($dirname)) {
               return false;
          }

          // Simple delete for a file
          if (is_file($dirname) || is_link($dirname)) {
               return unlink($dirname);
          }

          // Loop through the folder
          $dir = dir($dirname);
          while (false !== $entry = $dir->read()) {
               // Skip pointers
               if ($entry == '.' || $entry == '..') {
                    continue;
               }

               // Recurse
               $this->removeDirectory($dirname . DIRECTORY_SEPARATOR . $entry);
          }

          // Clean up
          $dir->close();
          return rmdir($dirname);
     }

     public function removespecials($url)
     {
          $url = str_replace(" ", "-", $url);

          $url = preg_replace('/[^a-zA-Z0-9\-]/', '', $url);
          $url = preg_replace('/^[\-]+/', '', $url);
          $url = preg_replace('/[\-]+$/', '', $url);
          $url = preg_replace('/[\-]{2,}/', '', $url);

          return strtolower($url);
     }

     public static function getDateTime($format = 'Y-m-d H:i:s', $timezone = null)
     {
          $timezone = $timezone ?? date_default_timezone_get();
          return (new DateTimeImmutable("now", new DateTimeZone($timezone)))->format($format);
     }

     public static function getDate($timezone = null)
     {
          return self::getDateTime('Y-m-d', $timezone);
     }

     public static function getCurrentTime($timezone = null)
     {
          return self::getDateTime('H:i', $timezone);
     }

     public function humanMonthlyDateFormat($date)
     {
          if ($date == null || $date == "" || $date == "0000-00-00" || $date == "0000-00-00 00:00:00") {
               return "";
          } else {
               $dateArray = explode('-', $date);
               $newDate =  "";

               $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
               $mon = $monthsArray[$dateArray[1] * 1];

               return $newDate .= $mon . " " . $dateArray[0];
          }
     }



     public function cacheQuery($queryString, $dbAdapter, $fetchCurrent = false)
     {
          // in case fetchCurrent is true, we want to ensure it is treated as a
          // separate query compared to fetchCurrent = false
          $cacheId = hash("sha512", ($fetchCurrent) ? 'current-' : '' . $queryString);

          try {
               return $this->cache->get($cacheId, function () use ($queryString, $dbAdapter, $fetchCurrent) {
                    if (!$fetchCurrent) {
                         return $dbAdapter->query($queryString, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    }
                    return $dbAdapter->query($queryString, $dbAdapter::QUERY_MODE_EXECUTE)->current();
               });
          } catch (Exception $e) {
               error_log($e->getMessage());
          }
     }

     public function clearAllCache()
     {
          return $this->cache->clear();
     }

     public function getRoleFacilities($params)
     {
          $facilityDb = $this->sm->get('FacilityTable');
          return $facilityDb->fetchRoleFacilities($params);
     }

     public function getSampleTestedFacilityInfo($params)
     {
          $facilityDb = $this->sm->get('FacilityTable');
          return $facilityDb->fetchSampleTestedFacilityInfo($params);
     }

     public function getSampleTestedLocationInfo($params)
     {
          $facilityDb = $this->sm->get('FacilityTable');
          return $facilityDb->fetchSampleTestedLocationInfo($params);
     }

     //dump the contents of a variable to the error log in a readable format
     public static function errorLog($object = null): void
     {
          ob_start();
          var_dump($object);
          error_log(ob_get_clean());
     }

     public function addBackupGeneration($params)
     {
          $facilityDb = $this->sm->get('GenerateBackupTable');
          return $facilityDb->addBackupGeneration($params);
     }

     public function translate($text)
     {
          $translateObj = $this->sm->get('translator');
          return $translateObj->translate($text);
     }

     //get all sample types
     public function getSampleType()
     {
          $sampleDb = $this->sm->get('SampleTypeTable');
          return $sampleDb->fetchAllSampleType();
     }



     //get all Lab Name
     public function getAllLabName()
     {
          $loginContainer = new Container('credo');
          $mappedFacilities = null;
          if ($loginContainer->role != 1) {
               $mappedFacilities = (!empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : null;
          }
          $facilityDb = $this->sm->get('FacilityTable');
          return $facilityDb->fetchAllLabName($mappedFacilities);
     }
     //get all Lab Name
     public function getAllClinicName()
     {

          $loginContainer = new Container('credo');
          $mappedFacilities = null;
          if ($loginContainer->role != 1) {
               $mappedFacilities = (!empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : null;
          }

          $facilityDb = $this->sm->get('FacilityTable');
          return $facilityDb->fetchAllClinicName($mappedFacilities);
     }
     //get all province name
     public function getAllProvinceList()
     {

          $loginContainer = new Container('credo');
          $mappedFacilities = null;
          if ($loginContainer->role != 1) {
               $mappedFacilities = (!empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : null;
          }

          $locationDb = $this->sm->get('LocationDetailsTable');
          return $locationDb->fetchLocationDetails($mappedFacilities);
     }


     public function getAllCountries()
     {
          $countriesDb = $this->sm->get('CountriesTable');
          return $countriesDb->fetchAllCountries();
     }

     public function getAllDistrictList()
     {

          $loginContainer = new Container('credo');
          $mappedFacilities = null;
          if ($loginContainer->role != 1) {
               $mappedFacilities = (!empty($loginContainer->mappedFacilities)) ? $loginContainer->mappedFacilities : null;
          }
          $locationDb = $this->sm->get('LocationDetailsTable');
          return $locationDb->fetchAllDistrictsList();
     }

     public function getDistrictList($locationId)
     {
          $locationDb = $this->sm->get('LocationDetailsTable');
          return $locationDb->fetchDistrictListByIds($locationId);
     }

     public function getFacilityList($districtId, $facilityType = 1)
     {
          $facilityDb = $this->sm->get('FacilityTable');
          return $facilityDb->fetchFacilityListByDistrict($districtId, $facilityType);
     }

     public function getLastModifiedDateTime($tableName, $modifiedDateTimeColName = 'updated_datetime', $condition = "")
     {
          $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
          $sql = new Sql($dbAdapter);
          $Query = $sql->select()->from($tableName)->columns(array($modifiedDateTimeColName))->order($modifiedDateTimeColName . ' DESC')->where(array($modifiedDateTimeColName . ' IS NOT NULL'))->limit(1);
          if (!empty($condition)) {
               $Query = $Query->where(array($condition));
          }
          $QueryStr = $sql->buildSqlString($Query);
          $result = $dbAdapter->query($QueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
          if (isset($result[$modifiedDateTimeColName]) && $result[$modifiedDateTimeColName] != '' && $result[$modifiedDateTimeColName] != NULL && !$this->startsWith($result[$modifiedDateTimeColName], '0000-00-00')) {
               return $result[$modifiedDateTimeColName];
          } else {
               return null;
          }
     }


     public function saveVlsmMetadataFromAPI($params)
     {
          $gateways = [
               'TestReasonTable' => $this->sm->get('TestReasonTable'),
               'Covid19TestReasonsTable' => $this->sm->get('Covid19TestReasonsTable'),
               'ArtCodeTable' => $this->sm->get('ArtCodeTable'),
               'SampleRejectionReasonTable' => $this->sm->get('SampleRejectionReasonTable'),
               'EidSampleRejectionReasonTable' => $this->sm->get('EidSampleRejectionReasonTable'),
               'Covid19SampleRejectionReasonsTable' => $this->sm->get('Covid19SampleRejectionReasonsTable'),
               'EidSampleTypeTable' => $this->sm->get('EidSampleTypeTable'),
               'Covid19SampleTypeTable' => $this->sm->get('Covid19SampleTypeTable'),
               'Covid19ComorbiditiesTable' => $this->sm->get('Covid19ComorbiditiesTable'),
               'Covid19SymptomsTable' => $this->sm->get('Covid19SymptomsTable'),
               'FacilityTableWithoutCache' => $this->sm->get('FacilityTableWithoutCache'),
               'LocationDetailsTable' => $this->sm->get('LocationDetailsTable'),
               'ImportConfigMachineTable' => $this->sm->get('ImportConfigMachineTable'),
               'HepatitisSampleTypeTable' => $this->sm->get('HepatitisSampleTypeTable'),
               'HepatitisSampleRejectionReasonTable' => $this->sm->get('HepatitisSampleRejectionReasonTable'),
               'HepatitisResultsTable' => $this->sm->get('HepatitisResultsTable'),
               'HepatitisRiskFactorTable' => $this->sm->get('HepatitisRiskFactorTable'),
               'HepatitisTestReasonsTable' => $this->sm->get('HepatitisTestReasonsTable'),
          ];
          $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
          [$apiData, $fileName] = $this->readMetadataUpload();
          $result = $this->syncMetadataPayload($apiData, $gateways, $dbAdapter);
          if ($fileName && file_exists($fileName)) {
               unlink($fileName);
          }
          return $result;
     }

     /** @return array{0: object|array, 1: string} */
     private function readMetadataUpload(): array
     {
          $directory = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'vlsm-reference';
          if (!file_exists($directory) && !is_dir($directory)) {
               mkdir($directory, 0777, true);
          }
          $extension = strtolower(pathinfo($_FILES['referenceFile']['name'], PATHINFO_EXTENSION));
          $fileName = $directory . DIRECTORY_SEPARATOR . self::generateRandomString(12) . '.' . $extension;
          if (!move_uploaded_file($_FILES['referenceFile']['tmp_name'], $fileName)) {
               error_log("Failed to move uploaded file to $fileName");
               exit(0);
          }
          if (!is_readable($fileName)) {
               error_log("File $fileName not readable after move");
               exit(0);
          }

          // Preserve table names and forceSync while consuming the streamed JSON payload.
          [$apiData] = self::processJsonFile($fileName, true, true, true);
          if ($apiData === null || !self::isTraversable($apiData)) {
               return [[], $fileName];
          }
          $apiData = is_array($apiData) ? $apiData : iterator_to_array($apiData, true);
          return [self::arrayToObject($apiData), $fileName];
     }

     private function metadataTableDefinitions(): array
     {
          // Order matters: geography is available before facility rows are resolved.
          return [
               'geographical_divisions' => [],
               'facility_details' => [],
               'r_vl_test_reasons' => ['service' => 'TestReasonTable'],
               'r_covid19_test_reasons' => ['service' => 'Covid19TestReasonsTable', 'name' => 'test_reason_name', 'id' => 'test_reason_id'],
               'r_vl_art_regimen' => ['service' => 'ArtCodeTable', 'exclude' => ['data_sync']],
               'r_vl_sample_rejection_reasons' => ['service' => 'SampleRejectionReasonTable', 'exclude' => ['data_sync']],
               'r_eid_sample_rejection_reasons' => ['service' => 'EidSampleRejectionReasonTable', 'name' => 'rejection_reason_name', 'id' => 'rejection_reason_id'],
               'r_covid19_sample_rejection_reasons' => ['service' => 'Covid19SampleRejectionReasonsTable', 'name' => 'rejection_reason_name', 'id' => 'rejection_reason_id'],
               'instrument_machines' => ['service' => 'ImportConfigMachineTable', 'name' => 'config_machine_name', 'id' => 'config_machine_id'],
               'instruments' => ['sqlUpsert' => true, 'encodeJson' => true],
               'r_vl_sample_type' => ['sqlUpsert' => true],
               'r_eid_sample_type' => ['service' => 'EidSampleTypeTable', 'name' => 'sample_name', 'id' => 'sample_id'],
               'r_covid19_sample_type' => ['service' => 'Covid19SampleTypeTable', 'name' => 'sample_name', 'id' => 'sample_id'],
               'r_covid19_comorbidities' => ['service' => 'Covid19ComorbiditiesTable', 'name' => 'comorbidity_name', 'id' => 'comorbidity_id'],
               'r_covid19_symptoms' => ['service' => 'Covid19SymptomsTable', 'name' => 'symptom_name', 'id' => 'symptom_id'],
               'r_hepatitis_sample_rejection_reasons' => ['service' => 'HepatitisSampleRejectionReasonTable', 'name' => 'rejection_reason_name', 'id' => 'rejection_reason_id'],
               'r_hepatitis_rick_factors' => ['service' => 'HepatitisRiskFactorTable', 'name' => 'riskfactor_name', 'id' => 'riskfactor_id'],
               'r_hepatitis_test_reasons' => ['service' => 'HepatitisTestReasonsTable', 'name' => 'test_reason_name', 'id' => 'test_reason_id'],
               'r_hepatitis_results' => ['service' => 'HepatitisResultsTable', 'name' => 'result', 'id' => 'result_id', 'quotedId' => true],
               'r_hepatitis_sample_type' => ['service' => 'HepatitisSampleTypeTable', 'name' => 'sample_name', 'id' => 'sample_id', 'quotedId' => true],
          ];
     }

     private function syncMetadataPayload(object|array $apiData, array $gateways, Adapter $dbAdapter): array
     {
          $forceSync = !empty($apiData->forceSync);
          // The sender uses "risk", while the local schema retains the historical "rick" name.
          if (isset($apiData->r_hepatitis_risk_factors) && !isset($apiData->r_hepatitis_rick_factors)) {
               $apiData->r_hepatitis_rick_factors = $apiData->r_hepatitis_risk_factors;
               unset($apiData->r_hepatitis_risk_factors);
          }

          $definitions = $this->metadataTableDefinitions();
          $syncErrors = [];
          foreach ($definitions as $table => $definition) {
               if (empty($apiData->$table)) {
                    continue;
               }
               try {
                    $payload = $apiData->$table;
                    if (!$this->shouldSyncMetadata($table, $payload->lastModifiedTime ?? null, $forceSync)) {
                         continue;
                    }
                    $this->syncMetadataTable($table, $payload, $definition, $gateways, $dbAdapter, $forceSync);
               } catch (Throwable $e) {
                    // A table failure must not prevent independent reference tables from syncing.
                    $syncErrors[$table] = $e->getMessage();
               }
          }
          $syncErrors += $this->unknownMetadataTables($apiData, $definitions);
          if ($syncErrors !== []) {
               return ['status' => 'partial', 'message' => 'Reference tables synced with errors', 'errors' => $syncErrors];
          }
          return ['status' => 'success', 'message' => 'All reference tables synced'];
     }

     private function shouldSyncMetadata(string $table, ?string $remoteTimestamp, bool $forceSync): bool
     {
          if ($forceSync || empty($remoteTimestamp)) {
               return true;
          }
          $localTimestamp = $this->getLastModifiedDateTime($table, 'updated_datetime');
          return empty($localTimestamp) || strtotime($remoteTimestamp) > strtotime($localTimestamp);
     }

     private function unknownMetadataTables(object|array $apiData, array $definitions): array
     {
          $errors = [];
          foreach ((array) $apiData as $table => $payload) {
               if ($table === 'forceSync' || isset($definitions[$table]) || empty($payload->tableData)) {
                    continue;
               }
               $errors[$table] = 'This dashboard has no handler for this table, so its rows were not stored.';
          }
          return $errors;
     }

     private function syncMetadataTable(string $table, object $payload, array $definition, array $gateways, Adapter $dbAdapter, bool $forceSync): void
     {
          if ($table === 'geographical_divisions') {
               $this->syncMetadataGeography($payload, $dbAdapter);
               return;
          }
          if ($table === 'facility_details') {
               $this->syncMetadataFacilities($payload, $gateways, $dbAdapter, $forceSync);
               return;
          }
          $template = $this->getTableFieldsAsArray($table, $definition['exclude'] ?? []);
          foreach ((array) $payload->tableData as $row) {
               $data = self::updateMatchingKeysOnly($template, (array) $row);
               if (!empty($definition['encodeJson'])) {
                    $data = $this->encodeMetadataJsonColumns($data);
               }
               $this->writeMetadataRow($table, $data, $definition, $gateways, $dbAdapter);
          }
     }

     private function encodeMetadataJsonColumns(array $data): array
     {
          // Instrument JSON columns arrive decoded and cannot be bound as PHP arrays.
          foreach ($data as $column => $value) {
               if (is_array($value) || is_object($value)) {
                    $data[$column] = json_encode($value);
               }
          }
          return $data;
     }

     private function writeMetadataRow(string $table, array $data, array $definition, array $gateways, Adapter $dbAdapter): void
     {
          if (!empty($definition['sqlUpsert'])) {
               self::upsert($dbAdapter, $table, $data);
               return;
          }
          $gateway = $gateways[$definition['service']];
          if (!isset($definition['id'])) {
               $gateway->insertOrUpdate($data);
               return;
          }
          $id = $definition['id'];
          $name = $definition['name'];
          // Preserve the legacy name-or-ID match and update-by-ID behavior.
          $idValue = !empty($definition['quotedId']) ? '"' . $data[$id] . '" ' : $data[$id];
          $condition = $name . ' LIKE "%' . $data[$name] . '%" OR ' . $id . ' = ' . $idValue;
          $sql = new Sql($dbAdapter);
          $query = $sql->select()->from($table)->where([$condition]);
          $existing = $dbAdapter->query($sql->buildSqlString($query), $dbAdapter::QUERY_MODE_EXECUTE)->current();
          if ($existing) {
               $gateway->update($data, [$id => $data[$id]]);
          } else {
               $gateway->insert($data);
          }
     }

     private function syncMetadataGeography(object $payload, Adapter $dbAdapter): void
     {
          $rQueryStr = 'SET FOREIGN_KEY_CHECKS=0; ALTER TABLE `geographical_divisions` DISABLE KEYS';
          $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
          $dbAdapter->query('TRUNCATE TABLE `geographical_divisions`', $dbAdapter::QUERY_MODE_EXECUTE);

          foreach ((array)$payload->tableData as $row) {
               $lData = (array)$row;
               $locationData = [
                    'geo_id' => $lData['geo_id'],
                    'geo_parent' => $lData['geo_parent'],
                    'geo_name' => $lData['geo_name'],
                    'geo_code' => $lData['geo_code'],
                    'geo_status' => $lData['geo_status'],
                    'updated_datetime' => $lData['updated_datetime']
               ];
               // WHY: Prevent duplicate errors on (geo_name, geo_parent) while keeping data current.
               self::upsert($dbAdapter, 'geographical_divisions', $locationData);
          }
     }

     private function syncMetadataFacilities(object $payload, array $gateways, Adapter $dbAdapter, bool $forceSync): void
     {
          if ($forceSync) {
               // WHY: Force sync should fully replace the table to avoid stale/partial rows.
               $dbAdapter->query('TRUNCATE TABLE `facility_details`', $dbAdapter::QUERY_MODE_EXECUTE);
          }
          $facilityTemplate = $this->getTableFieldsAsArray('facility_details', ['data_sync']);
          $facilityRowErrors = 0;
          $facilityRowErrorSample = null;
          $facilityRowsTotal = 0;
          $facilityRowsInserted = 0;
          foreach ((array)$payload->tableData as $row) {
               $facilityRowsTotal++;
               try {
                    $facilityData = $this->prepareMetadataFacility((array) $row, $gateways['LocationDetailsTable']);
                    $facilityData = self::updateMatchingKeysOnly($facilityTemplate, $facilityData);
                    $id = $gateways['FacilityTableWithoutCache']->insertOrUpdate($facilityData);
                    if (!empty($id)) {
                         $facilityRowsInserted++;
                    }
               } catch (Throwable $e) {
                    $facilityRowErrors++;
                    $facilityRowErrorSample ??= $e->getMessage();
               }
          }
          if ($facilityRowErrors > 0 || $facilityRowsInserted !== $facilityRowsTotal) {
               throw new RuntimeException("Rows total: {$facilityRowsTotal}, inserted: {$facilityRowsInserted}, errors: {$facilityRowErrors}. Sample: {$facilityRowErrorSample}");
          }
     }

     private function prepareMetadataFacility(array $data, object $locationDb): array
     {
          $stateId = $data['facility_state_id'] ?? null;
          $districtId = $data['facility_district_id'] ?? null;
          unset($data['data_sync'], $data['facility_state_id'], $data['facility_district_id']);
          $data['facility_state'] = $this->resolveMetadataLocation($data['facility_state'], $stateId, 0, $locationDb);
          $data['facility_district'] = $this->resolveMetadataLocation($data['facility_district'], $districtId, $data['facility_state'], $locationDb);
          return $data;
     }

     private function resolveMetadataLocation(mixed $name, mixed $id, mixed $parent, object $locationDb): mixed
     {
          if (trim($name) === '' && empty($id)) {
               return $name;
          }
          $location = !empty($id) ? $id : $name;
          $existing = $this->checkFacilityStateDistrictDetails(trim($location), $parent);
          if ($existing) {
               return $existing['geo_id'];
          }
          // Upsert before looking up the ID to tolerate an existing name/parent pair.
          $locationDb->insertOrUpdate(['geo_parent' => $parent, 'geo_name' => trim($location)]);
          $existing = $this->checkFacilityStateDistrictDetails(trim($location), $parent);
          return $existing['geo_id'] ?? null;
     }

     public function checkFacilityStateDistrictDetails($location, $parent)
     {
          $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
          $sql = new Sql($dbAdapter);
          if (is_numeric($location)) {
               $where = array('l.geo_parent' => $parent, 'l.geo_id' => trim($location));
          } else {
               $where = array('l.geo_parent' => $parent, 'l.geo_name' => trim($location));
          }
          $sQuery = $sql->select()->from(array('l' => 'geographical_divisions'))
               ->where($where);
          $sQuery = $sql->buildSqlString($sQuery);
          return $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
     }

     public function getMonthsInRange($startDate, $endDate)
     {
          $months = [];
          while (strtotime($startDate) <= strtotime($endDate)) {
               $monthYear = date('M', strtotime($startDate)) . "-" . date('Y', strtotime($startDate));
               $monthYearDBForamt = date('Y', strtotime($startDate)) . "-" . date('m', strtotime($startDate));
               $months[$monthYear] = $monthYearDBForamt;
               $startDate = date('d M Y', strtotime($startDate . '+ 1 month'));
          }
          return $months;
     }

     public function getAllDashApiReceiverStatsByGrid($parameters)
     {
          /** @var DashApiReceiverStatsTable $statsDb */
          $statsDb = $this->sm->get('DashApiReceiverStatsTable');
          return $statsDb->fetchAllDashApiReceiverStatsByGrid($parameters);
     }

     public function getStatusDetails($statusId)
     {
          /** @var DashApiReceiverStatsTable $statsDb */
          $statsDb = $this->sm->get('DashApiReceiverStatsTable');
          return $statsDb->fetchStatusDetails($statusId);
     }

     public function getLabSyncStatus($params)
     {
          /** @var DashApiReceiverStatsTable $statsDb */
          $statsDb = $this->sm->get('DashApiReceiverStatsTable');
          return $statsDb->fetchLabSyncStatus($params);
     }

     public function generateSyncStatusExcel($params)
     {
          $queryContainer = new Container('query');
          $translator = $this->sm->get('translator');
          if (property_exists($queryContainer, 'syncStatus') && $queryContainer->syncStatus !== null) {
               try {
                    $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
                    $sql = new Sql($dbAdapter);
                    $sQueryStr = $sql->buildSqlString($queryContainer->syncStatus);
                    $sResult = $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    if (isset($sResult) && !empty($sResult)) {
                         $excel = new Spreadsheet();
                         $sheet = $excel->getActiveSheet();
                         $output = [];

                         $today = new DateTimeImmutable();
                         $twoWeekExpiry = $today->sub(DateInterval::createFromDateString('2 weeks'));
                         //$twoWeekExpiry = date("Y-m-d", strtotime(date("Y-m-d") . '-2 weeks'));
                         $threeWeekExpiry = $today->sub(DateInterval::createFromDateString('4 weeks'));

                         foreach ($sResult as $aRow) {
                              $row = [];

                              $_color = "f08080";

                              $aRow['latest'] = $aRow['latest'] ?: $aRow['requested_on'];
                              $latest = new DateTimeImmutable($aRow['latest']);

                              $latest = (empty($aRow['latest'])) ? null : new DateTimeImmutable($aRow['latest']);

                              if (!$latest instanceof DateTimeImmutable) {
                                   $_color = "f08080";
                              } elseif ($latest >= $twoWeekExpiry) {
                                   $_color = "90ee90";
                              } elseif ($latest > $threeWeekExpiry && $latest < $twoWeekExpiry) {
                                   $_color = "ffff00";
                              } elseif ($latest >= $threeWeekExpiry) {
                                   $_color = "f08080";
                              }
                              $color[]['color'] = $_color;

                              $row[] = (isset($aRow['labName']) && !empty($aRow['labName'])) ? ucwords($aRow['labName']) : "";
                              $row[] = (isset($aRow['latest']) && !empty($aRow['latest'])) ? self::humanReadableDateFormat($aRow['latest']) : "";
                              $output[] = $row;
                         }
                         $styleArray = [
                              'font' => [
                                   'bold' => true,
                              ],
                              'alignment' => [
                                   'horizontal' => Alignment::HORIZONTAL_CENTER,
                                   'vertical' => Alignment::VERTICAL_CENTER,
                              ],
                              'borders' => [
                                   'outline' => [
                                        'style' => Border::BORDER_THIN,
                                   ],
                              ]
                         ];
                         $borderStyle = [
                              'alignment' => [
                                   'horizontal' => Alignment::HORIZONTAL_LEFT,
                              ],
                              'borders' => [
                                   'outline' => [
                                        'style' => Border::BORDER_THIN,
                                   ],
                              ]
                         ];

                         $sheet->setCellValue('A1', html_entity_decode($translator->translate('Lab Name'), ENT_QUOTES, 'UTF-8'));
                         $sheet->setCellValue('B1', html_entity_decode($translator->translate('Last Synced on'), ENT_QUOTES, 'UTF-8'));
                         $sheet->setCellValue('C1', html_entity_decode($translator->translate('Last Results Sync from Lab'), ENT_QUOTES, 'UTF-8'));
                         $sheet->setCellValue('D1', html_entity_decode($translator->translate('Last Requests Sync from VLSTS'), ENT_QUOTES, 'UTF-8'));

                         $sheet->getStyle('A1:D1')->applyFromArray($styleArray);

                         $colorNo = 0;
                         foreach ($output as $rowNo => $rowData) {
                              $colNo = 1;
                              foreach ($rowData as $field => $value) {
                                   $rRowCount = ($rowNo + 2);

                                   $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                                   $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo) . $rRowCount)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($color[$colorNo]['color']);
                                   $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo) . $rRowCount)->applyFromArray($borderStyle);
                                   $sheet->getDefaultRowDimension()->setRowHeight(18);
                                   $sheet->getColumnDimensionByColumn($colNo)->setWidth(30);
                                   $colNo++;
                              }
                              $colorNo++;
                         }
                         $writer = IOFactory::createWriter($excel, 'Xlsx');
                         $filename = 'SAMPLE-SYNC-STATUS-REPORT--' . date('d-M-Y-H-i-s') . '.xlsx';
                         $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
                         return $filename;
                    } else {
                         return "";
                    }
               } catch (Exception $exc) {
                    error_log("SAMPLE-SYNC-STATUS-REPORT--" . $exc->getMessage());
                    error_log($exc->getTraceAsString());
                    return "";
               }
          } else {
               return "";
          }
     }

     public static function convertDateRange(?string $dateRange, $seperator = "to"): array
     {
          if ($dateRange === null || $dateRange === '' || !str_contains($dateRange, $seperator)) {
               return ['', ''];
          }

          $dates = explode($seperator, $dateRange ?? '');
          $dates = array_map('trim', $dates);

          $startDate = empty($dates[0]) ? '' : (self::isoDateFormat($dates[0]) ?? '');
          $endDate = empty($dates[1]) ? '' : (self::isoDateFormat($dates[1]) ?? '');

          return [$startDate, $endDate];
     }

     // Returns the positive integer ids in a comma-separated string or an array,
     // for use in an IN (...) filter
     public static function parseIdList(array|string|null $ids): array
     {
          if (is_string($ids)) {
               $ids = explode(',', $ids);
          }
          $ids = array_map(fn($id) => trim((string) $id), $ids ?? []);
          $ids = array_filter($ids, fn($id) => ctype_digit($id) && (int) $id > 0);
          return array_values(array_map('intval', $ids));
     }

     public static function isJSON($string): bool
     {
          if (empty($string) || !is_string($string)) {
               return false;
          }

          json_decode($string);
          return json_last_error() === JSON_ERROR_NONE;
     }
     public static function toJSON($data): ?string
     {
          if (!empty($data)) {
               if (self::isJSON($data)) {
                    return $data;
               } else {
                    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if ($json !== false) {
                         return $json;
                    } else {
                         error_log('Data could not be encoded as JSON: ' . json_last_error_msg());
                    }
               }
          }
          return null;
     }

     public static function generateCsv($headings, $data, $filename, $delimiter = ',', $enclosure = '"')
     {
          $handle = fopen($filename, 'w'); // Open file for writing

          // The headings first
          if (!empty($headings)) {
               fputcsv($handle, $headings, $delimiter, $enclosure);
          }
          // Then the data
          if (!empty($data)) {
               foreach ($data as $line) {
                    fputcsv($handle, $line, $delimiter, $enclosure);
               }
          }

          //Clear Memory
          unset($data);
          fclose($handle);
          return $filename;
     }

     public static function makeDirectory($path, $mode = 0777, $recursive = true): bool
     {
          if (is_dir($path)) {
               return true;
          }

          return mkdir($path, $mode, $recursive);
     }

     public static function zipJson($json, $fileName)
     {
          $result = false;
          if (!empty($json) && !empty($fileName)) {
               $zip = new ZipArchive();
               $zipPath = "$fileName.zip";

               if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                    $zip->addFromString(basename($fileName), $json);

                    if ($zip->status == ZIPARCHIVE::ER_OK) {
                         $result = true;
                    }
                    $zip->close();
               }
          }
          return $result;
     }

     public static function generateUUID($attachExtraString = true): string
     {
          $uuid = (Uuid::uuid4())->toString();
          return $uuid . ($attachExtraString ? '-' . self::generateRandomString(6) : '');
     }

     public function generateSelectOptions($optionList, $selectedOptions = [], $emptySelectText = false)
     {
          return once(function () use ($optionList, $selectedOptions, $emptySelectText) {
               if (empty($optionList)) {
                    return '';
               }
               $response = '';
               if ($emptySelectText !== false) {
                    $response .= "<option value=''>$emptySelectText</option>";
               }

               foreach ($optionList as $optId => $optName) {
                    $selectedText = '';
                    if (
                         !empty($selectedOptions)
                         && ((is_array($selectedOptions) && in_array($optId, $selectedOptions))
                              || ($optId == $selectedOptions))
                    ) {
                         $selectedText = "selected='selected'";
                    }
                    $response .= "<option value='" . addslashes($optId) . "' $selectedText>" . addslashes($optName) . "</option>";
               }
               return $response;
          });
     }

     public static function getJsonFromZip($zipFile, $jsonFile): string
     {
          if (!file_exists($zipFile)) {
               return "{}";
          }
          $zip = new ZipArchive;
          if ($zip->open($zipFile) === true) {
               $json = $zip->getFromName($jsonFile);
               $zip->close();

               return $json;
          } else {
               return "{}";
          }
     }

     public static function prettyJson($json): string
     {
          $json = is_array($json) ? json_encode($json, JSON_PRETTY_PRINT) : json_encode(json_decode($json), JSON_PRETTY_PRINT);
          return htmlspecialchars($json, ENT_QUOTES, 'UTF-8');
     }

     public static function isGzipped($filePath)
     {
          $file = fopen($filePath, 'rb');
          if ($file === false) {
               return false;
          }

          $bytes = fread($file, 2);
          fclose($file);

          return $bytes === "\x1f\x8b";
     }

     public static function gunzip(string $sourceFilePath, string $destinationFilePath): bool
     {
          $bufferSize = 4096; // Adjust buffer size as needed

          // Validate that the source file exists and is readable
          if (!is_readable($sourceFilePath)) {
               throw new RuntimeException("Source file is not readable or does not exist: $sourceFilePath");
          }

          // Attempt to open both source and destination
          $source = gzopen($sourceFilePath, 'rb');
          if ($source === false) {
               throw new RuntimeException("Failed to open source gzip file: $sourceFilePath");
          }

          $destination = fopen($destinationFilePath, 'wb');
          if ($destination === false) {
               gzclose($source); // Ensure source is closed if destination opening fails
               throw new RuntimeException("Failed to open destination file: $destinationFilePath");
          }

          try {
               // Read from gzip and write to destination in chunks
               while (!gzeof($source)) {
                    $chunk = gzread($source, $bufferSize);
                    if ($chunk === false) {
                         throw new RuntimeException("Error reading from gzip file: $sourceFilePath");
                    }
                    if (fwrite($destination, $chunk) === false) {
                         throw new RuntimeException("Error writing to destination file: $destinationFilePath");
                    }
               }
          } finally {
               // Ensure resources are always closed, even in case of errors
               gzclose($source);
               fclose($destination);
          }

          return true;
     }


     public static function isTraversable($variable)
     {
          return is_array($variable) || $variable instanceof Traversable;
     }

     public function getTableFieldsAsArray(string $tableName, array $unwantedColumns = []): array
     {
          $tableFieldsAsArray = [];
          if ($tableName !== '' && $tableName !== '0') {
               $columns = $this->getTableColumns($tableName);
               if (!empty($columns)) {
                    $tableFieldsAsArray = array_fill_keys($columns, null);
                    if (!empty($unwantedColumns)) {
                         foreach ($unwantedColumns as $column) {
                              unset($tableFieldsAsArray[$column]);
                         }
                    }
               }
          }

          return $tableFieldsAsArray;
     }

     /**
      * Put the credential's laboratory on an incoming record.
      *
      * v2 authenticates a bearer token and then ingested every field straight
      * from the uploaded JSON, lab_id included. The credential established who
      * the caller was and nothing used it, so an enrolled instance could write
      * rows attributed to another laboratory. remote_sample_code is UNIQUE
      * across the whole table, which made that a single field to change.
      *
      * The value is overwritten rather than the record rejected. The sending
      * LIS decides a batch succeeded from the top-level status alone
      * (SmartConnectService::syncModule) and then advances a watermark, so
      * rejecting individual records inside a successful response loses them
      * permanently, and failing the whole batch leaves a lab retrying the same
      * rows every cron run until somebody edits its config. Coercion keeps the
      * data and still records that the instance is misconfigured.
      *
      * A null $credentialLabId means the caller is not scoped to one
      * laboratory. That covers v1, which has no credential at all, and an
      * aggregating instance enrolled without a lab_id, which legitimately
      * sends on behalf of many. Neither is constrained here.
      *
      * A record that arrives with no lab_id is filled in rather than left
      * alone. That column is null on most rows collected before 2022, and this
      * is where the next one would otherwise be created.
      */
     public static function applyCredentialIdentity(?array $data, ?array $credential, string $context = ''): ?array
     {
          if ($data === null || $credential === null) {
               return $data;
          }

          $labId = isset($credential['lab_id']) && $credential['lab_id'] !== ''
               ? (int) $credential['lab_id']
               : null;
          $instanceUuid = isset($credential['instance_uuid']) && $credential['instance_uuid'] !== ''
               ? (string) $credential['instance_uuid']
               : null;

          $warn = static function (string $field, $sent, $held) use ($context, $data): void {
               AppLogger::logWarning('API record claimed an identity its credential does not hold', [
                    'context' => $context,
                    'field' => $field,
                    'sent' => (string) $sent,
                    'credential' => (string) $held,
                    'sample_code' => (string) ($data['sample_code'] ?? ''),
                    'remote_sample_code' => (string) ($data['remote_sample_code'] ?? ''),
               ]);
          };

          // The laboratory, where the credential names one. A client enrolled
          // without a lab_id sends on behalf of many and is left alone.
          if ($labId !== null && array_key_exists('lab_id', $data)) {
               $sent = $data['lab_id'];
               if ($sent !== null && trim((string) $sent) !== '' && (int) $sent !== $labId) {
                    $warn('lab_id', $sent, $labId);
               }
               $data['lab_id'] = $labId;
          }

          // The sending installation, always. Unlike the laboratory this does
          // not vary with what a batch carries: whoever holds the token is the
          // installation that sent it, aggregator or not.
          if ($instanceUuid !== null && array_key_exists('vlsm_instance_id', $data)) {
               $sent = $data['vlsm_instance_id'];
               if ($sent !== null && trim((string) $sent) !== '' && (string) $sent !== $instanceUuid) {
                    $warn('vlsm_instance_id', $sent, $instanceUuid);
               }
               $data['vlsm_instance_id'] = $instanceUuid;
          }

          return $data;
     }

     public static function updateMatchingKeysOnly(?array $targetArray, ?array $sourceArray): ?array
     {
          if ($targetArray === null || $targetArray === [] || ($sourceArray === null || $sourceArray === [])) {
               return $targetArray;
          }
          return array_merge($targetArray, array_intersect_key($sourceArray, $targetArray));
     }

     private function getTableColumns(string $tableName): array
     {
          if (isset($this->tableColumnsCache[$tableName])) {
               return $this->tableColumnsCache[$tableName];
          }

          $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
          $dbRow = $dbAdapter->query('SELECT DATABASE() AS db', Adapter::QUERY_MODE_EXECUTE)->current();
          $dbName = $dbRow['db'] ?? null;
          if (empty($dbName)) {
               $this->tableColumnsCache[$tableName] = [];
               return $this->tableColumnsCache[$tableName];
          }

          $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND table_name = ?";
          $result = $dbAdapter->query($sql, [$dbName, $tableName]);
          $columns = [];
          foreach ($result as $row) {
               if (isset($row['COLUMN_NAME'])) {
                    $columns[] = $row['COLUMN_NAME'];
               }
          }

          $this->tableColumnsCache[$tableName] = $columns;
          return $columns;
     }

     private static function dataGenerator($apiData, $filePath, $deleteSourceFile = true, $preserveKeys = false): Generator
     {
          foreach ($apiData as $key => $item) {
               if ($preserveKeys) {
                    yield $key => $item;
               } else {
                    yield $item;
               }
          }

          if ($deleteSourceFile && file_exists($filePath)) {
               unlink($filePath);
          }
     }

     public static function processJsonFile($filePath, $returnTimestamp = true, $deleteSourceFile = true, $preserveKeys = false)
     {
          $apiData = null;
          $timestamp = null;
          $tempFilePath = $filePath;

          try {
               if (!empty($filePath) && is_readable($filePath)) {
                    $isGzipped = self::isGzipped($filePath);

                    if ($isGzipped) {
                         $tempFilePath = dirname($filePath) . DIRECTORY_SEPARATOR . 'json_' . uniqid() . ".json";
                         self::gunzip($filePath, $tempFilePath);
                         if ($deleteSourceFile) {
                              unlink($filePath);
                         }
                    }

                    // Process the JSON data from the file
                    $apiData = Items::fromFile($tempFilePath, [
                         'pointer' => '/data',
                         'decoder' => new ExtJsonDecoder(true)
                    ]);

                    if ($returnTimestamp) {
                         $timestampData = Items::fromFile($tempFilePath, [
                              'pointer' => '/timestamp',
                              'decoder' => new ExtJsonDecoder(true)
                         ]);
                         $timestamp = iterator_to_array($timestampData)['timestamp'] ?? time();
                    }
               }

               // WHY: Some payloads (e.g., metadata) require object keys to be preserved during streaming.
               $generator = self::dataGenerator($apiData, $tempFilePath, $deleteSourceFile, $preserveKeys);
               return $returnTimestamp ? [$generator, $timestamp] : $generator;
          } catch (Throwable $e) {
               error_log($e->getMessage());
               if ($deleteSourceFile && file_exists($tempFilePath) && $tempFilePath !== $filePath) {
                    unlink($tempFilePath);
               }
               return $returnTimestamp ? [null, null] : null;
          }
     }


     public static function upsert(Adapter $adapter, $table, array $data)
     {
          $sql = new Sql($adapter);
          $insert = new Insert($table);
          $insert->values($data);

          $update = [];
          $platform = $adapter->getPlatform(); // Get platform from the adapter
          foreach ($data as $key => $value) {
               $update[] = $platform->quoteIdentifier($key) . ' = VALUES(' . $platform->quoteIdentifier($key) . ')';
          }

          $query = $sql->buildSqlString($insert) . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $update);

          /** @var \Laminas\Db\Adapter\Driver\ResultInterface $result */
          $result = $adapter->query($query, Adapter::QUERY_MODE_EXECUTE);

          return $result->getGeneratedValue(); // Get the last generated value
     }
     public static function sanitizeFilename($filename)
     {
          // Replace any non-alphanumeric, non-dot, non-dash and non-underscore characters
          return preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
     }

     // Returns the lowercase extension of an uploaded image, or null when the
     // file is not a raster image type. Uploads land under the web root, so
     // anything else (a .php file, an .svg with script) must not be written.
     public static function imageUploadExtension(?string $fileName): ?string
     {
          $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
          return in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) ? $extension : null;
     }

     public static function arrayToObject($array)
     {
          if (!is_array($array)) {
               return $array;
          }

          $object = new stdClass();
          foreach ($array as $key => $value) {
               $object->$key = self::arrayToObject($value);
          }
          return $object;
     }

     public static function parseMultipartFormData($folderPath)
     {
          if (!empty($_FILES) || !empty($_POST)) {
               return; // No need to parse if $_FILES or $_POST is already populated
          }

          $rawPostData = file_get_contents('php://input');
          if (empty($rawPostData)) {
               return;
          }

          $boundary = substr($rawPostData, 0, strpos($rawPostData, "\r\n"));
          $parts = array_slice(explode($boundary, $rawPostData), 1);

          foreach ($parts as $part) {
               if ($part == "--\r\n") {
                    break;
               }

               $part = ltrim($part, "\r\n");
               [$headers, $body] = explode("\r\n\r\n", $part, 2);
               $headers = explode("\r\n", $headers);
               $name = null;
               $filename = null;

               foreach ($headers as $header) {
                    if (strpos($header, 'Content-Disposition:') !== false) {
                         preg_match('/name="([^"]*)"/', $header, $nameMatch);
                         if (isset($nameMatch[1])) {
                              $name = $nameMatch[1];
                         }
                         preg_match('/filename="([^"]*)"/', $header, $filenameMatch);
                         if (isset($filenameMatch[1])) {
                              $filename = $filenameMatch[1];
                         }
                    }
               }

               $body = substr($body, 0, strlen($body) - 2);
               if ($filename) {
                    $finalFilePath = $folderPath . DIRECTORY_SEPARATOR . $filename;
                    file_put_contents($finalFilePath, $body);
                    $_FILES[$name] = [
                         'name' => $filename,
                         'type' => mime_content_type($finalFilePath),
                         'tmp_name' => $finalFilePath,
                         'error' => UPLOAD_ERR_OK,
                         'size' => filesize($finalFilePath),
                    ];
               } else {
                    $_POST[$name] = $body;
               }
          }
     }
}
