import { cardHtml } from './_card.js';

/*
| Search modal enhancement (vanilla).
| The header search popup renders SSR (form + popular keywords). JS adds live
| suggest + product results as the user types, debounced. The form is a real GET
| to /search, so submitting works with JS off.
*/

export default function searchModal(root = document) {
    const el = root.querySelector('[data-search-modal]');
    if (!el || el.dataset.bound) return;
    el.dataset.bound = '1';

    const form = el.querySelector('[data-search-form]');
    const input = el.querySelector('[data-search-input]');
    const keywords = el.querySelector('[data-search-keywords]');
    const suggestionsEl = el.querySelector('[data-search-suggestions]');
    const productsBox = el.querySelector('[data-search-products]');
    const productsGrid = el.querySelector('[data-search-products-grid]');
    const viewAll = el.querySelector('[data-search-all]');
    const emptyEl = el.querySelector('[data-search-empty]');
    if (!input) return;

    let timer = null;

    function reset() {
        if (keywords) keywords.style.display = '';
        if (suggestionsEl) { suggestionsEl.style.display = 'none'; suggestionsEl.innerHTML = ''; }
        if (productsBox) productsBox.style.display = 'none';
        if (emptyEl) emptyEl.style.display = 'none';
    }

    async function fetchResults() {
        const term = input.value.trim();
        if (term.length < 2) { reset(); return; }

        if (keywords) keywords.style.display = 'none';

        try {
            const [sug, res] = await Promise.all([
                window.axios.get('/api/v1/search/suggest', { params: { q: term, limit: 6 } }),
                window.axios.get('/api/v1/search', { params: { q: term, per_page: 6 } }),
            ]);

            const suggestions = sug.data.data ?? [];
            const products = res.data.data ?? [];

            if (suggestionsEl) {
                suggestionsEl.innerHTML = suggestions
                    .map((s) => `<li><a href="javascript:void(0);" class="radius-60 link" data-search-keyword>${s}</a></li>`)
                    .join('');
                suggestionsEl.style.display = suggestions.length ? '' : 'none';
            }

            if (products.length) {
                if (productsGrid) productsGrid.innerHTML = products.map(cardHtml).join('');
                if (viewAll) viewAll.setAttribute('href', `/search?q=${encodeURIComponent(term)}`);
                if (productsBox) productsBox.style.display = '';
                if (emptyEl) emptyEl.style.display = 'none';
            } else {
                if (productsBox) productsBox.style.display = 'none';
                if (emptyEl) { emptyEl.textContent = `No products found for “${term}”.`; emptyEl.style.display = ''; }
            }
        } catch (e) { /* noop */ }
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(fetchResults, 250);
    });

    // Keyword / suggestion clicks (delegated — suggestions are re-rendered).
    el.addEventListener('click', (e) => {
        const kw = e.target.closest('[data-search-keyword]');
        if (!kw) return;
        input.value = kw.textContent.trim();
        fetchResults();
    });

    // Let the SSR GET form submit normally (works with JS off too).
    if (form) {
        form.addEventListener('submit', () => { /* default GET navigation */ });
    }
}
