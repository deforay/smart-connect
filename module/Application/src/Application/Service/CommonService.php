<?php

namespace Application\Service;

use stdClass;
use Exception;
use Throwable;
use ZipArchive;
use Traversable;
use DateTimeZone;
use Laminas\Mail;
use Ramsey\Uuid\Uuid;
use DateTimeImmutable;
use JsonMachine\Items;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Insert;
use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Mime\Part as MimePart;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mime\Message as MimeMessage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Application\Model\DashApiReceiverStatsTable;
use Laminas\Mail\Transport\Smtp as SmtpTransport;

class CommonService
{

     public $sm = null;
     public $cache = null;

     public function __construct($sm = null, $cache = null)
     {
          $this->sm = $sm;
          $this->cache = $cache;
     }

     public function startsWith($string, $startString)
     {
          $len = strlen($startString);
          return (substr($string, 0, $len) === $startString);
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
                         ->where(array($fieldName => $value));
                    $statement = $sql->prepareStatementForSqlObject($select);
                    $result = $statement->execute();
                    $data = count($result);
               } else {
                    $table = explode("##", $fnct);
                    if ($fieldName == 'password') {
                         //Password encrypted
                         $configResult = $this->sm->get('Config');
                         $password = sha1($value . $configResult["password"]["salt"]);
                         //$password = $value;
                         $select = $sql->select()
                              ->from($tableName)
                              ->where(array($fieldName => $password, $table[0] => $table[1]));
                         $statement = $sql->prepareStatementForSqlObject($select);
                         $result = $statement->execute();
                         $data = count($result);
                    } else {
                         $select = $sql->select()
                              ->from($tableName)
                              ->where(array("$fieldName='$value'", $table[0] . "!=" . "'$table[1]'"));
                         $statement = $sql->prepareStatementForSqlObject($select);
                         $result = $statement->execute();
                         $data = count($result);
                    }
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
               $format = "Y-m-d";
               if ($includeTime === true) {
                    $format .= " H:i:s";
               }
               return (new DateTimeImmutable($date))->format($format);
          }
     }


     // Returns the given date in d-M-Y format
     // (with or without time depending on the $includeTime parameter)
     public static function humanReadableDateFormat($date, $includeTime = false, $dateFormat = "d-M-Y", $timeFormat = "H:i:s")
     {
          $date = trim($date);
          if (false === self::verifyIfDateValid($date)) {
               return null;
          } else {

               if ($includeTime === true) {
                    $format = $dateFormat . " " . $timeFormat;
               }

               return (new DateTimeImmutable($date))->format($format);
          }
     }

     public function insertTempMail($to, $subject, $message, $fromMail, $fromName, $cc, $bcc)
     {
          $tempmailDb = $this->sm->get('TempMailTable');
          return $tempmailDb->insertTempMailDetails($to, $subject, $message, $fromMail, $fromName, $cc, $bcc);
     }

     public function sendTempMail()
     {
          try {
               $tempDb = $this->sm->get('TempMailTable');
               $configResult = $this->sm->get('Config');
               $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
               $sql = new Sql($dbAdapter);

               // Setup SMTP transport using LOGIN authentication
               $transport = new SmtpTransport();
               $options = new SmtpOptions([
                    'host' => $configResult["email"]["host"],
                    'port' => $configResult["email"]["config"]["port"],
                    'connection_class' => $configResult["email"]["config"]["auth"],
                    'connection_config' => array(
                         'username' => $configResult["email"]["config"]["username"],
                         'password' => $configResult["email"]["config"]["password"],
                         'ssl' => $configResult["email"]["config"]["ssl"],
                    ),
               ]);
               $transport->setOptions($options);
               $limit = '10';
               $mailQuery = $sql->select()->from(array('tm' => 'temp_mail'))
                    ->where("status='pending'")
                    ->limit($limit);
               $mailQueryStr = $sql->buildSqlString($mailQuery);
               $mailResult = $dbAdapter->query($mailQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
               if (count($mailResult) > 0) {
                    foreach ($mailResult as $result) {
                         $alertMail = new Mail\Message();
                         $id = $result['temp_id'];
                         $tempDb->updateTempMailStatus($id);

                         $fromEmail = $result['from_mail'];
                         $fromFullName = $result['from_full_name'];
                         $subject = $result['subject'];

                         $html = new MimePart($result['message']);
                         $html->type = "text/html";

                         $body = new MimeMessage();
                         $body->setParts(array($html));

                         $alertMail->setBody($body);
                         $alertMail->addFrom($fromEmail, $fromFullName);
                         $alertMail->addReplyTo($fromEmail, $fromFullName);

                         $toArray = explode(",", $result['to_email']);
                         foreach ($toArray as $toId) {
                              if ($toId != '') {
                                   $alertMail->addTo($toId);
                              }
                         }
                         if (isset($result['cc']) && trim($result['cc']) != "") {
                              $ccArray = explode(",", $result['cc']);
                              foreach ($ccArray as $ccId) {
                                   if ($ccId != '') {
                                        $alertMail->addCc($ccId);
                                   }
                              }
                         }

                         if (isset($result['bcc']) && trim($result['bcc']) != "") {
                              $bccArray = explode(",", $result['bcc']);
                              foreach ($bccArray as $bccId) {
                                   if ($bccId != '') {
                                        $alertMail->addBcc($bccId);
                                   }
                              }
                         }

                         $alertMail->setSubject($subject);
                         $transport->send($alertMail);
                         $tempDb->deleteTempMail($id);
                    }
               }
          } catch (Exception $e) {
               error_log($e->getMessage());
               error_log($e->getTraceAsString());
               error_log('whoops! Something went wrong in cron/SendMailAlerts.php');
          }
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
          $res = null;

          try {
               if (!$this->cache->hasItem($cacheId)) {
                    if (!$fetchCurrent) {
                         $res = $dbAdapter->query($queryString, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    } else {
                         $res = $dbAdapter->query($queryString, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }
                    $this->cache->addItem($cacheId, ($res));
               } else {
                    $res = ($this->cache->getItem($cacheId));
               }
               return $res;
          } catch (Exception $e) {
               error_log($e->getMessage());
          }
     }

     public function clearAllCache()
     {
          return $this->cache->flush();
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

     public function crypto($action, $inputString, $secretIv)
     {

          // return $inputString;
          if (empty($inputString)) {
               return "";
          }

          $output = false;
          $encrypt_method = "AES-256-CBC";
          $secret_key = 'rXBCNkAzkHXGBKEReqrTfPhGDqhzxgDRQ7Q0XqN6BVvuJjh1OBVvuHXGBKEReqrTfPhGDqhzxgDJjh1OB4QcIGAGaml';

          // hash
          $key = hash('sha256', $secret_key);

          if (empty($secretIv)) {
               $secretIv = 'sd893urijsdf8w9eurj';
          }
          // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
          $iv = substr(hash('sha256', $secretIv), 0, 16);

          if ($action == 'encrypt') {
               $output = openssl_encrypt($inputString, $encrypt_method, $key, 0, $iv);
               $output = base64_encode($output);
          } elseif ($action == 'decrypt') {
               $output = openssl_decrypt(base64_decode($inputString), $encrypt_method, $key, 0, $iv);
          }
          return $output;
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

          $testReasonDb = $this->sm->get('TestReasonTable');
          $covid19TestReasonDb = $this->sm->get('Covid19TestReasonsTable');
          $artCodeDb = $this->sm->get('ArtCodeTable');
          $sampleRejectionReasonDb = $this->sm->get('SampleRejectionReasonTable');
          $eidSampleRejectionReasonDb = $this->sm->get('EidSampleRejectionReasonTable');
          $covid19SampleRejectionDb = $this->sm->get('Covid19SampleRejectionReasonsTable');
          $eidSampleTypeDb = $this->sm->get('EidSampleTypeTable');
          $covid19SampleTypeDb = $this->sm->get('Covid19SampleTypeTable');
          $covid19ComorbiditiesDb = $this->sm->get('Covid19ComorbiditiesTable');
          $covid19SymptomsDb = $this->sm->get('Covid19SymptomsTable');
          /** @var \Application\Model\FacilityTable $facilityDb */
          $facilityDb = $this->sm->get('FacilityTableWithoutCache');
          $locationDb = $this->sm->get('LocationDetailsTable');
          $importConfigDb = $this->sm->get('ImportConfigMachineTable');
          $hepatitisSampleTypeDb = $this->sm->get('HepatitisSampleTypeTable');
          $hepatitisSampleRejectionDb = $this->sm->get('HepatitisSampleRejectionReasonTable');
          $hepatitisResultsDb = $this->sm->get('HepatitisResultsTable');
          $hepatitisRiskFactorDb = $this->sm->get('HepatitisRiskFactorTable');
          $hepatitisTestReasonsDb = $this->sm->get('HepatitisTestReasonsTable');

          $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
          $sql = new Sql($dbAdapter);


          $fileName = $_FILES['referenceFile']['name'];
          $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
          $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
          $fileName = $ranNumber . "." . $extension;
          // $fileName = 'ref.json';   // Testing
          if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
               mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "temporary", 0777);
          }
          if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-reference") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-reference")) {
               mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-reference", 0777, true);
          }

          $fileName = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-reference" . DIRECTORY_SEPARATOR . $fileName;
          if (is_readable($fileName) && move_uploaded_file($_FILES['referenceFile']['tmp_name'], $fileName)) {
               $apiData = self::processJsonFile($fileName);
               if ($apiData !== null && self::isTraversable($apiData)) {
                    $apiData = is_array($apiData) ? $apiData : iterator_to_array($apiData);
                    $apiData = self::arrayToObject($apiData);
               } else {
                    $apiData = [];
               }
          }


          /* For update the location details */
          if (isset($apiData->geographical_divisions) && !empty($apiData->geographical_divisions)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->geographical_divisions->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->geographical_divisions->lastModifiedTime) && !empty($apiData->geographical_divisions->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->geographical_divisions->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('geographical_divisions', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    $rQueryStr = 'SET FOREIGN_KEY_CHECKS=0; ALTER TABLE `geographical_divisions` DISABLE KEYS';
                    $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    $dbAdapter->query('TRUNCATE TABLE `geographical_divisions`', $dbAdapter::QUERY_MODE_EXECUTE);

                    foreach ((array)$apiData->geographical_divisions->tableData as $row) {
                         $lData = (array)$row;
                         $locationData = array(
                              'geo_id'       => $lData['geo_id'],
                              'geo_parent'   => $lData['geo_parent'],
                              'geo_name'     => $lData['geo_name'],
                              'geo_code'     => $lData['geo_code'],
                              'geo_status'     => $lData['geo_status'],
                              'updated_datetime'  => $lData['updated_datetime']
                         );
                         $locationDb->insert($locationData);
                    }
               }
          }

          /* For update the Facility Details */
          if (isset($apiData->facility_details) && !empty($apiData->facility_details)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->facility_details->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->facility_details->lastModifiedTime) && !empty($apiData->facility_details->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->facility_details->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('facility_details', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->facility_details->tableData as $row) {
                         $facilityData = (array)$row;
                         unset($facilityData['data_sync']);
                         unset($facilityData['facility_state_id']);
                         unset($facilityData['facility_district_id']);
                         if (trim($facilityData['facility_state']) != '' || $facilityData['facility_state_id'] != '') {
                              if ($facilityData['facility_state_id'] != "") {
                                   $facilityData['facility_state'] = $facilityData['facility_state_id'];
                              }
                              $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($facilityData['facility_state']), 0);
                              if ($sQueryResult) {
                                   $facilityData['facility_state'] = $sQueryResult['geo_id'];
                              } else {
                                   $locationDb->insert(array('geo_parent' => 0, 'geo_name' => trim($facilityData['facility_state'])));
                                   $facilityData['facility_state'] = $locationDb->lastInsertValue;
                              }
                         }
                         if (trim($facilityData['facility_district']) != '' || $facilityData['facility_district_id'] != '') {
                              if ($facilityData['facility_district_id'] != "") {
                                   $facilityData['facility_district'] = $facilityData['facility_district_id'];
                              }
                              $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($facilityData['facility_district']), $facilityData['facility_state']);
                              if ($sQueryResult) {
                                   $facilityData['facility_district'] = $sQueryResult['geo_id'];
                              } else {
                                   $locationDb->insert(array('geo_parent' => $facilityData['facility_state'], 'geo_name' => trim($facilityData['facility_district'])));
                                   $facilityData['facility_district'] = $locationDb->lastInsertValue;
                              }
                         }

                         $facilityDb->insertOrUpdate($facilityData);
                    }
               }
          }

          /* For update the Test Reasons */
          if (isset($apiData->r_vl_test_reasons) && !empty($apiData->r_vl_test_reasons)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_vl_test_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_vl_test_reasons->lastModifiedTime) && !empty($apiData->r_vl_test_reasons->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_vl_test_reasons->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_vl_test_reasons', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_vl_test_reasons->tableData as $row) {
                         $testReasonData = (array)$row;
                         $testReasonDb->insertOrUpdate($testReasonData);
                    }
               }
          }

          /* For update the Covid19 Test Reasons */
          if (isset($apiData->r_covid19_test_reasons) && !empty($apiData->r_covid19_test_reasons)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_test_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_covid19_test_reasons->lastModifiedTime) && !empty($apiData->r_covid19_test_reasons->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_covid19_test_reasons->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_covid19_test_reasons', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_covid19_test_reasons->tableData as $row) {
                         $covid19TestReasonData = (array)$row;
                         $rQuery = $sql->select()->from('r_covid19_test_reasons')->where(array('test_reason_name LIKE "%' . $covid19TestReasonData['test_reason_name'] . '%" OR test_reason_id = ' . $covid19TestReasonData['test_reason_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $covid19TestReasonDb->update($covid19TestReasonData, array('test_reason_id' => $covid19TestReasonData['test_reason_id']));
                         } else {
                              $covid19TestReasonDb->insert($covid19TestReasonData);
                         }
                    }
               }
          }

          /* For update the Art Code Details */
          if (isset($apiData->r_vl_art_regimen) && !empty($apiData->r_vl_art_regimen)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_vl_art_regimen->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_vl_art_regimen->lastModifiedTime) && !empty($apiData->r_vl_art_regimen->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_vl_art_regimen->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_vl_art_regimen', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_vl_art_regimen->tableData as $row) {
                         $artCodeData = (array)$row;
                         unset($artCodeData['data_sync']);
                         $artCodeDb->insertOrUpdate($artCodeData);
                    }
               }
          }

          /* For update the Sample Rejection Reason Details */
          if (isset($apiData->r_vl_sample_rejection_reasons) && !empty($apiData->r_vl_sample_rejection_reasons)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_vl_sample_rejection_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_vl_sample_rejection_reasons->lastModifiedTime) && !empty($apiData->r_vl_sample_rejection_reasons->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_vl_sample_rejection_reasons->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_vl_sample_rejection_reasons', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_vl_sample_rejection_reasons->tableData as $row) {
                         $sampleRejectionReasonData = (array)$row;
                         unset($sampleRejectionReasonData['data_sync']);
                         $sampleRejectionReasonDb->insertOrUpdate($sampleRejectionReasonData);
                    }
               }
          }

          /* For update the EID Sample Rejection Reason Details */
          if (isset($apiData->r_eid_sample_rejection_reasons) && !empty($apiData->r_eid_sample_rejection_reasons)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_eid_sample_rejection_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_eid_sample_rejection_reasons->lastModifiedTime) && !empty($apiData->r_eid_sample_rejection_reasons->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_eid_sample_rejection_reasons->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_eid_sample_rejection_reasons', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_eid_sample_rejection_reasons->tableData as $row) {
                         $eidSampleRejectionReasonData = (array)$row;
                         $rQuery = $sql->select()->from('r_eid_sample_rejection_reasons')->where(array('rejection_reason_name LIKE "%' . $eidSampleRejectionReasonData['rejection_reason_name'] . '%" OR rejection_reason_id = ' . $eidSampleRejectionReasonData['rejection_reason_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $eidSampleRejectionReasonDb->update($eidSampleRejectionReasonData, array('rejection_reason_id' => $eidSampleRejectionReasonData['rejection_reason_id']));
                         } else {
                              $eidSampleRejectionReasonDb->insert($eidSampleRejectionReasonData);
                         }
                    }
               }
          }

          /* For update the Covid19 Sample Rejection Reason Details */
          if (isset($apiData->r_covid19_sample_rejection_reasons) && !empty($apiData->r_covid19_sample_rejection_reasons)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_sample_rejection_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_covid19_sample_rejection_reasons->lastModifiedTime) && !empty($apiData->r_covid19_sample_rejection_reasons->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_covid19_sample_rejection_reasons->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_covid19_sample_rejection_reasons', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_covid19_sample_rejection_reasons->tableData as $row) {
                         $covid19SampleRejectionData = (array)$row;
                         $rQuery = $sql->select()->from('r_covid19_sample_rejection_reasons')->where(array('rejection_reason_name LIKE "%' . $covid19SampleRejectionData['rejection_reason_name'] . '%" OR rejection_reason_id = ' . $covid19SampleRejectionData['rejection_reason_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $covid19SampleRejectionDb->update($covid19SampleRejectionData, array('rejection_reason_id' => $covid19SampleRejectionData['rejection_reason_id']));
                         } else {
                              $covid19SampleRejectionDb->insert($covid19SampleRejectionData);
                         }
                    }
               }
          }

          /* For update the  Import Config Machine */
          if (isset($apiData->instrument_machines) && !empty($apiData->instrument_machines)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_symptoms->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->instrument_machines->lastModifiedTime) && !empty($apiData->instrument_machines->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->instrument_machines->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('instrument_machines', 'updated_datetime', $condition);

               // print_r($notUpdated);die;
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->instrument_machines->tableData as $row) {
                         $importConfigMachData = (array)$row;
                         // print_r($importConfigMachData);die;
                         $rQuery = $sql->select()->from('instrument_machines')->where(array('config_machine_name LIKE "%' . $importConfigMachData['config_machine_name'] . '%" OR config_machine_id = ' . $importConfigMachData['config_machine_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $importConfigDb->update($importConfigMachData, array('config_machine_id' => $importConfigMachData['config_machine_id']));
                         } else {
                              $importConfigDb->insert($importConfigMachData);
                         }
                    }
               }
          }

          /* For update the EID Sample Type Details */
          if (isset($apiData->r_eid_sample_type) && !empty($apiData->r_eid_sample_type)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_eid_sample_type->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_eid_sample_type->lastModifiedTime) && !empty($apiData->r_eid_sample_type->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_eid_sample_type->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_eid_sample_type', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_eid_sample_type->tableData as $row) {
                         $eidSampleTypeData = (array)$row;
                         $rQuery = $sql->select()->from('r_eid_sample_type')->where(array('sample_name LIKE "%' . $eidSampleTypeData['sample_name'] . '%" OR sample_id = ' . $eidSampleTypeData['sample_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $eidSampleTypeDb->update($eidSampleTypeData, array('sample_id' => $eidSampleTypeData['sample_id']));
                         } else {
                              $eidSampleTypeDb->insert($eidSampleTypeData);
                         }
                    }
               }
          }

          /* For update the Covid19 Sample Type Details */
          if (isset($apiData->r_covid19_sample_type) && !empty($apiData->r_covid19_sample_type)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_sample_type->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_covid19_sample_type->lastModifiedTime) && !empty($apiData->r_covid19_sample_type->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_covid19_sample_type->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_covid19_sample_type', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_covid19_sample_type->tableData as $row) {
                         $covid19SampleTypeData = (array)$row;
                         $rQuery = $sql->select()->from('r_covid19_sample_type')->where(array('sample_name LIKE "%' . $covid19SampleTypeData['sample_name'] . '%" OR sample_id = ' . $covid19SampleTypeData['sample_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $covid19SampleTypeDb->update($covid19SampleTypeData, array('sample_id' => $covid19SampleTypeData['sample_id']));
                         } else {
                              $covid19SampleTypeDb->insert($covid19SampleTypeData);
                         }
                    }
               }
          }

          /* For update the  Covid19 Comorbidities */
          if (isset($apiData->r_covid19_comorbidities) && !empty($apiData->r_covid19_comorbidities)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_comorbidities->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_covid19_comorbidities->lastModifiedTime) && !empty($apiData->r_covid19_comorbidities->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_covid19_comorbidities->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_covid19_comorbidities', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_covid19_comorbidities->tableData as $row) {
                         $covid19ComorbiditiesData = (array)$row;
                         $rQuery = $sql->select()->from('r_covid19_comorbidities')->where(array('comorbidity_name LIKE "%' . $covid19ComorbiditiesData['comorbidity_name'] . '%" OR comorbidity_id = ' . $covid19ComorbiditiesData['comorbidity_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $covid19ComorbiditiesDb->update($covid19ComorbiditiesData, array('comorbidity_id' => $covid19ComorbiditiesData['comorbidity_id']));
                         } else {
                              $covid19ComorbiditiesDb->insert($covid19ComorbiditiesData);
                         }
                    }
               }
          }

          /* For update the  Covid19 Symptoms */
          if (isset($apiData->r_covid19_symptoms) && !empty($apiData->r_covid19_symptoms)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_symptoms->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_covid19_symptoms->lastModifiedTime) && !empty($apiData->r_covid19_symptoms->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_covid19_symptoms->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_covid19_symptoms', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_covid19_symptoms->tableData as $row) {
                         $covid19SymptomsData = (array)$row;
                         $rQuery = $sql->select()->from('r_covid19_symptoms')->where(array('symptom_name LIKE "%' . $covid19SymptomsData['symptom_name'] . '%" OR symptom_id = ' . $covid19SymptomsData['symptom_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $covid19SymptomsDb->update($covid19SymptomsData, array('symptom_id' => $covid19SymptomsData['symptom_id']));
                         } else {
                              $covid19SymptomsDb->insert($covid19SymptomsData);
                         }
                    }
               }
          }

          /* For update the Hepatitis Sample Rejection Reasons Details */
          if (isset($apiData->r_hepatitis_sample_rejection_reasons) && !empty($apiData->r_hepatitis_sample_rejection_reasons)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_hepatitis_sample_rejection_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_hepatitis_sample_rejection_reasons->lastModifiedTime) && !empty($apiData->r_hepatitis_sample_rejection_reasons->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_hepatitis_sample_rejection_reasons->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_hepatitis_sample_rejection_reasons', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_hepatitis_sample_rejection_reasons->tableData as $row) {
                         $hepatitisSampleRejectionData = (array)$row;
                         $rQuery = $sql->select()->from('r_hepatitis_sample_rejection_reasons')->where(array('rejection_reason_name LIKE "%' . $hepatitisSampleRejectionData['rejection_reason_name'] . '%" OR rejection_reason_id = ' . $hepatitisSampleRejectionData['rejection_reason_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $hepatitisSampleRejectionDb->update($hepatitisSampleRejectionData, array('rejection_reason_id' => $hepatitisSampleRejectionData['rejection_reason_id']));
                         } else {
                              $hepatitisSampleRejectionDb->insert($hepatitisSampleRejectionData);
                         }
                    }
               }
          }

          /* For update the Hepatitis Risk Factor Details */
          if (isset($apiData->r_hepatitis_rick_factors) && !empty($apiData->r_hepatitis_rick_factors)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_hepatitis_rick_factors->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_hepatitis_rick_factors->lastModifiedTime) && !empty($apiData->r_hepatitis_rick_factors->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_hepatitis_rick_factors->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_hepatitis_rick_factors', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_hepatitis_rick_factors->tableData as $row) {
                         $hepatitisRiskData = (array)$row;
                         $rQuery = $sql->select()->from('r_hepatitis_rick_factors')->where(array('riskfactor_name LIKE "%' . $hepatitisRiskData['riskfactor_name'] . '%" OR riskfactor_id = ' . $hepatitisRiskData['riskfactor_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $hepatitisRiskFactorDb->update($hepatitisRiskData, array('riskfactor_id' => $hepatitisRiskData['riskfactor_id']));
                         } else {
                              $hepatitisRiskFactorDb->insert($hepatitisRiskData);
                         }
                    }
               }
          }

          /* For update the Hepatitis Results Details */
          if (isset($apiData->r_hepatitis_test_reasons) && !empty($apiData->r_hepatitis_test_reasons)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_hepatitis_test_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_hepatitis_test_reasons->lastModifiedTime) && !empty($apiData->r_hepatitis_test_reasons->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_hepatitis_test_reasons->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_hepatitis_test_reasons', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_hepatitis_test_reasons->tableData as $row) {
                         $hepatitisTestReasonData = (array)$row;
                         $rQuery = $sql->select()->from('r_hepatitis_test_reasons')->where(array('test_reason_name LIKE "%' . $hepatitisTestReasonData['test_reason_name'] . '%" OR test_reason_id = ' . $hepatitisTestReasonData['test_reason_id']));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $hepatitisTestReasonsDb->update($hepatitisTestReasonData, array('test_reason_id' => $hepatitisTestReasonData['test_reason_id']));
                         } else {
                              $hepatitisTestReasonsDb->insert($hepatitisTestReasonData);
                         }
                    }
               }
          }

          /* For update the Hepatitis Results Details */
          if (isset($apiData->r_hepatitis_results) && !empty($apiData->r_hepatitis_results)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_hepatitis_results->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_hepatitis_results->lastModifiedTime) && !empty($apiData->r_hepatitis_results->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_hepatitis_results->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_hepatitis_results', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_hepatitis_results->tableData as $row) {
                         $hepatitisResultData = (array)$row;
                         $rQuery = $sql->select()->from('r_hepatitis_results')->where(array('result LIKE "%' . $hepatitisResultData['result'] . '%" OR result_id = "' . $hepatitisResultData['result_id'] . '" '));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $hepatitisResultsDb->update($hepatitisResultData, array('result_id' => $hepatitisResultData['result_id']));
                         } else {
                              $hepatitisResultsDb->insert($hepatitisResultData);
                         }
                    }
               }
          }

          /* For update the Hepatitis Sample Type Details */
          if (isset($apiData->r_hepatitis_sample_type) && !empty($apiData->r_hepatitis_sample_type)) {
               /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_hepatitis_sample_type->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
               $condition = "";
               if (isset($apiData->r_hepatitis_sample_type->lastModifiedTime) && !empty($apiData->r_hepatitis_sample_type->lastModifiedTime)) {
                    $condition = "updated_datetime > '" . $apiData->r_hepatitis_sample_type->lastModifiedTime . "'";
               }
               $notUpdated = $this->getLastModifiedDateTime('r_hepatitis_sample_type', 'updated_datetime', $condition);
               if (empty($notUpdated) || !isset($notUpdated)) {
                    foreach ((array)$apiData->r_hepatitis_sample_type->tableData as $row) {
                         $hepatitisSampleTypeData = (array)$row;
                         $rQuery = $sql->select()->from('r_hepatitis_sample_type')->where(array('sample_name LIKE "%' . $hepatitisSampleTypeData['sample_name'] . '%" OR sample_id = "' . $hepatitisSampleTypeData['sample_id'] . '" '));
                         $rQueryStr = $sql->buildSqlString($rQuery);
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                         if ($rowData) {
                              $hepatitisSampleTypeDb->update($hepatitisSampleTypeData, array('sample_id' => $hepatitisSampleTypeData['sample_id']));
                         } else {
                              $hepatitisSampleTypeDb->insert($hepatitisSampleTypeData);
                         }
                    }
               }
          }
          if ($fileName) {
               unlink($fileName);
          }
          return array(
               'status' => 'success',
               'message' => 'All reference tables synced'
          );
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
                         $twoWeekExpiry = $today->sub(\DateInterval::createFromDateString('2 weeks'));
                         //$twoWeekExpiry = date("Y-m-d", strtotime(date("Y-m-d") . '-2 weeks'));
                         $threeWeekExpiry = $today->sub(\DateInterval::createFromDateString('4 weeks'));

                         foreach ($sResult as $aRow) {
                              $row = [];

                              $_color = "f08080";

                              $aRow['latest'] = $aRow['latest'] ?: $aRow['requested_on'];
                              $latest = new DateTimeImmutable($aRow['latest']);

                              $latest = (empty($aRow['latest'])) ? null : new DateTimeImmutable($aRow['latest']);
                              // $twoWeekExpiry = new DateTimeImmutable($twoWeekExpiry);
                              // $threeWeekExpiry = new DateTimeImmutable($threeWeekExpiry);

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
                         $styleArray = array(
                              'font' => array(
                                   'bold' => true,
                              ),
                              'alignment' => array(
                                   'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                                   'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                              ),
                              'borders' => array(
                                   'outline' => array(
                                        'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                   ),
                              )
                         );
                         $borderStyle = array(
                              'alignment' => array(
                                   'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                              ),
                              'borders' => array(
                                   'outline' => array(
                                        'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                   ),
                              )
                         );

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
                                   $sheet->getCellByColumnAndRow($colNo, $rRowCount)->setValueExplicit(html_entity_decode($value));
                                   // echo "Col : ".$colNo ." => Row : " . $rRowCount . " => Color : " .$color[$colorNo]['color'];
                                   // echo "<br>";
                                   $cellName = $sheet->getCellByColumnAndRow($colNo, $rRowCount)->getColumn();
                                   $sheet->getStyle($cellName . $rRowCount)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($color[$colorNo]['color']);
                                   $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
                                   $sheet->getDefaultRowDimension()->setRowHeight(18);
                                   $sheet->getColumnDimensionByColumn($colNo)->setWidth(30);
                                   $colNo++;
                              }
                              $colorNo++;
                         }
                         $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
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

     public static function convertDateRange(?string $dateRange): array
     {
          if ($dateRange === null || $dateRange === '') {
               return ['', ''];
          }

          $dates = explode("to", $dateRange ?? '');
          $dates = array_map('trim', $dates);

          $startDate = empty($dates[0]) ? '' : self::isoDateFormat($dates[0]);
          $endDate = empty($dates[1]) ? '' : self::isoDateFormat($dates[1]);

          return [$startDate, $endDate];
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
               $zipPath = $fileName . '.zip';

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
                    if (!empty($selectedOptions)) {
                         if (is_array($selectedOptions) && in_array($optId, $selectedOptions)) {
                              $selectedText = "selected='selected'";
                         } elseif ($optId == $selectedOptions) {
                              $selectedText = "selected='selected'";
                         }
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

     public static function ungzipFile($sourceFilePath, $destinationFilePath)
     {
          $bufferSize = 4096; // Adjust buffer size as needed

          $source = gzopen($sourceFilePath, 'rb');
          $destination = fopen($destinationFilePath, 'wb');

          if (!$source || !$destination) {

               error_log("Error opening gzip file");
               return false; // Handle error opening file
          }

          while (!gzeof($source)) {
               $chunk = gzread($source, $bufferSize);
               fwrite($destination, $chunk);
          }

          gzclose($source);
          fclose($destination);

          return true;
     }

     public static function isTraversable($variable)
     {
          return is_array($variable) || $variable instanceof Traversable;
     }

     private static function cleanupGenerator($generator, $filePath, $deleteSourceFile = true)
     {
          foreach ($generator as $item) {
               yield $item;
          }
          if ($deleteSourceFile) {
               unlink($filePath);
          }
     }

     public static function processJsonFile($filePath, $returnTimestamp = true, $deleteSourceFile = true)
     {
          $apiData = null;
          $timestamp = null;
          $tempFilePath = $filePath;

          try {
               if (!empty($filePath) && is_readable($filePath)) {
                    $isGzipped = self::isGzipped($filePath);

                    if ($isGzipped) {
                         $tempFilePath = dirname($filePath) . DIRECTORY_SEPARATOR . 'json_' . uniqid() . ".json";
                         self::ungzipFile($filePath, $tempFilePath);
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
                    } else {
                         $timestamp = time();
                    }
               }

               return $returnTimestamp ? [self::cleanupGenerator($apiData, $tempFilePath, $deleteSourceFile), $timestamp] : self::cleanupGenerator($apiData, $tempFilePath, $deleteSourceFile);
          } catch (Throwable $e) {
               if ($deleteSourceFile && file_exists($tempFilePath) && $tempFilePath !== $filePath) {
                    unlink($tempFilePath);
               }
               error_log($e->getMessage());
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
}
