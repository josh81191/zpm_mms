<?php

declare(strict_types=1);

namespace Zpm;

use RuntimeException;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function verify(?string $token): void
    {
        $sessionToken = $_SESSION['_csrf'] ?? '';

        if (!is_string($token) || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
            throw new RuntimeException('Your form session has expired. Please try again.');
        }
    }
}

