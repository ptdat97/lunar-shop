// Fashion Size Intelligence — "find my size". Posts body measurements to
// POST /api/v1/products/{slug}/recommend-size and renders the recommendation.
// Personalised/interactive (not SEO content) → fetch on submit is fine.
//
// "Use this size" dispatches `size:recommended` so the product-purchase island
// can preselect the matching size option.

import api from '../api.js';

const CONFIDENCE_CLASS = {
    high: 'bg-success',
    medium: 'bg-warning text-dark',
    low: 'bg-secondary',
};

function initFinder(root) {
    if (root.dataset.sizeFinderInit) return;
    root.dataset.sizeFinderInit = '1';

    const slug = root.dataset.slug;
    const form = root.querySelector('[data-size-finder-form]');
    const errorBox = root.querySelector('[data-size-finder-error]');
    const result = root.querySelector('[data-size-finder-result]');
    if (!form || !slug) return;

    const sizeEl = root.querySelector('[data-sf-size]');
    const confEl = root.querySelector('[data-sf-confidence]');
    const fitEl = root.querySelector('[data-sf-fit]');
    const altEl = root.querySelector('[data-sf-alternatives]');
    const applyBtn = root.querySelector('[data-sf-apply]');
    const saveToggle = root.querySelector('[data-sf-save]');

    // Prefill from the shopper's saved measurement profile (logged-in only).
    // Silent: a 401/empty for guests just leaves the form blank.
    (async () => {
        try {
            const { data } = await api.get('/customer/measurements');
            const saved = data.data ?? {};
            let any = false;
            Object.entries(saved).forEach(([k, v]) => {
                const input = form.elements[k];
                if (input && v != null) { input.value = v; any = true; }
            });
            // Only expose "save my measurements" to logged-in shoppers.
            if (saveToggle) saveToggle.closest('[data-sf-save-wrap]')?.removeAttribute('hidden');
            if (any && saveToggle) saveToggle.checked = true;
        } catch {
            // guest / not logged in → leave blank, hide the save option.
        }
    })();

    function showError(message) {
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.hidden = !message;
    }

    function render(data) {
        const rec = data.recommended;
        if (!rec) {
            showError('We couldn’t match a size from those measurements. Try the size chart.');
            result.hidden = true;
            return;
        }

        sizeEl.textContent = rec.size;
        confEl.textContent = `${rec.confidence} confidence`;
        confEl.className = `badge ${CONFIDENCE_CLASS[rec.confidence] ?? 'bg-secondary'}`;
        fitEl.textContent = rec.fit ? `(${rec.fit} fit)` : '';

        const alts = (data.alternatives ?? []).map((a) => a.size);
        altEl.textContent = alts.length ? `Also consider: ${alts.join(', ')}` : '';

        if (applyBtn) {
            applyBtn.hidden = false;
            applyBtn.dataset.size = rec.size;
        }
        result.hidden = false;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        showError('');
        const payload = {};
        new FormData(form).forEach((v, k) => { if (v !== '') payload[k] = Number(v); });

        if (!Object.keys(payload).length) {
            showError('Enter at least one measurement (e.g. bust, waist, hip).');
            return;
        }

        const btn = form.querySelector('[type="submit"]');
        const label = btn?.textContent;
        if (btn) { btn.disabled = true; btn.textContent = 'Finding…'; }
        try {
            const { data } = await api.post(`/products/${slug}/recommend-size`, payload);
            render(data.data);

            // Persist the entered measurements for next time (logged-in + opted in).
            if (saveToggle?.checked) {
                api.put('/customer/measurements', payload).catch(() => {});
            }
        } catch (err) {
            showError(err.response?.data?.message || 'Could not get a recommendation. Please try again.');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = label; }
        }
    });

    // Apply the recommended size to the variant picker, then close the modal.
    applyBtn?.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('size:recommended', { detail: { size: applyBtn.dataset.size } }));
        const modal = root.closest('.modal');
        if (modal && window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
        }
    });
}

export default function (root = document) {
    root.querySelectorAll('[data-size-finder]').forEach(initFinder);
}
