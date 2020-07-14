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
                        'controller' => 'Eid\Controller\Summary',
                        'action' => 'dashboard'
                    ),
                ),
            ),
            'eid-summary' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/eid/summary[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Eid\Controller\Summary',
                        'action' => 'index',
                    ),
                ),
            ),
            'eid-labs' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/eid/labs[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Eid\Controller\Labs',
                        'action' => 'index',
                    ),
                ),
            ),
            'eid-clinics' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/eid/clinics[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Eid\Controller\Clinics',
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