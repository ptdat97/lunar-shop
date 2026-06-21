// Checkout address: Vietnam Province → Ward dependent dropdowns (vanilla).
//
// The form is plain SSR Blade and submits with a real POST. Lunar stores the
// province in `state` and the ward in `city`, so the selects write the chosen
// NAMES into hidden [data-state] / [data-city] inputs. Wards are fetched per
// province from /api/v1/locations; no JS still leaves a usable (if un-cascaded)
// form because the server validates the submitted names.

import api from '../api.js';

async function loadWards(form, provinceId, preselectName = null) {
    const wardSelect = form.querySelector('[data-ward]');
    const cityField = form.querySelector('[data-city]');
    if (!wardSelect) return;

    wardSelect.disabled = true;
    wardSelect.innerHTML = '<option value="" disabled selected>Đang tải…</option>';

    try {
        const { data } = await api.get(`/locations/provinces/${provinceId}/wards`, { baseURL: '/api/v1' });
        const wards = data.data ?? [];

        wardSelect.innerHTML = '<option value="" disabled selected>Phường/Xã</option>';
        wards.forEach((w) => {
            const opt = document.createElement('option');
            opt.value = w.id;
            opt.dataset.name = w.name;
            opt.textContent = w.name;
            if (preselectName && w.name === preselectName) {
                opt.selected = true;
                if (cityField) cityField.value = w.name;
            }
            wardSelect.appendChild(opt);
        });
        wardSelect.disabled = false;
    } catch {
        wardSelect.innerHTML = '<option value="" disabled selected>Không tải được</option>';
    }
}

export default function (root = document) {
    const form = root.querySelector('[data-checkout-form]');
    if (!form || form.dataset.addressInit) return;
    form.dataset.addressInit = '1';

    const provinceSelect = form.querySelector('[data-province]');
    const wardSelect = form.querySelector('[data-ward]');
    const stateField = form.querySelector('[data-state]');
    const cityField = form.querySelector('[data-city]');
    if (!provinceSelect) return;

    // Province chosen → write its name into [data-state] and load its wards.
    provinceSelect.addEventListener('change', () => {
        const opt = provinceSelect.selectedOptions[0];
        if (stateField) stateField.value = opt?.dataset.name ?? '';
        if (cityField) cityField.value = '';
        loadWards(form, provinceSelect.value);
    });

    // Ward chosen → write its name into [data-city].
    wardSelect?.addEventListener('change', () => {
        const opt = wardSelect.selectedOptions[0];
        if (cityField) cityField.value = opt?.dataset.name ?? '';
    });

    // Re-populate wards after a validation redirect (province pre-selected,
    // ward name preserved in the hidden field).
    if (provinceSelect.value && cityField?.value) {
        loadWards(form, provinceSelect.value, cityField.value);
    }
}
