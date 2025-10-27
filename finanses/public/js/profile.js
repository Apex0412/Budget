const passwordForm = document.getElementById('passwordForm');
const preferencesForm = document.getElementById('preferencesForm');

passwordForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(passwordForm).entries());
    try {
        const response = await fetch('/finanses/public/api/auth.php?action=change-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!data.ok) throw data.error;
        window.App.toast('Пароль изменен', 'success');
        passwordForm.reset();
    } catch (error) {
        window.App.toast(typeof error === 'string' ? error : 'Ошибка смены пароля', 'danger');
    }
});

preferencesForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(preferencesForm).entries());
    try {
        const response = await fetch('/finanses/public/api/auth.php?action=preferences', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!data.ok) throw data.error;
        window.App.toast('Настройки сохранены', 'success');
        if (payload.theme) {
            document.documentElement.setAttribute('data-bs-theme', payload.theme);
        }
    } catch (error) {
        window.App.toast(typeof error === 'string' ? error : 'Ошибка сохранения', 'danger');
    }
});
