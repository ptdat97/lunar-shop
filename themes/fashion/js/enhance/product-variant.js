// Product variant picker (vanilla). Replaces the former Vue island.
//
// Reads the embedded ProductResource ($state in [data-product-state]) — same
// shape as GET /api/v1/products/{slug} — and, when option values are chosen,
// resolves the matching variant to update: price, stock, the add-to-cart hidden
// input + button state, and the gallery images. SSR pre-selects the first
// variant, so the page is complete with no JS. Listens for `size:recommended`
// (size-finder) to preselect a size. PhotoSwipe re-inits on gallery change.

import { MediaUrlGallery, initGalleryLightbox } from './_gallery.js';

function readState(root) {
    const tag = root.querySelector('[data-product-state]');
    if (!tag) return null;
    try { return JSON.parse(tag.textContent); } catch { return null; }
}

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

// Render the price for the chosen variant. When the product has an
// unconditional percentage price break (flash sale / sale), strike the variant
// original and show the discounted price; otherwise just the variant price.
// Matches the SSR markup in pages/product.blade.php (data-price-sale/original).
function renderPrice(el, variant, promotion) {
    const original = variant.price.formatted;
    const pct = (promotion && promotion.has_price_break) ? promotion.percentage : null;

    if (pct && Number.isFinite(variant.price.amount) && variant.price.currency) {
        const saleAmount = variant.price.amount * (1 - pct / 100);
        let saleFormatted;
        try {
            saleFormatted = new Intl.NumberFormat(undefined, {
                style: 'currency', currency: variant.price.currency,
            }).format(saleAmount);
        } catch {
            saleFormatted = saleAmount.toFixed(2);
        }
        el.innerHTML = `<span class="text-danger me-2" data-price-sale>${esc(saleFormatted)}</span>`
            + `<span class="text-muted text-decoration-line-through fs-6" data-price-original>${esc(original)}</span>`;
        return;
    }

    el.innerHTML = `<span data-price-sale>${esc(original)}</span>`;
}

function buildOptionGroups(variants) {
    const groups = new Map();
    variants.forEach((v) => {
        (v.options || []).forEach(({ option, value }) => {
            if (!option) return;
            if (!groups.has(option)) groups.set(option, new Set());
            groups.get(option).add(value);
        });
    });
    return [...groups.entries()].map(([name, values]) => ({ name, values: [...values] }));
}

export default function (root = document) {
    const panel = root.querySelector('[data-product-detail]');
    if (!panel || panel.dataset.variantInit) return;
    panel.dataset.variantInit = '1';

    const state = readState(panel);
    if (!state || !Array.isArray(state.variants) || !state.variants.length) return;

    const variants = state.variants;
    const groups = buildOptionGroups(variants);
    const selected = {}; // option name → value

    const priceEl = panel.querySelector('[data-product-price]');
    const stockEl = panel.querySelector('[data-product-stock]');
    const variantInput = panel.querySelector('[data-variant-input]');
    const addBtn = panel.querySelector('[data-add-to-cart-btn]');

    // Pre-select the first variant's options so JS state matches the SSR view.
    const first = variants[0];
    (first?.options || []).forEach(({ option, value }) => { selected[option] = value; });

    // The SSR gallery already shows the first variant; only re-render the
    // gallery when the chosen variant actually changes.
    let lastGalleryVariantId = first?.id ?? null;

    function currentVariant() {
        if (!groups.length) return variants[0] ?? null;
        return variants.find((v) =>
            groups.every((g) => {
                const chosen = selected[g.name];
                return chosen && (v.options || []).some((o) => o.option === g.name && o.value === chosen);
            })
        ) ?? null;
    }

    function isAvailable(optionName, value) {
        return variants.some((v) => {
            if (!(v.options || []).some((o) => o.option === optionName && o.value === value)) return false;
            return groups.every((g) => {
                if (g.name === optionName) return true;
                const chosen = selected[g.name];
                return !chosen || (v.options || []).some((o) => o.option === g.name && o.value === chosen);
            });
        });
    }

    function render() {
        // Button states (active + disabled-impossible).
        panel.querySelectorAll('[data-option]').forEach((btn) => {
            const name = btn.dataset.option;
            const value = btn.dataset.value;
            const active = selected[name] === value;
            btn.classList.toggle('btn-dark', active);
            btn.classList.toggle('btn-outline-dark', !active);
            btn.disabled = !isAvailable(name, value);
        });

        const variant = currentVariant();
        const allChosen = !groups.length || groups.every((g) => selected[g.name]);
        const inStock = (variant?.stock ?? 0) > 0;

        if (priceEl && variant?.price?.formatted) renderPrice(priceEl, variant, state.promotion);
        if (stockEl) stockEl.textContent = variant ? (inStock ? `${variant.stock} in stock` : 'Out of stock') : '';
        if (variantInput && variant) variantInput.value = variant.id;

        if (addBtn) {
            const canAdd = variant && inStock && allChosen;
            addBtn.disabled = !canAdd;
            addBtn.textContent = !allChosen ? 'Select options' : (!inStock ? 'Out of stock' : 'Add to cart');
        }

        if (variant && variant.id !== lastGalleryVariantId) {
            lastGalleryVariantId = variant.id;
            swapGallery(variant);
        }
    }

    // Swap the gallery to the variant's images (fall back to product images).
    function swapGallery(variant) {
        const images = (variant.images && variant.images.length) ? variant.images : state.images;
        if (!images || !images.length) return;
        MediaUrlGallery(root, images, state.name);
    }

    panel.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-option]');
        if (!btn || btn.disabled) return;
        const { option, value } = btn.dataset;
        selected[option] = selected[option] === value ? undefined : value;
        render();
    });

    // Size Intelligence: "find my size" picks a size → preselect it.
    window.addEventListener('size:recommended', (e) => {
        const size = e.detail?.size;
        if (!size) return;
        const group = groups.find((g) => /size/i.test(g.name) && g.values.includes(size))
            || groups.find((g) => g.values.includes(size));
        if (group) { selected[group.name] = size; render(); }
    });

    // Init PhotoSwipe on the SSR-rendered gallery; render syncs button states.
    initGalleryLightbox();
    render();
}
