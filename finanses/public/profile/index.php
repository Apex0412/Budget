<?php
require_once __DIR__ . '/../../bootstrap.php';
if (empty($_SESSION['user'])) {
    header('Location: /finanses/public/login.php');
    exit;
}
$title = 'Профиль';
$csrf = Finanses\Helpers::csrfToken();
ob_start();
?>
<h1 class="h3 mb-4">Настройки профиля</h1>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Смена пароля</div>
            <div class="card-body">
                <form id="passwordForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="mb-3">
                        <label class="form-label">Текущий пароль</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Новый пароль</label>
                        <input type="password" class="form-control" name="new_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Подтверждение</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Сменить пароль</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">Предпочтения</div>
            <div class="card-body">
                <form id="preferencesForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <div class="mb-3">
                        <label class="form-label">Тема</label>
                        <select class="form-select" name="theme">
                            <option value="light">Светлая</option>
                            <option value="dark">Тёмная</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Язык интерфейса</label>
                        <select class="form-select" name="locale">
                            <option value="ru">Русский</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                    <button class="btn btn-outline-primary" type="submit">Сохранить</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="/finanses/public/js/profile.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layout.php';
