<?php
require_once __DIR__ . '/../../bootstrap.php';
if (empty($_SESSION['user'])) {
    header('Location: /finanses/public/login.php');
    exit;
}
$title = 'Новая заявка';
$csrf = Finanses\Helpers::csrfToken();
ob_start();
?>
<h1 class="h3 mb-4">Создание заявки</h1>
<form id="requestForm" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Основание</label>
            <input type="text" class="form-control" name="basis" value="Муниципальное задание и Правила благоустройства" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Приоритет</label>
            <select class="form-select" name="priority" required>
                <option value="normal">Обычный</option>
                <option value="urgent">Срочный</option>
                <option value="critical">Критический</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Дедлайн</label>
            <input type="date" class="form-control" name="deadline_date">
        </div>
        <div class="col-12">
            <label class="form-label">Перечень обслуживаемых объектов</label>
            <textarea class="form-control" name="service_objects" rows="2" placeholder="Каждый объект с новой строки"></textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Обоснование закупки</label>
            <textarea class="form-control" name="justification" rows="4" required></textarea>
        </div>
    </div>
    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Позиции</h2>
        <button type="button" class="btn btn-outline-primary btn-sm" id="addItem">Добавить позицию</button>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle" id="itemsTable">
            <thead class="table-light">
                <tr>
                    <th>Категория</th>
                    <th>Материал</th>
                    <th>Ед. изм.</th>
                    <th>Количество</th>
                    <th>Назначение</th>
                    <th>Особенности</th>
                    <th>Остаток</th>
                    <th>Потребность</th>
                    <th>К закупке</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success">Сохранить и отправить</button>
        <button type="button" class="btn btn-outline-secondary" id="saveDraft">Сохранить черновик</button>
        <button type="button" class="btn btn-outline-primary" id="loadTemplate">Создать по шаблону</button>
    </div>
</form>
<script src="/finanses/public/js/request-form.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layout.php';
