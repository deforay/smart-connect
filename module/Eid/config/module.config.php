<?php
namespace Eid;

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
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    )
);