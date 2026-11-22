'use strict';

const finderForm = document.querySelector('#finder-form');
const finderResults = document.querySelector('[data-finder-results]');
let latestFinderRequest;

async function refreshFinder(scrollToResults) {
    latestFinderRequest?.abort();
    const request = new AbortController();
    latestFinderRequest = request;
    const resultsUrl = new URL(finderForm.action);
    for (const [key, value] of new FormData(finderForm)) {
        if (value && !(key === 'sort' && value === 'recent')) resultsUrl.searchParams.append(key, value);
    }
    const partialUrl = new URL(resultsUrl);
    partialUrl.hash = '';
    partialUrl.searchParams.set('fragment', 'listings');
    finderResults.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(partialUrl, { signal: request.signal });
        if (!response.ok) throw new Error('Could not load listings');
        const content = await response.text();
        if (request.signal.aborted) return;
        const sorting = document.activeElement?.matches('[data-finder-sort]');
        finderResults.innerHTML = content;
        if (resultsUrl.href !== location.href) history.pushState(null, '', resultsUrl);
        if (sorting) finderResults.querySelector('[data-finder-sort]')?.focus({ preventScroll: true });
        if (scrollToResults) {
            const filterMenu = finderForm.querySelector('.filter-menu');
            if (filterMenu.open && filterMenu.contains(document.activeElement)) filterMenu.querySelector('summary').focus({ preventScroll: true });
            filterMenu.open = false;
            finderResults.scrollIntoView({ block: 'start' });
        }
    } catch (error) {
        if (error.name !== 'AbortError') window.location.assign(resultsUrl);
    } finally {
        if (latestFinderRequest === request) finderResults.removeAttribute('aria-busy');
    }
}

if (finderForm && finderResults && window.fetch && window.AbortController) {
    finderForm.addEventListener('submit', (event) => {
        event.preventDefault();
        refreshFinder(true);
    });
    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-finder-sort], [data-quick-filter]')) refreshFinder(false);
    });
    window.addEventListener('popstate', () => window.location.reload());
}
