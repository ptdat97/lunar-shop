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

// Translated labels embedded by the Blade view (data-product-i18n). Falls back
// to English so the enhancer still works if the block is absent.
function readI18n(root) {
    const tag = root.querySelector('[data-product-i18n]');
    const fallback = {
        add_to_cart: 'Add to cart', out_of_stock: 'Out of stock',
        select_options: 'Select options', in_stock: '%d in stock',
    };
    if (!tag) return fallback;
    try { return { ...fallback, ...JSON.parse(tag.textContent) }; } catch { return fallback; }
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

function buildOptionGroups(state, variants) {
    if (state.options && !Array.isArray(state.options)) {
        return Object.entries(state.options).map(([name, group]) => ({
            name,
            values: (group.values || [])
                .map((value) => value?.label ?? value?.value ?? value)
                .filter((value) => value !== undefined && value !== null && value !== ''),
        }));
    }

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

function variantKey(indexes = []) {
    return (indexes || []).map((index) => String(parseInt(index, 10))).join('-');
}

function signatureFor(constraints) {
    return Object.keys(constraints)
        .sort((a, b) => Number(a) - Number(b))
        .map((axis) => `${axis}:${constraints[axis]}`)
        .join('|');
}

function buildVariantIndex(variants) {
    const byKey = new Map();
    const partials = new Set();

    variants.forEach((variant) => {
        const indexes = variant.variant_indexes || [];
        const key = variant.variant_key || variantKey(indexes);
        byKey.set(key, variant);

        const total = indexes.length;
        const subsetCount = 2 ** total;
        for (let mask = 1; mask < subsetCount; mask += 1) {
            const constraints = {};
            indexes.forEach((valueIndex, axis) => {
                if (mask & (1 << axis)) constraints[axis] = parseInt(valueIndex, 10);
            });
            partials.add(signatureFor(constraints));
        }
    });

    return { byKey, partials };
}

// URL param key for an option — lowercased option name (e.g. "Color" → "color"),
// spaces to hyphens. Deep-linkable + human-readable: /products/x?color=red&size=m.
function paramKey(optionName) {
    return String(optionName).toLowerCase().trim().replace(/\s+/g, '-');
}

// Reflect the current selection into the URL query without reloading. Only
// chosen options are written; the path (slug) is preserved. A no-op when the
// resulting URL matches the current one (avoids redundant history writes).
function syncUrl(groups, selected) {
    const url = new URL(window.location.href);
    const params = url.searchParams;
    groups.forEach((g) => {
        const key = paramKey(g.name);
        const value = selected[g.name];
        if (value) params.set(key, value);
        else params.delete(key);
    });
    const next = url.pathname + (params.toString() ? `?${params}` : '') + url.hash;
    if (next !== window.location.pathname + window.location.search + window.location.hash) {
        window.history.replaceState(window.history.state, '', next);
    }
}

// Read the current URL query back into a { optionName: value } map, matching
// param keys to option groups. Used on load so a deep link preselects options.
function selectionFromUrl(groups) {
    const params = new URL(window.location.href).searchParams;
    const out = {};
    groups.forEach((g) => {
        const raw = params.get(paramKey(g.name));
        if (raw === null) return;
        // Match case-insensitively against the group's known values so the URL
        // is forgiving (?size=m resolves to value "M").
        const match = g.values.find((v) => String(v).toLowerCase() === raw.toLowerCase());
        if (match !== undefined) out[g.name] = match;
    });
    return out;
}

export default function (root = document) {
    const panel = root.querySelector('[data-product-detail]');
    if (!panel || panel.dataset.variantInit) return;
    panel.dataset.variantInit = '1';

    const state = readState(panel);
    if (!state || !Array.isArray(state.variants) || !state.variants.length) return;
    const t = readI18n(panel);

    const variants = state.variants;
    const groups = buildOptionGroups(state, variants);
    groups.forEach((group) => {
        group.valueIndexes = new Map(group.values.map((value, index) => [String(value), index]));
    });
    const variantIndex = buildVariantIndex(variants);
    const selected = {}; // option name → value

    // A variant's gallery: its own images when the admin assigned any (all sizes
    // of one colour share the same set), else the product-level images. Never
    // returns an empty list while the product has images, so choosing a colour
    // without its own photos shows the full product gallery rather than nothing.
    function imagesFor(variant) {
        const own = variant?.images;
        return (Array.isArray(own) && own.length) ? own : (state.images || []);
    }

    // Identity of an image set, so we only re-render when the set really changes.
    function galleryKey(images) {
        return (images || []).map((img) => img.id ?? img.large ?? '').join('|');
    }

    const priceEl = panel.querySelector('[data-product-price]');
    const stockEl = panel.querySelector('[data-product-stock]');
    const variantInput = panel.querySelector('[data-variant-input]');
    const addBtn = panel.querySelector('[data-add-to-cart-btn]');

    // Pre-select the first variant's options so JS state matches the SSR view,
    // then let any deep-link query (?color=red&size=m) override it. The SSR
    // controller already preselected the same variant, so this just re-syncs the
    // JS state without a flash.
    const first = variants[0];
    (first?.options || []).forEach(({ option, value }) => { selected[option] = value; });
    Object.assign(selected, selectionFromUrl(groups));

    // The SSR gallery already shows the first variant's images. Track the image
    // SET (by id), not the variant id: every size of a colour shares one set, so
    // keying on the variant id would tear down and rebuild an identical gallery
    // — losing the visitor's slide position — on every size change.
    let lastGalleryKey = galleryKey(imagesFor(first));

    function selectionIndexes(candidate = null) {
        const constraints = {};

        groups.forEach((group, axis) => {
            const chosen = candidate?.name === group.name ? candidate.value : selected[group.name];
            if (!chosen) return;

            const valueIndex = group.valueIndexes.get(String(chosen));
            if (valueIndex !== undefined) constraints[axis] = valueIndex;
        });

        return constraints;
    }

    function currentVariant(allChosen) {
        if (!groups.length) return variants[0] ?? null;
        if (!allChosen) return null;

        const constraints = selectionIndexes();
        const indexes = groups.map((_, axis) => constraints[axis]);

        return variantIndex.byKey.get(variantKey(indexes)) ?? null;
    }

    function isAvailable(optionName, value) {
        const constraints = selectionIndexes({ name: optionName, value });

        return variantIndex.partials.has(signatureFor(constraints));
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

        const allChosen = !groups.length || groups.every((g) => selected[g.name]);
        const variant = currentVariant(allChosen);
        const inStock = (variant?.stock ?? 0) > 0;

        if (priceEl && variant?.price?.formatted) renderPrice(priceEl, variant, state.promotion);
        if (stockEl) stockEl.textContent = variant ? (inStock ? t.in_stock.replace('%d', variant.stock) : t.out_of_stock) : '';
        if (variantInput && variant) variantInput.value = variant.id;

        if (addBtn) {
            const canAdd = variant && inStock && allChosen;
            addBtn.disabled = !canAdd;
            addBtn.textContent = !allChosen ? t.select_options : (!inStock ? t.out_of_stock : t.add_to_cart);
        }

        if (variant) {
            const images = imagesFor(variant);
            const key = galleryKey(images);
            if (key !== lastGalleryKey) {
                lastGalleryKey = key;
                MediaUrlGallery(root, images, state.name);
            }
        }

        // Keep the URL in step with the current selection (deep-linkable, no reload).
        syncUrl(groups, selected);

        // Let other enhancers (e.g. notify-me) react to the selected variant
        // without coupling to this module's internals.
        panel.dispatchEvent(new CustomEvent('variant:changed', {
            bubbles: true,
            detail: { variant, inStock, allChosen },
        }));
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
