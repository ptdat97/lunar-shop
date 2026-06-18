import axios from 'axios';
import { createApp } from 'vue';

/*
| Modave theme = Blade SSR + Vanilla JS, with Vue reserved ONLY for the core
| commerce islands where rich client state genuinely pays off:
|   - product-purchase  (variant picker)
|   - cart-page, cart-drawer, cart-count  (cart)
|   - checkout-page     (checkout)
|   - quick-view        (variant + add-to-cart in a modal)
|
| Everything else (collection filters, search, wishlist, auth, per-card add-to-cart)
| is Blade-rendered and progressively enhanced with vanilla modules under ./enhance/*.
*/
window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

const csrf = document.querySelector('meta[name="csrf-token"]');
if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
}

/* -------------------------------------------------------------------------- */
/* Vue islands — core commerce only.                                          */
/* -------------------------------------------------------------------------- */

// Allow-list: only these data-vue names mount a Vue app (core commerce).
const VUE_ISLANDS = new Set([
    'product-purchase',  // variant picker
    'cart-page',
    'cart-drawer',
    'cart-count',
    'checkout-page',
    'quick-view',        // variant + add-to-cart in a modal
]);

const islands = import.meta.glob('./components/*.vue', { eager: true });
const registry = {};
for (const path in islands) {
    registry[path.split('/').pop().replace('.vue', '')] = islands[path].default;
}

document.querySelectorAll('[data-vue]').forEach((el) => {
    const name = el.dataset.vue;
    if (!VUE_ISLANDS.has(name)) return;          // non-core → handled by vanilla

    const component = registry[name];
    if (!component) return;

    const props = { ...el.dataset };
    delete props.vue;

    // SSR-first hydration: parse the embedded payload BEFORE mount (Vue clears
    // the container, so the island can't read it from setup()).
    const stateEl = el.querySelector(':scope > [data-island-state]');
    if (stateEl) {
        try { props.initialState = JSON.parse(stateEl.textContent); } catch { props.initialState = null; }
    }

    const app = createApp(component, props);
    for (const [n, child] of Object.entries(registry)) app.component(n, child);
    app.mount(el);
});

/* -------------------------------------------------------------------------- */
/* Vanilla enhancement modules — Blade SSR + progressive JS.                   */
/* Each module exports default fn(root=document) and self-targets via data-*.  */
/* -------------------------------------------------------------------------- */

const enhancers = import.meta.glob('./enhance/*.js', { eager: true });

function runEnhancers(root = document) {
    for (const path in enhancers) {
        const fn = enhancers[path].default;
        if (typeof fn === 'function') {
            try { fn(root); } catch (e) { /* keep other enhancers alive */ console.error(path, e); }
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => runEnhancers());
} else {
    runEnhancers();
}
