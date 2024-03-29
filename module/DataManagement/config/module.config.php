<?php
namespace DataManagement;

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
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    )
);