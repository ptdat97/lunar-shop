// Account dashboard: tabbed sidebar driving orders (list + detail), address
// book (CRUD), and profile/password forms. All data via /api/v1/customer/*.
// Logout lives in enhance/auth.js (shared [data-logout]).

import api from '../api.js';

function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function readState(root) {
    const tag = root.querySelector('[data-account-state]');
    try { return tag ? JSON.parse(tag.textContent) : {}; } catch { return {}; }
}

/* ---- Tabs --------------------------------------------------------------- */
function initTabs(root) {
    const panels = root.querySelectorAll('[data-tab-panel]');
    const buttons = root.querySelectorAll('[data-tab-btn]');

    function show(name) {
        panels.forEach((p) => { p.hidden = p.dataset.tabPanel !== name; });
        root.querySelectorAll('[data-tab-btn]').forEach((b) => {
            if (b.classList.contains('list-group-item')) b.classList.toggle('active', b.dataset.tabBtn === name);
        });
        if (window.location.hash !== `#${name}`) history.replaceState({}, '', `#${name}`);
    }

    // Delegated so dashboard tiles ([data-tab-btn]) work too.
    root.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-tab-btn]');
        if (!btn) return;
        e.preventDefault();
        show(btn.dataset.tabBtn);
    });

    const initial = (window.location.hash || '#dashboard').slice(1);
    show(buttons.length ? initial : 'dashboard');
}

/* ---- Orders ------------------------------------------------------------- */
function orderRow(order) {
    const date = order.placed_at ? new Date(order.placed_at).toLocaleDateString() : '—';
    const status = (order.status ?? '').replace(/-/g, ' ');
    return `
<tr>
    <td class="fw-semibold">${esc(order.reference ?? '—')}</td>
    <td>${esc(date)}</td>
    <td><span class="badge bg-light text-dark text-capitalize">${esc(status)}</span></td>
    <td class="text-end">${esc(order.total ?? '')}</td>
    <td class="text-end"><button class="btn btn-link btn-sm p-0" data-order-view="${order.id}">View</button></td>
</tr>`;
}

function orderDetailHtml(order) {
    const lines = (order.lines ?? []).map((l) => `
<tr><td>${esc(l.description)}</td><td class="text-center">× ${l.quantity}</td><td class="text-end">${esc(l.sub_total)}</td></tr>`).join('');
    const addr = order.shipping_address;
    const addrHtml = addr ? `
<div class="small text-muted mt-3">
    <div class="text-uppercase">Shipping to</div>
    <div>${esc(addr.name)}</div>
    <div>${esc(addr.line_one)}${addr.line_two ? ', ' + esc(addr.line_two) : ''}</div>
    <div>${esc(addr.city)}${addr.state ? ', ' + esc(addr.state) : ''}</div>
    ${addr.contact_phone ? `<div>${esc(addr.contact_phone)}</div>` : ''}
</div>` : '';
    return `
<div class="d-flex justify-content-between">
    <strong>${esc(order.reference)}</strong>
    <span class="badge bg-light text-dark text-capitalize">${esc((order.status ?? '').replace(/-/g, ' '))}</span>
</div>
<table class="table table-sm align-middle mt-3 mb-0"><tbody>${lines}</tbody></table>
<dl class="row small border-top pt-2 mt-2 mb-0">
    <dt class="col-8 fw-normal">Subtotal</dt><dd class="col-4 text-end">${esc(order.sub_total ?? '')}</dd>
    <dt class="col-8 fw-normal">Shipping</dt><dd class="col-4 text-end">${esc(order.shipping_total ?? '')}</dd>
    <dt class="col-8 fw-bold">Total</dt><dd class="col-4 text-end fw-bold">${esc(order.total ?? '')}</dd>
</dl>
${addrHtml}`;
}

function initOrders(root, stats) {
    const loading = root.querySelector('[data-orders-loading]');
    const empty = root.querySelector('[data-orders-empty]');
    const wrap = root.querySelector('[data-orders-wrap]');
    const body = root.querySelector('[data-orders-body]');
    const detail = root.querySelector('[data-order-detail]');
    const detailBody = root.querySelector('[data-order-detail-body]');
    let loaded = false;

    async function load() {
        if (loaded) return;
        loaded = true;
        try {
            const { data } = await api.get('/customer/orders');
            const orders = data.data ?? [];
            if (stats) stats.textContent = orders.length;
            if (loading) loading.hidden = true;
            if (!orders.length) { if (empty) empty.hidden = false; return; }
            body.innerHTML = orders.map(orderRow).join('');
            if (wrap) wrap.hidden = false;
        } catch {
            if (loading) loading.textContent = 'Could not load orders.';
        }
    }

    root.addEventListener('click', async (e) => {
        const view = e.target.closest('[data-order-view]');
        if (view) {
            e.preventDefault();
            detailBody.innerHTML = '<div class="text-muted small">Loading…</div>';
            detail.hidden = false;
            try {
                const { data } = await api.get(`/orders/${view.dataset.orderView}`);
                detailBody.innerHTML = orderDetailHtml(data.data);
            } catch {
                detailBody.innerHTML = '<div class="text-danger small">Could not load this order.</div>';
            }
            detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        if (e.target.closest('[data-order-back]')) detail.hidden = true;
    });

    // Load when the orders tab is first shown (or immediately if it's active).
    root.querySelectorAll('[data-tab-btn="orders"]').forEach((b) => b.addEventListener('click', load));
    if ((window.location.hash || '') === '#orders') load();
    return load;
}

/* ---- Addresses ---------------------------------------------------------- */
function addressCard(a) {
    return `
<div class="col-12 col-md-6" data-address-item="${a.id}">
    <div class="border rounded p-3 h-100">
        ${a.shipping_default ? '<span class="badge bg-dark mb-2">Default</span>' : ''}
        <div class="fw-semibold">${esc(a.first_name)} ${esc(a.last_name)}</div>
        <div class="small text-muted">${esc(a.line_one)}${a.line_two ? ', ' + esc(a.line_two) : ''}</div>
        <div class="small text-muted">${esc(a.city)}${a.state ? ', ' + esc(a.state) : ''}</div>
        ${a.contact_phone ? `<div class="small text-muted">${esc(a.contact_phone)}</div>` : ''}
        <div class="mt-2 d-flex gap-2">
            <button class="btn btn-link btn-sm p-0" data-address-edit="${a.id}">Edit</button>
            <button class="btn btn-link btn-sm p-0 text-danger" data-address-delete="${a.id}">Delete</button>
        </div>
    </div>
</div>`;
}

function initAddresses(root, countries, stats) {
    const loading = root.querySelector('[data-addresses-loading]');
    const empty = root.querySelector('[data-addresses-empty]');
    const list = root.querySelector('[data-addresses-list]');
    const formWrap = root.querySelector('[data-address-form-wrap]');
    const form = root.querySelector('[data-address-form]');
    const formTitle = root.querySelector('[data-address-form-title]');
    const errorBox = root.querySelector('[data-address-error]');
    const select = root.querySelector('[data-country-select]');
    const provinceSelect = root.querySelector('[data-province-select]');
    const wardSelect = root.querySelector('[data-ward-select]');
    let cache = [];
    let provinces = [];
    let loaded = false;

    if (select) {
        select.innerHTML = countries.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
    }

    // Province → ward dependent selects. The address stores names (state=province,
    // city=ward), so option values are names; province options carry data-id for
    // the ward lookup.
    async function loadProvinces() {
        if (provinces.length || !provinceSelect) return;
        const { data } = await api.get('/locations/provinces');
        provinces = data.data ?? [];
        provinceSelect.insertAdjacentHTML('beforeend', provinces
            .map((p) => `<option value="${esc(p.name)}" data-id="${p.id}">${esc(p.name)}</option>`).join(''));
    }

    async function loadWards(provinceId, selectedName = '') {
        if (!wardSelect) return;
        wardSelect.innerHTML = '<option value="" disabled selected>Phường/Xã</option>';
        wardSelect.disabled = ! provinceId;
        if (! provinceId) return;
        const { data } = await api.get(`/locations/provinces/${provinceId}/wards`);
        wardSelect.insertAdjacentHTML('beforeend', (data.data ?? [])
            .map((w) => `<option value="${esc(w.name)}"${w.name === selectedName ? ' selected' : ''}>${esc(w.name)}</option>`).join(''));
    }

    provinceSelect?.addEventListener('change', () => {
        const opt = provinceSelect.selectedOptions[0];
        loadWards(opt?.dataset.id);
    });

    function render() {
        if (loading) loading.hidden = true;
        if (empty) empty.hidden = cache.length > 0;
        list.innerHTML = cache.map(addressCard).join('');
        if (stats) stats.textContent = cache.length;
    }

    async function load() {
        if (loaded) return;
        loaded = true;
        try {
            const { data } = await api.get('/customer/addresses');
            cache = data.data ?? [];
            render();
        } catch {
            if (loading) loading.textContent = 'Could not load addresses.';
        }
    }

    async function openForm(address = null) {
        await loadProvinces();
        form.reset();
        if (errorBox) errorBox.hidden = true;
        form.elements.id.value = address?.id ?? '';

        // Reset dependent selects to placeholders.
        if (provinceSelect) provinceSelect.value = '';
        if (wardSelect) { wardSelect.innerHTML = '<option value="" disabled selected>Phường/Xã</option>'; wardSelect.disabled = true; }

        if (address) {
            Object.entries(address).forEach(([k, v]) => {
                const el = form.elements[k];
                // state/city are the province/ward selects, handled below.
                if (el && k !== 'state' && k !== 'city') {
                    if (el.type === 'checkbox') el.checked = !!v;
                    else el.value = v ?? '';
                }
            });

            // Preselect province (by name) → load its wards → preselect ward.
            if (provinceSelect && address.state) {
                provinceSelect.value = address.state;
                const opt = provinceSelect.selectedOptions[0];
                await loadWards(opt?.dataset.id, address.city ?? '');
            }
        }

        if (formTitle) formTitle.textContent = address ? 'Edit address' : 'Add address';
        formWrap.hidden = false;
        formWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    root.querySelector('[data-address-new]')?.addEventListener('click', () => openForm());
    root.querySelector('[data-address-cancel]')?.addEventListener('click', () => { formWrap.hidden = true; });

    list.addEventListener('click', async (e) => {
        const edit = e.target.closest('[data-address-edit]');
        const del = e.target.closest('[data-address-delete]');
        if (edit) openForm(cache.find((a) => a.id === Number(edit.dataset.addressEdit)));
        if (del) {
            if (!confirm('Delete this address?')) return;
            const id = Number(del.dataset.addressDelete);
            await api.delete(`/customer/addresses/${id}`);
            cache = cache.filter((a) => a.id !== id);
            render();
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (errorBox) errorBox.hidden = true;
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.shipping_default = form.elements.shipping_default.checked;
        payload.country_id = Number(payload.country_id);
        const id = payload.id;
        delete payload.id;
        try {
            const { data } = id
                ? await api.patch(`/customer/addresses/${id}`, payload)
                : await api.post('/customer/addresses', payload);
            const saved = data.data;
            const idx = cache.findIndex((a) => a.id === saved.id);
            // A new default unsets others locally.
            if (saved.shipping_default) cache.forEach((a) => { a.shipping_default = false; });
            if (idx >= 0) cache[idx] = saved; else cache.push(saved);
            render();
            formWrap.hidden = true;
        } catch (err) {
            const errors = err.response?.data?.errors;
            if (errorBox) {
                errorBox.textContent = errors ? Object.values(errors)[0][0] : 'Could not save the address.';
                errorBox.hidden = false;
            }
        }
    });

    root.querySelectorAll('[data-tab-btn="addresses"]').forEach((b) => b.addEventListener('click', load));
    if ((window.location.hash || '') === '#addresses') load();
    return load;
}

/* ---- Profile + password ------------------------------------------------- */
function bindFormToEndpoint(form, method, url, { okBox, errBox, onOk } = {}) {
    if (!form) return;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (okBox) okBox.hidden = true;
        if (errBox) errBox.hidden = true;
        const payload = Object.fromEntries(new FormData(form).entries());
        try {
            await api[method](url, payload);
            if (okBox) okBox.hidden = false;
            onOk?.();
        } catch (err) {
            const errors = err.response?.data?.errors;
            if (errBox) {
                errBox.textContent = errors ? Object.values(errors)[0][0] : 'Could not save changes.';
                errBox.hidden = false;
            }
        }
    });
}

function initProfile(root) {
    const profileForm = root.querySelector('[data-profile-form]');
    bindFormToEndpoint(profileForm, 'patch', '/customer', {
        okBox: root.querySelector('[data-profile-ok]'),
        errBox: root.querySelector('[data-profile-error]'),
    });

    const passwordForm = root.querySelector('[data-password-form]');
    bindFormToEndpoint(passwordForm, 'patch', '/customer/password', {
        okBox: root.querySelector('[data-password-ok]'),
        errBox: root.querySelector('[data-password-error]'),
        onOk: () => passwordForm.reset(),
    });
}

export default function (root = document) {
    const account = root.querySelector('[data-account]');
    if (!account || account.dataset.accountInit) return;
    account.dataset.accountInit = '1';

    const { countries = [] } = readState(account);

    initTabs(account);
    const loadOrders = initOrders(account, account.querySelector('[data-stat-orders]'));
    const loadAddresses = initAddresses(account, countries, account.querySelector('[data-stat-addresses]'));
    initProfile(account);

    // Populate dashboard counts up front (cheap GETs), regardless of active tab.
    loadOrders();
    loadAddresses();
}
