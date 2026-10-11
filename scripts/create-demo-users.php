<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (($config['environment'] ?? null) !== 'development') {
    fwrite(STDERR, "Demo users can only be created in the development environment.\n");
    exit(1);
}

$demoUsers = [
    [
        'email' => 'admin@zpm.test',
        'full_name' => 'Demo System Administrator',
        'password' => 'ZpmDemo!2026Admin',
        'role_key' => 'system-administrator',
    ],
    [
        'email' => 'central.office@zpm.test',
        'full_name' => 'Demo Central Office User',
        'password' => 'ZpmDemo!2026Central',
        'role_key' => 'central-office',
    ],
    [
        'email' => 'data.entry@zpm.test',
        'full_name' => 'Demo Data Entry Operator',
        'password' => 'ZpmDemo!2026Data',
        'role_key' => 'data-entry',
    ],
    [
        'email' => 'readonly@zpm.test',
        'full_name' => 'Demo Read-Only User',
        'password' => 'ZpmDemo!2026Read',
        'role_key' => 'read-only',
    ],
];

$connection = $database->connection();
$connection->beginTransaction();

try {
    $roleLookup = $connection->prepare('SELECT id FROM roles WHERE role_key = :role_key');
    $userUpsert = $connection->prepare(
        'INSERT INTO user_accounts (email, full_name, password_hash)
         VALUES (:email, :full_name, :password_hash)
         ON CONFLICT (email) DO UPDATE
         SET full_name = EXCLUDED.full_name,
             password_hash = EXCLUDED.password_hash,
             is_active = TRUE,
             updated_at = CURRENT_TIMESTAMP
         RETURNING id'
    );
    $roleAssignment = $connection->prepare(
        'INSERT INTO user_role_assignments (user_id, role_id)
         VALUES (:user_id, :role_id)
         ON CONFLICT DO NOTHING'
    );

    foreach ($demoUsers as $demoUser) {
        $roleLookup->execute(['role_key' => $demoUser['role_key']]);
        $roleId = $roleLookup->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException("Role {$demoUser['role_key']} is missing. Run migrations first.");
        }

        $userUpsert->execute([
            'email' => $demoUser['email'],
            'full_name' => $demoUser['full_name'],
            'password_hash' => password_hash($demoUser['password'], PASSWORD_DEFAULT),
        ]);
        $userId = $userUpsert->fetchColumn();
        $roleAssignment->execute(['user_id' => $userId, 'role_id' => $roleId]);
    }

    $connection->commit();
    fwrite(STDOUT, "Created or updated four development demo users.\n");
} catch (Throwable $exception) {
    $connection->rollBack();
    throw $exception;
}

