<?php

namespace Application;

use Application\Command\SendTempMail;
use Application\Command\SendTempMailFactory;
use Laminas\DevelopmentMode\Command;

return array(
    'router' => array(
        'routes' => array(
            'home' => array(
                'type' => 'Literal',
                'options' => array(
                    'route'    => '/',
                    'defaults' => array(
                        'controller' => Controller\IndexController::class,
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
                        'controller' => Controller\OrganizationsController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'laboratory' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/labs[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\LaboratoryController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'summary' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/summary[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\SummaryController::class,
                        'action' => 'dashboard',
                    ),
                ),
            ),
            'clinics' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/clinics[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\ClinicController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'hubs' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/hubs[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\HubsController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'login' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/login[/:action]',
                    'defaults' => array(
                        'controller' => Controller\LoginController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'login-otp' => array(
                'type' => 'Literal',
                'options' => array(
                    'route'    => '/login/otp',
                    'defaults' => array(
                        'controller' => Controller\IndexController::class,
                        'action'     => 'otp',
                    ),
                ),
            ),
            'users' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/users[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\UsersController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'common' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/common[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\CommonController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'config' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/config[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\ConfigController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'facility' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/facility[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\FacilityController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'times' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/times[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\TimeController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'status' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/status[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\StatusController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'sync-status' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/sync-status[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\SyncStatusController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'api-sync-history' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api-sync-history[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\ApiSyncHistoryController::class,
                        'action' => 'index',
                    ),
                ),
            ),
            'snapshot' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/snapshot[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => Controller\SnapshotController::class,
                        'action' => 'index',
                    ),
                ),
            ),
        ),
    ),
    'service_manager' => [
        'abstract_factories' => [
            'Laminas\Cache\Service\StorageCacheAbstractServiceFactory',
            'Laminas\Log\LoggerAbstractServiceFactory'
        ],
        'factories' => [
            'translator' => 'Laminas\Mvc\I18n\TranslatorFactory',
            SendTempMail::class => SendTempMailFactory::class,
        ],
    ],
    'translator' => [
        'locale' => 'en_US',
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern' => '%s.mo'
            ],
        ],
    ],
    'controllers' => [
        'invokables' => [
            'Application\Controller\IndexController' => Controller\IndexController::class
        ]
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => [
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'laminas-cli' => [
        'commands' => [
            'send-mail' => SendTempMail::class,
        ],
    ],
);
