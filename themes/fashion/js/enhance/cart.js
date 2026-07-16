// Mini-cart drawer + header count. Vanilla; cart state lives server-side
// (Lunar cart), JS only renders /api/v1/cart and keeps consumers in sync.
//
// Event flow (see events.js):
//   cart:updated   → this refreshes from the API (or renders detail.cart when
//                    the mutation response already carries it), then emits…
//   cart:refreshed → …so the cart page / other consumers re-render.
// A refresh never re-emits cart:updated (no loop).
//
// Every cart mutation endpoint returns the full updated cart, so a mutation
// that passes it along costs ZERO extra GETs: without this, one add-to-cart
// fired three /cart requests (POST + cart:updated GET + drawer-open GET).

import api from '../api.js';
import { CART_UPDATED, CART_REFRESHED, emit, on } from '../events.js';
import { renderGrid } from './_card.js';

let lastCart = null;

// When the last render happened; the drawer-open refresh is skipped while the
// state is this fresh (an add-to-cart renders the POST response, then opens
// the drawer milliseconds later — re-fetching would be a duplicate).
const FRESH_MS = 1000;
let lastRenderAt = 0;

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
<div class="d-flex gap-3 py-3" data-line="${line.id}">
    ${img}
    <div class="flex-grow-1">
        <a href="${url}" class="text-dark text-decoration-none small fw-semibold d-block">${esc(line.name)}</a>
        <div class="text-muted small">${esc(line.sku ?? '')}</div>
        <div class="d-flex align-items-center justify-content-between mt-2">
            <div class="input-group input-group-sm" style="width:104px">
                <button class="btn" type="button" data-qty-dec aria-label="Decrease">−</button>
                <input class="form-control text-center" value="${line.quantity}" data-qty inputmode="numeric" aria-label="Quantity">
                <button class="btn" type="button" data-qty-inc aria-label="Increase">+</button>
            </div>
            <span class="small fw-semibold">
                ${esc(line.sub_total ?? '')}
                ${line.sub_total_original ? `<s class="text-muted fw-normal ms-1">${esc(line.sub_total_original)}</s>` : ''}
            </span>
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

    const total = document.querySelector('#shoppingCart [data-cart-total]');
    if (total) total.textContent = cart.totals?.total ?? '';

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
    lastRenderAt = Date.now();
    renderCount(cart);
    renderDrawer(cart);
}

// Render a cart we already have (a mutation response) and notify consumers —
// same contract as refresh(), minus the GET.
function renderAndNotify(cart) {
    render(cart);
    emit(CART_REFRESHED, { cart: lastCart });
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

// In-flight dedupe: concurrent triggers (cart:updated + drawer open landing in
// the same tick) share one GET instead of stacking duplicates.
let inflight = null;

function refresh() {
    inflight ??= api.get('/cart')
        .then(({ data }) => { renderAndNotify(data.data ?? data); })
        .finally(() => { inflight = null; });
    return inflight;
}

// Optimistic-free line mutation: call API, re-render with the response.
async function mutateLine(lineId, quantity) {
    const { data } = await api.patch(`/cart/lines/${lineId}`, { quantity });
    renderAndNotify(data.data ?? data);
}

async function removeLine(lineId) {
    const { data } = await api.delete(`/cart/lines/${lineId}`);
    renderAndNotify(data.data ?? data);
}

export default function (root = document) {
    const drawerEl = document.getElementById('shoppingCart');
    if (drawerEl && !drawerEl.dataset.cartInit) {
        drawerEl.dataset.cartInit = '1';

        // Closing the drawer must not move the page. Bootstrap's data-api
        // returns focus to the toggle that opened it — without preventScroll —
        // and focus() honours scroll-padding-top, so focusing the toggle inside
        // the sticky header makes the browser scroll the whole page up by
        // ~header height (smoothly, thanks to scroll-behavior: smooth).
        // Pre-focusing the toggle with preventScroll turns Bootstrap's later
        // focus() into a no-op, and the next-frame restore pins the scroll
        // position in case the browser scrolled anyway. Runs before Bootstrap's
        // handler: this listener binds at init, Bootstrap's on toggle click.
        drawerEl.addEventListener('hidden.bs.offcanvas', () => {
            const x = window.scrollX;
            const y = window.scrollY;
            document.querySelector('[data-cart-toggle]')?.focus({ preventScroll: true });
            requestAnimationFrame(() => {
                if (window.scrollX !== x || window.scrollY !== y) {
                    window.scrollTo({ left: x, top: y, behavior: 'instant' });
                }
            });
        });

        // Fetch fresh contents each time the drawer opens — unless a mutation
        // just rendered the cart (add-to-cart opens the drawer right after
        // rendering its POST response; re-fetching would be a duplicate).
        drawerEl.addEventListener('show.bs.offcanvas', () => {
            if (Date.now() - lastRenderAt > FRESH_MS) refresh();
            refreshRecommendations();
        });

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

    // Someone changed the cart elsewhere → sync the drawer + count. Mutations
    // whose response already carries the cart pass it in detail.cart, which
    // renders directly (no GET); emitters without it fall back to a fetch.
    if (!window.__cartBusBound) {
        window.__cartBusBound = true;
        on(CART_UPDATED, (e) => {
            const cart = e.detail?.cart;
            cart ? renderAndNotify(cart) : refresh();
        });
    }

    // Lightweight count on initial load (no drawer render needed).
    refresh();
}
