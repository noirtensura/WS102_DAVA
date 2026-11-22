'use strict';

const finderForm = document.querySelector('#finder-form');
const finderResults = document.querySelector('[data-finder-results]');

if (finderForm && finderResults && window.fetch && window.AbortController) {
    const status = document.getElementById('finder-status');
    const filterMenu = finderForm.querySelector('.filter-menu');
    const municipalitySelect = finderForm.querySelector('[data-finder-municipality]');
    const barangaySelect = finderForm.querySelector('[data-finder-barangay]');
    const allBarangays = barangaySelect ? Array.from(barangaySelect.options, (opt) => opt.cloneNode(true)) : [];
    const advancedSelects = Array.from(finderForm.querySelectorAll('.advanced-content select'));
    let latestRequest;
    let searchTimer;

    function syncAdvancedSelects() {
        advancedSelects.forEach((sel) => sel.toggleAttribute('data-chosen', Boolean(sel.value)));
    }

    function syncBarangays(keepValue = true) {
        if (!municipalitySelect || !barangaySelect) return;
        const mun = municipalitySelect.value;
        const current = keepValue ? barangaySelect.value : '';
        barangaySelect.replaceChildren(
            ...allBarangays
                .filter((opt) => !opt.value || !mun || opt.dataset.municipality === mun)
                .map((opt) => opt.cloneNode(true))
        );
        if (current && Array.from(barangaySelect.options).some((o) => o.value === current)) {
            barangaySelect.value = current;
        } else {
            barangaySelect.value = '';
        }
    }

    function locationForFilters() {
        const url = new URL(finderForm.action);
        for (const [key, value] of new FormData(finderForm)) {
            const trimmed = typeof value === 'string' ? value.trim() : '';
            if (trimmed && !(key === 'sort' && trimmed === 'featured')) url.searchParams.append(key, trimmed);
        }
        return url;
    }

    function restoreFilters(params) {
        if (municipalitySelect) {
            municipalitySelect.value = params.get('municipality') ?? '';
            syncBarangays(false);
        }
        for (const control of finderForm.elements) {
            if (control instanceof HTMLSelectElement) {
                control.value = params.get(control.name) ?? (control.name === 'sort' ? 'featured' : '');
            } else if (control.type === 'checkbox') {
                control.checked = params.getAll(control.name).includes(control.value);
            } else if (control.type === 'radio') {
                control.checked = (params.get(control.name) ?? '') === control.value;
            } else if (control.type === 'search' || control.type === 'text') {
                control.value = params.get(control.name) ?? '';
            }
        }
        syncAdvancedSelects();
    }

    async function refreshFinder({ scroll = false, updateHistory = true } = {}) {
        latestRequest?.abort();
        const request = new AbortController();
        latestRequest = request;
        const url = updateHistory ? locationForFilters() : new URL(location.href);
        const fragment = new URL(url);
        fragment.hash = '';
        fragment.searchParams.set('fragment', 'listings');
        finderResults.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(fragment, { signal: request.signal });
            if (!response.ok) throw new Error('Could not load listings');
            const content = await response.text();
            if (request.signal.aborted) return;

            const restoreSortFocus = document.activeElement?.matches('[data-finder-sort]');
            finderResults.innerHTML = content;
            status.textContent = finderResults.querySelector('[data-finder-count]')?.textContent.trim() ?? '';
            if (updateHistory && url.href !== location.href) history.pushState(null, '', url);
            if (restoreSortFocus) finderResults.querySelector('[data-finder-sort]')?.focus({ preventScroll: true });
            if (scroll) {
                if (filterMenu) filterMenu.open = false;
                finderResults.querySelector('#results-title')?.focus({ preventScroll: true });
                finderResults.scrollIntoView({ block: 'start' });
            }
        } catch (error) {
            if (error.name !== 'AbortError') window.location.assign(url);
        } finally {
            if (latestRequest === request) finderResults.removeAttribute('aria-busy');
        }
    }

    syncBarangays(true);
    syncAdvancedSelects();

    finderForm.addEventListener('submit', (event) => {
        event.preventDefault();
        refreshFinder({ scroll: true });
    });

    municipalitySelect?.addEventListener('change', () => {
        syncBarangays(false);
        refreshFinder();
    });
    barangaySelect?.addEventListener('change', () => refreshFinder());
    finderForm.elements.namedItem('budget')?.addEventListener('change', () => refreshFinder());
    finderForm.elements.namedItem('q')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => refreshFinder(), 320);
    });
    advancedSelects.forEach((sel) => sel.addEventListener('change', syncAdvancedSelects));

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-finder-sort], [data-quick-filter]')) refreshFinder();
    });
    document.addEventListener('click', (event) => {
        if (event.button || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        if (event.target.closest('[data-clear-filters]')) {
            event.preventDefault();
            const sort = finderForm.elements.namedItem('sort')?.value || 'featured';
            restoreFilters(new URLSearchParams({ sort }));
            refreshFinder({ scroll: true });
        } else if (filterMenu?.open && !filterMenu.contains(event.target)) {
            filterMenu.open = false;
        }
    });
    filterMenu?.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !filterMenu.open) return;
        filterMenu.open = false;
        filterMenu.querySelector('summary').focus();
    });
    window.addEventListener('popstate', () => {
        restoreFilters(new URL(location.href).searchParams);
        refreshFinder({ updateHistory: false });
    });
}
