const filesForm = document.getElementById('filesForm');
const filesTable = document.querySelector('#filesTable tbody');
const BASE_PATH = window.APP_BASE_PATH || '';
const API_BASE = window.APP_API_BASE || (BASE_PATH + '/api');

async function loadFiles(requestId) {
    if (!requestId) return;
    const response = await fetch(`${API_BASE}/files.php?request_id=${requestId}`);
    const data = await response.json();
    if (!data.ok) throw data.error;
    filesTable.innerHTML = '';
    data.data.forEach((file) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><a href="${API_BASE}/files.php?pdf=${requestId}" target="_blank">${file.original_name}</a></td>
            <td>${file.mime_type}</td>
            <td>${(file.size / 1024).toFixed(1)} КБ</td>
            <td>${new Date(file.created_at).toLocaleString()}</td>
            <td class="text-end"><button class="btn btn-sm btn-outline-danger" data-id="${file.id}" data-request="${requestId}">Удалить</button></td>`;
        filesTable.appendChild(tr);
    });
}

filesForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(filesForm);
    const requestId = formData.get('request_id');
    try {
        const response = await fetch(`${API_BASE}/files.php`, {
            method: 'POST',
            body: formData,
        });
        const data = await response.json();
        if (!data.ok) throw data.error;
        window.App.toast('Файлы загружены', 'success');
        loadFiles(requestId).catch((error) => window.App.toast(error, 'danger'));
    } catch (error) {
        window.App.toast(typeof error === 'string' ? error : 'Ошибка загрузки', 'danger');
    }
});

filesTable?.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-id]');
    if (!button) return;
    const id = button.dataset.id;
    const requestId = button.dataset.request;
    if (!confirm('Удалить файл?')) return;
    try {
        await window.App.request(`files.php?id=${id}`, { method: 'DELETE' });
        window.App.toast('Файл удален', 'success');
        loadFiles(requestId).catch((error) => window.App.toast(error, 'danger'));
    } catch (error) {
        window.App.toast(error.error || 'Ошибка удаления', 'danger');
    }
});

filesForm?.elements['request_id']?.addEventListener('change', (event) => {
    const id = event.target.value;
    loadFiles(id).catch(() => filesTable && (filesTable.innerHTML = ''));
});
