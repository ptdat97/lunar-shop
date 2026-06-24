// Checkout coupon. The checkout page is SSR (Blade renders $cart), but the
// coupon is applied/removed via the same /api/v1/cart/coupon endpoints the cart
// page uses, then the order-summary lines update in place — no reload. The
// coupon lives on the server-side cart session, so placeOrder() applies it; no
// hidden field is needed in the form.

import api from '../api.js';
import { CART_UPDATED, emit } from '../events.js';
import { appliedDiscountsHtml } from './cart.js';

export default function (root = document) {
    const summary = root.querySelector('[data-checkout-summary]');
    if (!summary || summary.dataset.couponInit) return;
    summary.dataset.couponInit = '1';

    const form = summary.querySelector('[data-coupon-form]');
    const input = summary.querySelector('[data-coupon-input]');
    const status = summary.querySelector('[data-coupon-status]');
    const discountRow = summary.querySelector('[data-discount-row]');
    if (!form || !input) return;

    function setStatus(msg, ok) {
        if (!status) return;
        status.textContent = msg ?? '';
        status.className = `small mt-1 ${ok ? 'text-success' : 'text-danger'}`;
    }

    // Swap the Apply/Remove button to match whether a coupon is active.
    function renderButton(active) {
        const btn = form.querySelector('[data-coupon-apply], [data-coupon-remove]');
        if (!btn) return;
        if (active) {
            btn.className = 'btn btn-outline-danger';
            btn.textContent = 'Remove';
            btn.dataset.couponRemove = '';
            delete btn.dataset.couponApply;
        } else {
            btn.className = 'btn btn-outline-dark';
            btn.textContent = 'Apply';
            btn.dataset.couponApply = '';
            delete btn.dataset.couponRemove;
        }
    }

    function applyCart(cart) {
        const t = cart?.totals ?? {};
        const code = cart?.coupon_code ?? '';
        input.value = code;

        summary.querySelector('[data-sum-subtotal]').textContent = t.sub_total ?? '—';
        summary.querySelector('[data-sum-tax]').textContent = t.tax_total ?? '—';
        summary.querySelector('[data-sum-total]').textContent = t.total ?? '—';

        const hasDiscount = (t.discount_value ?? 0) > 0;
        const discEl = summary.querySelector('[data-sum-discount]');
        if (discEl) discEl.textContent = `−${t.discount_total ?? ''}`;
        if (discountRow) discountRow.classList.toggle('d-none', !hasDiscount);

        // Re-render the labelled applied-promotions list (automatic + coupon).
        const discounts = summary.querySelector('[data-checkout-discounts]');
        if (discounts) discounts.innerHTML = appliedDiscountsHtml(cart);

        renderButton(!!code);
        emit(CART_UPDATED); // keep the drawer/count in sync
    }

    async function apply() {
        const code = input.value.trim();
        if (!code) return;
        try {
            const { data } = await api.post('/cart/coupon', { code });
            applyCart(data.data ?? data);
            setStatus('Coupon applied.', true);
        } catch {
            setStatus('Invalid or expired coupon.', false);
        }
    }

    async function remove() {
        try {
            const { data } = await api.delete('/cart/coupon');
            applyCart(data.data ?? data);
            setStatus('Coupon removed.', true);
        } catch {
            setStatus('Could not remove coupon.', false);
        }
    }

    form.addEventListener('click', (e) => {
        if (e.target.closest('[data-coupon-apply]')) { e.preventDefault(); apply(); }
        else if (e.target.closest('[data-coupon-remove]')) { e.preventDefault(); remove(); }
    });

    // Enter in the input applies (the input-group isn't a <form>, so guard it).
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); apply(); }
    });
}
