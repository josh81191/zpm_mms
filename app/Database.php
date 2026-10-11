<?php

declare(strict_types=1);

namespace Zpm;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private PDO $connection;

    public function __construct(array $config)
    {
        $schema = $config['schema'] ?? 'zpm_mms';
        if (!is_string($schema) || preg_match('/^[a-z_][a-z0-9_]*$/', $schema) !== 1) {
            throw new RuntimeException('The database schema configuration is invalid.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['sslmode']
        );

        try {
            $this->connection = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->connection->exec(sprintf('SET search_path TO "%s"', $schema));
        } catch (PDOException $exception) {
            throw new RuntimeException('The database connection could not be established.', 0, $exception);
        }
    }

    public function connection(): PDO
    {
        return $this->connection;
    }
}
