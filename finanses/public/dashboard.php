<?php
require_once __DIR__ . '/../bootstrap.php';
if (empty($_SESSION['user'])) {
    header('Location: /finanses/public/login.php');
    exit;
}
$title = 'Панель';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3">Добро пожаловать, <?= htmlspecialchars($_SESSION['user']['fio']) ?></h1>
        <p class="text-body-secondary mb-0">Ваша роль: <?= htmlspecialchars($_SESSION['user']['role']) ?></p>
    </div>
    <a class="btn btn-primary" href="/finanses/public/requests/create.php">Создать заявку</a>
</div>
<div class="row g-3" id="kpiCards">
    <div class="col-md-3">
        <div class="card border-primary-subtle shadow-sm">
            <div class="card-body">
                <h6 class="text-body-secondary">Всего заявок</h6>
                <div class="display-6" id="kpiTotal">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning-subtle shadow-sm">
            <div class="card-body">
                <h6 class="text-body-secondary">В работе</h6>
                <div class="display-6" id="kpiInProgress">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success-subtle shadow-sm">
            <div class="card-body">
                <h6 class="text-body-secondary">Завершено</h6>
                <div class="display-6" id="kpiCompleted">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger-subtle shadow-sm">
            <div class="card-body">
                <h6 class="text-body-secondary">Отклонено</h6>
                <div class="display-6" id="kpiRejected">0</div>
            </div>
        </div>
    </div>
</div>
<div class="card mt-4 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Последние заявки</span>
        <a href="/finanses/public/requests/list.php" class="btn btn-sm btn-outline-primary">Все заявки</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="recentRequests">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Дата</th>
                    <th>Обоснование</th>
                    <th>Статус</th>
                    <th>Приоритет</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script src="/finanses/public/js/dashboard.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layout.php';
