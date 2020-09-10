<?php

namespace Application\Service;

use Laminas\Session\Container;
use Exception;
use Laminas\Db\Sql\Sql;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mail;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;


class CommonService
{

     public $sm = null;

     public function __construct($sm = null)
     {
          $this->sm = $sm;
     }

     public function getServiceManager()
     {
          return $this->sm;
     }

     public function startsWith($string, $startString)
     {
          $len = strlen($startString);
          return (substr($string, 0, $len) === $startString);
     }

     public static function generateRandomString($length = 8, $seeds = 'alphanum')
     {
          // Possible seeds
          $seedings['alpha'] = 'abcdefghijklmnopqrstuvwqyz';
          $seedings['numeric'] = '0123456789';
          $seedings['alphanum'] = 'abcdefghijklmnopqrstuvwqyz0123456789';
          $seedings['hexidec'] = '0123456789abcdef';

          // Choose seed
          if (isset($seedings[$seeds])) {
               $seeds = $seedings[$seeds];
          }

          // Seed generator
          list($usec, $sec) = explode(' ', microtime());
          $seed = (float) $sec + ((float) $usec * 100000);
          mt_srand($seed);

          // Generate
          $str = '';
          $seeds_count = strlen($seeds);

          for ($i = 0; $length > $i; $i++) {
               $str .= $seeds[mt_rand(0, $seeds_count - 1)];
          }

          return $str;
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
          $data = count($result);
          return $data;
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
                    $select = $sql->select()->from($tableName)->where(array($fieldName => $value));
                    //$statement=$adapter->query('SELECT * FROM '.$tableName.' WHERE '.$fieldName." = '".$value."'");
                    $statement = $sql->prepareStatementForSqlObject($select);
                    $result = $statement->execute();
                    $data = count($result);
               } else {
                    $table = explode("##", $fnct);
                    if ($fieldName == 'password') {
                         //Password encrypted
                         $config = new \Laminas\Config\Reader\Ini();
                         $configResult = $config->fromFile(CONFIG_PATH . '/custom.config.ini');
                         $password = sha1($value . $configResult["password"]["salt"]);
                         //$password = $value;
                         $select = $sql->select()->from($tableName)->where(array($fieldName => $password, $table[0] => $table[1]));
                         $statement = $sql->prepareStatementForSqlObject($select);
                         $result = $statement->execute();
                         $data = count($result);
                    } else {
                         // first trying $table[1] without quotes. If this does not work, then in catch we try with single quotes
                         //$statement=$adapter->query('SELECT * FROM '.$tableName.' WHERE '.$fieldName." = '".$value."' and ".$table[0]."!=".$table[1] );
                         $select = $sql->select()->from($tableName)->where(array("$fieldName='$value'", $table[0] . "!=" . "'$table[1]'"));
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

     public function dateFormat($date)
     {
          if (!isset($date) || $date == null || $date == "" || $date == "0000-00-00") {
               return "0000-00-00";
          } else {
               $dateArray = explode('-', $date);
               if (sizeof($dateArray) == 0) {
                    return;
               }
               $newDate = $dateArray[2] . "-";

               $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
               $mon = 1;
               $mon += array_search(ucfirst($dateArray[1]), $monthsArray);

               if (strlen($mon) == 1) {
                    $mon = "0" . $mon;
               }
               return $newDate .= $mon . "-" . $dateArray[0];
          }
     }

     public function humanDateFormat($date)
     {
          if ($date == null || $date == "" || $date == "0000-00-00" || $date == "0000-00-00 00:00:00") {
               return "";
          } else {
               $dateArray = explode('-', $date);
               $newDate = $dateArray[2] . "-";

               $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
               $mon = $monthsArray[$dateArray[1] - 1];
               return $newDate .= $mon . "-" . $dateArray[0];
          }
     }

     public function viewDateFormat($date)
     {
          if ($date == null || $date == "" || $date == "0000-00-00") {
               return "";
          } else {
               $dateArray = explode('-', $date);
               $newDate = $dateArray[2] . "-";

               $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
               $mon = $monthsArray[$dateArray[1] - 1];

               return $newDate .= $mon . "-" . $dateArray[0];
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
               $config = new \Laminas\Config\Reader\Ini();
               $configResult = $config->fromFile(CONFIG_PATH . '/custom.config.ini');
               $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
               $sql = new Sql($dbAdapter);

               // Setup SMTP transport using LOGIN authentication
               $transport = new SmtpTransport();
               $options = new SmtpOptions(array(
                    'host' => $configResult["email"]["host"],
                    'port' => $configResult["email"]["config"]["port"],
                    'connection_class' => $configResult["email"]["config"]["auth"],
                    'connection_config' => array(
                         'username' => $configResult["email"]["config"]["username"],
                         'password' => $configResult["email"]["config"]["password"],
                         'ssl' => $configResult["email"]["config"]["ssl"],
                    ),
               ));
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

     function removeDirectory($dirname)
     {
          // Sanity check
          if (!file_exists($dirname)) {
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

     public static function getDateTime($timezone = 'Asia/Calcutta')
     {
          $date = new \DateTime(date('Y-m-d H:i:s'), new \DateTimeZone($timezone));
          return $date->format('Y-m-d H:i:s');
     }

     public static function getDate($timezone = 'Asia/Calcutta')
     {
          $date = new \DateTime(date('Y-m-d'), new \DateTimeZone($timezone));
          return $date->format('Y-m-d');
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
          // $res = $dbAdapter->query($queryString, $dbAdapter::QUERY_MODE_EXECUTE);
          // return $res;

          $cacheObj = $this->sm->get('Cache\Persistent');
          $cacheId = hash("sha512", $queryString);
          $res = null;
          try {
               if (!$cacheObj->hasItem($cacheId)) {
                    if (!$fetchCurrent) {
                         $res = $dbAdapter->query($queryString, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
                    } else {
                         $res = $dbAdapter->query($queryString, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                    }
                    $cacheObj->addItem($cacheId, ($res));
               } else {
                    $res = ($cacheObj->getItem($cacheId));
               }
               return $res;
          } catch (Exception $e) {
               error_log($e->getMessage());
               //if(!$fetchCurrent){
               //    $res = $dbAdapter->query($queryString, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
               //}else{
               //    $res = $dbAdapter->query($queryString, $dbAdapter::QUERY_MODE_EXECUTE)->current();
               //}
               //return $res;
          }
     }

     public function clearAllCache()
     {
          $cacheObj = $this->sm->get('Cache\Persistent');
          return $cacheObj->flush();
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
          if (empty($inputString)) return "";

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
          } else if ($action == 'decrypt') {

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
          $logincontainer = new Container('credo');
          $mappedFacilities = null;
          if ($logincontainer->role != 1) {
               $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : null;
          }
          $facilityDb = $this->sm->get('FacilityTable');
          return $facilityDb->fetchAllLabName($mappedFacilities);
     }
     //get all Lab Name
     public function getAllClinicName()
     {

          $logincontainer = new Container('credo');
          $mappedFacilities = null;
          if ($logincontainer->role != 1) {
               $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : null;
          }

          $facilityDb = $this->sm->get('FacilityTable');
          return $facilityDb->fetchAllClinicName($mappedFacilities);
     }
     //get all province name
     public function getAllProvinceList()
     {

          $logincontainer = new Container('credo');
          $mappedFacilities = null;
          if ($logincontainer->role != 1) {
               $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : null;
          }

          $locationDb = $this->sm->get('LocationDetailsTable');
          return $locationDb->fetchLocationDetails($mappedFacilities);
     }
     public function getAllDistrictList()
     {

          $logincontainer = new Container('credo');
          $mappedFacilities = null;
          if ($logincontainer->role != 1) {
               $mappedFacilities = (isset($logincontainer->mappedFacilities) && count($logincontainer->mappedFacilities) > 0) ? $logincontainer->mappedFacilities : null;
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


     public function saveVlsmReferenceTablesFromAPI($params)
     {
          /* if(empty($params['api-version'])){
               return array('status' => 'fail', 'message' => 'Please specify API version');
          } */
          $facilityDb = $this->sm->get('FacilityTable');
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
          $locationDb = $this->sm->get('LocationDetailsTable');


          $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
          $sql = new Sql($dbAdapter);

          $apiData = array();
          $fileName = $_FILES['referenceFile']['name'];
          $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
          $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
          $fileName = $ranNumber . "." . $extension;

          if (!file_exists(TEMP_UPLOAD_PATH) && !is_dir(TEMP_UPLOAD_PATH)) {
               mkdir(APPLICATION_PATH . DIRECTORY_SEPARATOR . "uploads", 0777);
          }
          if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-reference") && !is_dir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-reference")) {
               mkdir(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-reference", 0777);
          }

          $pathname = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . "vlsm-reference" . DIRECTORY_SEPARATOR . $fileName;
          if (!file_exists($pathname)) {
               if (move_uploaded_file($_FILES['referenceFile']['tmp_name'], $pathname)) {
                    // $apiData = \JsonMachine\JsonMachine::fromFile($pathname);
                    $apiData = json_decode(file_get_contents($pathname));
               }
          }
          // echo "<pre>";print_r($apiData->facility_details->tableStructure);die;
          if ($apiData !== FALSE) {
               /* For update the Facility Details */
               if(isset($apiData->facility_details) && !empty($apiData->facility_details)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->facility_details->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->facility_details->lastModifiedTime) && !empty($apiData->facility_details->lastModifiedTime)){
                         $condition = "updated_datetime > '" . $apiData->facility_details->lastModifiedTime . "'";
                    }
                    $notUpdated = $this->getLastModifiedDateTime('facility_details', 'updated_datetime', $condition);
                    if (empty($notUpdated) || !isset($notUpdated)) {
                         foreach ((array)$apiData->facility_details->tableData as $row) {
                              $facilityData = (array)$row;
                              unset($facilityData['data_sync']);
                              if (trim($facilityData['facility_state']) != '') {
                                   $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($facilityData['facility_state']), 0);
                                   if ($sQueryResult) {
                                        $facilityData['facility_state'] = $sQueryResult['location_id'];
                                   } else {
                                        $locationDb->insert(array('parent_location' => 0, 'location_name' => trim($facilityData['facility_state'])));
                                        $facilityData['facility_state'] = $locationDb->lastInsertValue;
                                   }
                              }
                              if (trim($facilityData['facility_district']) != '') {
                                   $sQueryResult = $this->checkFacilityStateDistrictDetails(trim($facilityData['facility_district']), $facilityData['facility_state']);
                                   if ($sQueryResult) {
                                        $facilityData['facility_district'] = $sQueryResult['location_id'];
                                   } else {
                                        $locationDb->insert(array('parent_location' => $facilityData['facility_state'], 'location_name' => trim($facilityData['facility_district'])));
                                        $facilityData['facility_district'] = $locationDb->lastInsertValue;
                                   }
                              }

                              $rQuery = $sql->select()->from('facility_details')->where(array('facility_code LIKE "%' . $facilityData['facility_code'] . '%" OR facility_id = ' . $facilityData['facility_id']));
                              $rQueryStr = $sql->buildSqlString($rQuery);
                              // die($rQueryStr);
                              $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                              if ($rowData) {
                                   $facilityDb->update($facilityData, array('facility_id' => $facilityData['facility_id']));
                              } else {
                                   $facilityDb->insert($facilityData);
                              }
                         }
                    }
               }
               /* For update the Test Reasons */
               if(isset($apiData->r_vl_test_reasons) && !empty($apiData->r_vl_test_reasons)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_vl_test_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->r_vl_test_reasons->lastModifiedTime) && !empty($apiData->r_vl_test_reasons->lastModifiedTime)){
                         $condition = "updated_datetime > '" . $apiData->r_vl_test_reasons->lastModifiedTime . "'";
                    }
                    $notUpdated = $this->getLastModifiedDateTime('r_vl_test_reasons', 'updated_datetime', $condition);
                    if (empty($notUpdated) || !isset($notUpdated)) {
                         foreach ((array)$apiData->r_vl_test_reasons->tableData as $row) {
                              $testReasonData = (array)$row;
                              $rQuery = $sql->select()->from('r_vl_test_reasons')->where(array('test_reason_name LIKE "%' . $testReasonData['test_reason_name'] . '%" OR test_reason_id = ' . $testReasonData['test_reason_id']));
                              $rQueryStr = $sql->buildSqlString($rQuery);
                              $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                              if ($rowData) {
                                   $testReasonDb->update($testReasonData, array('test_reason_id' => $testReasonData['test_reason_id']));
                              } else {
                                   $testReasonDb->insert($testReasonData);
                              }
                         }
                    }
               }
               
               /* For update the Covid19 Test Reasons */
               if(isset($apiData->r_covid19_test_reasons) && !empty($apiData->r_covid19_test_reasons)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_test_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->r_covid19_test_reasons->lastModifiedTime) && !empty($apiData->r_covid19_test_reasons->lastModifiedTime)){
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
               if(isset($apiData->r_art_code_details) && !empty($apiData->r_art_code_details)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_art_code_details->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->r_art_code_details->lastModifiedTime) && !empty($apiData->r_art_code_details->lastModifiedTime)){
                         $condition = "updated_datetime > '" . $apiData->r_art_code_details->lastModifiedTime . "'";
                    }
                    $notUpdated = $this->getLastModifiedDateTime('r_art_code_details', 'updated_datetime', $condition);
                    if (empty($notUpdated) || !isset($notUpdated)) {
                         foreach ((array)$apiData->r_art_code_details->tableData as $row) {
                              $artCodeData = (array)$row;
                              unset($artCodeData['data_sync']);
                              $rQuery = $sql->select()->from('r_art_code_details')->where(array('art_code LIKE "%' . $artCodeData['art_code'] . '%" OR art_id = ' . $artCodeData['art_id']));
                              $rQueryStr = $sql->buildSqlString($rQuery);
                              $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                              if ($rowData) {
                                   $artCodeDb->update($artCodeData, array('art_id' => $artCodeData['art_id']));
                              } else {
                                   $artCodeDb->insert($artCodeData);
                              }
                         }
                    }
               }
               
               /* For update the Sample Rejection Reason Details */
               if(isset($apiData->r_sample_rejection_reasons) && !empty($apiData->r_sample_rejection_reasons)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_sample_rejection_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->r_sample_rejection_reasons->lastModifiedTime) && !empty($apiData->r_sample_rejection_reasons->lastModifiedTime)){
                         $condition = "updated_datetime > '" . $apiData->r_sample_rejection_reasons->lastModifiedTime . "'";
                    }
                    $notUpdated = $this->getLastModifiedDateTime('r_sample_rejection_reasons', 'updated_datetime', $condition);
                    if (empty($notUpdated) || !isset($notUpdated)) {
                         foreach ((array)$apiData->r_sample_rejection_reasons->tableData as $row) {
                              $sampleRejectionReasonData = (array)$row;
                              unset($sampleRejectionReasonData['data_sync']);
                              $rQuery = $sql->select()->from('r_sample_rejection_reasons')->where(array('rejection_reason_name LIKE "%' . $sampleRejectionReasonData['rejection_reason_name'] . '%" OR rejection_reason_id = ' . $sampleRejectionReasonData['rejection_reason_id']));
                              $rQueryStr = $sql->buildSqlString($rQuery);
                              $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->current();
                              if ($rowData) {
                                   $sampleRejectionReasonDb->update($sampleRejectionReasonData, array('rejection_reason_id' => $sampleRejectionReasonData['rejection_reason_id']));
                              } else {
                                   $sampleRejectionReasonDb->insert($sampleRejectionReasonData);
                              }
                         }
                    }
               }
               
               /* For update the EID Sample Rejection Reason Details */
               if(isset($apiData->r_eid_sample_rejection_reasons) && !empty($apiData->r_eid_sample_rejection_reasons)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_eid_sample_rejection_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->r_eid_sample_rejection_reasons->lastModifiedTime) && !empty($apiData->r_eid_sample_rejection_reasons->lastModifiedTime)){
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
               if(isset($apiData->r_covid19_sample_rejection_reasons) && !empty($apiData->r_covid19_sample_rejection_reasons)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_sample_rejection_reasons->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->r_covid19_sample_rejection_reasons->lastModifiedTime) && !empty($apiData->r_covid19_sample_rejection_reasons->lastModifiedTime)){
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
               
               /* For update the EID Sample Type Details */
               if(isset($apiData->r_eid_sample_type) && !empty($apiData->r_eid_sample_type)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_eid_sample_type->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->r_eid_sample_type->lastModifiedTime) && !empty($apiData->r_eid_sample_type->lastModifiedTime)){
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
               if(isset($apiData->r_covid19_sample_type) && !empty($apiData->r_covid19_sample_type)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_sample_type->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->r_covid19_sample_type->lastModifiedTime) && !empty($apiData->r_covid19_sample_type->lastModifiedTime)){
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
               if(isset($apiData->r_covid19_comorbidities) && !empty($apiData->r_covid19_comorbidities)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_comorbidities->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->r_covid19_comorbidities->lastModifiedTime) && !empty($apiData->r_covid19_comorbidities->lastModifiedTime)){
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
               if(isset($apiData->r_covid19_symptoms) && !empty($apiData->r_covid19_symptoms)){
                    /* if($apiData->forceSync){
                         $rQueryStr = $apiData->r_covid19_symptoms->tableStructure;
                         $rowData = $dbAdapter->query($rQueryStr, $dbAdapter::QUERY_MODE_EXECUTE);
                    } */
                    $condition = "";
                    if(isset($apiData->r_covid19_symptoms->lastModifiedTime) && !empty($apiData->r_covid19_symptoms->lastModifiedTime)){
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
               return array(
                    'status' => 'success',
                    'message' => 'All reference tables synced'
               );
          } else {
               return array(
                    'status' => 'fail',
                    'message' => "File doesn't have data to update"
               );
          }
     }

     public function checkFacilityStateDistrictDetails($location, $parent)
     {
          $dbAdapter = $this->sm->get('Laminas\Db\Adapter\Adapter');
          $sql = new Sql($dbAdapter);
          $sQuery = $sql->select()->from(array('l' => 'location_details'))
               ->where(array('l.parent_location' => $parent, 'l.location_name' => trim($location)));
          $sQuery = $sql->buildSqlString($sQuery);
          $sQueryResult = $dbAdapter->query($sQuery, $dbAdapter::QUERY_MODE_EXECUTE)->current();
          return $sQueryResult;
     }

     function getMonthsInRange($startDate, $endDate)
	{
          $months = array();
		while (strtotime($startDate) <= strtotime($endDate)) {
               $monthYear = date('M', strtotime($startDate)) . "-" . date('Y', strtotime($startDate));
               $monthYearDBForamt = date('Y', strtotime($startDate)) . "-" . date('m', strtotime($startDate));
			$months[$monthYear] = $monthYearDBForamt;
			$startDate = date('d M Y', strtotime($startDate . '+ 1 month'));
          }
		return $months;
	}
}
