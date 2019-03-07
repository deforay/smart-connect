<?php

return array(
    'router' => array(
        'routes' => array(
            'api-import-viral-load' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/import-viral-load[/:id]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Api\Controller\ImportViralLoad',
                    ),
                ),
            ),
            'api-source-data' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/source-data[/:id]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Api\Controller\SourceData',
                    ),
                ),
            ),
            'api-user' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/user[/:id]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Api\Controller\User',
                    ),
                ),
            ),
            'api-facility' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/facility[/:id]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Api\Controller\Facility',
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Api\Controller\ImportViralLoad' => 'Api\Controller\ImportViralLoadController',
            'Api\Controller\SourceData' => 'Api\Controller\SourceDataController',
            'Api\Controller\User' => 'Api\Controller\UserController',
            'Api\Controller\Facility' => 'Api\Controller\FacilityController',
        ),
    ),
    'view_manager' => array(
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),
);
