const editor = document.getElementById('requestEditor');
if (editor) {
    const requestId = editor.dataset.requestId;
    const form = document.getElementById('requestForm');
    const itemsBody = document.querySelector('#itemsTable tbody');
    const addItemButton = document.getElementById('addItem');
    const saveDraftButton = document.getElementById('saveDraft');
    const BASE_PATH = window.APP_BASE_PATH || '';
    const API_BASE = window.APP_API_BASE || (BASE_PATH + '/api');
    const LIST_URL = (BASE_PATH || '') + '/requests/list.php';

    function createRow(item = {}) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input class="form-control" name="category_id" value="${item.category_id || ''}"></td>
            <td><input class="form-control" name="material_id" value="${item.material_id || ''}"></td>
            <td><input class="form-control" name="unit_id" value="${item.unit_id || ''}"></td>
            <td><input class="form-control" type="number" step="0.01" name="qty" value="${item.qty || 1}" required></td>
            <td><input class="form-control" name="assignment" value="${item.assignment || ''}"></td>
            <td><input class="form-control" name="note" value="${item.note || ''}"></td>
            <td><input class="form-control" type="number" step="0.01" name="current_stock" value="${item.current_stock || 0}"></td>
            <td><input class="form-control" type="number" step="0.01" name="required_qty" value="${item.required_qty || 0}"></td>
            <td><input class="form-control" type="number" step="0.01" name="purchase_qty" value="${item.purchase_qty || item.qty || 1}"></td>
            <td><button class="btn btn-sm btn-outline-danger" type="button">✕</button></td>`;
        tr.querySelector('button').addEventListener('click', () => tr.remove());
        return tr;
    }

    addItemButton?.addEventListener('click', () => itemsBody.appendChild(createRow()));

    async function loadRequest() {
        const response = await fetch(`${API_BASE}/requests.php?id=${requestId}`);
        const data = await response.json();
        if (!data.ok) throw data.error;
        const { request, items } = data.data;
        form.elements['basis'].value = request.basis || '';
        form.elements['priority'].value = request.priority;
        form.elements['deadline_date'].value = request.deadline_date || '';
        form.elements['service_objects'].value = (JSON.parse(request.service_objects || '[]') || []).join('\n');
        form.elements['justification'].value = request.justification;
        itemsBody.innerHTML = '';
        items.forEach((item) => itemsBody.appendChild(createRow(item)));
    }

    function serialize(status) {
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        payload.status = status;
        payload.service_objects = (payload.service_objects || '').split('\n').map((line) => line.trim()).filter(Boolean);
        const items = [];
        itemsBody.querySelectorAll('tr').forEach((row) => {
            const item = {};
            row.querySelectorAll('input').forEach((input) => {
                if (['qty', 'current_stock', 'required_qty', 'purchase_qty'].includes(input.name)) {
                    item[input.name] = parseFloat(input.value || '0');
                } else {
                    item[input.name] = input.value;
                }
            });
            items.push(item);
        });
        payload.items = items;
        return payload;
    }

    async function submit(status) {
        try {
            const payload = serialize(status);
            const data = await window.App.request(`requests.php?id=${requestId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            window.App.toast('Заявка обновлена', 'success');
            window.location.href = LIST_URL;
        } catch (error) {
            window.App.toast(typeof error === 'string' ? error : 'Ошибка обновления', 'danger');
        }
    }

    saveDraftButton?.addEventListener('click', () => submit('draft'));
    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        submit('submitted');
    });

    loadRequest().catch((error) => window.App.toast('Не удалось загрузить заявку', 'danger'));
}
