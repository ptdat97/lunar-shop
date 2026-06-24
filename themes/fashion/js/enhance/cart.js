// Mini-cart drawer + header count. Vanilla; cart state lives server-side
// (Lunar cart), JS only renders /api/v1/cart and keeps consumers in sync.
//
// Event flow (see events.js):
//   cart:updated   → this refreshes from the API, then emits…
//   cart:refreshed → …so the cart page / other consumers re-render.
// A refresh never re-emits cart:updated (no loop).

import api from '../api.js';
import { CART_UPDATED, CART_REFRESHED, emit, on } from '../events.js';
import { renderGrid } from './_card.js';

let lastCart = null;

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function lineHtml(line) {
    const img = line.thumbnail
        ? `<img src="${esc(line.thumbnail)}" alt="${esc(line.name)}" width="64" height="80" style="object-fit:cover" class="rounded">`
        : '<div class="bg-light rounded" style="width:64px;height:80px"></div>';
    const url = line.slug ? `/products/${esc(line.slug)}` : '#';
    return `
<div class="d-flex gap-3 py-3 border-bottom" data-line="${line.id}">
    ${img}
    <div class="flex-grow-1">
        <a href="${url}" class="text-dark text-decoration-none small fw-semibold d-block">${esc(line.name)}</a>
        <div class="text-muted small">${esc(line.sku ?? '')}</div>
        <div class="d-flex align-items-center justify-content-between mt-2">
            <div class="input-group input-group-sm" style="width:104px">
                <button class="btn btn-outline-secondary" type="button" data-qty-dec aria-label="Decrease">−</button>
                <input class="form-control text-center" value="${line.quantity}" data-qty inputmode="numeric" aria-label="Quantity">
                <button class="btn btn-outline-secondary" type="button" data-qty-inc aria-label="Increase">+</button>
            </div>
            <span class="small fw-semibold">${esc(line.sub_total ?? '')}</span>
        </div>
        <button class="btn btn-link btn-sm text-danger p-0 mt-1" type="button" data-line-remove>Remove</button>
    </div>
</div>`;
}

// One labelled row per applied promotion. `flash` rows get a lightning icon.
// Reused by the cart page enhancer via the exported helper below.
export function appliedDiscountsHtml(cart) {
    const list = cart?.applied_discounts ?? [];
    if (!list.length) return '';
    return list.map((d) => {
        const icon = d.is_flash_sale
            ? '<i class="bi bi-lightning-charge-fill"></i>'
            : '<i class="bi bi-tag"></i>';
        const desc = d.description && d.description !== d.name
            ? ` <span class="text-muted">(${esc(d.description)})</span>`
            : '';
        return `
<div class="d-flex justify-content-between mb-1 small text-success">
    <span>${icon} ${esc(d.name)}${desc}</span>
    <span>−${esc(d.amount)}</span>
</div>`;
    }).join('');
}

function renderCount(cart) {
    document.querySelectorAll('[data-cart-count]').forEach((el) => {
        const n = cart?.lines_count ?? 0;
        el.textContent = n;
        el.hidden = n <= 0;
    });
}

function renderDrawer(cart) {
    const body = document.querySelector('#shoppingCart [data-cart-body]');
    const empty = document.querySelector('#shoppingCart [data-cart-empty]');
    const footer = document.querySelector('#shoppingCart [data-cart-footer]');
    const shipping = document.querySelector('#shoppingCart [data-cart-shipping]');
    if (!body) return;

    const lines = cart?.lines ?? [];
    if (!lines.length) {
        body.innerHTML = '';
        if (empty) empty.hidden = false;
        if (footer) footer.hidden = true;
        if (shipping) shipping.hidden = true;
        return;
    }

    if (empty) empty.hidden = true;
    if (footer) footer.hidden = false;
    body.innerHTML = lines.map(lineHtml).join('');

    const subtotal = document.querySelector('#shoppingCart [data-cart-subtotal]');
    if (subtotal) subtotal.textContent = cart.totals?.sub_total ?? '';

    // Applied promotions (flash sale / quantity / combo / coupon / membership).
    const discounts = document.querySelector('#shoppingCart [data-cart-discounts]');
    if (discounts) discounts.innerHTML = appliedDiscountsHtml(cart);

    // Total savings line below the labelled promotions.
    const savingsRow = document.querySelector('#shoppingCart [data-cart-savings-row]');
    const savings = document.querySelector('#shoppingCart [data-cart-savings]');
    if (savingsRow && savings) {
        const hasDiscount = (cart.totals?.discount_value ?? 0) > 0;
        savingsRow.hidden = !hasDiscount;
        savings.textContent = cart.totals?.discount_total ?? '';
    }

    if (shipping && cart.free_shipping) {
        shipping.hidden = false;
        shipping.textContent = cart.free_shipping.qualified
            ? 'You’ve unlocked free shipping!'
            : `Add ${cart.free_shipping.remaining} more for free shipping.`;
    } else if (shipping) {
        shipping.hidden = true;
    }
}

function render(cart) {
    lastCart = cart;
    renderCount(cart);
    renderDrawer(cart);
}

// "You may also like" — fetched separately from the cart so a slow/empty
// recommendation never blocks rendering the cart itself. Hidden when empty.
async function refreshRecommendations() {
    const block = document.querySelector('#shoppingCart [data-cart-recommendations]');
    const grid = block?.querySelector('[data-cart-recommendations-grid]');
    if (!block || !grid) return;

    try {
        const { data } = await api.get('/cart/recommendations');
        const items = data.data ?? data ?? [];
        if (items.length) {
            renderGrid(grid, items, 'col-6');
            block.hidden = false;
        } else {
            grid.innerHTML = '';
            block.hidden = true;
        }
    } catch {
        block.hidden = true;
    }
}

async function refresh() {
    const { data } = await api.get('/cart');
    render(data.data ?? data);
    emit(CART_REFRESHED, { cart: lastCart });
}

// Optimistic-free line mutation: call API, re-render with the response.
async function mutateLine(lineId, quantity) {
    const { data } = await api.patch(`/cart/lines/${lineId}`, { quantity });
    render(data.data ?? data);
    emit(CART_REFRESHED, { cart: lastCart });
}

async function removeLine(lineId) {
    const { data } = await api.delete(`/cart/lines/${lineId}`);
    render(data.data ?? data);
    emit(CART_REFRESHED, { cart: lastCart });
}

export default function (root = document) {
    const drawerEl = document.getElementById('shoppingCart');
    if (drawerEl && !drawerEl.dataset.cartInit) {
        drawerEl.dataset.cartInit = '1';

        // Fetch fresh contents each time the drawer opens.
        drawerEl.addEventListener('show.bs.offcanvas', () => { refresh(); refreshRecommendations(); });

        // Qty +/- , manual qty edit, remove — delegated (survives re-render).
        drawerEl.addEventListener('click', (e) => {
            const wrap = e.target.closest('[data-line]');
            if (!wrap) return;
            const id = Number(wrap.dataset.line);
            const input = wrap.querySelector('[data-qty]');
            const qty = Number(input?.value || 1);

            if (e.target.closest('[data-line-remove]')) removeLine(id);
            else if (e.target.closest('[data-qty-inc]')) mutateLine(id, qty + 1);
            else if (e.target.closest('[data-qty-dec]')) qty > 1 ? mutateLine(id, qty - 1) : removeLine(id);
        });
        drawerEl.addEventListener('change', (e) => {
            const input = e.target.closest('[data-qty]');
            if (!input) return;
            const id = Number(input.closest('[data-line]').dataset.line);
            const qty = Math.max(1, Number(input.value || 1));
            mutateLine(id, qty);
        });
    }

    // Someone changed the cart elsewhere → refresh the drawer + count.
    if (!window.__cartBusBound) {
        window.__cartBusBound = true;
        on(CART_UPDATED, () => { refresh(); });
    }

    // Lightweight count on initial load (no drawer render needed).
    refresh();
}
