<?php
require_once __DIR__ . '/../bootstrap.php';

use Finanses\Helpers;

Helpers::startSession();
$basePath = Helpers::basePath();
if (!empty($_SESSION['user'])) {
    Helpers::redirect(Helpers::asset('dashboard.php'));
}
$loginUrl = Helpers::asset('login.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FINANSES — система заявок</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-secondary">
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="#">FINANSES</a>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="<?= htmlspecialchars($loginUrl) ?>">Войти</a>
        </div>
    </div>
</nav>
<main class="py-5">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <h1 class="display-5 fw-bold">Цифровая система заявок для МБУ</h1>
                <p class="lead text-body-secondary">FINANSES помогает сотрудникам быстро оформлять заявки на закупку, согласовывать их и формировать отчётность без лишних файлов и переписки.</p>
                <div class="d-flex flex-column flex-md-row gap-3 mt-4">
                    <a class="btn btn-primary btn-lg" href="<?= htmlspecialchars($loginUrl) ?>">Перейти к авторизации</a>
                    <a class="btn btn-outline-secondary btn-lg" href="#features">Изучить возможности</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4">
                        <h5 class="card-title">Что входит в систему?</h5>
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill">1</span>
                                <span>Создание и отслеживание заявок с позициями и файлами.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill">2</span>
                                <span>Роли и права доступа: администратор, руководитель участка, пользователь.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill">3</span>
                                <span>Экспорт в PDF, CSV и XLSX, а также автоматический аудит действий.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill">4</span>
                                <span>Установка за несколько минут: Docker, Windows (XAMPP), Linux, macOS, WSL.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <section class="mt-5" id="features">
            <h2 class="h3 mb-4">Ключевые возможности</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <h3 class="h5">Умные заявки</h3>
                            <p class="text-body-secondary">Позиции с единицами измерения, автоподстановка материалов, приоритеты и дедлайны — всё в одном окне.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <h3 class="h5">Админ-панель</h3>
                            <p class="text-body-secondary">Управление пользователями, справочниками, отчётами и журналом действий без переключения между системами.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <h3 class="h5">Документы без шаблонов</h3>
                            <p class="text-body-secondary">Генерация служебных записок в формате PDF (mPDF) с учётом фирменного стиля и подписи руководителя.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>
<footer class="py-4 bg-body-tertiary border-top">
    <div class="container text-center small text-body-secondary">
        <p class="mb-1">© <?= date('Y') ?> FINANSES. Все права защищены.</p>
        <p class="mb-0">Готово к развертыванию на любом хостинге и в Docker.</p>
    </div>
</footer>
<script>
    window.APP_BASE_PATH = <?= json_encode($basePath, JSON_UNESCAPED_SLASHES) ?>;
    window.APP_API_BASE = <?= json_encode(($basePath === '' ? '' : $basePath) . '/api', JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
