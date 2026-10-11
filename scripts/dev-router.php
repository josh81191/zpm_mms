<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);
$basePath = '/6_ZPM_nunna_bu/public';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$relativePath = str_starts_with($requestPath, $basePath)
    ? substr($requestPath, strlen($basePath))
    : $requestPath;
$relativePath = '/' . ltrim($relativePath ?: '/', '/');
$assetPath = realpath($rootPath . DIRECTORY_SEPARATOR . 'public' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
$publicRoot = realpath($rootPath . DIRECTORY_SEPARATOR . 'public');

if ($assetPath !== false && $publicRoot !== false && str_starts_with($assetPath, $publicRoot) && is_file($assetPath)) {
    $mimeTypes = [
        '.css' => 'text/css; charset=utf-8',
        '.js' => 'application/javascript; charset=utf-8',
        '.png' => 'image/png',
        '.jpg' => 'image/jpeg',
        '.jpeg' => 'image/jpeg',
        '.svg' => 'image/svg+xml',
        '.ico' => 'image/x-icon',
    ];
    $extension = strtolower((string) pathinfo($assetPath, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mimeTypes['.' . $extension] ?? 'application/octet-stream'));
    readfile($assetPath);
    return;
}

require $rootPath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';

