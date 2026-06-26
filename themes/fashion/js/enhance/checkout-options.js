// Checkout option highlighting (Shopify-style). The shipping + payment choices
// render as full-width radio "cards"; this toggles the .checkout-option--active
// class on the selected label within each radio group so the active card is
// outlined. Pure presentation — the radios themselves carry the submitted value,
// so the form still works with no JS (the active card just isn't highlighted).

function sync(root, name) {
    const radios = root.querySelectorAll(`input[type="radio"][name="${name}"]`);
    radios.forEach((radio) => {
        const label = radio.closest('.checkout-option');
        if (label) label.classList.toggle('checkout-option--active', radio.checked);
    });
}

export default function (root = document) {
    const form = root.querySelector('[data-checkout-form]');
    if (!form || form.dataset.optionsInit) return;
    form.dataset.optionsInit = '1';

    ['shipping_option', 'payment_type'].forEach((name) => {
        if (!form.querySelector(`input[name="${name}"]`)) return;
        form.addEventListener('change', (e) => {
            if (e.target.name === name) sync(form, name);
        });
        sync(form, name); // initial paint (respects old() pre-selection)
    });
}
