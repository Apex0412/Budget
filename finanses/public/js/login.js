const BASE_PATH = window.APP_BASE_PATH || '';
const API_BASE = window.APP_API_BASE || (BASE_PATH + '/api');
const DASHBOARD_URL = window.APP_DASHBOARD_URL || ((BASE_PATH || '') + '/dashboard.php');

document.getElementById('loginForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const alertBox = document.getElementById('loginAlert');
    alertBox?.classList.add('d-none');
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw data.error || 'Ошибка входа';
        }
        window.location.href = DASHBOARD_URL;
    } catch (error) {
        alertBox.textContent = typeof error === 'string' ? error : (error?.error || 'Ошибка входа');
        alertBox.classList.remove('d-none');
    }
});
