<?php
return [
    'caches' => [
        'Cache\Persistent' => [
            'adapter' => 'Laminas\Cache\Storage\Adapter\Filesystem',
            'options' => [
                'cache_dir' => getcwd() . "/data/cache/",
                'dir_permission' => 0755,
                'file_permission' => 0666,
                'dir_level' => 1,
                'clear_stat_cache' => true,
                'ttl' => 1440, // 1440 minutes = 1 day
            ],
            'plugins' => [
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
                        'clearing_factor' => 1,
                    ],
                ],
            ]
        ]
    ],
];
