// Full cart page. Personalised/session content → fetches /api/v1/cart on load
// (allowed SSR-first exception). Listens for cart:refreshed so it stays in sync
// with the mini-cart drawer, and emits cart:updated when it mutates lines.

import api from '../api.js';
import { CART_UPDATED, CART_REFRESHED, emit, on } from '../events.js';
import { renderGrid } from './_card.js';
import { appliedDiscountsHtml } from './cart.js';

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function rowHtml(line) {
    const img = line.thumbnail
        ? `<img src="${esc(line.thumbnail)}" alt="${esc(line.name)}" width="56" height="70" style="object-fit:cover" class="rounded">`
        : '<div class="bg-light rounded" style="width:56px;height:70px"></div>';
    const url = line.slug ? `/products/${esc(line.slug)}` : '#';
    return `
<tr data-line="${line.id}">
    <td style="width:72px">${img}</td>
    <td>
        <a href="${url}" class="text-dark text-decoration-none fw-semibold">${esc(line.name)}</a>
        <div class="text-muted small">${esc(line.sku ?? '')}</div>
    </td>
    <td>${esc(line.unit_price ?? '')}</td>
    <td>
        <div class="input-group input-group-sm" style="width:104px">
            <button class="btn btn-outline-secondary" type="button" data-qty-dec>−</button>
            <input class="form-control text-center" value="${line.quantity}" data-qty inputmode="numeric">
            <button class="btn btn-outline-secondary" type="button" data-qty-inc>+</button>
        </div>
    </td>
    <td class="text-end fw-semibold">
        ${esc(line.sub_total ?? '')}
        ${line.sub_total_original ? `<s class="text-muted fw-normal ms-1">${esc(line.sub_total_original)}</s>` : ''}
    </td>
    <td class="text-end"><button class="btn btn-link btn-sm text-danger p-0" data-line-remove>✕</button></td>
</tr>`;
}

export default function (root = document) {
    const page = root.querySelector('[data-cart-page]');
    if (!page || page.dataset.cartPageInit) return;
    page.dataset.cartPageInit = '1';

    const loading = page.querySelector('[data-cart-loading]');
    const content = page.querySelector('[data-cart-content]');
    const empty = page.querySelector('[data-cart-empty]');
    const linesEl = page.querySelector('[data-cart-lines]');
    const recs = page.querySelector('[data-cart-recommendations]');
    const recsGrid = recs?.querySelector('[data-cart-recommendations-grid]');
    let cartHasLines = false;

    function render(cart) {
        if (loading) loading.hidden = true;
        const lines = cart?.lines ?? [];
        cartHasLines = lines.length > 0;
        if (!lines.length) {
            if (content) content.hidden = true;
            if (empty) empty.hidden = false;
            if (recs) recs.hidden = true; // no recs alongside an empty cart
            return;
        }
        if (empty) empty.hidden = true;
        if (content) content.hidden = false;
        linesEl.innerHTML = lines.map(rowHtml).join('');

        const discounts = page.querySelector('[data-cart-discounts]');
        if (discounts) discounts.innerHTML = appliedDiscountsHtml(cart);

        page.querySelector('[data-sum-subtotal]').textContent = cart.totals?.sub_total ?? '—';
        page.querySelector('[data-sum-discount]').textContent = cart.totals?.discount_total ?? '—';
        page.querySelector('[data-sum-tax]').textContent = cart.totals?.tax_total ?? '—';
        page.querySelector('[data-sum-total]').textContent = cart.totals?.total ?? '—';

        const couponInput = page.querySelector('[data-coupon-input]');
        if (couponInput && cart.coupon_code) couponInput.value = cart.coupon_code;
    }

    async function load() {
        const { data } = await api.get('/cart');
        render(data.data ?? data);
        refreshRecommendations();
    }

    // "You may also like" — fetched separately so a slow/empty recommendation
    // never blocks rendering the cart. Hidden when empty (mirrors the drawer).
    async function refreshRecommendations() {
        if (!recs || !recsGrid) return;
        try {
            const { data } = await api.get('/cart/recommendations');
            const items = data.data ?? data ?? [];
            if (items.length && cartHasLines) {
                renderGrid(recsGrid, items);
                recs.hidden = false;
            } else {
                recsGrid.innerHTML = '';
                recs.hidden = true;
            }
        } catch {
            recs.hidden = true;
        }
    }

    async function mutate(promise) {
        const { data } = await promise;
        render(data.data ?? data);
        emit(CART_UPDATED); // keep the drawer/count in sync
    }

    // Qty / remove (delegated)
    page.addEventListener('click', (e) => {
        const wrap = e.target.closest('[data-line]');
        if (wrap) {
            const id = Number(wrap.dataset.line);
            const qty = Number(wrap.querySelector('[data-qty]')?.value || 1);
            if (e.target.closest('[data-line-remove]')) return mutate(api.delete(`/cart/lines/${id}`));
            if (e.target.closest('[data-qty-inc]')) return mutate(api.patch(`/cart/lines/${id}`, { quantity: qty + 1 }));
            if (e.target.closest('[data-qty-dec]')) {
                return qty > 1
                    ? mutate(api.patch(`/cart/lines/${id}`, { quantity: qty - 1 }))
                    : mutate(api.delete(`/cart/lines/${id}`));
            }
        }
    });
    page.addEventListener('change', (e) => {
        const input = e.target.closest('[data-qty]');
        if (!input) return;
        const id = Number(input.closest('[data-line]').dataset.line);
        mutate(api.patch(`/cart/lines/${id}`, { quantity: Math.max(1, Number(input.value || 1)) }));
    });

    // Coupon apply
    page.querySelector('[data-coupon-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const status = page.querySelector('[data-coupon-status]');
        const code = page.querySelector('[data-coupon-input]')?.value?.trim();
        if (!code) return;
        try {
            await mutate(api.post('/cart/coupon', { code }));
            if (status) { status.textContent = 'Coupon applied.'; status.className = 'small mt-1 text-success'; }
        } catch {
            if (status) { status.textContent = 'Invalid or expired coupon.'; status.className = 'small mt-1 text-danger'; }
        }
    });

    // Stay in sync with the drawer (refresh emitted there); don't re-emit.
    on(CART_REFRESHED, (e) => { if (e.detail?.cart) render(e.detail.cart); });

    load();
}
