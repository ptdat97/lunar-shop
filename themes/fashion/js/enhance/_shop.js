// Shared SSR-first catalog grid behaviour for collection + search pages.
//
// Layer 3 of the SSR-first model: the page already rendered the grid, facets
// and pagination server-side, and embedded the same {data,facets,meta} payload
// (one contract with /api/v1/search). This enhancer:
//   - reads the embedded state as its INITIAL state (no fetch on load),
//   - re-fetches /api/v1/search ONLY when the user changes filter/sort/page,
//   - re-renders the grid (via _card.js), facets and pagination in place,
//   - syncs the URL with history.replaceState so reload/share/back work.
//
// No-JS fallback still works: the SSR controls are real GET forms/links; this
// just intercepts them for a smoother in-place update.

import api from '../api.js';
import { renderGrid } from './_card.js';

// Build the query object for /api/v1/search from the current page params.
function readParams(root, extra = {}) {
    const url = new URL(window.location.href);
    const filters = {};
    url.searchParams.forEach((value, key) => {
        const m = key.match(/^filters\[(\w+)\]\[?\]?$/);
        if (m) {
            (filters[m[1]] ||= []).push(value);
        }
    });

    return {
        q: url.searchParams.get('q') || undefined,
        scope: root.dataset.scope || undefined,
        sort: url.searchParams.get('sort') || undefined,
        page: Number(url.searchParams.get('page') || 1),
        filters,
        ...extra,
    };
}

// Serialise params back into a URLSearchParams (Laravel array syntax).
function toSearchParams(params) {
    const sp = new URLSearchParams();
    if (params.q) sp.set('q', params.q);
    if (params.sort) sp.set('sort', params.sort);
    if (params.page && params.page > 1) sp.set('page', String(params.page));
    Object.entries(params.filters || {}).forEach(([key, values]) => {
        (values || []).forEach((v) => sp.append(`filters[${key}][]`, v));
    });
    return sp;
}

function renderFacets(root, facets, activeFilters) {
    const host = root.querySelector('[data-facets]');
    if (!host) return;

    host.innerHTML = Object.entries(facets || {})
        .filter(([, buckets]) => buckets.length)
        .map(([key, buckets]) => {
            const active = new Set(activeFilters[key] || []);
            const items = buckets.map((b) => {
                const checked = active.has(b.value) ? 'checked' : '';
                const id = `f-${key}-${b.value}`.replace(/\W+/g, '-');
                return `
<div class="form-check">
    <input class="form-check-input" type="checkbox" id="${id}"
           data-facet="${key}" value="${b.value}" ${checked}>
    <label class="form-check-label d-flex justify-content-between" for="${id}">
        <span>${b.value}</span><span class="text-muted small">${b.count}</span>
    </label>
</div>`;
            }).join('');
            return `<div class="mb-4"><h6 class="text-uppercase small mb-2">${key}</h6>${items}</div>`;
        }).join('');
}

function renderPagination(root, meta) {
    const host = root.querySelector('[data-pagination]');
    if (!host) return;
    const { page, last_page: last } = meta;
    if (last <= 1) {
        host.innerHTML = '';
        return;
    }
    const btn = (p, label, disabled = false, active = false) =>
        `<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
            <button class="page-link" data-page="${p}" ${disabled ? 'disabled' : ''}>${label}</button>
        </li>`;

    let html = btn(page - 1, '‹', page <= 1);
    for (let p = 1; p <= last; p += 1) html += btn(p, p, false, p === page);
    html += btn(page + 1, '›', page >= last);
    host.innerHTML = `<ul class="pagination justify-content-center mb-0">${html}</ul>`;
}

export function initShop(root) {
    if (!root || root.dataset.shopInit) return;
    root.dataset.shopInit = '1';

    const grid = root.querySelector('[data-grid]');
    const countEl = root.querySelector('[data-result-count]');

    async function refresh(params, { push = true } = {}) {
        if (push) {
            const sp = toSearchParams(params);
            const qs = sp.toString();
            window.history.replaceState({}, '', qs ? `?${qs}` : window.location.pathname);
        }

        // Same endpoint + shape the page was server-rendered from.
        const { data } = await api.get('/search', {
            params: {
                q: params.q,
                scope: params.scope,
                sort: params.sort,
                page: params.page,
                filters: params.filters,
            },
        });

        if (grid) {
            renderGrid(grid, data.data);
            // Let other enhancers (e.g. wishlist) re-decorate the new cards.
            window.dispatchEvent(new CustomEvent('grid:rendered', { detail: { root: grid } }));
        }
        renderFacets(root, data.facets, params.filters);
        renderPagination(root, data.meta);
        if (countEl) countEl.textContent = data.meta.total;
        grid?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Sort dropdown
    root.querySelector('[data-sort]')?.addEventListener('change', (e) => {
        const params = readParams(root, { sort: e.target.value, page: 1 });
        refresh(params);
    });

    // Facet checkboxes (delegated — survives re-render)
    root.addEventListener('change', (e) => {
        const cb = e.target.closest('[data-facet]');
        if (!cb) return;
        const params = readParams(root);
        const key = cb.dataset.facet;
        const set = new Set(params.filters[key] || []);
        cb.checked ? set.add(cb.value) : set.delete(cb.value);
        params.filters[key] = [...set];
        params.page = 1;
        refresh(params);
    });

    // Pagination (delegated)
    root.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page]');
        if (!btn || btn.disabled) return;
        e.preventDefault();
        const params = readParams(root, { page: Number(btn.dataset.page) });
        refresh(params);
    });
}
