<?php
require_once __DIR__ . '/../../bootstrap.php';

use Finanses\Helpers;

if (empty($_SESSION['user'])) {
    Helpers::redirect(Helpers::asset('login.php'));
}
$title = 'Заявки';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3">Реестр заявок</h1>
        <p class="text-body-secondary mb-0">Управление заявками и статусами</p>
    </div>
    <a class="btn btn-primary" href="<?= htmlspecialchars(Helpers::asset('requests/create.php')) ?>">Новая заявка</a>
</div>
<div class="card shadow-sm">
    <div class="card-header">
        <form class="row g-3" id="requestFilters">
            <div class="col-md-3">
                <label class="form-label">Статус</label>
                <select name="status" class="form-select">
                    <option value="">Все</option>
                    <option value="draft">Черновик</option>
                    <option value="submitted">Отправлено</option>
                    <option value="returned">На доработке</option>
                    <option value="approved">Согласовано</option>
                    <option value="rejected">Отклонено</option>
                    <option value="in_progress">В работе</option>
                    <option value="purchased">Закуплено</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Приоритет</label>
                <select name="priority" class="form-select">
                    <option value="">Все</option>
                    <option value="normal">Обычный</option>
                    <option value="urgent">Срочный</option>
                    <option value="critical">Критический</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Подразделение</label>
                <input type="text" name="department" class="form-control" placeholder="Все подразделения">
            </div>
            <div class="col-md-3">
                <label class="form-label">Поиск</label>
                <input type="text" name="q" class="form-control" placeholder="ФИО или текст">
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="requestsTable">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Дата</th>
                    <th>Автор</th>
                    <th>Подразделение</th>
                    <th>Статус</th>
                    <th>Приоритет</th>
                    <th>Позиций</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div>Всего: <span id="requestsTotal">0</span></div>
        <nav>
            <ul class="pagination pagination-sm mb-0" id="requestsPagination"></ul>
        </nav>
    </div>
</div>
<script src="<?= htmlspecialchars(Helpers::asset('js/requests-list.js')) ?>"></script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../views/layout.php';
