<?php
require_once __DIR__ . '/../../bootstrap.php';
if (empty($_SESSION['user'])) {
    header('Location: /finanses/public/login.php');
    exit;
}
$title = 'Файлы';
$csrf = Finanses\Helpers::csrfToken();
ob_start();
?>
<h1 class="h3 mb-4">Прикрепленные файлы</h1>
<div class="card shadow-sm">
    <div class="card-body">
        <form id="filesForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">ID заявки</label>
                    <input type="number" class="form-control" name="request_id" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Файлы</label>
                    <input type="file" class="form-control" name="files[]" multiple>
                    <div class="form-text">Допустимые форматы: PDF, JPG, PNG, XLSX. До 5 МБ</div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Загрузить</button>
                </div>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-striped mb-0" id="filesTable">
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Тип</th>
                    <th>Размер</th>
                    <th>Дата</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script src="/finanses/public/js/files.js"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layout.php';
