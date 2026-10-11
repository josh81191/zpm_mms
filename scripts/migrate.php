<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

$connection = $database->connection();
$schema = $config['db']['schema'] ?? 'zpm_mms';
if (!is_string($schema) || preg_match('/^[a-z_][a-z0-9_]*$/', $schema) !== 1) {
    throw new RuntimeException('The database schema configuration is invalid.');
}
$quotedSchema = '"' . $schema . '"';

$connection->exec(sprintf('CREATE SCHEMA IF NOT EXISTS %s', $quotedSchema));

$publicMigrationTableExists = $connection
    ->query("SELECT to_regclass('public.schema_migrations') IS NOT NULL")
    ->fetchColumn();
$schemaMigrationTableExists = $connection
    ->query(sprintf("SELECT to_regclass('%s.schema_migrations') IS NOT NULL", $schema))
    ->fetchColumn();

if ($publicMigrationTableExists && !$schemaMigrationTableExists) {
    $connection->exec(sprintf('ALTER TABLE public.schema_migrations SET SCHEMA %s', $quotedSchema));
}

$connection->exec(sprintf(
    'CREATE TABLE IF NOT EXISTS %s.schema_migrations (
        filename VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
    )',
    $quotedSchema
));
$connection->exec(sprintf('SET search_path TO %s', $quotedSchema));

$files = glob($rootPath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.sql');
sort($files, SORT_STRING);

foreach ($files as $file) {
    $filename = basename($file);
    $exists = $connection->prepare('SELECT 1 FROM schema_migrations WHERE filename = :filename');
    $exists->execute(['filename' => $filename]);
    if ($exists->fetchColumn() !== false) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Could not read migration: {$filename}");
    }

    $connection->beginTransaction();
    try {
        $connection->exec($sql);
        $record = $connection->prepare('INSERT INTO schema_migrations (filename) VALUES (:filename)');
        $record->execute(['filename' => $filename]);
        $connection->commit();
        fwrite(STDOUT, "Applied {$filename}\n");
    } catch (Throwable $exception) {
        $connection->rollBack();
        throw $exception;
    }
}

fwrite(STDOUT, "Migrations are current.\n");
