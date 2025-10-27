<?php
/**
 * Скрипт проходит по таблице users и хеширует временные пароли.
 * Использование: php tools/hash_passwords.php
 */

require_once __DIR__ . '/../api/bootstrap.php';


$pdo = get_pdo();

$stmt = $pdo->query("SELECT id, password_hash FROM users");
$updated = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!preg_match('/^\$2y\$/', $row['password_hash'])) {
        $newHash = password_hash($row['password_hash'], PASSWORD_BCRYPT);
        $update = $pdo->prepare("UPDATE users SET password_hash = :hash, must_change_password = 1 WHERE id = :id");
        $update->execute([':hash' => $newHash, ':id' => $row['id']]);
        $updated++;
    }
}

echo "Перехешировано пользователей: {$updated}\n";
