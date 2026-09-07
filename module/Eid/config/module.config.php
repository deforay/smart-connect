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
            'eid' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/eid[/]',
                    'defaults' => array(
                        'controller' => 'Eid\Controller\SummaryController',
                        'action' => 'dashboard'
                    ),
                ),
            ),
            'eid-summary' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/eid/summary[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Eid\Controller\SummaryController',
                        'action' => 'dashboard',
                    ),
                ),
            ),
            'eid-labs' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/eid/labs[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Eid\Controller\LabsController',
                        'action' => 'index',
                    ),
                ),
            ),
            'eid-clinics' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/eid/clinics[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Eid\Controller\ClinicsController',
                        'action' => 'index',
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