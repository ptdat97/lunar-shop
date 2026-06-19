// Render a product card from a ProductResource JSON object, producing the SAME
// markup as themes/fashion/views/components/product-card.blade.php so the SSR
// grid and the JS-rendered grid (after filter/sort/page) are pixel-identical.
//
// Helper module (leading underscore): imported by grid enhancers, not auto-run.

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

// First variant's formatted price — mirrors the Blade <x price> component.
function priceHtml(product) {
    const price = product.variants?.[0]?.price?.formatted;
    return price ? `<span class="product-card__price fw-semibold">${esc(price)}</span>` : '';
}

export function cardHtml(product) {
    const url = product.slug ? `/products/${esc(product.slug)}` : '#';
    const name = esc(product.name);
    const media = product.thumbnail
        ? `<img src="${esc(product.thumbnail)}" alt="${name}" loading="lazy">`
        : `<span class="d-flex h-100 align-items-center justify-content-center text-muted small">${name}</span>`;
    const brand = product.brand
        ? `<div class="text-muted small text-uppercase">${esc(product.brand)}</div>`
        : '';

    return `
<article class="product-card h-100 position-relative">
    <button class="btn btn-light btn-sm rounded-circle product-card__wishlist position-absolute top-0 end-0 m-2"
            data-wishlist-toggle data-product-id="${esc(product.id)}" aria-label="Add to wishlist" aria-pressed="false">
        <i class="bi bi-heart"></i>
    </button>
    <a href="${url}" class="d-block product-card__media rounded mb-2">${media}</a>
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
export function renderGrid(container, products, colClass = 'col-6 col-md-4 col-lg-3') {
    container.innerHTML = products
        .map((p) => `<div class="${colClass}">${cardHtml(p)}</div>`)
        .join('');
}
