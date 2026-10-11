<?php

declare (strict_types = 1);

// Copy to config/config.php on the server (never commit the real file).
// chmod 640, owned by the deploy user and readable by the web-server group.
return [
    'app_name'     => 'ZPM Member Registry',
    'environment'  => 'production',
    // '' when the site is served from the domain root (recommended; DocumentRoot = public/).
    'base_path'    => '',
    'timezone'     => 'Asia/Kolkata',
    'session_name' => 'zpm_session',
    'db'           => [
        'host'     => '127.0.0.1',
        'port'     => 5432,
        'database' => 'zpm_membership',
        'username' => 'zpm_app',
        'password' => 'CHANGE_ME',
        'sslmode'  => 'prefer',
        'schema'   => 'zpm_mms',
    ],
    'ai'           => [
        'enabled'  => false,
        'base_url' => 'http://localhost:11434/v1',
        'model'    => 'llama3.1:8b',
        'api_key'  => '',
        'timeout'  => 40,
    ],
];
