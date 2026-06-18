/*
| Render a product card from the JSON Resource shape (ProductResource), matching
| themes/modave/views/components/product-card.blade.php so dynamically-rendered
| grids (collection, search) keep the same markup, wishlist + add-to-cart hooks.
*/

const PLACEHOLDER = '/themes/modave/images/products/womens/women-19.jpg';

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

export function cardHtml(p) {
    const url = p.slug ? `/products/${encodeURIComponent(p.slug)}` : '#';
    const img = p.thumbnail || PLACEHOLDER;
    const variant = p.variants?.[0];
    const price = variant?.price?.formatted;

    return `
    <div class="card-product">
        <div class="card-product-wrapper">
            <a href="${url}" class="product-img">
                <img class="lazyload img-product" data-src="${esc(img)}" src="${esc(img)}" alt="${esc(p.name)}">
                <img class="lazyload img-hover" data-src="${esc(img)}" src="${esc(img)}" alt="${esc(p.name)}">
            </a>
            <div class="list-product-btn">
                <a href="javascript:void(0);" class="box-icon wishlist btn-icon-action" data-wishlist-toggle="${p.id}">
                    <span class="icon icon-heart"></span>
                    <span class="tooltip">Add to wishlist</span>
                </a>
                <a href="javascript:void(0);" class="box-icon quickview"
                   onclick="window.dispatchEvent(new CustomEvent('quickview:open',{detail:{slug:'${esc(p.slug)}'}}))">
                    <span class="icon icon-eye"></span><span class="tooltip">Quick View</span>
                </a>
            </div>
            <div class="list-btn-main">
                <button type="button" class="btn-main-product" data-add-to-cart data-variant="${variant?.id ?? ''}">Add To cart</button>
            </div>
        </div>
        <div class="card-product-info">
            <a href="${url}" class="title link">${esc(p.name)}</a>
            ${price ? `<span class="price">${esc(price)}</span>` : ''}
        </div>
    </div>`;
}

export function renderGrid(gridEl, products) {
    gridEl.innerHTML = products.map(cardHtml).join('');
}
