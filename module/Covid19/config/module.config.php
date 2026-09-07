<?php
namespace Eid;

// The same test the Application module makes. These three module configs load
// after it and merge over it, so leaving either flag hardcoded to true here
// would re-enable exception output for the whole application.
$isDevelopment = file_exists(__DIR__ . '/../../../config/development.config.php')
    || getenv('APPLICATION_ENV') === 'development';

return array(
    'router' => array(
        'routes' => array(
            /* 'covid19' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/covid19[/]',
                    'defaults' => array(
                        'controller' => 'Covid19\Controller\SummaryController',
                        'action' => 'dashboard'
                    ),
                ),
            ), */
            'covid19-summary' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/covid19/summary[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Covid19\Controller\SummaryController',
                        'action' => 'dashboard',
                    ),
                ),
            ),
            'covid19-labs' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/covid19/labs[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Covid19\Controller\LabsController',
                        'action' => 'dashboard',
                    ),
                ),
            ),
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