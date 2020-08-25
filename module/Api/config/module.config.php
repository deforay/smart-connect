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
            
            'api-vlsm' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/vlsm[/:id]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Api\Controller\Vlsm',
                    ),
                ),
            ),
            'weblims-vl' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/weblims-vl[/:id]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Api\Controller\WeblimsVL',
                    ),
                ),
            ),
            'api-vlsm-eid' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/vlsm-eid[/:id]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Api\Controller\VlsmEid',
                    ),
                ),
            ),
            'api-vlsm-covid' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/vlsm-covid[/:id]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Api\Controller\VlsmCovid',
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Api\Controller\ImportViralLoad'    => 'Api\Controller\ImportViralLoadController',
            'Api\Controller\SourceData'         => 'Api\Controller\SourceDataController',
            'Api\Controller\User'               => 'Api\Controller\UserController',
            'Api\Controller\Facility'           => 'Api\Controller\FacilityController',
            'Api\Controller\WeblimsVL'          => 'Api\Controller\WeblimsVLController',
            'Api\Controller\VlsmEid'            => 'Api\Controller\VlsmEidController',
            'Api\Controller\VlsmCovid'          => 'Api\Controller\VlsmCovidController',
        ),
    ),
    'view_manager' => array(
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),
);
