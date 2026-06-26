// Back-in-stock subscription. Shows the "notify me" form only when the selected
// variant is out of stock (listens to variant:changed from product-variant.js),
// and subscribes via POST /api/v1/inventory/notify-me. The add-to-cart button
// already shows "Out of stock" with no JS — this is an additive enhancement.

import api from '../api.js';

export default function (root = document) {
    const box = root.querySelector('[data-notify-me]');
    if (!box || box.dataset.notifyInit) return;
    box.dataset.notifyInit = '1';

    const form = box.querySelector('[data-notify-form]');
    const variantField = box.querySelector('[data-notify-variant]');
    const emailField = box.querySelector('[data-notify-email]');
    const status = box.querySelector('[data-notify-status]');
    const panel = root.querySelector('[data-product-detail]') ?? root;

    function setStatus(msg, ok) {
        if (!status) return;
        status.textContent = msg ?? '';
        status.className = `small mt-1 ${ok ? 'text-success' : 'text-danger'}`;
    }

    function show(variantId) {
        if (variantId && variantField) variantField.value = variantId;
        box.hidden = false;
    }

    function hide() {
        box.hidden = true;
        setStatus('', true);
    }

    // React to variant selection from the variant picker.
    panel.addEventListener('variant:changed', (e) => {
        const { variant, inStock } = e.detail ?? {};
        if (variant && inStock === false) show(variant.id);
        else hide();
    });

    // If the page loaded already out of stock (no variant:changed yet), reveal.
    if (box.dataset.initialOut === '1') show(variantField?.value);

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const variant_id = parseInt(variantField?.value, 10);
        const email = emailField?.value.trim();
        if (!variant_id || !email) return;

        const submit = form.querySelector('[data-notify-submit]');
        if (submit) submit.disabled = true;

        try {
            const { data } = await api.post('/inventory/notify-me', { variant_id, email });
            setStatus(data?.message ?? 'Subscribed.', true);
            form.querySelector('[data-notify-email]')?.setAttribute('disabled', 'disabled');
        } catch (err) {
            const msg = err?.response?.data?.message
                ?? err?.response?.data?.errors?.email?.[0]
                ?? 'Could not subscribe. Please try again.';
            setStatus(msg, false);
            if (submit) submit.disabled = false;
        }
    });
}
