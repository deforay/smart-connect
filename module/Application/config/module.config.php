<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

return array(
    'router' => array(
        'routes' => array(
            'home' => array(
                'type' => 'Laminas\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /application/:controller/:action
            'application' => array(
                'type'    => 'Literal',
                'options' => array(
                    'route'    => '/dashboard',
                    'defaults' => array(
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'Index',
                        'action'        => 'index',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'default' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => array(
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(),
                        ),
                    ),
                ),
            ),
            'organizations' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/organizations[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Organizations',
                        'action' => 'index',
                    ),
                ),
            ),
            'laboratory' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/labs[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Laboratory',
                        'action' => 'index',
                    ),
                ),
            ),
            'summary' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/summary[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Summary',
                        'action' => 'index',
                    ),
                ),
            ),
            'clinics' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/clinics[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Clinic',
                        'action' => 'index',
                    ),
                ),
            ),
            'hubs' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/hubs[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Hubs',
                        'action' => 'index',
                    ),
                ),
            ),
            'login' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/login[/:action]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Login',
                        'action' => 'index',
                    ),
                ),
            ),
            'users' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/users[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Users',
                        'action' => 'index',
                    ),
                ),
            ),
            'import' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/import[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Import',
                        'action' => 'index',
                    ),
                ),
            ),
            'source' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/source[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Source',
                        'action' => 'index',
                    ),
                ),
            ),
            'common' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/common[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Common',
                        'action' => 'index',
                    ),
                ),
            ),
            'config' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/config[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Config',
                        'action' => 'index',
                    ),
                ),
            ),
            'facility' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/facility[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Facility',
                        'action' => 'index',
                    ),
                ),
            ),
            'times' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/times[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Application\Controller\Time',
                        'action' => 'index',
                    ),
                ),
            ),
        ),
    ),
    'service_manager' => array(
        'abstract_factories' => array(
            'Laminas\Cache\Service\StorageCacheAbstractServiceFactory',
            'Laminas\Log\LoggerAbstractServiceFactory'
        ),
        'factories' => array(
            'translator' => 'Laminas\Mvc\Service\TranslatorServiceFactory'
        ),
    ),
    'translator' => array(
        'locale' => 'en_US',
        'translation_file_patterns' => array(
            array(
                'type' => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern' => '%s.mo'
            ),
        )
    ),
    'controllers' => array(
        'invokables' => array(
            'Application\Controller\Index' => 'Application\Controller\IndexController',
            'Application\Controller\Organizations' => 'Application\Controller\OrganizationsController',
            'Application\Controller\Users' => 'Application\Controller\UsersController',
            'Application\Controller\Login' => 'Application\Controller\LoginController',
            //'Application\Controller\Laboratory' => 'Application\Controller\LaboratoryController',
            //'Application\Controller\Clinic' => 'Application\Controller\ClinicController',
            'Application\Controller\Hubs' => 'Application\Controller\HubsController',
            'Application\Controller\Import' => 'Application\Controller\ImportController',
            'Application\Controller\Source' => 'Application\Controller\SourceController',
            'Application\Controller\Config' => 'Application\Controller\ConfigController',
            'Application\Controller\Facility' => 'Application\Controller\FacilityController',
            //'Application\Controller\Summary' => 'Application\Controller\SummaryController',
            'Application\Controller\Time' => 'Application\Controller\TimeController',
            //'Application\Controller\Cron' => 'Application\Controller\CronController'
        ),
    ),
    'controller_plugins' => array(
        'invokables' => array(
            'HasParams' => 'Application\Controller\Plugin\HasParams'
        )
    ),
    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => array(
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
    // Placeholder for console routes
    'console' => array(
        'router' => array(
            'routes' => array(
                'import-vl' => array(
                    'type' => 'simple',
                    'options' => array(
                        'route' => 'import-vl',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Cron',
                            'action' => 'import-vl',
                        ),
                    ),
                ),
                'generate-backup' => array(
                    'type' => 'simple',
                    'options' => array(
                        'route' => 'generate-backup',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Cron',
                            'action' => 'generate-backup',
                        ),
                    ),
                ),
            ),
        ),
    ),
);
