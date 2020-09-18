<?php
namespace Eid;

return array(
    'router' => array(
        'routes' => array(
            /* 'covid19' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/covid19[/]',
                    'defaults' => array(
                        'controller' => 'Covid19\Controller\Summary',
                        'action' => 'dashboard'
                    ),
                ),
            ), */
            'covid19-summary' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/covid19/summary[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Covid19\Controller\Summary',
                        'action' => 'dashboard',
                    ),
                ),
            ),
            'covid19-labs' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/covid19/labs[/][:action][/:id]',
                    'defaults' => array(
                        'controller' => 'Covid19\Controller\Labs',
                        'action' => 'dashboard',
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