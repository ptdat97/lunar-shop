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
        // Array facets: filters[size][] → { size: [...] }
        const arr = key.match(/^filters\[(\w+)\]\[\]$/);
        if (arr) {
            (filters[arr[1]] ||= []).push(value);
            return;
        }
        // Object facets: filters[price][min] → { price: { min, max } }
        const obj = key.match(/^filters\[(\w+)\]\[(\w+)\]$/);
        if (obj && value !== '') {
            (filters[obj[1]] ||= {})[obj[2]] = value;
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

// Serialise params back into a URLSearchParams (Laravel array syntax). Supports
// both list facets (filters[key][]) and object facets like price (filters[price][min]).
function toSearchParams(params) {
    const sp = new URLSearchParams();
    if (params.q) sp.set('q', params.q);
    if (params.sort) sp.set('sort', params.sort);
    if (params.page && params.page > 1) sp.set('page', String(params.page));
    Object.entries(params.filters || {}).forEach(([key, val]) => {
        if (Array.isArray(val)) {
            val.forEach((v) => sp.append(`filters[${key}][]`, v));
        } else if (val && typeof val === 'object') {
            Object.entries(val).forEach(([k, v]) => {
                if (v !== '' && v != null) sp.append(`filters[${key}][${k}]`, v);
            });
        }
    });
    return sp;
}

function facetLabel(root, key) {
    // Read the localized label from the SSR heading if present, else Title-case.
    return root.dataset[`facetLabel${key}`] || (key.charAt(0).toUpperCase() + key.slice(1));
}

// Localized label for an enum-like facet VALUE (e.g. availability's "in_stock"),
// read from `data-value-label-{value}` on the shop root. Real data values
// (size/color/brand/material) have no override and display verbatim.
function valueLabel(root, value) {
    const camel = String(value).replace(/[-_](\w)/g, (_, c) => c.toUpperCase());
    return root.dataset[`valueLabel${camel.charAt(0).toUpperCase() + camel.slice(1)}`] || value;
}

function priceFacetHtml(facet, active) {
    if (!facet || !(facet.max > facet.min)) return '';
    const lo = Math.floor(facet.min);
    const hi = Math.ceil(facet.max);
    const min = active?.min ?? '';
    const max = active?.max ?? '';
    return `
<div class="mb-4" data-price-facet data-price-min="${facet.min}" data-price-max="${facet.max}">
    <h6 class="text-uppercase small mb-2" data-price-heading></h6>
    <div class="d-flex align-items-center gap-2">
        <input type="number" class="form-control form-control-sm" inputmode="decimal"
               data-price-input="min" min="${lo}" max="${hi}" placeholder="${lo}" value="${min}">
        <span class="text-muted">—</span>
        <input type="number" class="form-control form-control-sm" inputmode="decimal"
               data-price-input="max" min="${lo}" max="${hi}" placeholder="${hi}" value="${max}">
    </div>
</div>`;
}

function renderFacets(root, facets, activeFilters) {
    const host = root.querySelector('[data-facets]');
    if (!host) return;

    // Preserve the price heading text rendered by the SSR before we replace it.
    const priceHeading = host.querySelector('[data-price-facet] h6')?.textContent || '';

    const buckets = Object.entries(facets || {})
        .filter(([key, buckets]) => key !== 'price' && Array.isArray(buckets) && buckets.length)
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
        <span>${valueLabel(root, b.value)}</span><span class="text-muted small">${b.count}</span>
    </label>
</div>`;
            }).join('');
            return `<div class="mb-4"><h6 class="text-uppercase small mb-2">${facetLabel(root, key)}</h6>${items}</div>`;
        }).join('');

    host.innerHTML = buckets + priceFacetHtml(facets?.price, activeFilters.price);

    // Restore the localized price heading.
    const ph = host.querySelector('[data-price-heading]');
    if (ph) ph.textContent = priceHeading;
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

    // Price range inputs (delegated + debounced — survives re-render).
    let priceTimer = null;
    root.addEventListener('input', (e) => {
        const input = e.target.closest('[data-price-input]');
        if (!input) return;
        clearTimeout(priceTimer);
        priceTimer = setTimeout(() => {
            const wrap = input.closest('[data-price-facet]');
            const min = wrap?.querySelector('[data-price-input="min"]')?.value.trim() ?? '';
            const max = wrap?.querySelector('[data-price-input="max"]')?.value.trim() ?? '';
            const params = readParams(root);
            const price = {};
            if (min !== '') price.min = min;
            if (max !== '') price.max = max;
            if (Object.keys(price).length) params.filters.price = price;
            else delete params.filters.price;
            params.page = 1;
            refresh(params);
        }, 500);
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
