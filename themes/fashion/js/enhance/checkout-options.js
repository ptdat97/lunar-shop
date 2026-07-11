// Checkout option highlighting (Shopify-style). The shipping + payment choices
// render as full-width radio "cards"; this toggles the .checkout-option--active
// class on the selected label within each radio group so the active card is
// outlined. Pure presentation — the radios themselves carry the submitted value,
// so the form still works with no JS (the active card just isn't highlighted).
//
// Picking a shipping option also re-applies it to the server-side cart and
// refreshes the order summary (shipping row + total) in place, so the total the
// shopper sees always matches what placeOrder() will charge. Without JS the
// summary shows the pre-selected first option (applied server-side on render)
// and placeOrder() still applies whichever radio was submitted.

import api from '../api.js';

function sync(root, name) {
    const radios = root.querySelectorAll(`input[type="radio"][name="${name}"]`);
    radios.forEach((radio) => {
        const label = radio.closest('.checkout-option');
        if (label) label.classList.toggle('checkout-option--active', radio.checked);
    });
}

// Re-apply the chosen shipping option server-side and repaint the summary
// totals from the recalculated cart.
async function applyShipping(summary, identifier) {
    if (!summary || !identifier) return;
    try {
        const { data } = await api.post('/checkout/shipping', { identifier });
        const t = (data.data ?? data)?.totals ?? {};
        const set = (sel, val) => {
            const el = summary.querySelector(sel);
            if (el && val != null) el.textContent = val;
        };
        set('[data-sum-shipping]', t.shipping_total);
        set('[data-sum-tax]', t.tax_total);
        set('[data-sum-total]', t.total);
    } catch {
        // Leave the SSR summary as-is; placeOrder() still applies the submitted
        // option, so the order total stays correct even if this refresh fails.
    }
}

export default function (root = document) {
    const form = root.querySelector('[data-checkout-form]');
    if (!form || form.dataset.optionsInit) return;
    form.dataset.optionsInit = '1';

    const summary = document.querySelector('[data-checkout-summary]');

    ['shipping_option', 'payment_type'].forEach((name) => {
        if (!form.querySelector(`input[name="${name}"]`)) return;
        form.addEventListener('change', (e) => {
            if (e.target.name !== name) return;
            sync(form, name);
            if (name === 'shipping_option') applyShipping(summary, e.target.value);
        });
        sync(form, name); // initial paint (respects old() pre-selection)
    });
}
