const toastContainerId = 'toast-container';

export function showToast(message, type = 'info') {
    const container = document.getElementById(toastContainerId);
    if (!container) return;

    const colors = {
        success: 'bg-emerald-500',
        error: 'bg-red-500',
        info: 'bg-brand',
        warning: 'bg-amber-500'
    };
    const toast = document.createElement('div');
    toast.className = `${colors[type] || colors.info} text-white px-4 py-2 rounded shadow flex items-center space-x-2 animate-fade-in`;
    toast.innerHTML = `<span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('opacity-0');
        setTimeout(() => toast.remove(), 400);
    }, 4000);
}

export function renderStatusBadge(status) {
    const map = {
        draft: 'bg-slate-200 text-slate-700',
        submitted: 'bg-sky-200 text-sky-800',
        approved: 'bg-emerald-200 text-emerald-800',
        rejected: 'bg-red-200 text-red-800',
        in_progress: 'bg-amber-200 text-amber-800',
        purchased: 'bg-indigo-200 text-indigo-800'
    };
    const titles = {
        draft: 'Черновик',
        submitted: 'Отправлена',
        approved: 'Согласована',
        rejected: 'Отклонена',
        in_progress: 'В работе',
        purchased: 'Закуплено'
    };
    return `<span class="px-2 py-1 text-xs rounded ${map[status] || 'bg-slate-200'}">${titles[status] || status}</span>`;
}

export function renderJustificationPreview(text) {
    const short = text.length > 60 ? `${text.slice(0, 60)}…` : text;
    return `<span title="${text.replace(/"/g, '&quot;')}">${short}</span>`;
}
