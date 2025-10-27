document.getElementById('loginForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const alertBox = document.getElementById('loginAlert');
    alertBox?.classList.add('d-none');
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    try {
        const response = await fetch('/finanses/public/api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw data.error || 'Ошибка входа';
        }
        window.location.href = '/finanses/public/dashboard.php';
    } catch (error) {
        alertBox.textContent = typeof error === 'string' ? error : (error?.error || 'Ошибка входа');
        alertBox.classList.remove('d-none');
    }
});
