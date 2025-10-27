<?php
require_once __DIR__ . '/bootstrap.php';

ensure_method('GET');

if (empty($_SESSION['csrf_token'])) {
    $secret = env('CSRF_SECRET', bin2hex(random_bytes(16)));
    $_SESSION['csrf_token'] = hash_hmac('sha256', session_id() . microtime(true), $secret);
}

ok(['token' => $_SESSION['csrf_token']]);
