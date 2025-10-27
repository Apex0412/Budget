(async function () {
    try {
        const response = await fetch('/finanses/public/api/statistics.php');
        const data = await response.json();
        if (!data.ok) throw data.error;
        const stats = data.data;

        const statusCtx = document.getElementById('chartStatus');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: stats.status.map((row) => row.status),
                datasets: [{ data: stats.status.map((row) => row.total), backgroundColor: ['#0d6efd', '#ffc107', '#198754', '#dc3545', '#6c757d'] }]
            },
        });

        const categoriesCtx = document.getElementById('chartCategories');
        new Chart(categoriesCtx, {
            type: 'bar',
            data: {
                labels: stats.categories.map((row) => row.category),
                datasets: [{ label: 'Заявок', data: stats.categories.map((row) => row.total), backgroundColor: '#0d6efd' }]
            },
        });

        const monthlyCtx = document.getElementById('chartMonthly');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: stats.monthly.map((row) => row.month),
                datasets: [{ label: 'Заявок', data: stats.monthly.map((row) => row.total), borderColor: '#6610f2', fill: false }]
            },
        });

        const list = document.getElementById('inactiveUsers');
        list.innerHTML = '';
        stats.inactive_users.forEach((user) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.textContent = user.fio;
            list.appendChild(li);
        });
    } catch (error) {
        window.App.toast('Не удалось загрузить отчёты', 'danger');
    }
})();
