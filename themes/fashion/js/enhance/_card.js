// Render a product card from a ProductResource JSON object, producing the SAME
// markup as themes/fashion/views/components/product-card.blade.php so the SSR
// grid and the JS-rendered grid (after filter/sort/page) are pixel-identical.
//
// Helper module (leading underscore): imported by grid enhancers, not auto-run.

import { gridConfig } from '../config/grid.js';

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

// First variant's formatted price — mirrors the Blade <x price> component.
// When the product has a promotion price break, strike the original and show
// the sale price (matches themes/fashion/views/components/price.blade.php).
function priceHtml(product) {
    const promo = product.promotion;
    if (promo && promo.has_price_break) {
        return `<span class="product-card__price fw-semibold text-danger me-1">${esc(promo.sale)}</span>`
            + `<span class="product-card__price-original text-muted text-decoration-line-through small">${esc(promo.original)}</span>`;
    }
    const price = product.variants?.[0]?.price?.formatted;
    return price ? `<span class="product-card__price fw-semibold">${esc(price)}</span>` : '';
}

// Promotion badge (top-left) — mirrors product-card.blade.php.
function badgeHtml(product) {
    const promo = product.promotion;
    if (!promo) return '';
    const flash = promo.is_flash_sale;
    const deadline = promo.ends_at ? ` data-promo-deadline="${esc(promo.ends_at)}"` : '';
    const icon = flash ? '<i class="bi bi-lightning-charge-fill me-1"></i>' : '';
    return `<span class="product-card__badge badge position-absolute top-0 start-0 m-2 ${flash ? 'bg-danger' : 'bg-dark'}"${deadline}>${icon}${esc(promo.label)}</span>`;
}

export function cardHtml(product) {
    const url = product.slug ? `/products/${esc(product.slug)}` : '#';
    const name = esc(product.name);
    const hover = product.hover_thumbnail
        ? `<img src="${esc(product.hover_thumbnail)}" alt="" class="product-card__img product-card__img--hover" loading="lazy" aria-hidden="true">`
        : '';
    const media = product.thumbnail
        ? `<img src="${esc(product.thumbnail)}" alt="${name}" class="product-card__img" loading="lazy">${hover}`
        : `<span class="d-flex h-100 align-items-center justify-content-center text-muted small">${name}</span>`;
    const brand = product.brand
        ? `<div class="product-card__brand">${esc(product.brand)}</div>`
        : '';

    return `
<article class="product-card h-100 position-relative">
    ${badgeHtml(product)}
    <button class="btn btn-light btn-sm rounded-circle product-card__wishlist position-absolute top-0 end-0 m-2"
            data-wishlist-toggle data-product-id="${esc(product.id)}" aria-label="Add to wishlist" aria-pressed="false">
        <i class="bi bi-heart"></i>
    </button>
    <a href="${url}" class="d-block product-card__media rounded mb-2${product.hover_thumbnail ? ' has-hover' : ''}">${media}</a>
    <div class="product-card__body">
        ${brand}
        <h3 class="product-card__title mb-1">
            <a href="${url}" class="text-dark text-decoration-none">${name}</a>
        </h3>
        ${priceHtml(product)}
    </div>
</article>`;
}

// Render an array of products into a grid container, each wrapped in the same
// Bootstrap column the SSR grid uses.
export function renderGrid(container, products, colClass = gridConfig.default) {
    container.innerHTML = products
        .map((p) => `<div class="${colClass}">${cardHtml(p)}</div>`)
        .join('');
}
