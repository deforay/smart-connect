<?php
return [
    'defaults' => [
        'dbsId' => 1,
        'plasmaId' => 2,
        'tat-skipdays' => 365,
        'use-current-sample-table' => false,
        'vlModule'      => true,
        'eidModule'     => true,
        'covid19Module' => true,
        'pocDashboard'     => true,
        'time-zone' => 'UTC',
        'cache-expiry' => 1440, // in minutes
    ],
    'email' => [
        'host' => '',
        'config' => [
            'port' => '',
            'username' => '',
            'password' => '',
            'ssl' => 'tls',
            'auth' => 'login',
        ],
    ],
    'password' => [
        'salt' => '',
    ],
    'api' => [
        // Shared secret a LIS presents once to POST /api/v2/enroll and receive
        // its own token. Ships in the per-country vlsm installer config, so
        // enrollment needs no human on either side. Null disables enrollment,
        // which locks out every LIS that has not already enrolled.
        'enrollment_key' => null,

        // Date after which the legacy /api/* endpoints answer 410 Gone.
        // Until then they behave exactly as before, but advertise
        // Deprecation/Sunset headers (RFC 8594). Null = headers only, never 410.
        'legacy_sunset' => null,

        // Return exception messages in /api/v2 error bodies. Never on in production.
        'debug' => false,
    ],
];
