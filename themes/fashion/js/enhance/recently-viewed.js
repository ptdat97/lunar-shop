// Recently viewed (localStorage). On a product page the strip carries
// data-current-slug: we record it (newest-first, de-duped, capped) then render
// the OTHER recently-viewed products. Personalised → not SEO, so client-render
// + fetch is fine. Cards render via _card.js so they match the SSR grid exactly.

import api from '../api.js';
import { renderGrid } from './_card.js';
import { gridConfig } from '../config/grid.js';

const KEY = 'recently_viewed';
const MAX = 12;

function read() {
    try {
        const raw = localStorage.getItem(KEY);
        const list = raw ? JSON.parse(raw) : [];
        return Array.isArray(list) ? list.filter((s) => typeof s === 'string') : [];
    } catch {
        return [];
    }
}

function record(slug) {
    if (!slug) return read();
    const list = [slug, ...read().filter((s) => s !== slug)].slice(0, MAX);
    try { localStorage.setItem(KEY, JSON.stringify(list)); } catch { /* quota / private mode */ }
    return list;
}

export default async function (root = document) {
    const section = root.querySelector('[data-recently-viewed]');
    if (!section || section.dataset.rvInit) return;
    section.dataset.rvInit = '1';

    const grid = section.querySelector('[data-recently-viewed-grid]');
    const current = section.dataset.currentSlug || null;

    // How many to show — admin-configured (Catalog settings) via data-limit,
    // clamped to the stored cap. Falls back to 8 if the attribute is absent.
    const limit = Math.min(MAX, Math.max(1, parseInt(section.dataset.limit, 10) || 8));

    // Record the current product (if any), then build the display list excluding it.
    const stored = record(current);
    const slugs = stored.filter((s) => s !== current).slice(0, limit);

    if (!grid || slugs.length === 0) return; // nothing else to show yet

    try {
        const { data } = await api.get('/products', { params: { slugs: slugs.join(',') } });
        const products = Array.isArray(data?.data) ? data.data : [];
        if (!products.length) return;

        renderGrid(grid, products, gridConfig.recentlyViewed);
        section.hidden = false;
        // Let wishlist re-decorate the freshly rendered cards.
        window.dispatchEvent(new CustomEvent('grid:rendered', { detail: { root: grid } }));
    } catch {
        // Network failure → leave the strip hidden, no UI noise.
    }
}
