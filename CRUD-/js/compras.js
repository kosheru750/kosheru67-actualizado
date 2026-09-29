/**
 * Kosheru - Gestión de Compras Admin JS
 */

const AdminCompras = {
    statusOptions: [
        ['pendiente', 'Pendiente'],
        ['preparando', 'En preparación'],
        ['enviado', 'En camino'],
        ['completado', 'Entregado'],
        ['cancelado', 'Cancelado']
    ],

    async cargarCompras() {
        const tbody = document.getElementById('purchasesTableBody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="8" class="text-center">Cargando compras...</td></tr>';

        try {
            const res = await fetch('../api/compras/index.php');
            const data = await res.json();

            if (data.success) {
                if (data.compras.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center">No hay compras registradas en el sistema.</td></tr>';
                    return;
                }

                const esc = KosheruApp.escapeHtml;

                tbody.innerHTML = data.compras.map(c => {
                    const currentStatus = this.statusOptions.find(([value]) => value === c.status);
                    const statusLabel = currentStatus ? currentStatus[1] : c.status;
                    const statusOptions = this.statusOptions.map(([value, label]) => `
                        <option value="${value}" ${value === c.status ? 'selected' : ''}>${label}</option>
                    `).join('');

                    return `
                        <tr>
                            <td style="font-weight:800; color:var(--color-rojo-kosheru);">${esc(c.invoice_number)}</td>
                            <td style="font-weight:700;">${esc(c.user_name)}</td>
                            <td>${esc(c.user_email)}</td>
                            <td>${esc(c.order_date)} ${esc(c.order_time)}</td>
                            <td>${esc(c.payment_method)}</td>
                            <td style="font-weight:800; color:var(--color-cafe-oscuro);">${c.total_formatted}</td>
                            <td>
                                <div class="purchase-status-control">
                                    <span class="factura-status-badge badge-${esc(c.status)}">${esc(statusLabel)}</span>
                                    <select class="purchase-status-select" aria-label="Estado del pedido ${esc(c.invoice_number)}">${statusOptions}</select>
                                    <button type="button" class="btn-purchase-status" data-purchase-id="${Number(c.id)}">Actualizar</button>
                                </div>
                            </td>
                            <td>
                                <button type="button" class="btn-action-view" onclick="window.location.href='facturacion.php?fac=${encodeURIComponent(c.invoice_number)}'">Ver detalle</button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        } catch (e) {
            console.error('Error al cargar compras:', e);
        }
    },

    async actualizarEstado(purchaseId, button) {
        const statusSelect = button.closest('.purchase-status-control')?.querySelector('.purchase-status-select');
        if (!statusSelect) return;

        button.disabled = true;
        try {
            const response = await fetch('../api/compras/index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ purchase_id: purchaseId, status: statusSelect.value })
            });
            const data = await response.json();

            if (!data.success) {
                KosheruApp.showToast(data.message || 'No se pudo actualizar el pedido.', 'error');
                return;
            }

            KosheruApp.showToast(data.message, 'success');
            await this.cargarCompras();
        } catch (e) {
            KosheruApp.showToast('Error de conexión al actualizar el pedido.', 'error');
        } finally {
            button.disabled = false;
        }
    },
};

document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('purchasesTableBody');
    if (tbody) {
        tbody.addEventListener('click', event => {
            const button = event.target.closest('[data-purchase-id]');
            if (button) AdminCompras.actualizarEstado(Number(button.dataset.purchaseId), button);
        });
    }

    AdminCompras.cargarCompras();
});
