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
               $mailQueryStr = $sql->getSqlStringForSqlObject($mailQuery);
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
}
