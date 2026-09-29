document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-toggle]').forEach(toggle => {
        const input = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!input) return;

        toggle.addEventListener('click', () => {
            const showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            toggle.textContent = showPassword ? 'Ocultar' : 'Mostrar';
            toggle.setAttribute('aria-label', `${showPassword ? 'Ocultar' : 'Mostrar'} contraseña`);
            toggle.setAttribute('aria-pressed', String(showPassword));
        });
    });
});