// Collect-at-the-counter (roadmap §13): when the shopper picks it, the delivery
// address block has nothing to ask for, so it folds away and the counter's
// address / opening hours take its place.
//
// The `required` attributes have to come off with it. A hidden input that is
// still marked required makes the browser refuse to submit and — because it
// cannot scroll to a hidden field — refuse *silently*. Stripping them is not a
// weakening of validation: PlaceOrderRequest drops the same three rules for a
// collection order, and only when the shop actually offers collection.
//
// No JS? The block simply stays visible and the fields stay optional
// server-side, so the order still goes through.

const ADDRESS_FIELDS = ['line_one', 'state', 'city'];

function apply(form, collecting) {
    const block = form.querySelector('[data-delivery-block]');
    if (block) block.hidden = collecting;

    ADDRESS_FIELDS.forEach((name) => {
        const field = form.querySelector(`[name="${name}"]`);
        if (!field) return;

        if (collecting) {
            // Remember whether it was required so turning collection back off
            // restores exactly what the server rendered.
            if (field.required) field.dataset.wasRequired = '1';
            field.required = false;
        } else if (field.dataset.wasRequired) {
            field.required = true;
        }
    });

    form.querySelectorAll('[data-pickup-details]').forEach((el) => {
        el.hidden = !collecting;
    });
}

export default function (root = document) {
    const form = root.querySelector('[data-checkout-form]');
    if (!form || form.dataset.pickupInit) return;

    const pickupRadio = form.querySelector('input[name="shipping_option"][value="pickup"]');
    if (!pickupRadio) return; // shop does not offer collection

    form.dataset.pickupInit = '1';

    form.addEventListener('change', (e) => {
        if (e.target.name !== 'shipping_option') return;
        apply(form, e.target.value === 'pickup');
    });

    apply(form, pickupRadio.checked); // respect a pre-selected / old() value
}
