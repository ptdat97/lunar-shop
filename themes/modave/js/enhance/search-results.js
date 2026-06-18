import { renderGrid } from './_card.js';

/*
| Search results enhancement (vanilla, SSR-first).
| Results for the current query are already server-rendered. JS lets the user
| re-search without a full reload (live re-query on submit / debounced typing)
| and keeps the URL in sync. With JS off, the SSR GET form still works.
*/

export default function searchResults(root = document) {
    const el = root.querySelector('[data-search-results]');
    if (!el || el.dataset.bound) return;
    el.dataset.bound = '1';

    const form = el.querySelector('[data-search-form]');
    const input = el.querySelector('[data-search-input]');
    const grid = el.querySelector('[data-grid]');
    const countEl = el.querySelector('[data-result-count]');
    const empty = el.querySelector('[data-empty]');
    if (!form || !input) return;

    let timer = null;

    async function run() {
        const term = input.value.trim();
        try {
            const { data } = await window.axios.get('/api/v1/search', { params: { q: term } });
            const products = data.data ?? [];
            const total = data.meta?.total ?? products.length;

            if (grid) {
                renderGrid(grid, products);
                grid.style.display = products.length ? '' : 'none';
            }
            if (countEl) {
                countEl.textContent = term ? `${total} results for “${term}”` : `${total} results`;
                countEl.style.display = term ? '' : 'none';
            }
            if (empty) {
                empty.textContent = `No results${term ? ` for “${term}”` : ''}.`;
                empty.style.display = term && !products.length ? '' : 'none';
            }
            const qs = term ? `?q=${encodeURIComponent(term)}` : window.location.pathname;
            window.history.replaceState({}, '', qs);
        } catch (e) { /* leave SSR results */ }
    }

    form.addEventListener('submit', (e) => { e.preventDefault(); clearTimeout(timer); run(); });

    // Debounced live search as the user types (>= 2 chars).
    input.addEventListener('input', () => {
        clearTimeout(timer);
        if (input.value.trim().length < 2) return;
        timer = setTimeout(run, 300);
    });
}
