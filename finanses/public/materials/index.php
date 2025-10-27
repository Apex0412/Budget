<?php
require_once __DIR__ . '/../../bootstrap.php';
if (empty($_SESSION['user'])) {
    header('Location: /finanses/public/login.php');
    exit;
}
$title = 'Материалы';
$canManage = in_array($_SESSION['user']['role'], ['admin']);
$csrf = Finanses\Helpers::csrfToken();
ob_start();
?>
<h1 class="h3 mb-4">Каталог материалов</h1>
<div class="card shadow-sm">
    <div class="card-header">
        <form class="row g-3" id="materialsFilters">
            <div class="col-md-4">
                <input type="text" class="form-control" name="q" placeholder="Поиск по названию или коду">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="category_id">
                    <option value="">Все категории</option>
                </select>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="materialsTable">
            <thead>
                <tr>
                    <th>Код</th>
                    <th>Наименование</th>
                    <th>Категория</th>
                    <th>Ед. изм.</th>
                    <th>Описание</th>
                    <?php if ($canManage): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<?php if ($canManage): ?>
<div class="mt-4">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#materialModal">Добавить материал</button>
</div>
<div class="modal fade" id="materialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Материал</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="materialForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="id" value="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Код</label>
                            <input type="text" class="form-control" name="code">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Наименование</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Категория</label>
                            <select class="form-select" name="category_id" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ед. измерения</label>
                            <select class="form-select" name="unit_id" required></select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Описание</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                <label class="form-check-label">Активен</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button class="btn btn-primary" id="saveMaterial">Сохранить</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="/finanses/public/js/materials.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layout.php';
