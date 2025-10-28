const usersTable = document.querySelector('#usersTable tbody');
const userModal = document.getElementById('userModal');
const userForm = document.getElementById('userForm');
const BASE_PATH = window.APP_BASE_PATH || '';
const API_BASE = window.APP_API_BASE || (BASE_PATH + '/api');

async function loadUsers() {
    const response = await fetch(`${API_BASE}/users.php`);
    const data = await response.json();
    if (!data.ok) throw data.error;
    usersTable.innerHTML = '';
    data.data.forEach((user) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${user.fio}</td>
            <td>${user.position || '—'}</td>
            <td>${user.department || '—'}</td>
            <td>${user.login}</td>
            <td>${user.role}</td>
            <td>${user.is_active ? 'Активен' : 'Заблокирован'}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-secondary" data-action="reset" data-id="${user.id}">Сбросить пароль</button>
                <button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${user.id}">Удалить</button>
            </td>`;
        usersTable.appendChild(tr);
    });
}

document.getElementById('saveUser')?.addEventListener('click', async () => {
    const payload = Object.fromEntries(new FormData(userForm).entries());
    let data;
    try {
        data = await window.App.request('users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
    } catch (error) {
        window.App.toast(error.error || 'Ошибка сохранения', 'danger');
        return;
    }
    window.App.toast('Пользователь сохранен. Пароль: ' + (data.data?.password || '(указан администратором)'), 'success');
    userForm.reset();
    bootstrap.Modal.getInstance(userModal)?.hide();
    loadUsers().catch((error) => window.App.toast(error, 'danger'));
});

usersTable?.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const id = button.dataset.id;
    if (button.dataset.action === 'reset') {
        try {
            const data = await window.App.request(`users.php?id=${id}&action=reset-password`, { method: 'PATCH' });
            window.App.toast('Новый пароль: ' + data.data.password, 'info');
        } catch (error) {
            window.App.toast(error.error || 'Ошибка', 'danger');
        }
    }
    if (button.dataset.action === 'delete') {
        if (!confirm('Удалить пользователя?')) return;
        try {
            await window.App.request(`users.php?id=${id}`, { method: 'DELETE' });
            window.App.toast('Пользователь удален', 'success');
            loadUsers().catch((error) => window.App.toast(error, 'danger'));
        } catch (error) {
            window.App.toast(error.error || 'Ошибка', 'danger');
        }
    }
});

loadUsers().catch((error) => window.App.toast('Не удалось загрузить пользователей', 'danger'));
