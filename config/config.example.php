<?php

declare (strict_types = 1);

return [
    'app_name'     => 'ZPM Registry & GIS',
    'environment'  => 'development',
    'base_path'    => '/6_ZPM_nunna_bu/public',
    'timezone'     => 'Asia/Kolkata',
    'session_name' => 'zpm_session',
    'db'           => [
        'host'     => 'localhost',
        'port'     => 5432,
        'database' => 'postgres',
        'username' => 'postgres',
        'password' => 'replace-with-a-local-secret',
        'sslmode'  => 'prefer',
        'schema'   => 'zpm_mms',
    ],
];