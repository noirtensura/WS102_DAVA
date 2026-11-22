'use strict';

let toastTimer;

function showDayonMessage(message, success) {
    let toast = document.querySelector('.toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.setAttribute('role', 'status');
        document.body.append(toast);
    }
    toast.className = 'toast flash ' + (success ? 'flash-success' : 'flash-error');
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.hidden = true; }, 3800);
}

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-bookmark]');
    if (!form || !window.fetch) return;
    event.preventDefault();
    const button = form.querySelector('button');
    button.disabled = true;
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message);
        button.classList.toggle('is-saved', data.saved);
        button.setAttribute('aria-pressed', String(data.saved));
        const label = button.querySelector('[data-bookmark-label]');
        if (label) label.textContent = data.saved ? 'Bookmarked' : 'Bookmark Property';
        showDayonMessage(data.message, true);
    } catch (error) {
        showDayonMessage(error instanceof SyntaxError ? 'Reload the page and sign in to try again.' : (error.message || 'Could not update saved listings.'), false);
    } finally {
        button.disabled = false;
    }
});
