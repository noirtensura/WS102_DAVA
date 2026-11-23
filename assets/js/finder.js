'use strict';

// 1. Step-1 Location Picker (Municipality -> Barangay cascading, GPS, Change Location drawer)
const stepForm = document.querySelector('[data-location-step-form]');
if (stepForm) {
    const mun = stepForm.querySelector('[data-step-municipality]');
    const brgy = stepForm.querySelector('[data-step-barangay]');
    if (mun && brgy) {
        const allOptions = Array.from(brgy.options, (opt) => opt.cloneNode(true));
        const syncStepBarangays = (keepValue = true) => {
            const selectedMun = mun.value;
            const current = keepValue ? brgy.value : '';
            brgy.replaceChildren(
                ...allOptions
                    .filter((opt) => !opt.value || !selectedMun || opt.dataset.municipality === selectedMun)
                    .map((opt) => opt.cloneNode(true))
            );
            if (current && Array.from(brgy.options).some((o) => o.value === current)) {
                brgy.value = current;
            } else {
                brgy.value = '';
            }
        };
        syncStepBarangays(true);
        mun.addEventListener('change', () => syncStepBarangays(false));
    }
}

const toggleLocationBtn = document.querySelector('[data-toggle-location-panel]');
const locationCard = document.getElementById('location-selector-card');
if (toggleLocationBtn && locationCard) {
    toggleLocationBtn.addEventListener('click', () => {
        const opening = locationCard.hidden;
        locationCard.hidden = !opening;
        toggleLocationBtn.setAttribute('aria-expanded', String(opening));
        if (opening) locationCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
}

const gpsButton = document.querySelector('[data-use-gps]');
const gpsStatus = document.querySelector('[data-gps-status]');
if (gpsButton) {
    gpsButton.addEventListener('click', () => {
        if (!navigator.geolocation) {
            if (gpsStatus) {
                gpsStatus.hidden = false;
                gpsStatus.textContent = 'Geolocation is not supported by this browser. Please select a campus or town below.';
            }
            return;
        }
        gpsButton.disabled = true;
        if (gpsStatus) {
            gpsStatus.hidden = false;
            gpsStatus.textContent = 'Detecting your location to find the nearest boarding houses & apartments...';
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const url = new URL('index.php', location.href);
                url.searchParams.set('lat', pos.coords.latitude.toFixed(6));
                url.searchParams.set('lng', pos.coords.longitude.toFixed(6));
                url.searchParams.set('radius', '5000');
                url.hash = '#listings';
                window.location.assign(url);
            },
            () => {
                gpsButton.disabled = false;
                if (gpsStatus) {
                    gpsStatus.hidden = false;
                    gpsStatus.textContent = 'Location permission was declined or unavailable. Choose a campus or Antique town below instead.';
                }
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
}

// 2. Step-2 Refine Nearest Results (AJAX live filtering & sorting)
const finderForm = document.querySelector('#finder-form');
const finderResults = document.querySelector('[data-finder-results]');

if (finderForm && finderResults && window.fetch && window.AbortController) {
    const status = document.getElementById('finder-status');
    const filterMenu = finderForm.querySelector('.filter-menu');
    let latestRequest;
    let searchTimer;

    function locationForFilters() {
        const url = new URL(finderForm.action);
        for (const [key, value] of new FormData(finderForm)) {
            const trimmed = typeof value === 'string' ? value.trim() : '';
            if (trimmed !== '') url.searchParams.append(key, trimmed);
        }
        return url;
    }

    function restoreFilters(params) {
        for (const control of finderForm.elements) {
            if (control.type === 'hidden') continue;
            if (control instanceof HTMLSelectElement) {
                if (params.has(control.name)) {
                    control.value = params.get(control.name);
                } else if (control.name === 'budget' || control.name === 'room_type' || control.name === 'gender') {
                    control.value = '';
                }
            } else if (control.type === 'checkbox') {
                control.checked = params.getAll(control.name).includes(control.value);
            } else if (control.type === 'radio') {
                control.checked = (params.get(control.name) ?? '') === control.value;
            } else if (control.type === 'search' || control.type === 'text') {
                control.value = params.get(control.name) ?? '';
            }
        }
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
            if (status) status.textContent = finderResults.querySelector('[data-finder-count]')?.textContent.trim() ?? '';
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

    finderForm.addEventListener('submit', (event) => {
        event.preventDefault();
        refreshFinder({ scroll: true });
    });

    ['budget', 'room_type', 'gender'].forEach((name) => {
        finderForm.elements.namedItem(name)?.addEventListener('change', () => refreshFinder());
    });
    finderForm.elements.namedItem('q')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => refreshFinder(), 300);
    });

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-finder-sort], [data-quick-filter]')) refreshFinder();
    });
    document.addEventListener('click', (event) => {
        if (event.button || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const clearRefine = event.target.closest('[data-clear-refine]');
        if (clearRefine) {
            event.preventDefault();
            restoreFilters(new URL(clearRefine.href).searchParams);
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
