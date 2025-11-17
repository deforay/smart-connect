<?php

namespace Application;

use Application\Command\SendTempMail;
use Application\Command\SendTempMailFactory;
use Application\Command\SeedAdmin;
use Application\Command\SeedAdminFactory;

return [
    'router' => [
        'routes' => [
            'home' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /application/:controller/:action
            'application' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/dashboard',
                    'defaults' => [
                        '__NAMESPACE__' => 'Application\Controller',
                        'controller'    => 'Index',
                        'action'        => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'default' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [],
                        ],
                    ],
                ],
            ],
            'organizations' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/organizations[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\OrganizationsController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'laboratory' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/labs[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\LaboratoryController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'summary' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/summary[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\SummaryController::class,
                        'action' => 'dashboard',
                    ],
                ],
            ],
            'clinics' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/clinics[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\ClinicController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'hubs' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/hubs[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\HubsController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'login' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/login[/:action]',
                    'defaults' => [
                        'controller' => Controller\LoginController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'login-otp' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/login/otp',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'otp',
                    ],
                ],
            ],
            'users' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/users[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\UsersController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'common' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/common[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\CommonController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'config' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/config[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\ConfigController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'facility' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/facility[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\FacilityController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'times' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/times[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\TimeController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'status' => [
                'type' => 'segment',
                'options' => [
                    'route' => '/status[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\StatusController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'sync-status' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/sync-status[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\SyncStatusController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'api-sync-history' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/api-sync-history[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\ApiSyncHistoryController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'snapshot' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/snapshot[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\SnapshotController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'roles' => [
                'type'    => 'segment',
                'options' => [
                    'route'    => '/roles[/][:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\RolesController::class,
                        'action' => 'index',
                    ],
                ],
            ],
        ],
    ],
    'service_manager' => [
        'abstract_factories' => [
            'Laminas\Cache\Service\StorageCacheAbstractServiceFactory',
        ],
        'factories' => [
            'translator' => 'Laminas\Mvc\I18n\TranslatorFactory',
            SendTempMail::class => SendTempMailFactory::class,
            SeedAdmin::class => SeedAdminFactory::class,
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
            'seed-admin' => SeedAdmin::class,
        ],
    ],
];
