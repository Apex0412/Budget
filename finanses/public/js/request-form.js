const itemsTable = document.querySelector('#itemsTable tbody');
const addItemButton = document.getElementById('addItem');
const requestForm = document.getElementById('requestForm');
const saveDraftButton = document.getElementById('saveDraft');
const loadTemplateButton = document.getElementById('loadTemplate');

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

addItemButton?.addEventListener('click', () => {
    itemsTable.appendChild(createRow());
});

loadTemplateButton?.addEventListener('click', async () => {
    try {
        const response = await fetch('/finanses/public/api/materials.php?limit=5');
        const data = await response.json();
        if (!data.ok) throw data.error;
        itemsTable.innerHTML = '';
        data.data.forEach((material) => {
            itemsTable.appendChild(createRow({
                category_id: material.category_id,
                material_id: material.id,
                unit_id: material.unit_id,
                qty: 1,
                assignment: material.description || material.name,
                note: '',
                required_qty: 1,
                purchase_qty: 1
            }));
        });
    } catch (error) {
        window.App.toast('Не удалось загрузить шаблон', 'danger');
    }
});

saveDraftButton?.addEventListener('click', () => submitRequest('draft'));
requestForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    submitRequest('submitted');
});

function serializeForm(status) {
    const formData = new FormData(requestForm);
    const payload = Object.fromEntries(formData.entries());
    payload.status = status;
    payload.service_objects = (payload.service_objects || '').split('\n').map((line) => line.trim()).filter(Boolean);
    const items = [];
    itemsTable.querySelectorAll('tr').forEach((row) => {
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

async function submitRequest(status) {
    try {
        const payload = serializeForm(status);
        await window.App.request('/finanses/public/api/requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        window.App.toast('Заявка сохранена', 'success');
        window.location.href = '/finanses/public/requests/list.php';
    } catch (error) {
        window.App.toast(typeof error === 'string' ? error : 'Ошибка сохранения', 'danger');
    }
}

if (!itemsTable.querySelector('tr')) {
    addItemButton?.click();
}
