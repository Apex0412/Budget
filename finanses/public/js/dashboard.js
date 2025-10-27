(async function () {
    try {
        const response = await fetch('/finanses/public/api/requests.php');
        const data = await response.json();
        if (!data.ok) throw data.error;
        const items = data.data.items || [];
        document.getElementById('kpiTotal').textContent = items.length;
        document.getElementById('kpiInProgress').textContent = items.filter((item) => ['submitted', 'returned', 'in_progress'].includes(item.status)).length;
        document.getElementById('kpiCompleted').textContent = items.filter((item) => ['approved', 'purchased'].includes(item.status)).length;
        document.getElementById('kpiRejected').textContent = items.filter((item) => item.status === 'rejected').length;
        const tbody = document.querySelector('#recentRequests tbody');
        tbody.innerHTML = '';
        items.slice(0, 10).forEach((item) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.id}</td>
                <td>${new Date(item.created_at).toLocaleDateString()}</td>
                <td>${item.justification.slice(0, 60)}...</td>
                <td>${window.App.badgeStatus(item.status)}</td>
                <td>${window.App.badgePriority(item.priority)}</td>`;
            tbody.appendChild(tr);
        });
    } catch (error) {
        window.App.toast('Не удалось загрузить данные панели', 'danger');
    }
})();
