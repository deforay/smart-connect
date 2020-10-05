<?php

namespace Application;

use Laminas\Session\Container;

use Application\Model\UsersTable;
use Application\Model\OrganizationsTable;
use Application\Model\OrganizationTypesTable;
use Application\Model\CountriesTable;
use Application\Model\RolesTable;
use Application\Model\UserOrganizationsMapTable;
use Application\Model\SampleTable;
use Application\Model\FacilityTable;
use Application\Model\FacilityTypeTable;
use Application\Model\SampleStatusTable;
use Application\Model\TestReasonTable;
use Application\Model\SampleTypeTable;
use Application\Model\GlobalTable;
use Application\Model\ArtCodeTable;
use Application\Model\UserFacilityMapTable;
use Application\Model\LocationDetailsTable;
use Application\Model\RemovedSamplesTable;
use Application\Model\GenerateBackupTable;
use Application\Model\SampleRejectionReasonTable;
use Application\Model\EidSampleRejectionReasonTable;
use Application\Model\Covid19SampleRejectionReasonsTable;
use Application\Model\EidSampleTypeTable;
use Application\Model\Covid19SampleTypeTable;
use Application\Model\Covid19ComorbiditiesTable;
use Application\Model\Covid19SymptomsTable;
use Application\Model\Covid19TestReasonsTable;
use Application\Model\ProvinceTable;
use Application\Model\DashApiReceiverStatsTable;

use Application\Service\CommonService;
use Application\Service\UserService;
use Application\Service\OrganizationService;
use Application\Service\SampleService;
use Application\Service\ConfigService;
use Application\Service\FacilityService;
use Application\Service\SummaryService;

use Laminas\Mvc\ModuleRouteListener;
use Laminas\Mvc\MvcEvent;
use Laminas\Cache\PatternFactory;

use Laminas\View\Model\ViewModel;

class Module
{
	public function onBootstrap(MvcEvent $e)
	{
		define("APP_VERSION", "3.0");
		$languagecontainer = new Container('language');
		$eventManager        = $e->getApplication()->getEventManager();
		$moduleRouteListener = new ModuleRouteListener();
		$moduleRouteListener->attach($eventManager);
		if (php_sapi_name() != 'cli') {
			$eventManager->attach('dispatch', array($this, 'preSetter'), 100);
			//$eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'dispatchError'), -999);
		}
		if (isset($languagecontainer->locale) && $languagecontainer->locale != '') {
			// Just a call to the translator, nothing special!
			$this->initTranslator($e);
		}
	}

	public function preSetter(MvcEvent $e)
	{
		$session = new Container('credo');
		$tempName = explode('Controller', $e->getRouteMatch()->getParam('controller'));
		if (substr($tempName[0], 0, -1) != 'Api') {
			if ($e->getRouteMatch()->getParam('controller') != 'Application\Controller\Login') {
				//$session->userId = 'guest';
				//$session->accessType = 4;
				if (!isset($session->userId) || $session->userId == "") {
					$url = $e->getRouter()->assemble(array(), array('name' => 'login'));
					$response = $e->getResponse();
					$response->getHeaders()->addHeaderLine('Location', $url);
					$response->setStatusCode(302);
					$response->sendHeaders();

					// To avoid additional processing
					// we can attach a listener for Event Route with a high priority
					$stopCallBack = function ($event) use ($response) {
						$event->stopPropagation();
						return $response;
					};
					//Attach the "break" as a listener with a high priority
					$e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
					return $response;
				} else {
					if ((substr($tempName[1], 1) == 'Clinic' || substr($tempName[0], 1) == 'Hubs')  && $session->role == '2') {
						$response = $e->getResponse();
						$response->getHeaders()->addHeaderLine('Location', '/labs/dashboard');
						$response->setStatusCode(302);
						$response->sendHeaders();

						// To avoid additional processing
						// we can attach a listener for Event Route with a high priority
						$stopCallBack = function ($event) use ($response) {
							$event->stopPropagation();
							return $response;
						};
						//Attach the "break" as a listener with a high priority
						$e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
						return $response;
					} else if ((substr($tempName[1], 1) == 'Laboratory' || substr($tempName[1], 1) == 'Hubs')  && $session->role == '3') {
						$response = $e->getResponse();
						$response->getHeaders()->addHeaderLine('Location', '/clinics/dashboard');
						$response->setStatusCode(302);
						$response->sendHeaders();

						// To avoid additional processing
						// we can attach a listener for Event Route with a high priority
						$stopCallBack = function ($event) use ($response) {
							$event->stopPropagation();
							return $response;
						};
						//Attach the "break" as a listener with a high priority
						$e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
						return $response;
					} else if ((substr($tempName[1], 1) == 'Laboratory' || substr($tempName[1], 1) == 'Clinic')  && $session->role == '4') {
						$response = $e->getResponse();
						$response->getHeaders()->addHeaderLine('Location', '/hubs/dashboard');
						$response->setStatusCode(302);
						$response->sendHeaders();

						// To avoid additional processing
						// we can attach a listener for Event Route with a high priority
						$stopCallBack = function ($event) use ($response) {
							$event->stopPropagation();
							return $response;
						};
						//Attach the "break" as a listener with a high priority
						$e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
						return $response;
					}
					//clinic/lab dashboard re-direction, in-case of passing invalid url params
					if ($session->role != 1) {
						$mappedFacilities = (isset($session->mappedFacilities) && count($session->mappedFacilities) > 0) ? $session->mappedFacilities : array();
						$mappedFacilitiesName = (isset($session->mappedFacilitiesName) && count($session->mappedFacilitiesName) > 0) ? $session->mappedFacilitiesName : array();
						$mappedFacilitiesCode = (isset($session->mappedFacilitiesCode) && count($session->mappedFacilitiesCode) > 0) ? $session->mappedFacilitiesCode : array();
						$lab = array();
						if (isset($_GET['lab']) && trim($_GET['lab']) != '') {
							$lab = array_values(array_filter(explode(',', $_GET['lab'])));
						}
						$redirect = false;
						if (count($lab) > 0) {
							for ($l = 0; $l < count($lab); $l++) {
								if (!in_array($lab[$l], $mappedFacilities) && !in_array($lab[$l], $mappedFacilitiesName) && !in_array($lab[$l], $mappedFacilitiesCode)) {
									$redirect = true;
									break;
								}
							}
						}

						if (substr($tempName[1], 1) == 'Users' || substr($tempName[1], 1) == 'Config' || substr($tempName[1], 1) == 'Facility' || substr($tempName[1], 1) == 'Import') {
							$redirect = true;
						}
						if ($redirect == true) {
							//set redirect path
							$response = $e->getResponse();
							if ($session->role == 2) {
								$response->getHeaders()->addHeaderLine('Location', '/labs/dashboard');
							} else if ($session->role == 3) {
								$response->getHeaders()->addHeaderLine('Location', '/clinics/dashboard');
							} else if ($session->role == 4) {
								$response->getHeaders()->addHeaderLine('Location', '/hubs/dashboard');
							} else if ($session->role == 5) {
								$response->getHeaders()->addHeaderLine('Location', '/labs/dashboard');
							}
							$response->setStatusCode(302);
							$response->sendHeaders();

							// To avoid additional processing
							// we can attach a listener for Event Route with a high priority
							$stopCallBack = function ($event) use ($response) {
								$event->stopPropagation();
								return $response;
							};
							//Attach the "break" as a listener with a high priority
							$e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
							return $response;
						}
					}
				}
			}
		}
	}

	protected function initTranslator(MvcEvent $event)
	{
		$languagecontainer = new Container('language');
		$serviceManager = $event->getApplication()->getServiceManager();
		$translator = $serviceManager->get('translator');
		$translator->setLocale($languagecontainer->locale)
			->setFallbackLocale('en_US');
	}

	public function getConfig()
	{
		return include __DIR__ . '/config/module.config.php';
	}

	public function getServiceConfig()
	{
		return array(
			'factories' => array(
				'UsersTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new UsersTable($dbAdapter, $sm);
					return $table;
				},
				'OrganizationsTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new OrganizationsTable($dbAdapter);
					return $table;
				},
				'OrganizationTypesTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new OrganizationTypesTable($dbAdapter);
					return $table;
				},
				'CountriesTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new CountriesTable($dbAdapter);
					return $table;
				},
				'RolesTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new RolesTable($dbAdapter);
					return $table;
				},
				'UserOrganizationsMapTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new UserOrganizationsMapTable($dbAdapter);
					return $table;
				},
				'SampleTable' => function ($sm) {
					$session = new Container('credo');
					$mappedFacilities = (isset($session->mappedFacilities) && count($session->mappedFacilities) > 0) ? $session->mappedFacilities : array();
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$sampleTable = isset($session->sampleTable) ? $session->sampleTable :  null;
					$tableObj = new SampleTable($dbAdapter, $sm, $mappedFacilities, $sampleTable);
					$table = PatternFactory::factory('object', [
						'storage' => $sm->get('Cache\Persistent'),
						'object' => $tableObj,
						'object_key' => $sampleTable // this makes sure we have different caches for both current and archive
					]);
					return $table;
				},
				'SampleTableWithoutCache' => function ($sm) {
					$session = new Container('credo');
					$mappedFacilities = (isset($session->mappedFacilities) && count($session->mappedFacilities) > 0) ? $session->mappedFacilities : array();
					$sampleTable = isset($session->sampleTable) ? $session->sampleTable :  null;
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					return new SampleTable($dbAdapter, $sm, $mappedFacilities, $sampleTable);
				},
				'FacilityTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$tableObj = new FacilityTable($dbAdapter, $sm);
					$table = PatternFactory::factory('object', [
						'storage' => $sm->get('Cache\Persistent'),
						'object' => $tableObj
					]);
					return $table;
				},
				'FacilityTableWithoutCache' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					return new FacilityTable($dbAdapter, $sm);
				},
				'FacilityTypeTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new FacilitytypeTable($dbAdapter);
					return $table;
				},
				'TestReasonTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new TestReasonTable($dbAdapter);
					return $table;
				},
				'SampleStatusTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new SampleStatusTable($dbAdapter);
					return $table;
				},
				'SampleTypeTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new SampleTypeTable($dbAdapter);
					return $table;
				}, 'GlobalTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					return new GlobalTable($dbAdapter, $sm);
					// $tableObj = new GlobalTable($dbAdapter, $sm);
					// $table = PatternFactory::factory('object', [
					// 	'storage' => $sm->get('Cache\Persistent'),
					// 	'object' => $tableObj
					// ]);
					// return $table;					
				}, 'ArtCodeTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new ArtCodeTable($dbAdapter);
					return $table;
				}, 'UserFacilityMapTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new UserFacilityMapTable($dbAdapter);
					return $table;
				},
				'LocationDetailsTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new LocationDetailsTable($dbAdapter);
					return $table;
				},
				'RemovedSamplesTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new RemovedSamplesTable($dbAdapter);
					return $table;
				},
				'GenerateBackupTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new GenerateBackupTable($dbAdapter);
					return $table;
				},
				'SampleRejectionReasonTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new SampleRejectionReasonTable($dbAdapter);
					return $table;
				},
				'EidSampleRejectionReasonTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new EidSampleRejectionReasonTable($dbAdapter);
					return $table;
				},
				'Covid19SampleRejectionReasonsTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new Covid19SampleRejectionReasonsTable($dbAdapter);
					return $table;
				},
				'EidSampleTypeTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new EidSampleTypeTable($dbAdapter);
					return $table;
				},
				'Covid19SampleTypeTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new Covid19SampleTypeTable($dbAdapter);
					return $table;
				},
				'Covid19ComorbiditiesTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new Covid19ComorbiditiesTable($dbAdapter);
					return $table;
				},
				'Covid19SymptomsTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new Covid19SymptomsTable($dbAdapter);
					return $table;
				},
				'Covid19TestReasonsTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new Covid19TestReasonsTable($dbAdapter);
					return $table;
				},
				'ProvinceTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new ProvinceTable($dbAdapter);
					return $table;
				},
				'DashApiReceiverStatsTable' => function ($sm) {
					$dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
					$table = new DashApiReceiverStatsTable($dbAdapter);
					return $table;
				},

				'CommonService' => function ($sm) {
					return new CommonService($sm);
				},
				'UserService' => function ($sm) {
					return new UserService($sm);
				},
				'OrganizationService' => function ($sm) {
					return new OrganizationService($sm);
				},
				'SampleService' => function ($sm) {
					return new SampleService($sm);
				},
				'SummaryService' => function ($sm) {
					return new SummaryService($sm);
				},
				'ConfigService' => function ($sm) {
					return new ConfigService($sm);
				},
				'FacilityService' => function ($sm) {
					return new FacilityService($sm);
				},
				'translator' => 'Laminas\Mvc\Service\TranslatorServiceFactory',
			),
			'abstract_factories' => array(
				'Laminas\Cache\Service\StorageCacheAbstractServiceFactory',
			),
		);
	}

	public function getControllerConfig()
	{
		return array(
			'factories' => array(
				'Application\Controller\Login' => function ($sm) {
					$configService = $sm->getServiceLocator()->get('ConfigService');
					$userService = $sm->getServiceLocator()->get('UserService');
					return new \Application\Controller\LoginController($userService, $configService);
				},
				'Application\Controller\Users' => function ($sm) {
					$commonService = $sm->getServiceLocator()->get('ConfigService');
					$orgService = $sm->getServiceLocator()->get('OrganizationService');
					$userService = $sm->getServiceLocator()->get('UserService');
					return new \Application\Controller\UsersController($userService, $commonService, $orgService);
				},
				'Application\Controller\Cron' => function ($sm) {
					$sampleService = $sm->getServiceLocator()->get('SampleService');
					return new \Application\Controller\CronController($sampleService);
				},
				'Application\Controller\Config' => function ($sm) {
					$configService = $sm->getServiceLocator()->get('ConfigService');
					return new \Application\Controller\ConfigController($configService);
				},
				'Application\Controller\Facility' => function ($sm) {
					$facilityService = $sm->getServiceLocator()->get('FacilityService');
					return new \Application\Controller\FacilityController($facilityService);
				},
				'Application\Controller\Summary' => function ($sm) {
					$sampleService = $sm->getServiceLocator()->get('SampleService');
					$summaryService = $sm->getServiceLocator()->get('SummaryService');
					return new \Application\Controller\SummaryController($summaryService, $sampleService);
				},
				'Application\Controller\Laboratory' => function ($sm) {
					$sampleService = $sm->getServiceLocator()->get('SampleService');
					$commonService = $sm->getServiceLocator()->get('CommonService');
					return new \Application\Controller\LaboratoryController($sampleService, $commonService);
				},
				'Application\Controller\Clinic' => function ($sm) {
					$sampleService = $sm->getServiceLocator()->get('SampleService');
					$configService = $sm->getServiceLocator()->get('ConfigService');
					return new \Application\Controller\ClinicController($sampleService, $configService);
				},
				'Application\Controller\Common' => function ($sm) {
					$commonService = $sm->getServiceLocator()->get('CommonService');
					$configService = $sm->getServiceLocator()->get('ConfigService');
					return new \Application\Controller\CommonController($commonService, $configService);
				},
			),
		);
	}

	public function getViewHelperConfig()
	{
		return array(
			'invokables' => array(
				'humanDateFormat' 		=> 'Application\View\Helper\HumanDateFormat',
				'GetConfigData' 		=> 'Application\View\Helper\GetConfigData',
				'GetActiveModules' 		=> 'Application\View\Helper\GetActiveModules',
				'GetLocaleData' 		=> 'Application\View\Helper\GetLocaleData'
			),
		);
	}

	public function getAutoloaderConfig()
	{
		return array(
			'Laminas\Loader\StandardAutoloader' => array(
				'namespaces' => array(
					__NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
				),
			),
		);
	}
}
