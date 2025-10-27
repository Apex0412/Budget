const materialsFilters = document.getElementById('materialsFilters');
const materialsTable = document.querySelector('#materialsTable tbody');
const materialModal = document.getElementById('materialModal');
const materialForm = document.getElementById('materialForm');
const canManage = !!materialForm;

let metaLoaded = false;

async function loadMaterials() {
    const params = new URLSearchParams(new FormData(materialsFilters));
    if (!metaLoaded) {
        params.set('meta', '1');
    }
    const response = await fetch('/finanses/public/api/materials.php?' + params.toString());
    const data = await response.json();
    if (!data.ok) throw data.error;
    materialsTable.innerHTML = '';
    data.data.forEach((material) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${material.code || '—'}</td>
            <td>${material.name}</td>
            <td>${material.category_name || '—'}</td>
            <td>${material.unit_name || '—'}</td>
            <td>${material.description || ''}</td>
            ${canManage ? `<td class="text-end"><button class="btn btn-sm btn-outline-primary" data-action="edit" data-id="${material.id}">Редактировать</button></td>` : ''}`;
        tr.dataset.material = JSON.stringify(material);
        materialsTable.appendChild(tr);
    });

    if (!metaLoaded && data.meta) {
        populateSelect(materialsFilters.querySelector('select[name="category_id"]'), data.meta.categories, true);
        if (materialForm) {
            populateSelect(materialForm.elements['category_id'], data.meta.categories, false);
            populateSelect(materialForm.elements['unit_id'], data.meta.units, false);
        }
        metaLoaded = true;
    }
}

function populateSelect(select, items, allowEmpty) {
    if (!select) return;
    const current = select.value;
    select.innerHTML = '';
    if (allowEmpty) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'Все категории';
        select.appendChild(option);
    }
    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = item.name;
        select.appendChild(option);
    });
    if (current) {
        select.value = current;
    }
}

materialsFilters?.addEventListener('input', () => loadMaterials().catch((error) => window.App.toast(error, 'danger')));

materialsTable?.addEventListener('click', (event) => {
    if (!canManage) return;
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const material = JSON.parse(button.closest('tr').dataset.material);
    for (const [key, value] of Object.entries(material)) {
        if (materialForm.elements[key]) {
            materialForm.elements[key].value = value;
        }
    }
    materialForm.elements['is_active'].checked = material.is_active === 1;
    bootstrap.Modal.getOrCreateInstance(materialModal).show();
});

if (canManage) {
    document.getElementById('saveMaterial')?.addEventListener('click', async () => {
        const payload = Object.fromEntries(new FormData(materialForm).entries());
        payload.is_active = materialForm.elements['is_active'].checked ? 1 : 0;
        const method = payload.id ? 'PUT' : 'POST';
        const url = payload.id ? `/finanses/public/api/materials.php?id=${payload.id}` : '/finanses/public/api/materials.php';
        const data = await window.App.request(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        window.App.toast('Материал сохранен', 'success');
        materialForm.reset();
        bootstrap.Modal.getInstance(materialModal)?.hide();
        loadMaterials().catch((error) => window.App.toast(error, 'danger'));
    });
}

loadMaterials().catch((error) => window.App.toast('Не удалось загрузить материалы', 'danger'));
