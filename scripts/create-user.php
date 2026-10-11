<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    $value = fgets(STDIN);
    return $value === false ? '' : trim($value);
}

$email = strtolower(prompt('Email address: '));
$fullName = prompt('Full name: ');
$password = prompt('Password (visible while typing): ');
$roleKey = prompt('Role key [system-administrator]: ');
$roleKey = $roleKey === '' ? 'system-administrator' : $roleKey;

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $fullName === '' || strlen($password) < 12) {
    fwrite(STDERR, "Provide a valid email, a full name, and a password of at least 12 characters.\n");
    exit(1);
}

$connection = $database->connection();
$connection->beginTransaction();

try {
    $role = $connection->prepare('SELECT id FROM roles WHERE role_key = :role_key');
    $role->execute(['role_key' => $roleKey]);
    $roleId = $role->fetchColumn();
    if ($roleId === false) {
        throw new RuntimeException('The requested role does not exist. Run migrations first and use a valid role key.');
    }

    $user = $connection->prepare(
        'INSERT INTO user_accounts (email, full_name, password_hash)
         VALUES (:email, :full_name, :password_hash)
         RETURNING id'
    );
    $user->execute([
        'email' => $email,
        'full_name' => $fullName,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $userId = $user->fetchColumn();

    $assignment = $connection->prepare(
        'INSERT INTO user_role_assignments (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assignment->execute(['user_id' => $userId, 'role_id' => $roleId]);

    $connection->commit();
    fwrite(STDOUT, "Created {$email} with role {$roleKey}.\n");
} catch (Throwable $exception) {
    $connection->rollBack();
    throw $exception;
}

