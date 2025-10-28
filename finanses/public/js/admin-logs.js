(async function () {
    const API_BASE = window.APP_API_BASE || ((window.APP_BASE_PATH || '') + '/api');
    try {
        const response = await fetch(`${API_BASE}/logs.php`);
        const data = await response.json();
        if (!data.ok) throw data.error;
        const tbody = document.querySelector('#logsTable tbody');
        tbody.innerHTML = '';
        data.data.forEach((log) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${new Date(log.created_at).toLocaleString()}</td>
                <td>${log.fio || '—'}</td>
                <td>${log.action}</td>
                <td>${log.entity || ''}</td>
                <td><code>${log.meta || ''}</code></td>
                <td>${log.ip}</td>`;
            tbody.appendChild(tr);
        });
    } catch (error) {
        window.App.toast('Не удалось загрузить журнал', 'danger');
    }
})();
