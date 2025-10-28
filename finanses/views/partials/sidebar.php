<?php if (!empty($_SESSION['user'])): ?>
<aside class="col-lg-2 col-md-3 bg-body border-end min-vh-100 p-0">
    <div class="list-group list-group-flush">
        <a href="<?= htmlspecialchars(Finanses\Helpers::asset('dashboard.php')) ?>" class="list-group-item list-group-item-action">Панель</a>
        <a href="<?= htmlspecialchars(Finanses\Helpers::asset('requests/list.php')) ?>" class="list-group-item list-group-item-action">Заявки</a>
        <a href="<?= htmlspecialchars(Finanses\Helpers::asset('requests/create.php')) ?>" class="list-group-item list-group-item-action">Создать заявку</a>
        <a href="<?= htmlspecialchars(Finanses\Helpers::asset('materials/index.php')) ?>" class="list-group-item list-group-item-action">Материалы</a>
        <a href="<?= htmlspecialchars(Finanses\Helpers::asset('files/list.php')) ?>" class="list-group-item list-group-item-action">Файлы</a>
        <a href="<?= htmlspecialchars(Finanses\Helpers::asset('profile/index.php')) ?>" class="list-group-item list-group-item-action">Профиль</a>
        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <a href="<?= htmlspecialchars(Finanses\Helpers::asset('admin/reports.php')) ?>" class="list-group-item list-group-item-action">Отчёты</a>
            <a href="<?= htmlspecialchars(Finanses\Helpers::asset('admin/users.php')) ?>" class="list-group-item list-group-item-action">Пользователи</a>
            <a href="<?= htmlspecialchars(Finanses\Helpers::asset('admin/logs.php')) ?>" class="list-group-item list-group-item-action">Журнал действий</a>
        <?php endif; ?>
    </div>
</aside>
<?php endif; ?>
