<?php
use Finanses\Helpers;
Helpers::startSession();
$user = $_SESSION['user'] ?? null;
$theme = $user['theme'] ?? 'light';
$locale = $user['locale'] ?? 'ru';
$basePath = Helpers::basePath();
$apiBase = ($basePath === '' ? '' : $basePath) . '/api';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale) ?>" data-bs-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'FINANSES') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= htmlspecialchars(Helpers::asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-body-secondary">
<?php include __DIR__ . '/partials/navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>
        <main class="col-lg-10 ms-auto px-4 py-4">
            <?php include __DIR__ . '/partials/toasts.php'; ?>
            <?= $content ?? '' ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    window.APP_BASE_PATH = <?= json_encode($basePath, JSON_UNESCAPED_SLASHES) ?>;
    window.APP_API_BASE = <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars(Helpers::asset('js/app.js')) ?>"></script>
</body>
</html>
