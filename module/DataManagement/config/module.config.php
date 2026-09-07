<?php
namespace DataManagement;

// The same test the Application module makes. These three module configs load
// after it and merge over it, so leaving either flag hardcoded to true here
// would re-enable exception output for the whole application.
$isDevelopment = file_exists(__DIR__ . '/../../../config/development.config.php')
    || getenv('APPLICATION_ENV') === 'development';

return array(
    'router' => array(
        'routes' => array(
            'duplicate-data' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/data-management/duplicate-data[/:action][/][:id]',
                    'defaults' => array(
                        'controller' => 'DataManagement\Controller\DuplicateData',
                        'action' => 'index'
                    ),
                ),
            ),
            'data-management-export' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/data-management/export[/:action][/][:id]',
                    'defaults' => array(
                        'controller' => 'DataManagement\Controller\Export',
                        'action' => 'index'
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'DataManagement\Controller\DuplicateData' => 'DataManagement\Controller\DuplicateDataController',
            'DataManagement\Controller\Export' => 'DataManagement\Controller\ExportController'
        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => $isDevelopment,
        'display_exceptions'       => $isDevelopment,
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    )
);