// Fashion theme bootstrap.
//
// Two layers (see lunarphp_sme_fashion_theme_plan.md §3):
//   1. Vanilla enhancers — the default. Each enhance/*.js exports
//      `default fn(root = document)` and targets its own markup via data-*.
//   2. Vue islands — ONLY the allow-listed three. A [data-vue] outside the
//      allow-list is intentionally ignored (no accidental client-render).
//
// CSS is a separate Vite entry (themes/fashion/css/app.css), loaded via @vite
// in the layout — not imported here.

import { createApp } from 'vue';

// --- Vue islands allow-list -----------------------------------------------
// quick-view is intentionally deferred (not built yet).
import ProductPurchase from './islands/ProductPurchase.vue';
import ProductDetail from './islands/ProductDetail.vue';
import CheckoutPage from './islands/CheckoutPage.vue';

const VUE_ISLANDS = {
    'product-purchase': ProductPurchase,
    'product-detail': ProductDetail,
    'checkout-page': CheckoutPage,
};

function mountIslands(root = document) {
    root.querySelectorAll('[data-vue]').forEach((el) => {
        const component = VUE_ISLANDS[el.dataset.vue];
        if (!component) return; // not allow-listed → leave the SSR markup as-is
        if (el.dataset.mounted) return;

        createApp(component, readIslandState(el)).mount(el);
        el.dataset.mounted = '1';
    });
}

// Read a sibling/child <script type="application/json" data-island-state> so
// islands hydrate from the SSR payload instead of fetching on mount.
function readIslandState(el) {
    const tag = el.querySelector('[data-island-state]')
        || el.parentElement?.querySelector('[data-island-state]');
    if (!tag) return {};
    try {
        return JSON.parse(tag.textContent);
    } catch {
        return {};
    }
}

// --- Vanilla enhancers (filled in Bước 2–5) -------------------------------
// Eager-glob so adding a file under enhance/ auto-registers it. The leading
// underscore convention (e.g. _card.js) marks render helpers that are imported
// by other enhancers rather than auto-run on load.
const enhancers = import.meta.glob('./enhance/*.js', { eager: false });

function runEnhancers(root = document) {
    Object.entries(enhancers).forEach(([path, load]) => {
        if (/\/_/.test(path)) return; // helper, not an auto-run enhancer
        load().then((mod) => mod.default?.(root));
    });
}

function boot() {
    runEnhancers(document);
    mountIslands(document);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
