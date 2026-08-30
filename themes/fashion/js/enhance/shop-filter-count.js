// Shows how many facets are active on the button that opens the filter sheet
// (roadmap §15). On a phone the facets are behind a sheet, so without a count
// there is nothing to say the list is narrowed — the shopper sees few results
// and assumes the shop is empty.
//
// Counts checked boxes plus a filled price range. Presentation only: the facet
// form remains the source of truth.

function count(form) {
    const checked = form.querySelectorAll('input[type="checkbox"]:checked').length;
    const price = [...form.querySelectorAll('[data-price-facet] input[type="number"]')]
        .some((input) => input.value !== '');

    return checked + (price ? 1 : 0);
}

function paint(form, badge) {
    const total = count(form);
    badge.textContent = String(total);
    badge.hidden = total === 0;
}

export default function (root = document) {
    const form = root.querySelector('[data-facet-form]');
    const badge = root.querySelector('[data-active-facet-count]');
    if (!form || !badge || form.dataset.facetCountInit) return;
    form.dataset.facetCountInit = '1';

    form.addEventListener('change', () => paint(form, badge));
    form.addEventListener('input', () => paint(form, badge));
    paint(form, badge); // initial paint reflects facets already in the URL
}
