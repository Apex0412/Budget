<?php
declare(strict_types=1);

function load_env(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"' ");
        $_ENV[$name] = $value;
        putenv("{$name}={$value}");
    }
}

function env(string $key, $default = null)
{
    if (array_key_exists($key, $_ENV)) {
        $value = $_ENV[$key];
    } else {
        $value = getenv($key);
    }

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function json_input(): array
{
    $data = file_get_contents('php://input');
    if ($data === false || $data === '') {
        return [];
    }
    $decoded = json_decode($data, true);
    return is_array($decoded) ? $decoded : [];
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ok($data = null): void
{
    respond(['ok' => true, 'data' => $data]);
}

function fail(string $message, int $status = 400): void
{
    respond(['ok' => false, 'error' => $message], $status);
}
