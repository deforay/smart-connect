<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;


use Application\Model\UsersTable;
use Application\Model\OrganizationsTable;
use Application\Model\OrganizationTypesTable;
use Application\Model\CountriesTable;
use Application\Model\RolesTable;
use Application\Model\UserOrganizationsMapTable;
use Application\Model\SourceTable;
use Application\Model\SampleTable;
use Application\Model\FacilityTable;
use Application\Model\FacilityTypeTable;
use Application\Model\SampleStatusTable;
use Application\Model\TestReasonTable;
use Application\Model\SampleTypeTable;
use Application\Model\GlobalTable;
use Application\Model\ArtCodeTable;
use Application\Model\UserFacilityMapTable;

use Application\Service\CommonService;
use Application\Service\UserService;
use Application\Service\OrganizationService;
use Application\Service\SourceService;
use Application\Service\SampleService;
use Application\Service\ConfigService;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Cache\PatternFactory;

use Zend\Session\Container;
use Zend\View\Model\ViewModel;

class Module{
    public function onBootstrap(MvcEvent $e){
        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);
        
        if (php_sapi_name() != 'cli') {
            $eventManager->attach('dispatch', array($this, 'preSetter'), 100);
            //$eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'dispatchError'), -999);
        }        
        
    }
    
    public function preSetter(MvcEvent $e) {
        $session = new Container('credo');
		$tempName=explode('Controller',$e->getRouteMatch()->getParam('controller'));
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
                $stopCallBack = function($event) use ($response) {
                                    $event->stopPropagation();
                                    return $response;
                                };
                //Attach the "break" as a listener with a high priority
                $e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
                return $response;
            }else{
				if((substr($tempName[1], 1) == 'Clinic' || substr($tempName[0], 1) == 'Hubs')  && $session->role == '2'){
					$response = $e->getResponse();
					$response->getHeaders()->addHeaderLine('Location', '/labs/dashboard');
					 $response->setStatusCode(302);
					$response->sendHeaders();
	
					// To avoid additional processing
					// we can attach a listener for Event Route with a high priority
					$stopCallBack = function($event) use ($response) {
										$event->stopPropagation();
										return $response;
									};
					//Attach the "break" as a listener with a high priority
					$e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
					return $response;
				}else if((substr($tempName[1], 1) == 'Laboratory' || substr($tempName[1], 1) == 'Hubs')  && $session->role == '3'){
					$response = $e->getResponse();
					$response->getHeaders()->addHeaderLine('Location', '/clinics/dashboard');
					$response->setStatusCode(302);
					$response->sendHeaders();
	
					// To avoid additional processing
					// we can attach a listener for Event Route with a high priority
					$stopCallBack = function($event) use ($response) {
										$event->stopPropagation();
										return $response;
									};
					//Attach the "break" as a listener with a high priority
					$e->getApplication()->getEventManager()->attach(MvcEvent::EVENT_ROUTE, $stopCallBack, -10000);
					return $response;
				}else if((substr($tempName[1], 1) == 'Laboratory' || substr($tempName[1], 1) == 'Clinic')  && $session->role == '4'){
					$response = $e->getResponse();
					$response->getHeaders()->addHeaderLine('Location', '/hubs/dashboard');
					$response->setStatusCode(302);
					$response->sendHeaders();
	
					// To avoid additional processing
					// we can attach a listener for Event Route with a high priority
					$stopCallBack = function($event) use ($response) {
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
    

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
    
    
    public function getServiceConfig() {
        return array(
            'factories' => array(
                'UsersTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new UsersTable($dbAdapter);
                    return $table;
                },
				'OrganizationsTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new OrganizationsTable($dbAdapter);
                    return $table;
                },
				'OrganizationTypesTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new OrganizationTypesTable($dbAdapter);
                    return $table;
                },
				'CountriesTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new CountriesTable($dbAdapter);
                    return $table;
                },
				'RolesTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new RolesTable($dbAdapter);
                    return $table;
                },
				'UserOrganizationsMapTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new UserOrganizationsMapTable($dbAdapter);
                    return $table;
                },
				'SourceTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new SourceTable($dbAdapter);
                    return $table;
                },
				'SampleTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $tableObj = new SampleTable($dbAdapter,$sm);
					$table = PatternFactory::factory('object', [
						'storage' => $sm->get('Cache\Persistent'),
						'object' => $tableObj
					]);   					
                    return $table;
                },
				'FacilityTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $tableObj = new FacilityTable($dbAdapter);
					$table = PatternFactory::factory('object', [
						'storage' => $sm->get('Cache\Persistent'),
						'object' => $tableObj
					]);   					
                    return $table;
                },
				'FacilityTypeTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new FacilitytypeTable($dbAdapter);
                    return $table;
                },
				'TestReasonTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new TestReasonTable($dbAdapter);
                    return $table;
                },
				'SampleStatusTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new SampleStatusTable($dbAdapter);
                    return $table;
                },
				'SampleTypeTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new SampleTypeTable($dbAdapter);
                    return $table;
                },'GlobalTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new GlobalTable($dbAdapter);
                    return $table;
                },'ArtCodeTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new ArtCodeTable($dbAdapter);
                    return $table;
                },'UserFacilityMapTable' => function($sm) {
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $table = new UserFacilityMapTable($dbAdapter);
                    return $table;
                },
				
                'CommonService' => function($sm) {
                    return new CommonService($sm);
                },
                'UserService' => function($sm) {
                    return new UserService($sm);
                },
                'OrganizationService' => function($sm) {
                    return new OrganizationService($sm);
                },
                'SourceService' => function($sm) {
                    return new SourceService($sm);
                },
                'SampleService' => function($sm) {
                    return new SampleService($sm);
                },
		'ConfigService' => function($sm) {
                    return new ConfigService($sm);
                },
            ),
	    'abstract_factories' => array(
	        'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
	    ),
        );
    }
    
    public function getViewHelperConfig(){
        return array(
           'invokables' => array(
              'humanDateFormat' => 'Application\View\Helper\HumanDateFormat',
              'GetConfigData' => 'Application\View\Helper\GetConfigData'
           ),
        );
    }	

    public function getAutoloaderConfig(){
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
}
