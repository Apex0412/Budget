import { apiClient } from './api.js';
import { showToast, renderStatusBadge, renderJustificationPreview } from './ui.js';
import { renderTableRows } from './table.js';

let adminPage = 1;
let adminTotalPages = 1;
let adminFilters = {};

function collectFilters() {
    return {
        from: document.getElementById('adminFilterFrom').value,
        to: document.getElementById('adminFilterTo').value,
        fio: document.getElementById('adminFilterFio').value,
        department: document.getElementById('adminFilterDept').value,
        status: document.getElementById('adminFilterStatus').value,
        category: document.getElementById('adminFilterCategory').value
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
            { text: item.author_fio },
            { text: item.author_position || '' },
            { text: item.author_department || '' },
            { text: item.items_count.toString() },
            { html: renderStatusBadge(item.status) },
            { html: renderJustificationPreview(item.justification) },
            {
                html: `
                <div class="flex justify-end space-x-2">
                    <button data-action="pdf" data-id="${item.id}" class="text-emerald-600 hover:text-emerald-800">PDF</button>
                    <button data-action="status" data-id="${item.id}" class="text-brand hover:text-brand-dark">Статус</button>
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
    const response = await apiClient.post('/requests.php', '/status', { id, status }, csrfToken);
    if (response.ok) {
        showToast('Статус обновлён', 'success');
        await loadRequests(adminPage);
    } else {
        showToast(response.error || 'Ошибка изменения статуса', 'error');
    }
}

function attachStatusMenu(target, id) {
    const template = document.getElementById('statusMenuTemplate');
    const menu = template.content.cloneNode(true).firstElementChild;
    menu.classList.add('bg-white', 'border', 'border-slate-200', 'shadow', 'p-2', 'rounded');
    const wrapper = document.createElement('div');
    wrapper.className = 'absolute z-20';
    wrapper.appendChild(menu);

    menu.querySelectorAll('button').forEach((btn) => {
        btn.addEventListener('click', () => {
            updateStatus(id, btn.dataset.status);
            wrapper.remove();
        });
    });

    document.body.appendChild(wrapper);
    const rect = target.getBoundingClientRect();
    wrapper.style.left = `${rect.left}px`;
    wrapper.style.top = `${rect.bottom + window.scrollY}px`;

    const onClickOutside = (event) => {
        if (!wrapper.contains(event.target)) {
            wrapper.remove();
            document.removeEventListener('click', onClickOutside);
        }
    };
    document.addEventListener('click', onClickOutside);
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
            { text: user.fio },
            { text: user.login },
            { text: user.role === 'admin' ? 'Администратор' : 'Пользователь' },
            { text: user.is_active ? 'Активен' : 'Заблокирован' },
            {
                html: `
                <div class="flex justify-end space-x-2">
                    <button data-action="reset" data-id="${user.id}" class="text-amber-600 hover:text-amber-800">Сбросить пароль</button>
                    <button data-action="toggle" data-id="${user.id}" class="text-red-500 hover:text-red-700">${user.is_active ? 'Деактивировать' : 'Активировать'}</button>
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
    const response = await apiClient.post('/users.php', '/create', { fio, login, password, role }, csrfToken);
    if (response.ok) {
        showToast('Пользователь создан', 'success');
        await loadUsers();
    } else {
        showToast(response.error || 'Ошибка создания', 'error');
    }
}

async function toggleUser(id) {
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/users.php', '/deactivate', { id }, csrfToken);
    if (response.ok) {
        showToast('Статус пользователя обновлён', 'success');
        await loadUsers();
    } else {
        showToast(response.error || 'Ошибка обновления', 'error');
    }
}

async function resetPassword(id) {
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/users.php', '/reset_password', { id }, csrfToken);
    if (response.ok) {
        showToast(`Временный пароль: ${response.data.temp_password}`, 'info');
        await loadUsers();
    } else {
        showToast(response.error || 'Ошибка сброса пароля', 'error');
    }
}

async function loadCatalogs() {
    const response = await apiClient.get('/catalogs.php?action=list');
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить справочники', 'error');
        return;
    }
    const { categories, units } = response.data;
    const categoriesList = document.getElementById('categoriesList');
    const unitsList = document.getElementById('unitsList');
    categoriesList.innerHTML = '';
    unitsList.innerHTML = '';

    categories.forEach((category) => {
        const li = document.createElement('li');
        li.className = 'flex items-center justify-between bg-slate-100 px-3 py-2 rounded';
        li.innerHTML = `<span>${category.name}</span><button data-id="${category.id}" class="toggleCategory text-xs text-brand">${category.is_active ? 'Скрыть' : 'Показать'}</button>`;
        categoriesList.appendChild(li);
    });

    units.forEach((unit) => {
        const li = document.createElement('li');
        li.className = 'flex items-center justify-between bg-slate-100 px-3 py-2 rounded';
        li.innerHTML = `<span>${unit.name}</span><button data-id="${unit.id}" class="toggleUnit text-xs text-brand">${unit.is_active ? 'Скрыть' : 'Показать'}</button>`;
        unitsList.appendChild(li);
    });
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

async function loadSummary() {
    const response = await apiClient.get('/requests.php?action=summary');
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить сводку', 'error');
        return;
    }
    const container = document.getElementById('summaryContainer');
    container.innerHTML = '';
    response.data.forEach((item) => {
        const card = document.createElement('div');
        card.className = 'bg-slate-100 rounded p-4 shadow-inner';
        card.innerHTML = `
            <div class="text-xs uppercase text-slate-500">${item.metric}</div>
            <div class="mt-2 text-2xl font-semibold text-slate-800">${item.value}</div>
            <div class="mt-1 text-xs text-slate-500">${item.description}</div>
        `;
        container.appendChild(card);
    });
}

async function loadAuditLog() {
    const response = await apiClient.get('/requests.php?action=audit');
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить аудит', 'error');
        return;
    }
    const rows = response.data.map((entry) => ({
        id: entry.id,
        cells: [
            { text: new Date(entry.created_at).toLocaleString('ru-RU') },
            { text: entry.user_fio || 'Система' },
            { text: entry.action },
            { text: entry.entity || '-' },
            { text: entry.meta || '' }
        ]
    }));
    renderTableRows(document.getElementById('auditBody'), rows);
}

export async function initAdminPanel() {
    const me = await apiClient.get('/auth.php?action=me');
    if (!me.ok || me.data.role !== 'admin') {
        window.location.href = '../index.html';
        return;
    }
    document.getElementById('adminInfo').textContent = me.data.fio;

    document.getElementById('logoutBtn').addEventListener('click', async () => {
        const csrfToken = await apiClient.getCsrfToken();
        await apiClient.post('/auth.php', '/logout', {}, csrfToken);
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
            attachStatusMenu(button, id);
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

    document.getElementById('refreshSummaryBtn').addEventListener('click', loadSummary);

    await Promise.all([loadRequests(), loadUsers(), loadCatalogs(), loadSummary(), loadAuditLog()]);
}
