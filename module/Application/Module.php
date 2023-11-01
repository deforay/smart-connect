<?php

namespace Application;

use Laminas\Mvc\MvcEvent;

use Laminas\Session\Container;
use Application\Model\RolesTable;
use Application\Model\UsersTable;
use Laminas\View\Model\ViewModel;
use Application\Model\GlobalTable;
use Application\Model\SampleTable;
use Application\Model\ArtCodeTable;
use Application\Model\FacilityTable;
use Application\Model\ProvinceTable;
use Application\Service\UserService;
use Laminas\Mvc\ModuleRouteListener;
use Application\Model\CountriesTable;
use Application\Model\SampleTypeTable;
use Application\Model\TestReasonTable;
use Application\Service\CommonService;
use Application\Service\ConfigService;
use Application\Service\SampleService;
use Laminas\Cache\Pattern\ObjectCache;
use Application\Service\SummaryService;
use Application\Model\FacilityTypeTable;
use Application\Model\SampleStatusTable;
use Application\Service\FacilityService;
use Application\Model\EidSampleTypeTable;
use Application\Model\OrganizationsTable;
use Laminas\Cache\Pattern\PatternOptions;
use Application\Model\GenerateBackupTable;
use Application\Model\RemovedSamplesTable;
use Application\View\Helper\GetLocaleData;
use Application\Model\Covid19SymptomsTable;
use Application\Model\LocationDetailsTable;
use Application\Model\UserFacilityMapTable;
use Application\Model\HepatitisResultsTable;
use Application\Service\OrganizationService;
use Application\Model\Covid19SampleTypeTable;

use Application\Model\OrganizationTypesTable;
use Application\Model\Covid19TestReasonsTable;
use Application\Model\HepatitisRiskFactorTable;
use Application\Model\HepatitisSampleTypeTable;
use Application\Model\ImportConfigMachineTable;
use Application\Model\Covid19ComorbiditiesTable;
use Application\Model\DashApiReceiverStatsTable;

use Application\Model\HepatitisTestReasonsTable;
use Application\Model\UserOrganizationsMapTable;

use Application\Model\SampleRejectionReasonTable;
use Application\Model\EidSampleRejectionReasonTable;

use Application\Model\Covid19SampleRejectionReasonsTable;
use Application\Model\HepatitisSampleRejectionReasonTable;

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
			if ($e->getRouteMatch()->getParam('controller') != 'Application\Controller\LoginController') {
				//$session->userId = 'guest';
				//$session->accessType = 4;
				if (!isset($session->userId) || $session->userId == "") {
					$url = $e->getRouter()->assemble(array(), array('name' => 'login'));
					/** @var \Laminas\Http\Response $response */
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
						/** @var \Laminas\Http\Response $response */
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
						/** @var \Laminas\Http\Response $response */
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
						/** @var \Laminas\Http\Response $response */
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
						$mappedFacilities = (isset($session->mappedFacilities) && !empty($session->mappedFacilities)) ? $session->mappedFacilities : array();
						$mappedFacilitiesName = (isset($session->mappedFacilitiesName) && !empty($session->mappedFacilitiesName)) ? $session->mappedFacilitiesName : array();
						$mappedFacilitiesCode = (isset($session->mappedFacilitiesCode) && !empty($session->mappedFacilitiesCode)) ? $session->mappedFacilitiesCode : array();
						$lab = array();
						if (isset($_GET['lab']) && trim($_GET['lab']) != '') {
							$lab = array_values(array_filter(explode(',', $_GET['lab'])));
						}
						$redirect = false;
						if (!empty($lab)) {
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
						if ($redirect === true) {
							//set redirect path
							/** @var \Laminas\Http\Response $response */
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
				'UsersTable' => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						$commonService = $diContainer->get('CommonService');
						return new UsersTable($dbAdapter, $diContainer, $commonService);
					}
				},
				'OrganizationsTable' => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new OrganizationsTable($dbAdapter);
					}
				},
				'OrganizationTypesTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new OrganizationTypesTable($dbAdapter);
					}
				},
				'CountriesTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new CountriesTable($dbAdapter);
					}
				},
				'RolesTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new RolesTable($dbAdapter);
					}
				},
				'UserOrganizationsMapTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new UserOrganizationsMapTable($dbAdapter);
					}
				},
				'SampleTable' => new class
				{
					public function __invoke($diContainer)
					{
						$session = new Container('credo');
						$mappedFacilities = (isset($session->mappedFacilities) && !empty($session->mappedFacilities)) ? $session->mappedFacilities : array();
						$sampleTable = isset($session->sampleTable) ? $session->sampleTable :  null;
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						$commonService = $diContainer->get('CommonService');
						$tableObj = new SampleTable($dbAdapter, $diContainer, $mappedFacilities, $sampleTable, $commonService);
						$storage = $diContainer->get('Cache\Persistent');

						return new ObjectCache(
							$storage,
							new PatternOptions([
								'object' => $tableObj,
								'object_key' => $sampleTable // this makes sure we have different caches for both current and archive
							])
						);
					}
				},
				'SampleTableWithoutCache' => new class
				{
					public function __invoke($diContainer)
					{
						$session = new Container('credo');
						$mappedFacilities = (isset($session->mappedFacilities) && !empty($session->mappedFacilities)) ? $session->mappedFacilities : array();
						$sampleTable = isset($session->sampleTable) ? $session->sampleTable :  null;
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						$commonService = $diContainer->get('CommonService');
						return new SampleTable($dbAdapter, $diContainer, $mappedFacilities, $sampleTable, $commonService);
					}
				},
				'FacilityTable' => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						$commonService = $diContainer->get('CommonService');
						$tableObj = new FacilityTable($dbAdapter, $diContainer, $commonService);

						$storage = $diContainer->get('Cache\Persistent');
						return new ObjectCache(
							$storage,
							new PatternOptions([
								'object' => $tableObj
							])
						);
					}
				},
				'FacilityTableWithoutCache' => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						$commonService = $diContainer->get('CommonService');
						return new FacilityTable($dbAdapter, $diContainer, $commonService);
					}
				},
				'FacilityTypeTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new FacilitytypeTable($dbAdapter);
					}
				},
				'TestReasonTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new TestReasonTable($dbAdapter);
					}
				},
				'SampleStatusTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new SampleStatusTable($dbAdapter);
					}
				},
				'SampleTypeTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new SampleTypeTable($dbAdapter);
					}
				}, 'GlobalTable' => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						$commonService = $diContainer->get('CommonService');
						return new GlobalTable($dbAdapter, $diContainer, $commonService);
					}
				}, 'ArtCodeTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new ArtCodeTable($dbAdapter);
					}
				}, 'UserFacilityMapTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new UserFacilityMapTable($dbAdapter);
					}
				},
				'LocationDetailsTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new LocationDetailsTable($dbAdapter);
					}
				},
				'RemovedSamplesTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new RemovedSamplesTable($dbAdapter);
					}
				},
				'GenerateBackupTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new GenerateBackupTable($dbAdapter);
					}
				},
				'SampleRejectionReasonTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new SampleRejectionReasonTable($dbAdapter);
					}
				},
				'EidSampleRejectionReasonTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new EidSampleRejectionReasonTable($dbAdapter);
					}
				},
				'Covid19SampleRejectionReasonsTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new Covid19SampleRejectionReasonsTable($dbAdapter);
					}
				},
				'EidSampleTypeTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new EidSampleTypeTable($dbAdapter);
					}
				},
				'Covid19SampleTypeTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new Covid19SampleTypeTable($dbAdapter);
					}
				},
				'Covid19ComorbiditiesTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new Covid19ComorbiditiesTable($dbAdapter);
					}
				},
				'Covid19SymptomsTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new Covid19SymptomsTable($dbAdapter);
					}
				},
				'Covid19TestReasonsTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new Covid19TestReasonsTable($dbAdapter);
					}
				},
				'ProvinceTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new ProvinceTable($dbAdapter);
					}
				},
				'DashApiReceiverStatsTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new DashApiReceiverStatsTable($dbAdapter);
					}
				},
				'ImportConfigMachineTable'  => new class
				{
					public function __invoke($diContainer)
					{
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new ImportConfigMachineTable($dbAdapter);
					}
				},
				'HepatitisSampleTypeTable'  => new class
				{
					public function __invoke($diContainer)
					{

						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new HepatitisSampleTypeTable($dbAdapter);
					}
				},
				'HepatitisSampleRejectionReasonTable'  => new class
				{
					public function __invoke($diContainer)
					{

						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new HepatitisSampleRejectionReasonTable($dbAdapter);
					}
				},
				'HepatitisResultsTable'  => new class
				{
					public function __invoke($diContainer)
					{

						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new HepatitisResultsTable($dbAdapter);
					}
				},
				'HepatitisRiskFactorTable'  => new class
				{
					public function __invoke($diContainer)
					{

						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new HepatitisRiskFactorTable($dbAdapter);
					}
				},
				'HepatitisTestReasonsTable'  => new class
				{
					public function __invoke($diContainer)
					{

						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new HepatitisTestReasonsTable($dbAdapter);
					}
				},

				'CommonService'  => new class
				{
					public function __invoke($diContainer)
					{
						$cache = $diContainer->get('Cache\Persistent');
						return new CommonService($diContainer, $cache);
					}
				},
				'UserService'  => new class
				{
					public function __invoke($diContainer)
					{
						$usersTable = $diContainer->get('UsersTable');
						return new UserService($diContainer, $usersTable);
					}
				},
				'OrganizationService'  => new class
				{
					public function __invoke($diContainer)
					{
						return new OrganizationService($diContainer);
					}
				},
				'SampleService' => new class
				{
					public function __invoke($diContainer)
					{
						$sampleTable = $diContainer->get('SampleTable');
						$apiTrackerTable = $diContainer->get('DashApiReceiverStatsTable');
						$commonService = $diContainer->get('CommonService');
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new SampleService($diContainer, $sampleTable, $commonService, $apiTrackerTable, $dbAdapter);
					}
				},
				'SummaryService'  => new class
				{
					public function __invoke($diContainer)
					{

						$sampleTable = $diContainer->get('SampleTable');
						$translator = $diContainer->get('translator');
						$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
						return new SummaryService($sampleTable, $translator, $dbAdapter);
					}
				},
				'ConfigService' => new class
				{
					public function __invoke($diContainer)
					{

						return new ConfigService($diContainer);
					}
				},
				'FacilityService' => new class
				{
					public function __invoke($diContainer)
					{

						return new FacilityService($diContainer);
					}
				},
				'translator' => 'Laminas\Mvc\I18n\TranslatorFactory',
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
				'Application\Controller\LoginController' => new class
				{
					public function __invoke($diContainer)
					{
						$configService = $diContainer->get('ConfigService');
						$userService = $diContainer->get('UserService');
						return new \Application\Controller\LoginController($userService, $configService);
					}
				},
				'Application\Controller\UsersController' => new class
				{
					public function __invoke($diContainer)
					{
						$commonService = $diContainer->get('CommonService');
						$orgService = $diContainer->get('OrganizationService');
						$userService = $diContainer->get('UserService');
						return new \Application\Controller\UsersController($userService, $commonService, $orgService);
					}
				},
				'Application\Controller\CronController' => new class
				{
					public function __invoke($diContainer)
					{
						$sampleService = $diContainer->get('SampleService');
						return new \Application\Controller\CronController($sampleService);
					}
				},
				'Application\Controller\StatusController' => new class
				{
					public function __invoke($diContainer)
					{
						$commonService = $diContainer->get('CommonService');
						return new \Application\Controller\StatusController($commonService);
					}
				},
				'Application\Controller\SyncStatusController' => new class
				{
					public function __invoke($diContainer)
					{
						$commonService = $diContainer->get('CommonService');
						return new \Application\Controller\SyncStatusController($commonService);
					}
				},
				'Application\Controller\ConfigController' => new class
				{
					public function __invoke($diContainer)
					{
						$configService = $diContainer->get('ConfigService');
						return new \Application\Controller\ConfigController($configService);
					}
				},
				'Application\Controller\FacilityController' => new class
				{
					public function __invoke($diContainer)
					{
						$facilityService = $diContainer->get('FacilityService');
						return new \Application\Controller\FacilityController($facilityService);
					}
				},
				'Application\Controller\SummaryController' => new class
				{
					public function __invoke($diContainer)
					{
						$sampleService = $diContainer->get('SampleService');
						$summaryService = $diContainer->get('SummaryService');
						return new \Application\Controller\SummaryController($summaryService, $sampleService);
					}
				},
				'Application\Controller\LaboratoryController' => new class
				{
					public function __invoke($diContainer)
					{
						$sampleService = $diContainer->get('SampleService');
						$commonService = $diContainer->get('CommonService');
						return new \Application\Controller\LaboratoryController($sampleService, $commonService);
					}
				},
				'Application\Controller\ClinicController' => new class
				{
					public function __invoke($diContainer)
					{
						$sampleService = $diContainer->get('SampleService');
						$configService = $diContainer->get('ConfigService');
						return new \Application\Controller\ClinicController($sampleService, $configService);
					}
				},
				'Application\Controller\CommonController' => new class
				{
					public function __invoke($diContainer)
					{
						$commonService = $diContainer->get('CommonService');
						$configService = $diContainer->get('ConfigService');
						return new \Application\Controller\CommonController($commonService, $configService);
					}
				},
				'Application\Controller\TimeController' => new class
				{
					public function __invoke($diContainer)
					{
						$sampleService = $diContainer->get('SampleService');
						$facilityService = $diContainer->get('FacilityService');
						return new \Application\Controller\TimeController($facilityService, $sampleService);
					}
				},
				'Application\Controller\OrganizationsController' => new class
				{
					public function __invoke($diContainer)
					{
						$organizationService = $diContainer->get('OrganizationService');
						$commonService = $diContainer->get('CommonService');
						$userService = $diContainer->get('UserService');
						return new \Application\Controller\OrganizationsController($organizationService, $commonService, $userService);
					}
				},
			),
		);
	}

	public function getViewHelperConfig()
	{
		return array(
			'invokables' => array(
				'humanReadableDateFormat' 		=> 'Application\View\Helper\HumanReadableDateFormat'
			),
			'factories' => array(
				'GetLocaleData'  => new class
				{
					public function __invoke($diContainer)
					{
						$globalTable = $diContainer->get('GlobalTable');
						return new GetLocaleData($globalTable);
					}
				},
				'GetConfigData' => new class
				{
					public function __invoke($diContainer)
					{
						$globalTable = $diContainer->get('GlobalTable');
						return new \Application\View\Helper\GetConfigData($globalTable);
					}
				},
				'GetActiveModules' => new class
				{
					public function __invoke($diContainer)
					{
						$config = $diContainer->get('Config');
						return new \Application\View\Helper\GetActiveModules($config);
					}
				},
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
