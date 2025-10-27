<?php
declare(strict_types=1);


function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        fail('Требуется авторизация', 401);
    }
    if (!(int)($user['is_active'] ?? 0)) {
        fail('Учетная запись заблокирована', 403);
    }
    return $user;
}

function require_admin(): array
{
    $user = require_auth();
    if (($user['role'] ?? 'user') !== 'admin') {
        fail('Недостаточно прав', 403);
    }
    return $user;
}

function csrf_check(): void
{
    $token = $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        fail('CSRF проверка не пройдена', 419);
    }
}

function ensure_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        fail('Неверный HTTP-метод', 405);
    }
}

function log_action(PDO $pdo, string $action, ?string $entity = null, ?int $entityId = null, array $meta = []): void
{
    $user = current_user();
    $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, entity, entity_id, meta, ip) VALUES (:user_id, :action, :entity, :entity_id, :meta, :ip)');
    $stmt->execute([
        ':user_id' => $user['id'] ?? null,
        ':action' => $action,
        ':entity' => $entity,
        ':entity_id' => $entityId,
        ':meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ':ip' => client_ip()
    ]);
}
