import { apiClient } from './api.js';
import { showToast, renderStatusBadge, renderJustificationPreview, renderPriorityBadge } from './ui.js';
import { renderTableRows } from './table.js';

let adminPage = 1;
let adminTotalPages = 1;
let adminFilters = {};
let catalogData = { categories: [], units: [] };
let materialsCache = [];
let activeStatusRequestId = null;
let charts = {};
let statsLoaded = false;

const statuses = {
    draft: 'Черновик',
    submitted: 'Отправлена',
    returned: 'На доработке',
    approved: 'Согласована',
    rejected: 'Отклонена',
    in_progress: 'В работе',
    purchased: 'Закуплено'
};

const statusOptions = [
    { value: 'submitted', label: 'Отправлена', description: 'Заявка в очереди на обработку.' },
    { value: 'returned', label: 'На доработке', description: 'Вернуть инициатору для корректировок.' },
    { value: 'approved', label: 'Согласована', description: 'Подтвердить закупку и приступить к выполнению.' },
    { value: 'rejected', label: 'Отклонена', description: 'Закупка не требуется или невозможна.' },
    { value: 'in_progress', label: 'В работе', description: 'Закупочный отдел ведёт исполнение.' },
    { value: 'purchased', label: 'Закуплено', description: 'Закупка завершена и поставлена.' }
];

function collectFilters() {
    return {
        from: document.getElementById('adminFilterFrom').value,
        to: document.getElementById('adminFilterTo').value,
        fio: document.getElementById('adminFilterFio').value,
        department: document.getElementById('adminFilterDept').value,
        status: document.getElementById('adminFilterStatus').value,
        category: document.getElementById('adminFilterCategory').value,
        priority: document.getElementById('adminFilterPriority').value
    };
}

async function loadRequests(page = 1) {
    adminFilters = collectFilters();
    adminPage = page;
    const query = new URLSearchParams({ page: adminPage, role: 'admin', ...adminFilters });
    const response = await apiClient.get(`/requests.php?action=list&${query.toString()}`);
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить заявки', 'error');
        return;
    }

    const { items, pagination } = response.data;
    adminTotalPages = pagination.total_pages;

    const rows = items.map((item) => ({
        id: item.id,
        cells: [
            { html: `<span class="font-medium">${item.id}</span>` },
            { text: new Date(item.created_at).toLocaleString('ru-RU') },
            { html: `<div class="space-y-1"><div class="font-medium text-slate-700">${item.author_fio}</div><div class="text-xs text-slate-400">${item.author_position || ''}</div></div>` },
            { text: item.author_department || '—' },
            { html: renderPriorityBadge(item.priority) },
            { html: `<div class="space-y-1">${renderStatusBadge(item.status)}<div class="text-xs text-slate-400">${new Date(item.updated_at || item.created_at).toLocaleDateString('ru-RU')}</div></div>` },
            { text: item.items_count.toString() },
            { text: item.attachments_count.toString() },
            { html: renderJustificationPreview(item.justification) },
            {
                html: `
                <div class="flex justify-end space-x-2 text-sm">
                    <button data-action="pdf" data-id="${item.id}" class="text-emerald-600 hover:text-emerald-800">PDF</button>
                    <button data-action="status" data-id="${item.id}" data-status="${item.status}" class="text-blue-600 hover:text-blue-800">Изменить статус</button>
                </div>`
            }
        ]
    }));

    renderTableRows(document.getElementById('adminRequestsBody'), rows);
    document.getElementById('adminRequestsSummary').textContent = `Страница ${pagination.current_page} из ${pagination.total_pages}, всего ${pagination.total_items}`;
    document.getElementById('adminPrevPage').disabled = pagination.current_page <= 1;
    document.getElementById('adminNextPage').disabled = pagination.current_page >= pagination.total_pages;
}

async function updateStatus(id, status) {
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/requests.php', 'status', { id, status }, csrfToken);
    if (response.ok) {
        showToast('Статус обновлён', 'success');
        await loadRequests(adminPage);
        closeStatusDialog();
    } else {
        showToast(response.error || 'Ошибка изменения статуса', 'error');
    }
}

function renderStatusChoices(container, current) {
    container.innerHTML = '';
    statusOptions.forEach((option) => {
        const wrapper = document.createElement('label');
        wrapper.className = 'flex items-start space-x-3 rounded-lg border border-slate-200 px-4 py-3 hover:border-emerald-400 transition-colors cursor-pointer bg-white shadow-sm';
        wrapper.innerHTML = `
            <input type="radio" name="statusOption" value="${option.value}" class="mt-1" ${option.value === current ? 'checked' : ''}>
            <div>
                <div class="font-semibold text-slate-700">${option.label}</div>
                <div class="text-sm text-slate-500">${option.description}</div>
            </div>
        `;
        container.appendChild(wrapper);
    });
}

function openStatusDialog(id, currentStatus) {
    activeStatusRequestId = id;
    const dialog = document.getElementById('statusDialog');
    const optionsContainer = document.getElementById('statusOptions');
    renderStatusChoices(optionsContainer, currentStatus);
    dialog.classList.remove('hidden');
    requestAnimationFrame(() => dialog.classList.remove('opacity-0'));
}

function closeStatusDialog() {
    const dialog = document.getElementById('statusDialog');
    dialog.classList.add('opacity-0');
    setTimeout(() => dialog.classList.add('hidden'), 150);
    activeStatusRequestId = null;
}

async function exportFile(type) {
    const query = new URLSearchParams(adminFilters).toString();
    window.open(`../api/files.php?action=export_${type}&${query}`, '_blank');
}

async function loadUsers() {
    const response = await apiClient.get('/users.php?action=list');
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить пользователей', 'error');
        return;
    }

    const rows = response.data.map((user) => ({
        id: user.id,
        cells: [
            { html: `<div class="space-y-1"><div class="font-medium text-slate-700">${user.fio}</div><div class="text-xs text-slate-400">${user.department || ''}</div></div>` },
            { text: user.login },
            { text: user.role === 'admin' ? 'Администратор' : 'Пользователь' },
            { text: user.is_active ? 'Активен' : 'Заблокирован' },
            {
                html: `
                <div class="flex justify-end space-x-2 text-sm">
                    <button data-action="reset" data-id="${user.id}" class="text-amber-600 hover:text-amber-800">Сбросить пароль</button>
                    <button data-action="toggle" data-id="${user.id}" class="text-blue-600 hover:text-blue-800">${user.is_active ? 'Деактивировать' : 'Активировать'}</button>
                    <button data-action="delete" data-id="${user.id}" class="text-red-500 hover:text-red-700">Удалить</button>
                </div>`
            }
        ]
    }));

    renderTableRows(document.getElementById('usersBody'), rows);
}

async function createUser() {
    const fio = prompt('ФИО пользователя');
    if (!fio) return;
    const login = prompt('Логин');
    if (!login) return;
    const password = prompt('Временный пароль (минимум 8 символов)');
    if (!password || password.length < 8) {
        showToast('Пароль слишком короткий', 'error');
        return;
    }
    const role = confirm('Сделать пользователя администратором?') ? 'admin' : 'user';

    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/users.php', 'create', { fio, login, password, role }, csrfToken);
    if (response.ok) {
        showToast('Пользователь создан', 'success');
        await loadUsers();
    } else {
        showToast(response.error || 'Ошибка создания', 'error');
    }
}

async function toggleUser(id) {
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/users.php', 'deactivate', { id }, csrfToken);
    if (response.ok) {
        showToast('Статус пользователя обновлён', 'success');
        await loadUsers();
        await loadRequests(adminPage);
    } else {
        showToast(response.error || 'Ошибка обновления', 'error');
    }
}

async function resetPassword(id) {
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/users.php', 'reset_password', { id }, csrfToken);
    if (response.ok) {
        showToast(`Временный пароль: ${response.data.temp_password}`, 'info');
        await loadUsers();
    } else {
        showToast(response.error || 'Ошибка сброса пароля', 'error');
    }
}

async function deleteUser(id) {
    if (!confirm('Удалить пользователя и связанные заявки?')) {
        return;
    }
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/users.php', 'delete', { id }, csrfToken);
    if (response.ok) {
        showToast('Пользователь удалён', 'success');
        await loadUsers();
        await loadRequests(adminPage);
    } else {
        showToast(response.error || 'Ошибка удаления', 'error');
    }
}

async function loadCatalogs() {
    const response = await apiClient.get('/catalogs.php?action=list');
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить справочники', 'error');
        return;
    }
    const { categories, units } = response.data;
    catalogData = { categories, units };
    const categoriesList = document.getElementById('categoriesList');
    const unitsList = document.getElementById('unitsList');
    categoriesList.innerHTML = '';
    unitsList.innerHTML = '';

    categories.forEach((category) => {
        const li = document.createElement('li');
        li.className = 'flex items-center justify-between bg-white px-3 py-2 rounded border border-slate-200';
        li.innerHTML = `<span>${category.name}</span><button data-id="${category.id}" class="toggleCategory text-xs text-blue-600 hover:text-blue-800">${category.is_active ? 'Скрыть' : 'Показать'}</button>`;
        categoriesList.appendChild(li);
    });

    units.forEach((unit) => {
        const li = document.createElement('li');
        li.className = 'flex items-center justify-between bg-white px-3 py-2 rounded border border-slate-200';
        li.innerHTML = `<span>${unit.name}</span><button data-id="${unit.id}" class="toggleUnit text-xs text-blue-600 hover:text-blue-800">${unit.is_active ? 'Скрыть' : 'Показать'}</button>`;
        unitsList.appendChild(li);
    });
    updateMaterialFormOptions();
}

async function addCatalogItem(type) {
    const name = prompt(`Введите название ${type === 'category' ? 'категории' : 'единицы измерения'}`);
    if (!name) return;

    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/catalogs.php', `/create_${type}`, { name }, csrfToken);
    if (response.ok) {
        showToast('Элемент добавлен', 'success');
        await loadCatalogs();
    } else {
        showToast(response.error || 'Ошибка сохранения', 'error');
    }
}

async function toggleCatalogItem(type, id) {
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/catalogs.php', `/toggle_${type}`, { id }, csrfToken);
    if (response.ok) {
        showToast('Статус обновлён', 'success');
        await loadCatalogs();
    } else {
        showToast(response.error || 'Ошибка обновления', 'error');
    }
}

function updateMaterialFormOptions() {
    const categorySelect = document.getElementById('materialCategory');
    const unitSelect = document.getElementById('materialUnit');
    if (!categorySelect || !unitSelect) return;
    categorySelect.innerHTML = '<option value="">Без категории</option>';
    unitSelect.innerHTML = '<option value="">Без единицы</option>';
    catalogData.categories.forEach((category) => {
        const option = document.createElement('option');
        option.value = category.id;
        option.textContent = category.name;
        categorySelect.appendChild(option);
    });
    catalogData.units.forEach((unit) => {
        const option = document.createElement('option');
        option.value = unit.id;
        option.textContent = unit.name;
        unitSelect.appendChild(option);
    });
}

async function loadMaterials() {
    const response = await apiClient.get('/materials.php?action=list');
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить материалы', 'error');
        return;
    }
    materialsCache = response.data;
    const rows = materialsCache.map((material) => ({
        id: material.id,
        cells: [
            { text: material.name },
            { text: material.category_name || '—' },
            { text: material.unit_name || '—' },
            { text: material.is_active ? 'Активен' : 'Скрыт' },
            { text: material.description || '' },
            {
                html: `
                <div class="flex justify-end space-x-2 text-sm">
                    <button data-action="toggle" data-id="${material.id}" class="text-blue-600 hover:text-blue-800">${material.is_active ? 'Скрыть' : 'Показать'}</button>
                    <button data-action="delete" data-id="${material.id}" class="text-red-500 hover:text-red-700">Удалить</button>
                </div>`
            }
        ]
    }));
    renderTableRows(document.getElementById('materialsBody'), rows);
}

async function createMaterial(event) {
    event.preventDefault();
    const form = event.target;
    const name = form.materialName.value.trim();
    if (!name) {
        showToast('Укажите наименование материала', 'error');
        return;
    }
    const payload = {
        name,
        category_id: form.materialCategory.value ? Number(form.materialCategory.value) : null,
        unit_id: form.materialUnit.value ? Number(form.materialUnit.value) : null,
        description: form.materialDescription.value.trim()
    };
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/materials.php', 'create', payload, csrfToken);
    if (response.ok) {
        showToast('Материал добавлен', 'success');
        form.reset();
        updateMaterialFormOptions();
        await loadMaterials();
    } else {
        showToast(response.error || 'Ошибка добавления материала', 'error');
    }
}

async function toggleMaterial(id) {
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/materials.php', 'toggle', { id }, csrfToken);
    if (response.ok) {
        showToast('Статус материала обновлён', 'success');
        await loadMaterials();
    } else {
        showToast(response.error || 'Не удалось обновить статус', 'error');
    }
}

async function deleteMaterial(id) {
    if (!confirm('Удалить материал?')) {
        return;
    }
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/materials.php', 'delete', { id }, csrfToken);
    if (response.ok) {
        showToast('Материал удалён', 'success');
        await loadMaterials();
    } else {
        showToast(response.error || 'Ошибка удаления материала', 'error');
    }
}

async function importMaterials(event) {
    event.preventDefault();
    const form = event.target;
    const fileInput = form.querySelector('input[type="file"]');
    if (!fileInput || fileInput.files.length === 0) {
        showToast('Выберите CSV-файл', 'error');
        return;
    }
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.upload('/materials.php', 'import', formData, csrfToken);
    if (response.ok) {
        showToast(`Импортировано: ${response.data.inserted}, обновлено: ${response.data.updated}`, 'success');
        fileInput.value = '';
        await Promise.all([loadCatalogs(), loadMaterials()]);
    } else {
        showToast(response.error || 'Ошибка импорта', 'error');
    }
}

function exportMaterials() {
    window.open('../api/materials.php?action=export', '_blank');
}

async function loadSummary() {
    const response = await apiClient.get('/requests.php?action=summary');
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить сводку', 'error');
        return;
    }
    const container = document.getElementById('summaryContainer');
    if (!container) return;
    container.innerHTML = '';
    response.data.forEach((item) => {
        const card = document.createElement('div');
        card.className = 'bg-white rounded-xl p-4 shadow-inner border border-slate-200';
        card.innerHTML = `
            <div class="text-xs uppercase text-slate-400">${item.metric}</div>
            <div class="mt-2 text-2xl font-semibold text-slate-800">${item.value}</div>
            <div class="mt-1 text-xs text-slate-500">${item.description}</div>
        `;
        container.appendChild(card);
    });
}

function destroyChart(id) {
    if (charts[id]) {
        charts[id].destroy();
        delete charts[id];
    }
}

function renderChart(id, type, labels, data, colors) {
    const canvas = document.getElementById(id);
    if (!canvas || typeof Chart === 'undefined') return;
    destroyChart(id);
    charts[id] = new Chart(canvas, {
        type,
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: colors,
                borderWidth: 0
            }]
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

async function loadStatistics() {
    const response = await apiClient.get('/statistics.php');
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить статистику', 'error');
        return;
    }
    const data = response.data;
    const palette = ['#10b981', '#0ea5e9', '#6366f1', '#f97316', '#f43f5e', '#8b5cf6', '#14b8a6', '#facc15'];

    const statusLabels = data.status.map((s) => statuses[s.status] || s.status);
    const priorityLabels = data.priority.map((s) => ({ normal: 'Обычный', urgent: 'Срочный', critical: 'Критический' }[s.priority] || s.priority));
    renderChart('chartStatus', 'doughnut', statusLabels, data.status.map((s) => Number(s.cnt)), palette);
    renderChart('chartPriority', 'pie', priorityLabels, data.priority.map((s) => Number(s.cnt)), ['#94a3b8', '#f59e0b', '#ef4444']);
    renderChart('chartCategory', 'bar', data.category.map((s) => s.category), data.category.map((s) => Number(s.cnt)), palette);
    renderChart('chartDepartment', 'bar', data.department.map((s) => s.department), data.department.map((s) => Number(s.cnt)), palette);

    const monthlyLabels = data.monthly.map((item) => item.ym);
    const monthlyValues = data.monthly.map((item) => Number(item.cnt));
    const canvas = document.getElementById('chartMonthly');
    if (canvas && typeof Chart !== 'undefined') {
        destroyChart('chartMonthly');
        charts.chartMonthly = new Chart(canvas, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Заявок',
                    data: monthlyValues,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,0.2)',
                    tension: 0.35,
                    fill: true
                }]
            },
            options: {
                plugins: {
                    legend: { display: false }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    statsLoaded = true;
}

async function loadAuditLog() {
    const response = await apiClient.get('/logs.php?page=1&per_page=150');
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить аудит', 'error');
        return;
    }
    const rows = response.data.items.map((entry) => ({
        id: entry.id,
        cells: [
            { text: new Date(entry.created_at).toLocaleString('ru-RU') },
            { text: entry.user_fio || 'Система' },
            { text: entry.action },
            { text: entry.entity || '-' },
            { text: entry.meta ? JSON.stringify(entry.meta, null, 0) : '' },
            { text: entry.ip || '' }
        ]
    }));
    renderTableRows(document.getElementById('auditBody'), rows);
}

function initTabs() {
    const buttons = document.querySelectorAll('.tab-btn');
    const sections = document.querySelectorAll('[data-tab-content]');
    buttons.forEach((btn) => {
        btn.addEventListener('click', async () => {
            const target = btn.getAttribute('data-tab');
            buttons.forEach((b) => b.classList.remove('bg-emerald-100', 'text-emerald-700', 'shadow-inner'));
            btn.classList.add('bg-emerald-100', 'text-emerald-700', 'shadow-inner');
            sections.forEach((section) => {
                section.classList.toggle('hidden', section.getAttribute('data-tab-content') !== target);
            });
            if (target === 'reports' && !statsLoaded) {
                await Promise.all([loadSummary(), loadStatistics()]);
            }
            if (target === 'audit') {
                await loadAuditLog();
            }
        });
    });
}

export async function initAdminPanel() {
    const me = await apiClient.get('/auth.php?action=me');
    if (!me.ok || me.data.role !== 'admin') {
        window.location.href = '../index.html';
        return;
    }
    document.getElementById('adminInfo').textContent = me.data.fio;

    initTabs();

    document.getElementById('logoutBtn').addEventListener('click', async () => {
        const csrfToken = await apiClient.getCsrfToken();
        await apiClient.post('/auth.php', 'logout', {}, csrfToken);
        window.location.href = '../index.html';
    });

    document.getElementById('adminApplyFilters').addEventListener('click', () => loadRequests(1));
    document.getElementById('adminPrevPage').addEventListener('click', () => {
        if (adminPage > 1) loadRequests(adminPage - 1);
    });
    document.getElementById('adminNextPage').addEventListener('click', () => {
        if (adminPage < adminTotalPages) loadRequests(adminPage + 1);
    });

    document.getElementById('adminRequestsBody').addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        const id = button.dataset.id;
        if (button.dataset.action === 'pdf') {
            window.open(`../api/files.php?action=pdf&id=${id}`, '_blank');
        }
        if (button.dataset.action === 'status') {
            openStatusDialog(Number(id), button.dataset.status || 'submitted');
        }
    });

    document.getElementById('exportCsvBtn').addEventListener('click', () => exportFile('csv'));
    document.getElementById('exportXlsxBtn').addEventListener('click', () => exportFile('xlsx'));

    document.getElementById('createUserBtn').addEventListener('click', createUser);
    document.getElementById('usersBody').addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        const id = button.dataset.id;
        if (button.dataset.action === 'toggle') {
            toggleUser(id);
        }
        if (button.dataset.action === 'reset') {
            resetPassword(id);
        }
        if (button.dataset.action === 'delete') {
            deleteUser(id);
        }
    });

    document.getElementById('addCategoryBtn').addEventListener('click', () => addCatalogItem('category'));
    document.getElementById('addUnitBtn').addEventListener('click', () => addCatalogItem('unit'));

    document.getElementById('categoriesList').addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        toggleCatalogItem('category', button.dataset.id);
    });
    document.getElementById('unitsList').addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        toggleCatalogItem('unit', button.dataset.id);
    });

    const materialForm = document.getElementById('materialForm');
    if (materialForm) {
        materialForm.addEventListener('submit', createMaterial);
    }
    const materialsBody = document.getElementById('materialsBody');
    if (materialsBody) {
        materialsBody.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) return;
            const id = button.dataset.id;
            if (button.dataset.action === 'toggle') {
                toggleMaterial(id);
            }
            if (button.dataset.action === 'delete') {
                deleteMaterial(id);
            }
        });
    }
    const materialImportForm = document.getElementById('materialImportForm');
    if (materialImportForm) {
        materialImportForm.addEventListener('submit', importMaterials);
    }
    const materialsExportBtn = document.getElementById('materialsExportBtn');
    if (materialsExportBtn) {
        materialsExportBtn.addEventListener('click', exportMaterials);
    }

    const statusForm = document.getElementById('statusDialogForm');
    if (statusForm) {
        statusForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!activeStatusRequestId) {
                closeStatusDialog();
                return;
            }
            const formData = new FormData(statusForm);
            const selected = formData.get('statusOption');
            if (!selected) {
                showToast('Выберите статус', 'warning');
                return;
            }
            const submitBtn = statusForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Сохраняем…';
            }
            try {
                await updateStatus(activeStatusRequestId, selected);
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Сохранить';
                }
            }
        });
    }

    document.querySelectorAll('[data-status-cancel]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            closeStatusDialog();
        });
    });

    const statusBackdrop = document.getElementById('statusDialog');
    if (statusBackdrop) {
        statusBackdrop.addEventListener('click', (event) => {
            if (event.target === statusBackdrop) {
                closeStatusDialog();
            }
        });
    }

    await Promise.all([loadRequests(), loadUsers(), loadCatalogs(), loadMaterials()]);
    await loadSummary();
}
