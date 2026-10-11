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
    // Optional AI question planner. Only the question text and a data-free schema are sent.
    // Use a local Ollama server (no data leaves the machine) or an approved OpenAI-compatible provider.
    'ai'           => [
        'enabled'  => false,
        'base_url' => 'http://localhost:11434/v1',
        'model'    => 'llama3.1:8b',
        'api_key'  => '',
        'timeout'  => 40,
    ],
];