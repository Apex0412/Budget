<?php
require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Helpers;

if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    Helpers::redirect(Helpers::asset('login.php'));
}
$title = 'Пользователи';
$csrf = Helpers::csrfToken();
ob_start();
?>
<h1 class="h3 mb-4">Управление пользователями</h1>
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Список пользователей</span>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal">Добавить</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="usersTable">
            <thead>
                <tr>
                    <th>ФИО</th>
                    <th>Должность</th>
                    <th>Подразделение</th>
                    <th>Логин</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Новый пользователь</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ФИО</label>
                            <input type="text" class="form-control" name="fio" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Логин</label>
                            <input type="text" class="form-control" name="login" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Должность</label>
                            <input type="text" class="form-control" name="position">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Подразделение</label>
                            <input type="text" class="form-control" name="department">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Роль</label>
                            <select class="form-select" name="role">
                                <option value="user">Пользователь</option>
                                <option value="viewer">Наблюдатель</option>
                                <option value="admin">Администратор</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Пароль (опционально)</label>
                            <input type="text" class="form-control" name="password" placeholder="Если оставить пустым — сгенерируется автоматически">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button class="btn btn-primary" id="saveUser">Сохранить</button>
            </div>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars(Helpers::asset('js/admin-users.js')) ?>"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layout.php';
