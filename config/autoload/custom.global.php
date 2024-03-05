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
        'time-zone' => 'Africa/Kinshasa',
        'cache-expiry' => 1440, // in minutes
    ],
    'email' => [
        'host' => 'smtp.gmail.com',
        'config' => [
            'port' => 587,
            'username' => '',
            'password' => '',
            'ssl' => 'tls',
            'auth' => 'login',
        ],
    ],
    'password' => [
        'salt' => '',
    ]
];
