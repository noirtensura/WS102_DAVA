'use strict';

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    button.hidden = false;
    button.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(reveal));
        button.querySelector('[data-password-hidden]').hidden = reveal;
        button.querySelector('[data-password-visible]').hidden = !reveal;
    });
});

const loginForm = document.querySelector('[data-login-form]');
if (loginForm) {
    const email = loginForm.elements.namedItem('email');
    const password = loginForm.elements.namedItem('password');
    const remember = loginForm.elements.namedItem('remember_email');
    const portal = loginForm.elements.namedItem('portal') ? 'admin' : 'public';
    const storageKey = 'dayon:' + new URL('.', loginForm.action).pathname + ':remembered-email:' + portal;

    document.querySelectorAll('[data-demo-fill]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (email) email.value = btn.dataset.demoFill;
            if (password) password.value = 'DayonDemo2026!';
            loginForm.querySelector('button[type="submit"]')?.focus();
        });
    });

    try {
        const storedEmail = localStorage.getItem(storageKey);
        if (storedEmail && !email.value) {
            email.value = storedEmail;
            remember.checked = true;
        }
        loginForm.querySelector('[data-remember-option]').hidden = false;
        remember.addEventListener('change', () => {
            if (!remember.checked) localStorage.removeItem(storageKey);
        });
        loginForm.addEventListener('submit', () => {
            if (remember.checked) localStorage.setItem(storageKey, email.value.trim());
        });
    } catch {
        // Login is independent of optional email storage.
    }
}
