window.App = (function () {
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    function toast(message, variant = 'primary') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="toast align-items-center text-bg-${variant} border-0" role="status">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`;
        const toastEl = wrapper.firstElementChild;
        container.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            headers: Object.assign({
                'X-CSRF-TOKEN': csrfToken,
            }, options.headers || {}),
            credentials: 'same-origin',
            ...options,
        });
        const contentType = response.headers.get('Content-Type') || '';
        const data = contentType.includes('application/json') ? await response.json() : await response.text();
        if (!response.ok || (typeof data === 'object' && data && data.ok === false)) {
            throw data;
        }
        return data;
    }

    async function logout() {
        try {
            await request('/finanses/public/api/auth.php?action=logout', { method: 'POST' });
        } catch (e) {
            console.error(e);
        }
        window.location.href = '/finanses/public/login.php';
    }

    function badgeStatus(status) {
        const map = {
            draft: 'secondary',
            submitted: 'info',
            returned: 'warning',
            approved: 'success',
            rejected: 'danger',
            in_progress: 'primary',
            purchased: 'dark'
        };
        return `<span class="badge text-bg-${map[status] || 'secondary'} status-badge">${status}</span>`;
    }

    function badgePriority(priority) {
        return `<span class="badge ${priority === 'urgent' ? 'badge-priority-urgent' : priority === 'critical' ? 'badge-priority-critical' : 'badge-priority-normal'}">${priority}</span>`;
    }

    return { toast, request, logout, badgeStatus, badgePriority };
})();
