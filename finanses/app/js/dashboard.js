import { apiClient } from './api.js';
import { showToast, renderStatusBadge, renderJustificationPreview, renderPriorityBadge } from './ui.js';
import { renderTableRows } from './table.js';

const state = {
    currentRequestId: null,
    currentStatus: 'draft',
    attachments: [],
    pendingFiles: []
};

let currentPage = 1;
let totalPages = 1;
let filters = {};
let materialLibrary = [];
let categoryDefaults = new Map();
let latestTemplate = null;

const statuses = {
    draft: 'Черновик',
    submitted: 'Отправлена',
    returned: 'На доработке',
    approved: 'Согласована',
    rejected: 'Отклонена',
    in_progress: 'В работе',
    purchased: 'Закуплено'
};

const DEFAULT_BASIS = 'Основание: муниципальное задание и "Правила благоустройства территории муниципального образования «Городской округ Серпухов Московской области»", утверждённые решением Совета депутатов от 24.12.2024 № 25/288.';

function serializeItems(tableBody) {
    const rows = Array.from(tableBody.querySelectorAll('tr'));
    return rows.map((row) => {
        const item = {};
        row.querySelectorAll('input, textarea').forEach((field) => {
            const { name } = field;
            if (!name) return;
            if (field.type === 'number') {
                const value = field.value;
                item[name] = value !== '' ? parseFloat(value) : null;
            } else {
                item[name] = field.value.trim();
            }
        });
        return item;
    });
}

function resetForm() {
    state.currentRequestId = null;
    state.currentStatus = 'draft';
    state.attachments = [];
    state.pendingFiles = [];
    document.getElementById('requestForm').reset();
    document.getElementById('basis').value = DEFAULT_BASIS;
    document.getElementById('serviceObjects').value = '';
    document.getElementById('periodLabel').value = '';
    document.getElementById('attachmentInput').value = '';
    renderAttachmentList();
    const body = document.getElementById('itemsBody');
    body.innerHTML = '';
    addItemRow();
    document.getElementById('downloadPdfBtn').disabled = true;
}

function applyMaterialToRow(row, material) {
    if (!material) return;
    const categoryInput = row.querySelector('input[name="category"]');
    const unitInput = row.querySelector('input[name="unit"]');
    const noteInput = row.querySelector('input[name="note"]');
    const purposeArea = row.querySelector('textarea[name="purpose"]');
    const featuresArea = row.querySelector('textarea[name="features"]');
    if (material.category_name) {
        categoryInput.value = material.category_name;
    }
    if (material.unit_name && unitInput.dataset.autofill !== 'manual') {
        unitInput.value = material.unit_name;
        unitInput.dataset.autofill = 'material';
    }
    if (material.description) {
        if (featuresArea && !featuresArea.value) {
            featuresArea.value = material.description;
        }
        if (noteInput && !noteInput.value) {
            noteInput.value = material.description;
        }
        if (purposeArea && !purposeArea.value) {
            purposeArea.value = material.category_name || '';
        }
    }
    row.dataset.materialId = String(material.id);
}

function detectMaterialByName(name) {
    if (!name) return null;
    const normalized = name.trim().toLowerCase();
    if (!normalized) return null;
    return materialLibrary.find((item) => item.name.toLowerCase() === normalized) || null;
}

function attachRowEnhancements(row) {
    const nameInput = row.querySelector('input[name="item_name"]');
    const categoryInput = row.querySelector('input[name="category"]');
    const unitInput = row.querySelector('input[name="unit"]');

    const resetAutofill = () => {
        if (unitInput.dataset.autofill === 'material') {
            unitInput.value = '';
            delete unitInput.dataset.autofill;
        }
        row.dataset.materialId = '';
    };

    const applyCategoryDefault = () => {
        const value = categoryInput.value.trim().toLowerCase();
        if (!value) return;
        const defaultUnit = categoryDefaults.get(value);
        if (defaultUnit && (!unitInput.value || unitInput.dataset.autofill === 'category')) {
            unitInput.value = defaultUnit;
            unitInput.dataset.autofill = 'category';
        }
    };

    ['change', 'blur'].forEach((eventName) => {
        nameInput.addEventListener(eventName, () => {
            const material = detectMaterialByName(nameInput.value);
            if (material) {
                applyMaterialToRow(row, material);
            } else {
                resetAutofill();
            }
        });
    });

    nameInput.addEventListener('input', () => {
        if (!nameInput.value.trim()) {
            resetAutofill();
        }
    });

    ['change', 'blur'].forEach((eventName) => {
        categoryInput.addEventListener(eventName, applyCategoryDefault);
    });

    ['input', 'change'].forEach((eventName) => {
        unitInput.addEventListener(eventName, () => {
            unitInput.dataset.autofill = 'manual';
        });
    });
}

function addItemRow(data = {}) {
    const template = document.getElementById('itemRowTemplate');
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('tr');

    row.querySelectorAll('input, textarea').forEach((field) => {
        const { name } = field;
        if (!name) return;
        let value = null;
        if (name === 'distribution' && Array.isArray(data.distribution)) {
            value = data.distribution
                .map((item) => {
                    const dept = item.department || '';
                    const qty = item.qty !== null && item.qty !== undefined && item.qty !== ''
                        ? String(item.qty)
                        : '';
                    return qty ? `${dept}: ${qty}`.trim() : dept;
                })
                .filter(Boolean)
                .join('\n');
        } else if (Object.prototype.hasOwnProperty.call(data, name)) {
            value = data[name];
        }
        if (value === null || value === undefined) {
            value = '';
        }
        field.value = value;
    });

    row.querySelector('.removeItem').addEventListener('click', () => {
        row.remove();
        updateIndices();
    });

    document.getElementById('itemsBody').appendChild(row);
    attachRowEnhancements(row);
    const material = detectMaterialByName(row.querySelector('input[name="item_name"]').value);
    if (material) {
        applyMaterialToRow(row, material);
    }
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
        return;
    }
    materialLibrary = response.data;
    categoryDefaults.clear();
    materialLibrary.forEach((material) => {
        if (material.category_name && material.unit_name) {
            categoryDefaults.set(material.category_name.toLowerCase(), material.unit_name);
        }
    });
    populateMaterialSources();
}

function collectFormData() {
    const justification = document.getElementById('justification').value.trim();
    if (!justification) {
        throw new Error('Обоснование обязательно');
    }
    const priority = document.getElementById('priority').value;
    const deadline = document.getElementById('deadline').value || null;
    const basis = document.getElementById('basis').value.trim() || DEFAULT_BASIS;
    const periodLabel = document.getElementById('periodLabel').value.trim();
    const serviceObjects = document.getElementById('serviceObjects').value.trim();
    const items = serializeItems(document.getElementById('itemsBody')).map((item, index) => {
        if (!item.category) {
            throw new Error(`Заполните категорию для позиции №${index + 1}`);
        }
        if (!item.item_name) {
            throw new Error(`Заполните наименование для позиции №${index + 1}`);
        }
        if (!item.unit) {
            throw new Error(`Укажите единицу измерения для позиции №${index + 1}`);
        }
        if (!item.qty || Number.isNaN(item.qty) || item.qty <= 0) {
            throw new Error(`Количество в позиции №${index + 1} должно быть больше 0`);
        }
        item.qty = parseFloat(item.qty);
        ['stock_qty', 'need_qty'].forEach((field) => {
            if (item[field] === null || item[field] === '' || item[field] === undefined) {
                item[field] = null;
                return;
            }
            if (Number.isNaN(item[field])) {
                throw new Error(`Поле «${field === 'stock_qty' ? 'Остаток' : 'Потребность'}» в позиции №${index + 1} заполнено некорректно`);
            }
            if (item[field] < 0) {
                throw new Error(`Поле «${field === 'stock_qty' ? 'Остаток' : 'Потребность'}» не может быть отрицательным (позиция №${index + 1})`);
            }
            item[field] = parseFloat(item[field]);
        });
        if (!item.note && item.features) {
            item.note = item.features;
        }
        return item;
    });
    if (!items.length) {
        throw new Error('Добавьте хотя бы одну позицию');
    }
    return {
        justification,
        priority,
        deadline_date: deadline,
        basis,
        period_label: periodLabel || null,
        service_objects: serviceObjects,
        items
    };
}

async function fetchRequest(id) {
    const response = await apiClient.get(`/requests.php?action=one&id=${id}`);
    if (!response.ok) {
        showToast(response.error || 'Не удалось загрузить заявку', 'error');
        return null;
    }
    return response.data;
}

async function loadAttachments(requestId) {
    if (!requestId) {
        state.attachments = [];
        renderAttachmentList();
        return;
    }
    const data = await fetchRequest(requestId);
    if (data) {
        state.attachments = data.attachments || [];
        renderAttachmentList();
    }
}

async function saveRequest(status, silent = false, skipAttachments = false) {
    let payload;
    try {
        payload = collectFormData();
    } catch (error) {
        if (!silent) {
            showToast(error.message, 'error');
        }
        throw error;
    }
    payload.status = status;
    payload.priority = payload.priority || 'normal';

    const csrfToken = await apiClient.getCsrfToken();
    let response;
    if (state.currentRequestId) {
        response = await apiClient.post('/requests.php', 'update', { id: state.currentRequestId, ...payload }, csrfToken);
    } else {
        response = await apiClient.post('/requests.php', 'create', payload, csrfToken);
    }

    if (!response.ok) {
        if (!silent) {
            showToast(response.error || 'Не удалось сохранить заявку', 'error');
        }
        throw new Error(response.error || 'Ошибка сохранения');
    }

    state.currentRequestId = response.data.id;
    state.currentStatus = status;
    document.getElementById('downloadPdfBtn').disabled = false;
    if (!silent) {
        showToast(status === 'draft' ? 'Черновик сохранён' : 'Заявка отправлена', 'success');
    }
    if (!skipAttachments) {
        await uploadPendingAttachments();
    }
    await loadAttachments(state.currentRequestId);
    await loadRequests(currentPage);
    return state.currentRequestId;
}

async function uploadPendingAttachments() {
    if (!state.pendingFiles.length || !state.currentRequestId) {
        return;
    }
    const csrfToken = await apiClient.getCsrfToken();
    for (const file of state.pendingFiles) {
        const formData = new FormData();
        formData.append('request_id', state.currentRequestId);
        formData.append('file', file);
        const response = await apiClient.upload('/requests.php', 'upload_attachment', formData, csrfToken);
        if (response.ok) {
            state.attachments = response.data;
        } else {
            showToast(response.error || `Ошибка загрузки файла ${file.name}`, 'error');
        }
    }
    state.pendingFiles = [];
    document.getElementById('attachmentInput').value = '';
    renderAttachmentList();
}

async function deleteAttachment(id) {
    const csrfToken = await apiClient.getCsrfToken();
    const response = await apiClient.post('/requests.php', 'delete_attachment', { id }, csrfToken);
    if (response.ok) {
        state.attachments = response.data;
        showToast('Файл удалён', 'success');
        renderAttachmentList();
    } else {
        showToast(response.error || 'Не удалось удалить файл', 'error');
    }
}

function renderAttachmentList() {
    const list = document.getElementById('attachmentList');
    list.innerHTML = '';
    state.pendingFiles.forEach((file, index) => {
        const li = document.createElement('li');
        li.className = 'flex items-center justify-between bg-slate-100 px-3 py-2 rounded';
        li.innerHTML = `<span>${file.name} <span class="text-xs text-slate-400">(к загрузке)</span></span><button class="text-red-500 text-xs" data-pending-index="${index}">Убрать</button>`;
        list.appendChild(li);
    });
    state.attachments.forEach((file) => {
        const li = document.createElement('li');
        li.className = 'flex items-center justify-between bg-white px-3 py-2 rounded border border-slate-200';
        li.innerHTML = `
            <span>${file.original_name}</span>
            <div class="flex items-center gap-3 text-xs">
                <a href="../api/files.php?action=attachment&id=${file.id}" target="_blank" class="text-emerald-600 hover:text-emerald-800">Скачать</a>
                <button data-attachment-id="${file.id}" class="text-red-500 hover:text-red-700">Удалить</button>
            </div>`;
        list.appendChild(li);
    });
}

function handlePendingRemoval(event) {
    const button = event.target.closest('button[data-pending-index]');
    if (!button) return;
    const index = Number(button.dataset.pendingIndex);
    state.pendingFiles.splice(index, 1);
    renderAttachmentList();
}

function collectFilters() {
    return {
        status: document.getElementById('filterStatus').value,
        priority: document.getElementById('filterPriority').value,
        from: document.getElementById('filterFrom').value,
        to: document.getElementById('filterTo').value
    };
}

async function loadRequests(page = 1) {
    filters = collectFilters();
    currentPage = page;
    const query = new URLSearchParams({ page: currentPage, ...filters });
    const response = await apiClient.get(`/requests.php?action=list&${query.toString()}`);
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
            { html: renderPriorityBadge(item.priority) },
            { html: renderStatusBadge(item.status) },
            { text: item.items_count.toString() },
            { html: renderJustificationPreview(item.justification) },
            {
                html: `
                <div class="flex justify-end space-x-2 text-sm">
                    <button data-action="pdf" data-id="${item.id}" class="text-emerald-600 hover:text-emerald-800">PDF</button>
                    ${(item.can_edit ? `<button data-action="edit" data-id="${item.id}" class="text-blue-600 hover:text-blue-800">Редактировать</button>` : '')}
                </div>`
            }
        ]
    }));

    renderTableRows(document.getElementById('requestsBody'), rows);
    document.getElementById('requestsSummary').textContent = `Страница ${pagination.current_page} из ${pagination.total_pages}, всего ${pagination.total_items}`;
    document.getElementById('prevPageBtn').disabled = pagination.current_page <= 1;
    document.getElementById('nextPageBtn').disabled = pagination.current_page >= pagination.total_pages;

    const statusFilter = document.getElementById('filterStatus');
    if (statusFilter && statusFilter.options.length === 1) {
        Object.entries(statuses).forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            statusFilter.appendChild(option);
        });
    }
}

async function openRequestForEdit(id) {
    const data = await fetchRequest(id);
    if (!data) return;
    state.currentRequestId = id;
    state.currentStatus = data.request.status;
    document.getElementById('justification').value = data.request.justification;
    document.getElementById('priority').value = data.request.priority;
    document.getElementById('deadline').value = data.request.deadline_date || '';
    document.getElementById('basis').value = (data.request.basis || '').trim() || DEFAULT_BASIS;
    document.getElementById('periodLabel').value = data.request.period_label || '';
    document.getElementById('serviceObjects').value = data.request.service_objects || '';
    const body = document.getElementById('itemsBody');
    body.innerHTML = '';
    data.items.forEach((item) => addItemRow(item));
    updateIndices();
    state.attachments = data.attachments || [];
    state.pendingFiles = [];
    renderAttachmentList();
    document.getElementById('downloadPdfBtn').disabled = false;
    showToast('Заявка открыта для редактирования', 'info');
}

async function repeatLastRequest() {
    const response = await apiClient.get('/requests.php?action=latest');
    if (!response.ok || !response.data) {
        showToast(response.error || 'Не найдено прошлых заявок для повтора', 'warning');
        return;
    }
    latestTemplate = response.data;
    state.currentRequestId = null;
    state.currentStatus = 'draft';
    document.getElementById('requestForm').reset();
    document.getElementById('justification').value = latestTemplate.request.justification;
    document.getElementById('priority').value = latestTemplate.request.priority;
    document.getElementById('deadline').value = latestTemplate.request.deadline_date || '';
    document.getElementById('basis').value = (latestTemplate.request.basis || '').trim() || DEFAULT_BASIS;
    document.getElementById('serviceObjects').value = latestTemplate.request.service_objects || '';
    document.getElementById('periodLabel').value = latestTemplate.request.period_label || '';
    const body = document.getElementById('itemsBody');
    body.innerHTML = '';
    latestTemplate.items.forEach((item) => addItemRow(item));
    updateIndices();
    state.attachments = [];
    state.pendingFiles = [];
    renderAttachmentList();
    document.getElementById('downloadPdfBtn').disabled = true;
    showToast('Данные последней заявки подставлены. Проверьте и отправьте.', 'success');
}

function applyTemplate() {
    const template = {
        justification: 'Прошу обеспечить подразделение комплектами спецодежды и инструментом для выполнения плановых работ.',
        priority: 'urgent',
        deadline_date: '',
        basis: DEFAULT_BASIS,
        period_label: '',
        service_objects: '',
        items: [
            {
                category: 'Спецодежда',
                item_name: 'Куртка утеплённая зимняя',
                unit: 'шт.',
                qty: 20,
                purpose: 'Выдача сотрудникам дорожных бригад',
                features: 'Комплект с утеплителем до -30°C',
                stock_qty: 5,
                need_qty: 25,
                note: 'Для бригад дорожного участка'
            },
            {
                category: 'Инструмент',
                item_name: 'Отбойный молоток электрический',
                unit: 'шт.',
                qty: 2,
                purpose: 'Ремонт асфальтового покрытия',
                features: 'Мощность 1700 Вт',
                stock_qty: 1,
                need_qty: 3,
                note: 'Замена изношенного инструмента'
            }
        ]
    };
    state.currentRequestId = null;
    state.currentStatus = 'draft';
    document.getElementById('justification').value = template.justification;
    document.getElementById('priority').value = template.priority;
    document.getElementById('deadline').value = template.deadline_date;
    document.getElementById('basis').value = template.basis;
    document.getElementById('serviceObjects').value = template.service_objects;
    document.getElementById('periodLabel').value = template.period_label;
    const body = document.getElementById('itemsBody');
    body.innerHTML = '';
    template.items.forEach((item) => addItemRow(item));
    updateIndices();
    state.attachments = [];
    state.pendingFiles = [];
    renderAttachmentList();
    document.getElementById('downloadPdfBtn').disabled = true;
    showToast('Шаблон заполнен. Добавьте дополнительные позиции при необходимости.', 'info');
}

function handleAttachmentSelection(event) {
    const files = Array.from(event.target.files || []);
    if (!files.length) {
        return;
    }
    state.pendingFiles.push(...files);
    renderAttachmentList();
}

async function handleUploadButton() {
    if (!state.pendingFiles.length) {
        showToast('Выберите файлы для загрузки', 'warning');
        return;
    }
    try {
        if (!state.currentRequestId) {
            await saveRequest('draft', true, true);
        }
        await uploadPendingAttachments();
    } catch (error) {
        // already handled
    }
}

function initEventHandlers() {
    document.getElementById('addItemBtn').addEventListener('click', () => addItemRow());
    document.getElementById('materialQuickSelect').addEventListener('change', (event) => {
        const id = Number(event.target.value);
        if (!id) return;
        const material = materialLibrary.find((item) => item.id === id);
        if (!material) return;
        addItemRow({
            category: material.category_name || '',
            item_name: material.name,
            unit: material.unit_name || '',
            qty: 1,
            note: material.description || ''
        });
        event.target.value = '';
    });
    document.getElementById('addMaterialFromCatalog').addEventListener('click', () => {
        const select = document.getElementById('materialQuickSelect');
        const id = Number(select.value);
        if (!id) {
            showToast('Выберите материал из списка', 'warning');
            return;
        }
        const material = materialLibrary.find((item) => item.id === id);
        if (!material) return;
        addItemRow({
            category: material.category_name || '',
            item_name: material.name,
            unit: material.unit_name || '',
            qty: 1,
            note: material.description || ''
        });
        select.value = '';
    });

    document.getElementById('saveDraftBtn').addEventListener('click', () => saveRequest('draft'));
    document.getElementById('submitRequestBtn').addEventListener('click', () => saveRequest('submitted'));

    document.getElementById('downloadPdfBtn').addEventListener('click', () => {
        if (!state.currentRequestId) return;
        window.open(`../api/files.php?action=pdf&id=${state.currentRequestId}`, '_blank');
    });

    document.getElementById('templateRequestBtn').addEventListener('click', applyTemplate);
    document.getElementById('repeatLastRequestBtn').addEventListener('click', repeatLastRequest);

    document.getElementById('attachmentInput').addEventListener('change', handleAttachmentSelection);
    document.getElementById('uploadAttachmentBtn').addEventListener('click', handleUploadButton);
    document.getElementById('attachmentList').addEventListener('click', (event) => {
        if (event.target.matches('[data-attachment-id]')) {
            deleteAttachment(Number(event.target.dataset.attachmentId));
        }
        if (event.target.matches('[data-pending-index]')) {
            handlePendingRemoval(event);
        }
    });

    document.getElementById('requestsBody').addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        const id = Number(button.dataset.id);
        if (button.dataset.action === 'pdf') {
            window.open(`../api/files.php?action=pdf&id=${id}`, '_blank');
        }
        if (button.dataset.action === 'edit') {
            openRequestForEdit(id);
        }
    });

    document.getElementById('applyFiltersBtn').addEventListener('click', () => loadRequests(1));
    document.getElementById('prevPageBtn').addEventListener('click', () => {
        if (currentPage > 1) loadRequests(currentPage - 1);
    });
    document.getElementById('nextPageBtn').addEventListener('click', () => {
        if (currentPage < totalPages) loadRequests(currentPage + 1);
    });
}

async function initUserInfo() {
    const me = await apiClient.get('/auth.php?action=me');
    if (!me.ok) {
        window.location.href = '../index.html';
        return;
    }
    document.getElementById('userInfo').textContent = me.data.fio;
    if (me.data.must_change_password) {
        document.getElementById('passwordChangeSection').classList.remove('hidden');
        document.getElementById('changePasswordBtn').classList.remove('hidden');
        showToast('Необходимо сменить пароль при первом входе', 'warning');
    }
    document.getElementById('logoutBtn').addEventListener('click', async () => {
        const csrfToken = await apiClient.getCsrfToken();
        await apiClient.post('/auth.php', 'logout', {}, csrfToken);
        window.location.href = '../index.html';
    });
}

async function initPasswordChange() {
    const btn = document.getElementById('changePasswordBtn');
    const section = document.getElementById('passwordChangeSection');
    const form = document.getElementById('passwordChangeForm');
    btn.addEventListener('click', () => section.classList.toggle('hidden'));
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');
        if (newPassword !== confirmPassword) {
            showToast('Пароли не совпадают', 'error');
            return;
        }
        const csrfToken = await apiClient.getCsrfToken();
        const response = await apiClient.post('/auth.php', 'password/change', { new_password: newPassword }, csrfToken);
        if (response.ok) {
            showToast('Пароль изменён', 'success');
            section.classList.add('hidden');
        } else {
            showToast(response.error || 'Не удалось сменить пароль', 'error');
        }
    });
}

export async function initDashboard() {
    await initUserInfo();
    await initPasswordChange();
    initEventHandlers();
    resetForm();
    await loadMaterialsDictionary();
    await loadRequests();
}
