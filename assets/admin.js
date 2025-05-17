document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('at24spf_add_rule');
    const table = document.querySelector('#at24spf_rules_table tbody');

    if (btn && table) {
        btn.addEventListener('click', function () {
            const index = table.rows.length;
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input name="at24spf_settings[rules][${index}][min]" type="number" step="0.01"></td>
                <td><input name="at24spf_settings[rules][${index}][max]" type="number" step="0.01"></td>
                <td><input name="at24spf_settings[rules][${index}][amount]" type="number" step="0.01"></td>
            `;
            table.appendChild(row);
        });
    }
});