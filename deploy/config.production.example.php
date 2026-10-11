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
        // Free hosted option (Groq, OpenAI-compatible): create a key at https://console.groq.com/keys
        // Requires explicit approval to send questions and aggregate counts to a third party.
        'enabled'  => false,
        'base_url' => 'https://api.groq.com/openai/v1',
        'model'    => 'llama-3.1-8b-instant',
        'api_key'  => '',
        'timeout'  => 40,
    ],
];
