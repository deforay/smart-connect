<?php

namespace Application;

use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\JsonModel;
use App\Log\AppLogger;

use Application\Model\Acl;
use Application\Session\Container;
use Application\Model\RolesTable;
use Application\Model\UsersTable;
use Application\Model\GlobalTable;
use Application\Model\SampleTable;
use Application\Model\ArtCodeTable;
use Application\Model\FacilityTable;
use Application\Model\ProvinceTable;
use Application\Model\TempMailTable;
use Application\Model\ActivityLogTable;
use Application\Model\UserLoginHistoryTable;
use Application\Service\RoleService;
use Application\Service\UserService;
use Application\Service\UserLoginHistoryService;
use Laminas\Mvc\ModuleRouteListener;
use Application\Model\CountriesTable;
use Application\Model\ResourcesTable;
use Application\Model\SampleTypeTable;
use Application\Model\TestReasonTable;
use Application\Service\CommonService;
use Application\Service\ConfigService;
use Application\Service\SampleService;
use Application\Service\FileCacheUtility;
use Application\Service\CachedMethodProxy;
use Application\Service\SummaryService;
use Application\Model\FacilityTypeTable;
use Application\Model\SampleStatusTable;
use Application\Service\FacilityService;
use Application\Service\SnapShotService;
use Application\Model\EidSampleTypeTable;
use Application\Model\OrganizationsTable;
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
use Application\Service\ApiSyncHistoryService;
use Application\Model\HepatitisRiskFactorTable;
use Application\Model\HepatitisSampleTypeTable;
use Application\Model\ImportConfigMachineTable;

use Application\Model\Covid19ComorbiditiesTable;
use Application\Model\DashApiReceiverStatsTable;

use Application\Model\DashTrackApiRequestsTable;
use Application\Model\HepatitisTestReasonsTable;

use Application\Model\UserOrganizationsMapTable;
use Application\Model\SampleRejectionReasonTable;
use Application\Model\EidSampleRejectionReasonTable;
use Application\Model\Covid19SampleRejectionReasonsTable;
use Application\Model\HepatitisSampleRejectionReasonTable;

class Module
{

	private function getModuleNameFromController($controllerName)
	{
		// Split the controller name by backslash
		$parts = explode('\\', $controllerName);

		// The first part is typically the module name
		return $parts[0] ?? '';
	}
	public function onBootstrap(MvcEvent $e)
	{
		// composer.json holds the version. Keeping the constant derived from it
		// stops the footer and the migrator from drifting apart.
		define("APP_VERSION", \App\Version::app());

		/**
		 * @var \Laminas\Mvc\Application $application
		 */
		$application = $e->getApplication();

		$languagecontainer = new Container('language');
		$eventManager = $application->getEventManager();
		$moduleRouteListener = new ModuleRouteListener();
		$moduleRouteListener->attach($eventManager);
		if (php_sapi_name() != 'cli') {
			$eventManager->attach('dispatch', function (MvcEvent $e) {
				return $this->preSetter($e);
			}, 100);
			// Ahead of the responders, so the line is written whatever they
			// then decide to render.
			$eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'logMvcError'], 10000);
			$eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'logMvcError'], 10000);
			$eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, [$this, 'renderJsonErrorForAjax'], 1000);
			$eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, [$this, 'renderJsonErrorForAjax'], 1000);
			//$eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'dispatchError'), -999);
		}
		if (isset($languagecontainer->locale) && $languagecontainer->locale !== null && $languagecontainer->locale != '') {
			// Just a call to the translator, nothing special!
			$this->initTranslator($e);
		}
	}

	/**
	 * Record why a request failed.
	 *
	 * Laminas catches a controller throwable and turns it into this event, so
	 * without a listener here the only trace of a 500 is whatever the host's
	 * PHP error log kept. The URL is included because the exception alone does
	 * not say which page an operator was on when it broke.
	 */
	public function logMvcError(MvcEvent $event)
	{
		$exception = $event->getParam('exception');
		$request = $event->getRequest();
		$uri = $request instanceof Request ? $request->getUriString() : 'cli';

		if ($exception instanceof \Throwable) {
			AppLogger::logThrowable($exception, 'Request failed', ['url' => $uri]);
			return;
		}

		// A routing miss carries no exception. It is still worth a line, at a
		// level that does not read as a crash.
		AppLogger::logWarning('Request could not be dispatched', [
			'url' => $uri,
			'reason' => (string) $event->getError(),
		]);
	}

	public function renderJsonErrorForAjax(MvcEvent $event)
	{
		$request = $event->getRequest();

		if (!$this->requestWantsJson($request)) {
			return;
		}

		$response = $event->getResponse() instanceof Response ? $event->getResponse() : new Response();
		$statusCode = $response->getStatusCode();
		if ($statusCode < 400) {
			$statusCode = $event->getError() === Application::ERROR_ROUTER_NO_MATCH ? 404 : 500;
		}
		$response->setStatusCode($statusCode);
		$event->setResponse($response);

		// The exception message only leaves the server where the deployment
		// already asks for exception detail. It carries SQL fragments, column
		// names and absolute file paths, and this listener answers any caller
		// that sent an Accept of application/json or an XmlHttpRequest header.
		// Turning display_exceptions off in the view manager did nothing for
		// this path, so the JSON response kept disclosing what the HTML one no
		// longer would.
		// The fallback is the half a person reads, so it goes through the
		// translator like every other user-visible string. An exception message
		// is not translated: it is developer text, and it only appears where
		// the deployment has asked for exception detail.
		$message = $this->translate($event, 'An unexpected error occurred');
		$exception = $event->getParam('exception');
		if ($exception instanceof \Throwable && $this->displayExceptions($event)) {
			$message = $exception->getMessage();
		}

		$model = new JsonModel([
			'status' => 'error',
			'message' => $message
		]);
		$model->setTerminal(true);
		$event->setResult($model);

		return $model;
	}

	/**
	 * Whether this deployment asks for exception detail in responses.
	 *
	 * Reads the view manager's own setting rather than re-deriving the
	 * environment, so the HTML error page and the JSON one are governed by a
	 * single switch. Defaults to false, so a config that cannot be read hides
	 * the message rather than showing it.
	 */
	/**
	 * Translate a string from a listener, where no view helper is in reach.
	 *
	 * Returns the original text when the translator cannot be resolved, so an
	 * error responder never fails while reporting a failure.
	 */
	private function translate(MvcEvent $event, string $message): string
	{
		$application = $event->getApplication();
		if (!$application instanceof Application) {
			return $message;
		}

		try {
			return (string) $application->getServiceManager()->get('translator')->translate($message);
		} catch (\Throwable) {
			return $message;
		}
	}

	private function displayExceptions(MvcEvent $event): bool
	{
		$application = $event->getApplication();
		if (!$application instanceof Application) {
			return false;
		}

		$config = $application->getServiceManager()->get('config');

		return !empty($config['view_manager']['display_exceptions']);
	}

	private function requestWantsJson($request): bool
	{
		if (!$request instanceof Request) {
			return false;
		}

		if ($request->isXmlHttpRequest()) {
			return true;
		}

		$accept = $request->getHeaders()->get('Accept');
		if ($accept) {
			foreach ($accept->getPrioritized() as $mediaType) {
				if (stripos($mediaType->getTypeString(), 'application/json') === 0) {
					return true;
				}
			}
		}

		return false;
	}

	public function preSetter(MvcEvent $e)
	{

		/** @var \Laminas\Http\Request $request */
		$request = $e->getRequest();

		// A request that matched no route has no controller to authorise. It is
		// on its way to the 404 handler, and reading the route match here would
		// fatal before it got there.
		$routeMatch = $e->getRouteMatch();
		if ($routeMatch === null) {
			return;
		}

		$session = new Container('credo');
		$shortControllerName = explode('Controller', $routeMatch->getParam('controller'));
		$shortControllerName = substr($shortControllerName[1], 1);


		/**
		 * @var \Laminas\Mvc\Application $application
		 */
		$application = $e->getApplication();
		$diContainer = $application->getServiceManager();
		$viewModel = $application->getMvcEvent()->getViewModel();

		// Get the ACL service from the DI container
		$acl = $diContainer->get('AppAcl');

		// Store the ACL in the session and view model
		$viewModel->acl = $acl;
		$session->acl = serialize($acl);

		$controllerName = $routeMatch->getParam('controller');
		$moduleName = $this->getModuleNameFromController($controllerName);



		// The login page is reachable before there is a session to check. So is
		// the endpoint browser errors are reported to, because an error on the
		// login page is exactly the kind nobody would otherwise hear about.
		$isPublic = in_array($controllerName, [
			Controller\LoginController::class,
			Controller\ClientErrorController::class,
		], true);

		// Authentication covers every module. It used to be spelled
		// `$moduleName == 'Application'`, which left the Eid, Covid19 and
		// DataManagement controllers answering without a session at all. It also
		// used to be skipped for every XmlHttpRequest, which meant an
		// `X-Requested-With` header was the whole of the access control on the
		// grid, chart and dropdown endpoints. Those endpoints read sample and
		// patient data.
		//
		// The Api module keeps its own credential. v2 authenticates a bearer
		// token in its own middleware, and v1 is legacy. Gating either here
		// would reject instances mid-sync.
		$needsSession = !$isPublic && $moduleName !== 'Api';

		if ($needsSession && empty($session->userId)) {
			/** @var \Laminas\Http\PhpEnvironment\Response $response */
			$response = $e->getResponse();

			// A redirect answers an XmlHttpRequest with the login page, which
			// the caller then renders into a table or parses as JSON. Say
			// "unauthenticated" in the status line instead.
			if ($this->requestWantsJson($request)) {
				$response->setStatusCode(401);
				$response->getHeaders()->addHeaderLine('Content-Type', 'application/json');

				// `error` stays a fixed token so a caller can branch on it in
				// any locale. `message` is the half a person reads, so it goes
				// through the translator like every other user-visible string.
				$response->setContent(json_encode([
					'error' => 'not_authenticated',
					'message' => $diContainer->get('translator')->translate('Not authenticated'),
				]));
			} else {
				$url = $e->getRouter()->assemble([], ['name' => 'login']);
				$response->getHeaders()->addHeaderLine('Location', $url);
				$response->setStatusCode(302);
				$response->sendHeaders();
			}

			$stopCallBack = function ($event) use ($response) {
				$event->stopPropagation();
				return $response;
			};
			$application->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
			return $response;
		}

		// The per-action ACL check stays where it was, on non-XHR requests to
		// the Application module. Widening it needs a privilege row for every
		// action reached over XmlHttpRequest, and controllers such as
		// CommonController have no rows at all, so gating them here would break
		// the dropdowns rather than secure them. Authentication above is what
		// closes the hole. Registering those actions is the follow-up.
		if ($moduleName == 'Application' && !$isPublic && !$request->isXmlHttpRequest()) {
			// **ACL Permission Check for Controllers/Actions**:
			// Get controller and action (resource and privilege)
			$params = $routeMatch->getParams();
			$resource = $params['controller'];
			$privilege = $params['action'];
			$role = $session->roleCode;

			// The home page has no content of its own — it only redirects
			// (see IndexController::indexAction), so it stays outside the ACL
			$isHomeRedirect = $resource == Controller\IndexController::class && $privilege == 'index';

			// Check if the ACL allows access to the resource (controller/action)
			if (!$isHomeRedirect && (!$acl->hasResource($resource) || !$acl->isAllowed($role, $resource, $privilege))) {
				/** @var \Laminas\Http\PhpEnvironment\Response $response */
				$response = $e->getResponse();
				$response->setStatusCode(403);

				$errorModel = new \Laminas\View\Model\ViewModel([
					'resource' => $resource,
					'privilege' => $privilege,
				]);
				$errorModel->setTemplate('error/403');
				$response->setContent($diContainer->get('ViewRenderer')->render($errorModel));

				$stopCallBack = function ($event) use ($response) {
					$event->stopPropagation();
					return $response;
				};
				$application->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
				return $response;
			}

			if (($shortControllerName == 'Clinic' || $shortControllerName == 'Hubs') && $session->role == '2') {
				/** @var \Laminas\Http\PhpEnvironment\Response $response */
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
				$application->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
				return $response;
			} elseif (($shortControllerName == 'Laboratory' || $shortControllerName == 'Hubs') && $session->role == '3') {
				/** @var \Laminas\Http\PhpEnvironment\Response $response */
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
				$application->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
				return $response;
			} elseif (($shortControllerName == 'Laboratory' || $shortControllerName == 'Clinic') && $session->role == '4') {
				/** @var \Laminas\Http\PhpEnvironment\Response $response */
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
				$application->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
				return $response;
			}

			//clinic/lab dashboard re-direction, in-case of passing invalid url params
			if ($session->role != 1) {
				/*$mappedFacilities = (isset($session->mappedFacilities) && !empty($session->mappedFacilities)) ? $session->mappedFacilities : array();
				$mappedFacilitiesName = (isset($session->mappedFacilitiesName) && !empty($session->mappedFacilitiesName)) ? $session->mappedFacilitiesName : array();
				$mappedFacilitiesCode = (isset($session->mappedFacilitiesCode) && !empty($session->mappedFacilitiesCode)) ? $session->mappedFacilitiesCode : array();
				$lab = [];
				if (isset($_GET['lab']) && trim($_GET['lab']) != '') {
					$lab = array_values(array_filter(explode(',', $_GET['lab'])));
				}
				$redirect = false;
				if ($lab !== []) {
					$counter = count($lab);
					for ($l = 0; $l < $counter; $l++) {
						if (!in_array($lab[$l], $mappedFacilities) && !in_array($lab[$l], $mappedFacilitiesName) && !in_array($lab[$l], $mappedFacilitiesCode)) {
							$redirect = true;
							break;
						}
					}
				}*/

				if ($shortControllerName == 'Users' || $shortControllerName == 'Config' || $shortControllerName == 'Facility' || $shortControllerName == 'Import') {
					$redirect = true;
				}
				if ($redirect) {
					//set redirect path
					/** @var \Laminas\Http\PhpEnvironment\Response $response */
					$response = $e->getResponse();
					if ($session->role == 2) {
						$response->getHeaders()->addHeaderLine('Location', '/labs/dashboard');
					} elseif ($session->role == 3) {
						$response->getHeaders()->addHeaderLine('Location', '/clinics/dashboard');
					} elseif ($session->role == 4) {
						$response->getHeaders()->addHeaderLine('Location', '/hubs/dashboard');
					} elseif ($session->role == 5) {
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
					$application->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
					return $response;
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
		return [
			'factories' => [
				'AppCache' => new class {
				public function __invoke($diContainer)
				{
					$isDevelopment = file_exists(__DIR__ . '/../../config/development.config.php')
						|| getenv('APPLICATION_ENV') === 'development';
					// disabled in development => NullAdapter, same as the old BlackHole
					return new FileCacheUtility(getcwd() . '/data/cache/app', !$isDevelopment);
				}
					},
				'AppAcl' => new class {
			public function __invoke($diContainer)
			{
				/** @var ResourcesTable $resourcesTable */
				$resourcesTable = $diContainer->get('ResourcesTable');
				/** @var RolesTable $rolesTable */
				$rolesTable = $diContainer->get('RolesTable');
				// return new Acl($resourcesTable->fetchAllResourceMap(), $rolesTable->fecthAllActiveRoles());
				return new Acl($resourcesTable->fetchAllResourceMap(), $rolesTable->fecthAllActiveRoles(), $rolesTable->getAllPrivilegesMap(), $rolesTable->getAllPrivileges());
			}
				},
				'LogFileReader' => new class {
			public function __invoke($diContainer)
			{
				return new \App\Log\LogFileReader(\App\Log\AppLogger::logPath());
			}
				},
				'ResourcesTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new ResourcesTable($dbAdapter);
			}
				},
				'UsersTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				$commonService = $diContainer->get('CommonService');
				return new UsersTable($dbAdapter, $diContainer, $commonService);
			}
				},
				'ActivityLogTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new ActivityLogTable($dbAdapter);
			}
				},
					'UserLoginHistoryTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new UserLoginHistoryTable($dbAdapter);
			}
				},
				'OrganizationsTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new OrganizationsTable($dbAdapter);
			}
				},
				'OrganizationTypesTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new OrganizationTypesTable($dbAdapter);
			}
				},
				'CountriesTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new CountriesTable($dbAdapter);
			}
				},
				'UserOrganizationsMapTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new UserOrganizationsMapTable($dbAdapter);
			}
				},
				'SampleTable' => new class {
			public function __invoke($diContainer)
			{
				$session = new Container('credo');
				$mappedFacilities = (property_exists($session, 'mappedFacilities') && $session->mappedFacilities !== null && !empty($session->mappedFacilities)) ? $session->mappedFacilities : [];
				$sampleTable = property_exists($session, 'sampleTable') && $session->sampleTable !== null ? $session->sampleTable : null;
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				$commonService = $diContainer->get('CommonService');
				$tableObj = new SampleTable($dbAdapter, $diContainer, $mappedFacilities, $sampleTable, $commonService);

				// object key makes sure we have different caches for both current and archive
				return new CachedMethodProxy($tableObj, $diContainer->get('AppCache'), $sampleTable);
			}
				},
				'SampleTableWithoutCache' => new class {
			public function __invoke($diContainer)
			{
				$session = new Container('credo');
				$mappedFacilities = (property_exists($session, 'mappedFacilities') && $session->mappedFacilities !== null && !empty($session->mappedFacilities)) ? $session->mappedFacilities : [];
				$sampleTable = property_exists($session, 'sampleTable') && $session->sampleTable !== null ? $session->sampleTable : null;
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				$commonService = $diContainer->get('CommonService');
				return new SampleTable($dbAdapter, $diContainer, $mappedFacilities, $sampleTable, $commonService);
			}
				},
				'FacilityTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				$commonService = $diContainer->get('CommonService');
				$tableObj = new FacilityTable($dbAdapter, $commonService, $diContainer);

				return new CachedMethodProxy($tableObj, $diContainer->get('AppCache'));
			}
				},
				'FacilityTableWithoutCache' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				$commonService = $diContainer->get('CommonService');
				return new FacilityTable($dbAdapter, $commonService, $diContainer);
			}
				},
				'TempMailTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new TempMailTable($dbAdapter);
			}
				},
				'FacilityTypeTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new FacilitytypeTable($dbAdapter);
			}
				},
				'TestReasonTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new TestReasonTable($dbAdapter);
			}
				},
				'SampleStatusTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new SampleStatusTable($dbAdapter);
			}
				},
				'SampleTypeTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new SampleTypeTable($dbAdapter);
			}
				},
				'GlobalTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				$commonService = $diContainer->get('CommonService');
				return new GlobalTable($dbAdapter, $commonService, $diContainer);
			}
				},
				'ArtCodeTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new ArtCodeTable($dbAdapter);
			}
				},
				'UserFacilityMapTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new UserFacilityMapTable($dbAdapter);
			}
				},
				'LocationDetailsTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new LocationDetailsTable($dbAdapter);
			}
				},
				'RemovedSamplesTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new RemovedSamplesTable($dbAdapter);
			}
				},
				'GenerateBackupTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new GenerateBackupTable($dbAdapter);
			}
				},
				'SampleRejectionReasonTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new SampleRejectionReasonTable($dbAdapter);
			}
				},
				'EidSampleRejectionReasonTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new EidSampleRejectionReasonTable($dbAdapter);
			}
				},
				'Covid19SampleRejectionReasonsTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new Covid19SampleRejectionReasonsTable($dbAdapter);
			}
				},
				'EidSampleTypeTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new EidSampleTypeTable($dbAdapter);
			}
				},
				'Covid19SampleTypeTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new Covid19SampleTypeTable($dbAdapter);
			}
				},
				'Covid19ComorbiditiesTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new Covid19ComorbiditiesTable($dbAdapter);
			}
				},
				'Covid19SymptomsTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new Covid19SymptomsTable($dbAdapter);
			}
				},
				'Covid19TestReasonsTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new Covid19TestReasonsTable($dbAdapter);
			}
				},
				'ProvinceTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new ProvinceTable($dbAdapter);
			}
				},
				'DashApiReceiverStatsTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new DashApiReceiverStatsTable($dbAdapter);
			}
				},
				'DashTrackApiRequestsTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new DashTrackApiRequestsTable($dbAdapter);
			}
				},
				'ImportConfigMachineTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new ImportConfigMachineTable($dbAdapter);
			}
				},
				'HepatitisSampleTypeTable' => new class {
			public function __invoke($diContainer)
			{

				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new HepatitisSampleTypeTable($dbAdapter);
			}
				},
				'HepatitisSampleRejectionReasonTable' => new class {
			public function __invoke($diContainer)
			{

				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new HepatitisSampleRejectionReasonTable($dbAdapter);
			}
				},
				'HepatitisResultsTable' => new class {
			public function __invoke($diContainer)
			{

				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new HepatitisResultsTable($dbAdapter);
			}
				},
				'HepatitisRiskFactorTable' => new class {
			public function __invoke($diContainer)
			{

				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new HepatitisRiskFactorTable($dbAdapter);
			}
				},
				'HepatitisTestReasonsTable' => new class {
			public function __invoke($diContainer)
			{

				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new HepatitisTestReasonsTable($dbAdapter);
			}
				},
				'RolesTable' => new class {
			public function __invoke($diContainer)
			{
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				$commonService = $diContainer->get('CommonService');
				return new RolesTable($dbAdapter, $commonService, $diContainer);
			}
				},

				'CommonService' => new class {
			public function __invoke($diContainer)
			{
				$tempMailTable = $diContainer->get('TempMailTable');
				$cache = $diContainer->get('AppCache');
				return new CommonService($diContainer, $cache, $tempMailTable);
			}
				},
				'UserService' => new class {
			public function __invoke($diContainer)
			{
				$usersTable = $diContainer->get('UsersTable');
				return new UserService($diContainer, $usersTable);
			}
				},
				'OrganizationService' => new class {
			public function __invoke($diContainer)
			{
				return new OrganizationService($diContainer);
			}
				},
				'SampleService' => new class {
			public function __invoke($diContainer)
			{
				$sampleTable = $diContainer->get('SampleTable');
				$apiTrackerTable = $diContainer->get('DashApiReceiverStatsTable');
				$facilityTable = $diContainer->get('FacilityTable');
				$commonService = $diContainer->get('CommonService');
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new SampleService($diContainer, $sampleTable, $commonService, $apiTrackerTable, $facilityTable, $dbAdapter);
			}
				},
				'SnapShotService' => new class {
			public function __invoke($diContainer)
			{
				$commonService = $diContainer->get('CommonService');
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new SnapShotService($diContainer, $commonService, $dbAdapter);
			}
				},
				'SummaryService' => new class {
			public function __invoke($diContainer)
			{

				$sampleTable = $diContainer->get('SampleTable');
				$translator = $diContainer->get('translator');
				$dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
				return new SummaryService($sampleTable, $translator, $dbAdapter);
			}
				},
				'ConfigService' => new class {
			public function __invoke($diContainer)
			{

				return new ConfigService($diContainer);
			}
				},
				'UserLoginHistoryService' => new class {
			public function __invoke($diContainer)
			{
				return new UserLoginHistoryService($diContainer);
			}
				},
				'FacilityService' => new class {
			public function __invoke($diContainer)
			{

				return new FacilityService($diContainer);
			}
				},
				'ApiSyncHistoryService' => new class {
			public function __invoke($diContainer)
			{

				return new ApiSyncHistoryService($diContainer);
			}
				},
				'RoleService' => new class {
			public function __invoke($diContainer)
			{

				return new RoleService($diContainer);
			}
				},
				// 'translator' was registered here as well as in
				// module.config.php, both pointing at the same Laminas factory.
				// Only the module.config.php one remains, next to the
				// 'translator' config block it reads.
			],
		];
	}

	public function getControllerConfig()
	{
		return [
			'factories' => [
				Controller\LoginController::class => new class {
			public function __invoke($diContainer)
			{
				$configService = $diContainer->get('ConfigService');
				$userService = $diContainer->get('UserService');
				return new Controller\LoginController($userService, $configService);
			}
				},
				'Application\Controller\UsersController' => new class {
			public function __invoke($diContainer)
			{
				$commonService = $diContainer->get('CommonService');
				$orgService = $diContainer->get('OrganizationService');
				$userService = $diContainer->get('UserService');
				return new \Application\Controller\UsersController($userService, $commonService, $orgService);
			}
				},
				'Application\Controller\UserLoginHistoryController' => new class {
			public function __invoke($diContainer)
			{
				$userLoginHistoryService = $diContainer->get('UserLoginHistoryService');
				return new \Application\Controller\UserLoginHistoryController($userLoginHistoryService);			
			}
				},
				'Application\Controller\CronController' => new class {
			public function __invoke($diContainer)
			{
				$sampleService = $diContainer->get('SampleService');
				return new \Application\Controller\CronController($sampleService);
			}
				},
				'Application\Controller\StatusController' => new class {
			public function __invoke($diContainer)
			{
				$commonService = $diContainer->get('CommonService');
				return new \Application\Controller\StatusController($commonService);
			}
				},
				'Application\Controller\SyncStatusController' => new class {
			public function __invoke($diContainer)
			{
				$commonService = $diContainer->get('CommonService');
				return new \Application\Controller\SyncStatusController($commonService);
			}
				},
				'Application\Controller\ConfigController' => new class {
			public function __invoke($diContainer)
			{
				$configService = $diContainer->get('ConfigService');
				return new \Application\Controller\ConfigController($configService);
			}
				},
				'Application\Controller\FacilityController' => new class {
			public function __invoke($diContainer)
			{
				$facilityService = $diContainer->get('FacilityService');
				return new \Application\Controller\FacilityController($facilityService);
			}
				},
				'Application\Controller\ClientErrorController' => new class {
			public function __invoke($diContainer)
			{
				return new \Application\Controller\ClientErrorController();
			}
				},
				'Application\Controller\LogsController' => new class {
			public function __invoke($diContainer)
			{
				$logFileReader = $diContainer->get('LogFileReader');
				return new \Application\Controller\LogsController($logFileReader);
			}
				},
				'Application\Controller\ApiSyncHistoryController' => new class {
			public function __invoke($diContainer)
			{
				$apiSyncHistoryService = $diContainer->get('ApiSyncHistoryService');
				return new \Application\Controller\ApiSyncHistoryController($apiSyncHistoryService);
			}
				},
				'Application\Controller\SummaryController' => new class {
			public function __invoke($diContainer)
			{
				$sampleService = $diContainer->get('SampleService');
				$summaryService = $diContainer->get('SummaryService');
				return new \Application\Controller\SummaryController($summaryService, $sampleService);
			}
				},
				'Application\Controller\LaboratoryController' => new class {
			public function __invoke($diContainer)
			{
				$sampleService = $diContainer->get('SampleService');
				$commonService = $diContainer->get('CommonService');
				return new \Application\Controller\LaboratoryController($sampleService, $commonService);
			}
				},
				'Application\Controller\ClinicController' => new class {
			public function __invoke($diContainer)
			{
				$sampleService = $diContainer->get('SampleService');
				$configService = $diContainer->get('ConfigService');
				return new \Application\Controller\ClinicController($sampleService, $configService);
			}
				},
				'Application\Controller\CommonController' => new class {
			public function __invoke($diContainer)
			{
				$commonService = $diContainer->get('CommonService');
				$configService = $diContainer->get('ConfigService');
				return new \Application\Controller\CommonController($commonService, $configService);
			}
				},
				'Application\Controller\TimeController' => new class {
			public function __invoke($diContainer)
			{
				$sampleService = $diContainer->get('SampleService');
				$facilityService = $diContainer->get('FacilityService');
				return new \Application\Controller\TimeController($facilityService, $sampleService);
			}
				},
				'Application\Controller\OrganizationsController' => new class {
			public function __invoke($diContainer)
			{
				$organizationService = $diContainer->get('OrganizationService');
				$commonService = $diContainer->get('CommonService');
				$userService = $diContainer->get('UserService');
				return new \Application\Controller\OrganizationsController($organizationService, $commonService, $userService);
			}
				},
				'Application\Controller\SnapshotController' => new class {
			public function __invoke($diContainer)
			{
				$snapshotService = $diContainer->get('SnapShotService');
				$commonService = $diContainer->get('CommonService');
				return new \Application\Controller\SnapshotController($commonService, $snapshotService);
			}
				},
				'Application\Controller\RolesController' => new class {
			public function __invoke($diContainer)
			{
				$roleService = $diContainer->get('RoleService');
				return new \Application\Controller\RolesController($roleService);
			}
				},
			],
		];
	}

	public function getViewHelperConfig()
	{
		return [
			'invokables' => [
				'humanReadableDateFormat' => 'Application\View\Helper\HumanReadableDateFormat',
				'deployedRef' => 'Application\View\Helper\DeployedRef'
			],
			'factories' => [
				'GetLocaleData' => new class {
			public function __invoke($diContainer)
			{
				$globalTable = $diContainer->get('GlobalTable');
				return new GetLocaleData($globalTable);
			}
				},
				'GetConfigData' => new class {
			public function __invoke($diContainer)
			{
				$globalTable = $diContainer->get('GlobalTable');
				return new \Application\View\Helper\GetConfigData($globalTable);
			}
				},
				'GetActiveModules' => new class {
			public function __invoke($diContainer)
			{
				$config = $diContainer->get('Config');
				return new \Application\View\Helper\GetActiveModules($config);
			}
				},
				// laminas-i18n used to register this one. Same name and
				// signature, so the ~3300 translate() calls in the views are
				// untouched.
				'translate' => new class {
			public function __invoke($diContainer)
			{
				return new \Application\View\Helper\Translate($diContainer->get('translator'));
			}
				},
			],
		];
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
