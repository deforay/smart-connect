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
            'ttl'     => 86400,
            'options' => array(
                'cache_dir' => realpath(__DIR__ . '/../../data/cache/'),
                'dirPermission' => 0755,
                'filePermission' => 0666,
                'dirLevel' => 2,
            ),
            'plugins' => array('serializer'),
            
        ),
    ),
);