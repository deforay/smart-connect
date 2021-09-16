<?php
return array(
    'caches' => array(
        'Cache\Persistent' => array(
            'adapter' => 'Laminas\Cache\Storage\Adapter\Filesystem',
            'minTtl' => 1,  // 1440 minutes = 1 day
            'maxTtl' => 1,  // 1440 minutes = 1 day
            'lockOnExpire' => false,  // 1440 minutes = 1 day
            'options' => [
                'cache_dir' => getcwd() . "/data/cache/",
                'dir_permission' => 0755,
                'file_permission' => 0666,
                'dir_level' => 1,
                'clear_stat_cache' => true,
            ],
            'plugins' => [
                'ClearExpiredByFactor',
                'Serializer',
                'exception_handler' => [
                    'throw_exceptions' => true,
                ],
            ],
        )
    ),
);
