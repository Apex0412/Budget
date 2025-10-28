const filtersForm = document.getElementById('requestFilters');
const tableBody = document.querySelector('#requestsTable tbody');
const pagination = document.getElementById('requestsPagination');
const totalLabel = document.getElementById('requestsTotal');
const BASE_PATH = window.APP_BASE_PATH || '';
const API_BASE = window.APP_API_BASE || (BASE_PATH + '/api');

async function loadRequests(page = 1) {
    const params = new URLSearchParams(new FormData(filtersForm));
    params.set('page', page);
    const response = await fetch(`${API_BASE}/requests.php?${params.toString()}`);
    const data = await response.json();
    if (!data.ok) throw data.error;
    const { items, pagination: meta } = data.data;
    tableBody.innerHTML = '';
    items.forEach((item) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.id}</td>
            <td>${new Date(item.created_at).toLocaleDateString()}</td>
            <td>${item.fio}</td>
            <td>${item.department || '—'}</td>
            <td>${window.App.badgeStatus(item.status)}</td>
            <td>${window.App.badgePriority(item.priority)}</td>
            <td>${item.items_count}</td>
            <td class="text-end">
                <a href="${(BASE_PATH || '') + '/requests/edit.php?id=' + item.id}" class="btn btn-sm btn-outline-primary">Редактировать</a>
                <a href="${API_BASE + '/files.php?pdf=' + item.id}" target="_blank" class="btn btn-sm btn-outline-secondary">PDF</a>
            </td>`;
        tableBody.appendChild(tr);
    });
    totalLabel.textContent = meta.total;
    renderPagination(meta.page, Math.ceil(meta.total / meta.per_page));
}

function renderPagination(current, total) {
    pagination.innerHTML = '';
    for (let page = 1; page <= total; page++) {
        const li = document.createElement('li');
        li.className = 'page-item' + (page === current ? ' active' : '');
        li.innerHTML = `<a class="page-link" href="#">${page}</a>`;
        li.addEventListener('click', (event) => {
            event.preventDefault();
            loadRequests(page).catch((error) => window.App.toast(error, 'danger'));
        });
        pagination.appendChild(li);
    }
}

filtersForm?.addEventListener('change', () => loadRequests().catch((error) => window.App.toast(error, 'danger')));

loadRequests().catch((error) => window.App.toast('Не удалось загрузить заявки', 'danger'));
