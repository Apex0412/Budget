<?php if (!empty($_SESSION['user'])): ?>
<aside class="col-lg-2 col-md-3 bg-body border-end min-vh-100 p-0">
    <div class="list-group list-group-flush">
        <a href="/finanses/public/dashboard.php" class="list-group-item list-group-item-action">Панель</a>
        <a href="/finanses/public/requests/list.php" class="list-group-item list-group-item-action">Заявки</a>
        <a href="/finanses/public/requests/create.php" class="list-group-item list-group-item-action">Создать заявку</a>
        <a href="/finanses/public/materials/index.php" class="list-group-item list-group-item-action">Материалы</a>
        <a href="/finanses/public/files/list.php" class="list-group-item list-group-item-action">Файлы</a>
        <a href="/finanses/public/profile/index.php" class="list-group-item list-group-item-action">Профиль</a>
        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <a href="/finanses/public/admin/reports.php" class="list-group-item list-group-item-action">Отчёты</a>
            <a href="/finanses/public/admin/users.php" class="list-group-item list-group-item-action">Пользователи</a>
            <a href="/finanses/public/admin/logs.php" class="list-group-item list-group-item-action">Журнал действий</a>
        <?php endif; ?>
    </div>
</aside>
<?php endif; ?>
