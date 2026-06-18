/*
| Cart page (vanilla) — table + order summary, fetched from /api/v1/cart.
| Replaces cart-page.vue. Cart is per-session (not SEO) so a client fetch is fine.
| Stays in sync with the mini-cart via the `cart:updated` / `cart:refreshed` events.
*/

const PLACEHOLDER = '/themes/modave/images/products/womens/women-8.jpg';

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

let state = { lines: [], totals: {}, coupon_code: null, free_shipping: null };
let coupons = [];

function rowHtml(line) {
    const url = line.slug ? `/products/${encodeURIComponent(line.slug)}` : '#';
    const img = line.thumbnail || PLACEHOLDER;
    return `
    <tr class="tf-cart-item" data-line="${line.id}">
        <td class="tf-cart-item_product">
            <a href="${url}" class="img-box"><img src="${esc(img)}" alt="${esc(line.name)}"></a>
            <div class="cart-info">
                <a href="${url}" class="name text-button link">${esc(line.name)}</a>
                <div class="text-secondary small">${esc(line.sku)}</div>
            </div>
        </td>
        <td class="tf-cart-item_price text-center">
            <div class="cart-price text-button">${esc(line.unit_price ?? line.sub_total)}</div>
        </td>
        <td class="tf-cart-item_quantity text-center">
            <div class="wg-quantity mx-auto">
                <input type="number" min="1" value="${line.quantity}" class="quantity-product" data-cart-qty="${line.id}">
            </div>
        </td>
        <td class="tf-cart-item_total text-center">
            <div class="cart-total text-button total-price">${esc(line.sub_total)}</div>
        </td>
        <td>
            <button type="button" class="remove icon-close" data-cart-remove="${line.id}" aria-label="Remove"></button>
        </td>
    </tr>`;
}

function render(root) {
    const has = state.lines.length > 0;
    const q = (sel) => root.querySelector(sel);

    q('[data-cart-empty]').style.display = has ? 'none' : '';
    const table = q('[data-cart-table]');
    table.style.display = has ? '' : 'none';
    q('[data-cart-rows]').innerHTML = state.lines.map(rowHtml).join('');

    // Free-shipping bar.
    const fs = state.free_shipping;
    const fsBox = q('[data-cart-freeship]');
    if (fs && has) {
        fsBox.style.display = '';
        q('[data-cart-freeship-text]').innerHTML = fs.qualified
            ? '🎉 You’ve unlocked <strong>free shipping</strong>!'
            : `Add <strong>${esc(fs.remaining)}</strong> more to get <strong>free shipping</strong>.`;
        q('[data-cart-freeship-fill]').style.width = `${fs.progress}%`;
    } else {
        fsBox.style.display = 'none';
    }

    // Coupon.
    const couponBox = q('[data-cart-coupon-box]');
    couponBox.style.display = has ? '' : 'none';
    const applied = q('[data-cart-coupon-applied]');
    const entry = q('[data-cart-coupon-entry]');
    if (state.coupon_code) {
        applied.style.display = '';
        entry.style.display = 'none';
        q('[data-cart-coupon-code]').textContent = state.coupon_code;
    } else {
        applied.style.display = 'none';
        entry.style.display = '';
    }

    // Available coupons.
    const couponsWrap = q('[data-cart-coupons]');
    if (coupons.length && !state.coupon_code) {
        couponsWrap.style.display = '';
        q('[data-cart-coupons-list]').innerHTML = coupons
            .map((c) => `<button type="button" class="coupon-chip" title="${esc(c.name)}" data-cart-apply-code="${esc(c.code)}">${esc(c.code)}</button>`)
            .join(' ');
    } else {
        couponsWrap.style.display = 'none';
    }

    // Totals.
    q('[data-cart-subtotal]').textContent = state.totals.sub_total ?? '—';
    const discRow = q('[data-cart-discount-row]');
    if (state.coupon_code && state.totals.discount_total) {
        discRow.style.display = '';
        q('[data-cart-discount]').textContent = `-${state.totals.discount_total}`;
    } else { discRow.style.display = 'none'; }
    const taxRow = q('[data-cart-tax-row]');
    if (state.totals.tax_total) {
        taxRow.style.display = '';
        q('[data-cart-tax]').textContent = state.totals.tax_total;
    } else { taxRow.style.display = 'none'; }
    q('[data-cart-total]').textContent = state.totals.total ?? '—';
    q('[data-cart-checkout]').classList.toggle('disabled', !has);
}

async function refresh(root) {
    try {
        const { data } = await window.axios.get('/api/v1/cart');
        state = data.data ?? state;
        render(root);
    } catch (e) { /* noop */ }
}

async function mutate(root, promise) {
    const errEl = root.querySelector('[data-cart-coupon-error]');
    if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
    try {
        const { data } = await promise;
        state = data.data ?? state;
        render(root);
        window.dispatchEvent(new CustomEvent('cart:updated'));
    } catch (e) {
        if (errEl) {
            errEl.textContent = e?.response?.data?.errors?.code?.[0]
                ?? e?.response?.data?.message ?? 'Could not apply coupon.';
            errEl.style.display = '';
        }
    }
}

export default function cartPage(root = document) {
    const el = root.querySelector('[data-cart-page]');
    if (!el || el.dataset.bound) return;
    el.dataset.bound = '1';

    refresh(el);
    window.axios.get('/api/v1/cart/coupons')
        .then(({ data }) => { coupons = data.data ?? []; render(el); })
        .catch(() => {});

    el.addEventListener('click', (e) => {
        const rm = e.target.closest('[data-cart-remove]');
        const code = e.target.closest('[data-cart-apply-code]');
        const couponRm = e.target.closest('[data-cart-coupon-remove]');
        if (rm) mutate(el, window.axios.delete(`/api/v1/cart/lines/${rm.dataset.cartRemove}`));
        else if (code) mutate(el, window.axios.post('/api/v1/cart/coupon', { code: code.dataset.cartApplyCode }));
        else if (couponRm) mutate(el, window.axios.delete('/api/v1/cart/coupon'));
    });

    el.addEventListener('change', (e) => {
        const qty = e.target.closest('[data-cart-qty]');
        if (qty) {
            const v = Math.max(1, Number(qty.value) || 1);
            mutate(el, window.axios.patch(`/api/v1/cart/lines/${qty.dataset.cartQty}`, { quantity: v }));
        }
    });

    const couponForm = el.querySelector('[data-cart-coupon-form]');
    couponForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        const code = couponForm.querySelector('input[name="code"]').value.trim();
        if (code) mutate(el, window.axios.post('/api/v1/cart/coupon', { code }));
    });

    // Sync when the mini-cart changes the cart.
    window.addEventListener('cart:refreshed', () => refresh(el));
}
