'use strict';

document.querySelectorAll('[data-navigation]').forEach((menu) => {
    const viewport = matchMedia('(max-width: ' + menu.dataset.navigation + 'px)');
    const sync = () => { menu.open = !viewport.matches; };
    sync();
    viewport.addEventListener('change', sync);
    menu.addEventListener('keydown', (event) => {
        if (viewport.matches && menu.open && event.key === 'Escape') {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
    menu.querySelectorAll('nav a').forEach((link) => {
        link.addEventListener('click', () => {
            if (viewport.matches && link.hash && new URL(link.href).pathname === location.pathname) {
                menu.open = false;
            }
        });
    });
});

function revealLocationTarget() {
    const target = document.getElementById(location.hash.slice(1));
    let disclosure = target?.closest('details');
    let revealed = false;
    while (disclosure) {
        revealed ||= !disclosure.open;
        disclosure.open = true;
        disclosure = disclosure.parentElement.closest('details');
    }
    if (revealed) target.scrollIntoView({ block: 'start' });
    return target;
}

const locationTarget = revealLocationTarget();
window.addEventListener('hashchange', revealLocationTarget);
const flashStack = document.querySelector('.flash-stack');
if (flashStack && locationTarget) {
    const panel = locationTarget.closest('main .panel, main .booking-card');
    if (panel && !panel.contains(flashStack)) {
        const summary = locationTarget.closest('details')?.querySelector(':scope > summary');
        const heading = panel.firstElementChild;
        if (summary) {
            summary.after(flashStack);
        } else if (heading?.matches('.panel-head, .section-head, h2')) {
            heading.after(flashStack);
        } else {
            panel.prepend(flashStack);
        }
    }
}

document.querySelectorAll('.table-wrap').forEach((container) => {
    const table = container.querySelector('table');
    if (!table) return;

    const panel = container.closest('.panel');
    const heading = container.previousElementSibling?.matches('h2, h3') ? container.previousElementSibling
        : (panel?.querySelector('h2, h3') ?? container.closest('main')?.querySelector('h1'));
    const hint = document.createElement('p');
    hint.className = 'table-scroll-hint';
    hint.textContent = 'Scroll horizontally to see all columns.';
    hint.hidden = true;
    container.before(hint);

    const sync = () => {
        const overflow = container.scrollWidth > container.clientWidth + 1;
        hint.hidden = !overflow;
        if (overflow) {
            container.tabIndex = 0;
            container.setAttribute('role', 'region');
            container.setAttribute('aria-label', (heading?.textContent.trim() || 'Data') + ' table, scroll for more columns');
        } else {
            container.removeAttribute('tabindex');
            container.removeAttribute('role');
            container.removeAttribute('aria-label');
        }
    };

    if ('ResizeObserver' in window) new ResizeObserver(sync).observe(container);
    sync();
});

const confirmDialog = document.querySelector('[data-confirm-dialog]');
let pendingForm;
let pendingButton;
let confirmingSubmission = false;

document.addEventListener('submit', (event) => {
    const message = event.submitter?.dataset.confirm;
    if (!message || confirmingSubmission || event.defaultPrevented) return;
    if (!confirmDialog?.showModal) {
        if (!window.confirm(message)) event.preventDefault();
        return;
    }
    event.preventDefault();
    pendingForm = event.target;
    pendingButton = event.submitter;
    confirmDialog.querySelector('#confirm-message').textContent = message;
    confirmDialog.querySelector('[data-confirm-submit]').textContent =
        pendingButton.dataset.confirmLabel || pendingButton.textContent.trim();
    confirmDialog.returnValue = '';
    confirmDialog.showModal();
});

confirmDialog?.addEventListener('close', () => {
    const form = pendingForm;
    const button = pendingButton;
    pendingForm = null;
    pendingButton = null;
    if (confirmDialog.returnValue !== 'confirm' || !form?.isConnected || button?.form !== form) return;

    confirmingSubmission = true;
    try {
        form.requestSubmit(button);
    } finally {
        confirmingSubmission = false;
    }
});

let toastTimer;

function showDayonMessage(message) {
    let toast = document.querySelector('.toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast flash flash-success';
        toast.setAttribute('role', 'status');
        document.body.append(toast);
    }
    toast.hidden = false;
    toast.textContent = message;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.hidden = true; }, 4300);
}

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-bookmark]');
    if (!form || !window.fetch) return;
    event.preventDefault();
    const button = form.querySelector('button');
    if (button.getAttribute('aria-busy') === 'true') return;
    const card = form.closest('.listing-card, .booking-card');
    card?.querySelector('.bookmark-error')?.remove();
    button.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message);
        button.classList.toggle('is-saved', data.saved);
        button.classList.remove('bookmark-pop');
        void button.offsetWidth;
        button.classList.add('bookmark-pop');
        button.setAttribute('aria-pressed', String(data.saved));
        const label = button.querySelector('[data-bookmark-label]');
        if (label) label.textContent = data.saved ? 'Bookmarked' : 'Bookmark This Property';
        if (typeof data.count === 'number') {
            document.querySelectorAll('[data-nav-bookmark-count]').forEach((badge) => {
                badge.textContent = String(data.count);
                badge.hidden = data.count === 0;
            });
        }
        showDayonMessage(data.message);
    } catch (error) {
        const feedback = document.createElement('p');
        feedback.setAttribute('role', 'alert');
        feedback.className = 'bookmark-error';
        feedback.textContent = error instanceof SyntaxError
            ? 'Sign in again to update your bookmarked houses.'
            : (error instanceof TypeError ? 'Dayon could not update the bookmark. Try again.' : error.message);
        if (card) {
            card.append(feedback);
        } else {
            form.after(feedback);
        }
    } finally {
        button.removeAttribute('aria-busy');
    }
});

// Optional Antique Permit form helper (moved from inline script for strict script-src 'self' CSP)
const permitTypeSelect = document.querySelector('[data-permit-type]');
if (permitTypeSelect) {
    const customWrap = document.querySelector('[data-custom-permit-wrap]');
    const officeInput = document.querySelector('[data-permit-office]');
    const syncPermitFields = () => {
        const opt = permitTypeSelect.selectedOptions[0];
        if (customWrap) customWrap.hidden = permitTypeSelect.value !== 'other_local_permit';
        if (officeInput && opt?.dataset.office) officeInput.placeholder = opt.dataset.office;
    };
    permitTypeSelect.addEventListener('change', syncPermitFields);
    syncPermitFields();
}

// Duplicate-submission protection for standard POST forms on mobile & slow connections
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (form.method.toLowerCase() !== 'post' || form.hasAttribute('data-bookmark')) return;
    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = 'true';
    window.addEventListener('pageshow', () => { delete form.dataset.submitting; }, { once: true });
});
