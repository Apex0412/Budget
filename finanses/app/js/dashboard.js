import { apiClient } from './api.js';
import { showToast, renderStatusBadge, renderJustificationPreview } from './ui.js';
import { renderTableRows } from './table.js';

let currentRequestId = null;
let currentPage = 1;
let totalPages = 1;
let filters = {};
let materialLibrary = [];

const statuses = {
    draft: 'Черновик',
    submitted: 'Отправлена',
    approved: 'Согласована',
    rejected: 'Отклонена',
    in_progress: 'В работе',
    purchased: 'Закуплено'
};

function serializeItems(tableBody) {
    const rows = Array.from(tableBody.querySelectorAll('tr'));
    return rows.map((row) => {
        const item = {};
        row.querySelectorAll('input').forEach((input) => {
            if (input.name === 'qty') {
                item[input.name] = parseFloat(input.value);
            } else {
                item[input.name] = input.value.trim();
            }
        });
        return item;
    });
}

function resetForm() {
    currentRequestId = null;
    document.getElementById('requestForm').reset();
    const body = document.getElementById('itemsBody');
    body.innerHTML = '';
    addItemRow();
    document.getElementById('downloadPdfBtn').disabled = true;
}

function addItemRow(data = {}) {
    const template = document.getElementById('itemRowTemplate');
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('tr');

    row.querySelectorAll('input').forEach((input) => {
        if (Object.prototype.hasOwnProperty.call(data, input.name)) {
            input.value = data[input.name];
        }
    });

    row.querySelector('.removeItem').addEventListener('click', () => {
        row.remove();
        updateIndices();
    });

    document.getElementById('itemsBody').appendChild(row);
    updateIndices();
}

function updateIndices() {
    document.querySelectorAll('#itemsBody tr').forEach((row, index) => {
        row.querySelector('.index').textContent = index + 1;
    });
}

function populateMaterialSources() {
    const select = document.getElementById('materialQuickSelect');
    if (select) {
        select.innerHTML = '<option value="">Выберите материал…</option>';
        materialLibrary.forEach((material) => {
            const option = document.createElement('option');
            option.value = material.id;
            const meta = [material.category_name, material.unit_name].filter(Boolean).join(' · ');
            option.textContent = meta ? `${material.name} (${meta})` : material.name;
            select.appendChild(option);
        });
    }
    const nameList = document.getElementById('materialNameSuggestions');
    if (nameList) {
        nameList.innerHTML = '';
        materialLibrary.forEach((material) => {
            const option = document.createElement('option');
            option.value = material.name;
            nameList.appendChild(option);
        });
    }
    const categoryList = document.getElementById('categorySuggestions');
    if (categoryList) {
        categoryList.innerHTML = '';
        const categories = new Set(materialLibrary.map((m) => m.category_name).filter(Boolean));
        categories.forEach((category) => {
            const option = document.createElement('option');
            option.value = category;
            categoryList.appendChild(option);
        });
    }
    const unitList = document.getElementById('unitSuggestions');
    if (unitList) {
        unitList.innerHTML = '';
        const units = new Set(materialLibrary.map((m) => m.unit_name).filter(Boolean));
        units.forEach((unit) => {
            const option = document.createElement('option');
            option.value = unit;
            unitList.appendChild(option);
        });
    }
}

async function loadMaterialsDictionary() {
    const response = await apiClient.get('/materials.php?action=list_active');
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить справочник материалов', 'warning');
        materialLibrary = [];
    } else {
        materialLibrary = response.data;
    }
    populateMaterialSources();
}

function addMaterialFromCatalog() {
    const select = document.getElementById('materialQuickSelect');
    if (!select) return;
    const id = Number(select.value);
    if (!id) {
        showToast('Выберите материал из списка', 'warning');
        return;
    }
    const material = materialLibrary.find((item) => item.id === id);
    if (!material) {
        showToast('Материал не найден', 'error');
        return;
    }
    const qty = 1;
    addItemRow({
        category: material.category_name || '',
        item_name: material.name,
        unit: material.unit_name || '',
        qty,
        note: material.description || ''
    });
    select.value = '';
    showToast('Материал добавлен в таблицу', 'success');
}

async function loadRequests(page = 1) {
    currentPage = page;
    const query = new URLSearchParams({ page: currentPage, ...filters });
    const response = await apiClient.get(`/requests.php?${query.toString()}&action=list`);
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить заявки', 'error');
        return;
    }

    const { items, pagination } = response.data;
    totalPages = pagination.total_pages;

    const rows = items.map((item) => ({
        id: item.id,
        cells: [
            { html: `<span class="font-medium">${item.id}</span>` },
            { text: new Date(item.created_at).toLocaleString('ru-RU') },
            { html: renderStatusBadge(item.status) },
            { text: item.items_count.toString() },
            { html: renderJustificationPreview(item.justification) },
            {
                html: `
                <div class="flex justify-end space-x-2">
                    <button data-action="pdf" data-id="${item.id}" class="text-emerald-600 hover:text-emerald-800">PDF</button>
                    ${item.can_edit ? `<button data-action="edit" data-id="${item.id}" class="text-blue-600 hover:text-blue-800">Изменить</button>` : ''}
                </div>`
            }
        ]
    }));

    renderTableRows(document.getElementById('requestsBody'), rows);
    document.getElementById('requestsSummary').textContent = `Страница ${pagination.current_page} из ${pagination.total_pages}, всего ${pagination.total_items}`;
    document.getElementById('prevPageBtn').disabled = pagination.current_page <= 1;
    document.getElementById('nextPageBtn').disabled = pagination.current_page >= pagination.total_pages;
}

async function loadRequest(id) {
    const response = await apiClient.get(`/requests.php?action=one&id=${id}`);
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить заявку', 'error');
        return;
    }

    const { request, items } = response.data;
    currentRequestId = request.id;
    document.getElementById('justification').value = request.justification;

    const body = document.getElementById('itemsBody');
    body.innerHTML = '';
    items.forEach(addItemRow);
    if (items.length === 0) addItemRow();

    document.getElementById('downloadPdfBtn').disabled = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function submitRequest(status) {
    const form = document.getElementById('requestForm');
    if (!form.reportValidity()) {
        showToast('Проверьте корректность данных', 'error');
        return;
    }

    const justification = form.justification.value.trim();
    if (!justification) {
        showToast('Обоснование обязательно', 'error');
        return;
    }

    const items = serializeItems(document.getElementById('itemsBody'));
    if (items.length === 0) {
        showToast('Добавьте хотя бы одну позицию', 'error');
        return;
    }

    if (items.some((item) => !item.category || !item.item_name || !item.unit || !(item.qty > 0))) {
        showToast('Проверьте заполнение всех позиций и количество > 0', 'error');
        return;
    }

    const payload = { justification, items, status, id: currentRequestId };
    const csrfToken = await apiClient.getCsrfToken();
    const action = currentRequestId ? '/update' : '/create';
    const response = await apiClient.post('/requests.php', action, payload, csrfToken);
    if (response.ok) {
        showToast('Заявка сохранена', 'success');
        currentRequestId = response.data.id;
        document.getElementById('downloadPdfBtn').disabled = false;
        await loadRequests(currentPage);
    } else {
        showToast(response.error || 'Ошибка сохранения', 'error');
    }
}

async function downloadPdf(id) {
    const link = document.createElement('a');
    link.href = `../api/files.php?action=pdf&id=${id}`;
    link.target = '_blank';
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
}

async function handlePasswordChange(event) {
    event.preventDefault();
    const form = event.target;
    const newPassword = form.new_password.value;
    const confirmPassword = form.confirm_password.value;

    if (newPassword !== confirmPassword) {
        showToast('Пароли не совпадают', 'error');
        return;
    }

    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/auth.php', '/password/change', { new_password: newPassword }, csrfToken);
    if (response.ok) {
        showToast('Пароль успешно изменён', 'success');
        document.getElementById('passwordChangeSection').classList.add('hidden');
    } else {
        showToast(response.error || 'Ошибка смены пароля', 'error');
    }
}

export async function initDashboard() {
    document.getElementById('addItemBtn').addEventListener('click', () => addItemRow());
    document.getElementById('saveDraftBtn').addEventListener('click', () => submitRequest('draft'));
    document.getElementById('submitRequestBtn').addEventListener('click', () => submitRequest('submitted'));
    document.getElementById('downloadPdfBtn').addEventListener('click', () => {
        if (!currentRequestId) return;
        downloadPdf(currentRequestId);
    });

    document.getElementById('passwordChangeForm').addEventListener('submit', handlePasswordChange);

    document.getElementById('prevPageBtn').addEventListener('click', () => {
        if (currentPage > 1) loadRequests(currentPage - 1);
    });
    document.getElementById('nextPageBtn').addEventListener('click', () => {
        if (currentPage < totalPages) loadRequests(currentPage + 1);
    });

    document.getElementById('applyFiltersBtn').addEventListener('click', () => {
        filters = {
            status: document.getElementById('filterStatus').value,
            from: document.getElementById('filterFrom').value,
            to: document.getElementById('filterTo').value
        };
        loadRequests(1);
    });

    document.getElementById('requestsBody').addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        const id = button.dataset.id;
        const action = button.dataset.action;
        if (action === 'pdf') {
            downloadPdf(id);
        }
        if (action === 'edit') {
            loadRequest(id);
        }
    });

    const addMaterialFromCatalogBtn = document.getElementById('addMaterialFromCatalog');
    if (addMaterialFromCatalogBtn) {
        addMaterialFromCatalogBtn.addEventListener('click', addMaterialFromCatalog);
    }

    document.getElementById('logoutBtn').addEventListener('click', async () => {
        const csrfToken = await apiClient.getCsrfToken();
        const response = await apiClient.post('/auth.php', '/logout', {}, csrfToken);
        if (response.ok) {
            window.location.href = '../index.html';
        }
    });

    const me = await apiClient.get('/auth.php?action=me');
    if (!me.ok) {
        window.location.href = '../index.html';
        return;
    }
    const user = me.data;
    document.body.dataset.role = user.role;
    document.getElementById('userInfo').textContent = `${user.fio} (${user.department || 'подразделение не указано'})`;

    const changePasswordBtn = document.getElementById('changePasswordBtn');
    if (user.must_change_password || new URLSearchParams(window.location.search).get('change_password') === '1') {
        document.getElementById('passwordChangeSection').classList.remove('hidden');
        changePasswordBtn.classList.remove('hidden');
    } else {
        changePasswordBtn.classList.add('hidden');
    }

    changePasswordBtn.addEventListener('click', () => {
        document.getElementById('passwordChangeSection').classList.toggle('hidden');
    });

    const statusSelect = document.getElementById('filterStatus');
    Object.entries(statuses).forEach(([value, label]) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        statusSelect.appendChild(option);
    });

    await loadMaterialsDictionary();
    addItemRow();

    await loadRequests();
}
