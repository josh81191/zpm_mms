<?php

declare(strict_types=1);

namespace Zpm;

use PDO;

final class Auth
{
    public function __construct(private Database $database)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $statement = $this->database->connection()->prepare(
            'SELECT u.id, u.email, u.full_name, u.password_hash
             FROM user_accounts u
             WHERE lower(u.email) = lower(:email)
               AND u.is_active = TRUE
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user === false || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
        ];
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));

        $this->recordLogin((int) $user['id']);
        $this->recordAudit((int) $user['id'], 'auth.login', 'user_account', (string) $user['id']);

        return true;
    }

    public function user(): ?array
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], (bool) $parameters['secure'], (bool) $parameters['httponly']);
        }

        session_destroy();
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $statement = $this->database->connection()->prepare(
            'SELECT 1
             FROM user_role_assignments ura
             INNER JOIN role_permissions rp ON rp.role_id = ura.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE ura.user_id = :user_id
               AND p.permission_key = :permission_key
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $user['id'],
            'permission_key' => $permission,
        ]);

        return $statement->fetchColumn() !== false;
    }

    private function recordLogin(int $userId): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE user_accounts
             SET last_login_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['id' => $userId]);
    }

    private function recordAudit(int $userId, string $action, string $entityType, string $entityId): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO audit_logs (actor_user_id, action_key, entity_type, entity_id, request_ip, metadata)
             VALUES (:actor_user_id, :action_key, :entity_type, :entity_id, CAST(:request_ip AS INET), CAST(:metadata AS JSONB))'
        );
        $statement->execute([
            'actor_user_id' => $userId,
            'action_key' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'request_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'metadata' => '{}',
        ]);
    }
}
