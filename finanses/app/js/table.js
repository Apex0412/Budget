export function renderTableRows(tbody, rows) {
    tbody.innerHTML = '';
    if (!rows.length) {
        const emptyRow = document.createElement('tr');
        const columnCount = tbody.closest('table')?.querySelectorAll('thead th').length || 1;
        emptyRow.innerHTML = `<td colspan="${columnCount}" class="px-3 py-4 text-center text-sm text-slate-500">Нет данных</td>`;
        tbody.appendChild(emptyRow);
        return;
    }

    rows.forEach((row) => {
        const tr = document.createElement('tr');
        tr.dataset.id = row.id;
        row.cells.forEach((cell) => {
            const td = document.createElement('td');
            td.className = 'px-3 py-2 text-sm text-slate-600';
            if (cell.html) {
                td.innerHTML = cell.html;
            } else {
                td.textContent = cell.text || '';
            }
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
}
