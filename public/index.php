<?php

declare(strict_types=1);

use Zpm\Application;
use Zpm\Database;

$rootPath = dirname(__DIR__);
$configPath = $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

if (!is_file($configPath)) {
    http_response_code(503);
    echo 'Application configuration is missing. Copy config/config.example.php to config/config.php and set local database credentials.';
    exit;
}

spl_autoload_register(static function (string $class) use ($rootPath): void {
    $prefix = 'Zpm\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . substr($class, strlen($prefix)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = require $configPath;

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name($config['session_name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => rtrim($config['base_path'], '/') . '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

(new Application($config, new Database($config['db']), $rootPath . DIRECTORY_SEPARATOR . 'templates'))->handle();

