<?php
return array(
    'caches' => array(
        //'Cache\Transient' => array(


        //'adapter' => 'redis',
        //'ttl'     => 60,
        //'plugins' => array(
        //    'exception_handler' => array(
        //        'throw_exceptions' => false,
        //    ),
        //),
        //),
        'Cache\Persistent' => array(
            'adapter' => 'filesystem',
            'ttl' => 1440,  // 1440 minutes = 1 day
            'options' => array(
                'cache_dir' => realpath(__DIR__ . '/../../data/cache/'),
                'dirPermission' => 0755,
                'filePermission' => 0666,
                'dirLevel' => 1,
            ),
            'plugins' => array('serializer'),

        ),
        'Cache\Memcached' => array( //can be called directly via SM in the name of 'memcached'
            'adapter' => array(
                'name'     => 'memcached',
                'options'  => array(
                    'ttl' => 1440,  // 1440 minutes = 1 day
                    'servers'   => array(
                        array(
                            '127.0.0.1', 11211
                        )
                    ),
                    'namespace'  => 'VLDASHBOARD',
                    'liboptions' => array(
                        'COMPRESSION' => true,
                        'binary_protocol' => true,
                        'no_block' => true,
                        'connect_timeout' => 100
                    )
                )
            ),
            'plugins' => array(
                'serializer',
                'exception_handler' => array(
                    'throw_exceptions' => false
                ),
            ),
        ),
    ),
);
