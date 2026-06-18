import { buildQuery, urlFilters } from './_util.js';
import { renderGrid } from './_card.js';

/*
| Collection shop enhancement (vanilla, SSR-first).
|
| The grid/facets/pagination are already server-rendered. This module reads the
| embedded JSON state, then on facet/sort/page interaction calls /api/v1/search
| and re-renders the grid in place — no full reload. With JS off, the SSR <a>
| links and <select> form still work (real GET navigation).
*/

const SORT_MAP = {
    'best-selling': null,
    'a-z': 'a-z',
    'z-a': 'z-a',
    'price-low-high': 'price-low-high',
    'price-high-low': 'price-high-low',
};

export default function collectionShop(root = document) {
    const el = root.querySelector('[data-collection-shop]');
    if (!el || el.dataset.bound) return;
    el.dataset.bound = '1';

    const scope = el.dataset.scope || '';
    const grid = el.querySelector('[data-grid]');
    const countEl = el.querySelector('[data-result-count]');
    const pagination = el.querySelector('[data-pagination]');
    const sortSelect = el.querySelector('[data-sort]');
    const sortForm = el.querySelector('[data-sort-form]');

    // State: hydrate from URL (filters) + current sort; products come from SSR.
    const selected = urlFilters();
    let sort = el.dataset.currentSort || 'best-selling';
    let page = 1;

    function setLoading(on) {
        el.classList.toggle('is-loading', on);
        if (grid) grid.style.opacity = on ? '0.5' : '';
    }

    function syncUrl() {
        const params = new URLSearchParams();
        if (sort && sort !== 'best-selling') params.set('sort', sort);
        if (page > 1) params.set('page', page);
        selected.size.forEach((v) => params.append('filters[size][]', v));
        selected.color.forEach((v) => params.append('filters[color][]', v));
        const qs = params.toString();
        window.history.replaceState({}, '', qs ? `?${qs}` : window.location.pathname);
    }

    function renderPagination(meta) {
        if (!pagination) return;
        const last = meta.last_page || 1;
        if (last <= 1) { pagination.style.display = 'none'; pagination.innerHTML = ''; return; }
        pagination.style.display = '';
        let html = '';
        for (let p = 1; p <= last; p++) {
            html += `<li class="${p === meta.page ? 'active' : ''}"><a href="#" data-page="${p}">${p}</a></li>`;
        }
        pagination.innerHTML = html;
    }

    function renderFacetsActive() {
        el.querySelectorAll('[data-facet]').forEach((a) => {
            const on = selected[a.dataset.group]?.includes(a.dataset.value);
            a.classList.toggle('active', !!on);
            a.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    async function load() {
        setLoading(true);
        try {
            const qs = buildQuery({
                scope,
                page,
                per_page: 24,
                sort: SORT_MAP[sort] ?? undefined,
                filters: { size: selected.size, color: selected.color },
            });
            const { data } = await window.axios.get(`/api/v1/search?${qs}`);
            const products = data.data ?? [];
            const meta = data.meta ?? { total: products.length, page: 1, last_page: 1 };

            if (grid) renderGrid(grid, products);
            if (countEl) countEl.textContent = `${meta.total} products`;
            const empty = el.querySelector('[data-empty]');
            if (empty) empty.style.display = products.length ? 'none' : '';
            renderPagination(meta);
            renderFacetsActive();
            syncUrl();
        } catch (e) {
            /* leave SSR content in place on error */
        } finally {
            setLoading(false);
        }
    }

    // Facet toggle (multi-select).
    el.querySelectorAll('[data-facets]').forEach((aside) => {
        aside.addEventListener('click', (e) => {
            const a = e.target.closest('[data-facet]');
            if (!a) return;
            e.preventDefault();
            const group = a.dataset.group;
            const value = a.dataset.value;
            const arr = selected[group];
            const i = arr.indexOf(value);
            i === -1 ? arr.push(value) : arr.splice(i, 1);
            page = 1;
            load();
        });
    });

    // Sort (intercept the SSR <select>; keep GET fallback when JS off).
    if (sortSelect && sortForm) {
        sortSelect.removeAttribute('onchange');
        sortForm.addEventListener('submit', (e) => e.preventDefault());
        sortSelect.addEventListener('change', () => {
            sort = sortSelect.value;
            page = 1;
            load();
        });
    }

    // Pagination (delegated; survives re-render).
    if (pagination) {
        pagination.addEventListener('click', (e) => {
            const a = e.target.closest('[data-page]');
            if (!a) return;
            e.preventDefault();
            page = Number(a.dataset.page);
            load();
            window.scrollTo({ top: el.offsetTop - 80, behavior: 'smooth' });
        });
    }
}
