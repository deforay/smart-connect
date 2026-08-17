<?php

return array(
    'router' => array(
        'routes' => array(
            'api-health' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/health',
                    'defaults' => array(
                        'controller' => 'Api\Controller\Health',
                    ),
                ),
            ),
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
            'api-vlsm-covid19' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/vlsm-covid19[/:id]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Api\Controller\VlsmCovid19',
                    ),
                ),
            ),
            'vlsm-metadata' => array(
                'type'    => 'segment',
                'options' => array(
                    'route'    => '/api/vlsm-metadata[/:id]',
                    'constraints' => array(
                        'id'     => '[0-9]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Api\Controller\VlsmMetadata',
                    ),
                ),
            ),
            // /api/receiver/{vl,eid,covid19} removed in 1.2.0: the controllers
            // called saveVLDataFromAPI() / saveEidDataFromAPI() /
            // saveCovid19DataFromAPI(), none of which exist, so every request
            // to them was a fatal. They have no v2 equivalent.
        ),
    ),
    'view_manager' => array(
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ),
);
