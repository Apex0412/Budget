<?php
require_once __DIR__ . '/../../bootstrap.php';
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /finanses/public/login.php');
    exit;
}
$title = 'Журнал действий';
ob_start();
?>
<h1 class="h3 mb-4">Журнал действий</h1>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped mb-0" id="logsTable">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Пользователь</th>
                    <th>Действие</th>
                    <th>Сущность</th>
                    <th>Данные</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script src="/finanses/public/js/admin-logs.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layout.php';
