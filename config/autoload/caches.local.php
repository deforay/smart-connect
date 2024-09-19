<?php

$isDevelopment = file_exists(__DIR__ . '/../development.config.php') || getenv('APPLICATION_ENV') === 'development';

return [
    'caches' => [
        'Cache\Persistent' => [
            'adapter' => $isDevelopment ? 'Laminas\Cache\Storage\Adapter\BlackHole' : 'Laminas\Cache\Storage\Adapter\Filesystem',
            'options' => $isDevelopment
                ? [] // No options required for BlackHole
                : [
                    'cache_dir' => getcwd() . "/data/cache/",
                    'dir_permission' => 0755,
                    'file_permission' => 0666,
                    'dir_level' => 1,
                    'clear_stat_cache' => true,
                    'ttl' => 1440, // 1440 minutes = 1 day
                ],
            'plugins' => $isDevelopment
                ? [] // No plugins for BlackHole adapter
                : [
                    [
                        'name' => 'exception_handler',
                        'options' => [
                            'throw_exceptions' => true,
                        ],
                    ],
                    [
                        'name' => 'serializer'
                    ],
                    [
                        'name' => 'clearExpiredByFactor',
                        'options' => [
                            'clearing_factor' => 10,
                        ],
                    ],
                ],
        ],
    ],
];
