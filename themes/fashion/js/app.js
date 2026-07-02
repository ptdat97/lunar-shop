// Fashion theme bootstrap.
//
// The storefront is Blade SSR + vanilla enhancers — no Vue. Each enhance/*.js
// exports `default fn(root = document)` and targets its own markup via data-*
// attributes. Pages render fully server-side; JS only progressively enhances
// (cart drawer, variant picker, checkout address dropdowns, filters, etc.).
//
// CSS is a separate Vite entry (themes/fashion/css/app.scss), loaded via @vite
// in the layout — not imported here.

// Bootstrap JS from npm — provides Bootstrap's JS components (dropdown, modal,
// collapse, offcanvas, carousel, etc.) via data-bs-* attributes AND expose it as
// window.bootstrap so enhancers can drive components programmatically
// (e.g. add-to-cart.js opening the mini-cart Offcanvas, size-finder Modal).
// Importing the module for side-effects alone wires the data-bs-* auto-init but
// leaves window.bootstrap undefined, so those programmatic calls silently no-op.
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Eager-glob so adding a file under enhance/ auto-registers it. The leading
// underscore convention (e.g. _card.js, _gallery.js) marks render helpers that
// are imported by other enhancers rather than auto-run on load.
const enhancers = import.meta.glob('./enhance/*.js', { eager: false });

function runEnhancers(root = document) {
    Object.entries(enhancers).forEach(([path, load]) => {
        if (/\/_/.test(path)) return; // helper, not an auto-run enhancer
        load().then((mod) => mod.default?.(root));
    });
}

function boot() {
    runEnhancers(document);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
