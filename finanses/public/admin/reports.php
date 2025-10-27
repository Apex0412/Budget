<?php
require_once __DIR__ . '/../../bootstrap.php';
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /finanses/public/login.php');
    exit;
}
$title = 'Отчеты';
ob_start();
?>
<h1 class="h3 mb-4">Аналитика и отчеты</h1>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Заявки по статусам</div>
            <div class="card-body">
                <canvas id="chartStatus" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Заявки по категориям</div>
            <div class="card-body">
                <canvas id="chartCategories" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Динамика по месяцам</div>
            <div class="card-body">
                <canvas id="chartMonthly" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Кто не подал заявки</div>
            <div class="card-body">
                <ul class="list-group" id="inactiveUsers"></ul>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="/finanses/public/js/admin-reports.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layout.php';
