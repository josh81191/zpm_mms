<?php

declare(strict_types=1);

use Zpm\Database;

$rootPath = dirname(__DIR__);
$configPath = $rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config/config.php. Copy config/config.example.php and configure the database first.\n");
    exit(1);
}

require $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'polyfills.php';

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
$database = new Database($config['db']);

