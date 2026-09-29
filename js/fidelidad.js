/**
 * Kosheru - Programa de Fidelidad (frontend)
 */
const KosheruFidelidad = {
    data: null,

    async load() {
        const root = document.getElementById('fidelidadRoot');
        if (!root) return;

        try {
            const res = await fetch('api/fidelidad.php');
            const data = await res.json();

            if (!data.success) {
                root.innerHTML = '<div class="fidelidad-loading">No pudimos cargar tu información de fidelidad.</div>';
                return;
            }

            this.data = data;
            this.render();
        } catch (e) {
            root.innerHTML = '<div class="fidelidad-loading">Error de conexión. Intenta de nuevo.</div>';
        }
    },

    render() {
        const root = document.getElementById('fidelidadRoot');
        const d = this.data;
        const esc = (window.KosheruApp && KosheruApp.escapeHtml) ? KosheruApp.escapeHtml : (v => v);
        const tierClass = { 'Bronce': 'tier-bronce', 'Plata': 'tier-plata', 'Oro': 'tier-oro' }[d.tier.name] || 'tier-bronce';

        const progressText = d.tier.next_name
            ? `Te faltan <strong>${d.tier.points_to_next}</strong> puntos para llegar a ${d.tier.next_name}`
            : '¡Alcanzaste el nivel máximo!';

        const rewardsHtml = d.reward_tiers.map(t => `
            <div class="loyalty-reward-card">
                <div class="loyalty-reward-points">${t.points} pts</div>
                <div class="loyalty-reward-label">${esc(t.label)}</div>
                <button type="button" class="btn-loyalty-redeem" data-points="${t.points}" ${d.balance < t.points ? 'disabled' : ''}>
                    ${d.balance < t.points ? 'Puntos insuficientes' : 'Canjear'}
                </button>
            </div>
        `).join('');

        const couponsHtml = d.active_coupons.length > 0
            ? d.active_coupons.map(c => `
                <div class="loyalty-coupon-item">
                    <span class="loyalty-coupon-code">${esc(c.code)}</span>
                    <span class="loyalty-coupon-discount">${c.discount_formatted} de descuento</span>
                </div>
            `).join('')
            : '<div class="loyalty-empty">No tienes cupones activos. ¡Canjea tus puntos!</div>';

        const historyHtml = d.history.length > 0
            ? d.history.map(h => `
                <div class="loyalty-history-item">
                    <div>
                        <div class="loyalty-history-desc">${esc(h.description)}</div>
                        <div class="loyalty-history-date">${esc(h.created_at)}</div>
                    </div>
                    <div class="loyalty-history-points ${h.type}">${h.points > 0 ? '+' : ''}${h.points}</div>
                </div>
            `).join('')
            : '<div class="loyalty-empty">Todavía no tienes movimientos. ¡Haz tu primera compra!</div>';

        root.innerHTML = `
            <div class="loyalty-summary-card">
                <div class="loyalty-tier-badge ${tierClass}">${d.tier.name}</div>
                <div>
                    <div class="loyalty-points-label">PUNTOS DISPONIBLES</div>
                    <div class="loyalty-points-value">${d.balance} pts</div>
                    <div class="loyalty-progress-track">
                        <div class="loyalty-progress-fill" style="width:${d.tier.progress_percent}%"></div>
                    </div>
                    <div class="loyalty-progress-text">${progressText}</div>
                </div>
            </div>

            <h2 class="loyalty-section-title">Canjea tus puntos</h2>
            <div class="loyalty-rewards-grid">${rewardsHtml}</div>

            <h2 class="loyalty-section-title">Tus cupones activos</h2>
            <div style="margin-bottom: 2.5rem;">${couponsHtml}</div>

            <h2 class="loyalty-section-title">Historial de puntos</h2>
            <div>${historyHtml}</div>
        `;

        root.querySelectorAll('.btn-loyalty-redeem').forEach(btn => {
            btn.addEventListener('click', () => this.redeem(parseInt(btn.dataset.points, 10)));
        });
    },

    async redeem(points) {
        if (!confirm(`¿Canjear ${points} puntos por un cupón de descuento?`)) return;

        const formData = new FormData();
        formData.append('points', points);

        try {
            const res = await fetch('api/fidelidad.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                if (window.KosheruApp) KosheruApp.showToast(data.message, 'success');
                this.load(); // recarga balance, cupones e historial
            } else {
                if (window.KosheruApp) KosheruApp.showToast(data.message, 'error');
            }
        } catch (e) {
            if (window.KosheruApp) KosheruApp.showToast('Error al procesar el canje.', 'error');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => KosheruFidelidad.load());