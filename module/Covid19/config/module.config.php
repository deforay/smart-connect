<?php
namespace Eid;

return array(
    'router' => array(
        'routes' => array(
            'eid' => array(
                'type' => 'segment',
                'options' => array(
                    'route' => '/covid19[/]',
                    'defaults' => array(
                        'controller' => 'Covid19\Controller\Index',
                        'action' => 'dashboard'
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