// Auth + account. Vanilla. Login/register post to /api/v1/auth/* (Sanctum SPA
// cookie session), logout clears it, and the account page loads order history.
// Auth pages are server-guarded (redirect when already/!authed); JS only drives
// the form submit + redirect.

import api from '../api.js';

const REDIRECT_AFTER_AUTH = '/account';
const REDIRECT_AFTER_LOGOUT = '/';

function showError(form, message) {
    const box = form.querySelector('[data-auth-error]');
    if (box) { box.textContent = message; box.hidden = !message; }
}

// First validation message from a 422, else a generic line.
function errorMessage(e) {
    const data = e.response?.data;
    if (data?.errors) return Object.values(data.errors)[0]?.[0];
    return data?.message || 'Something went wrong. Please try again.';
}

function bindAuthForm(form) {
    const mode = form.dataset.authForm; // 'login' | 'register'
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        showError(form, '');
        const btn = form.querySelector('[data-auth-submit]');
        const label = btn?.textContent;
        if (btn) { btn.disabled = true; btn.textContent = 'Please wait…'; }

        const payload = Object.fromEntries(new FormData(form).entries());
        if (form.querySelector('[name="remember"]')) payload.remember = form.querySelector('[name="remember"]').checked;

        try {
            await api.post(`/auth/${mode}`, payload);
            window.location.href = REDIRECT_AFTER_AUTH;
        } catch (err) {
            showError(form, errorMessage(err));
            if (btn) { btn.disabled = false; btn.textContent = label; }
        }
    });
}

function bindLogout() {
    document.querySelectorAll('[data-logout]').forEach((btn) => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', async () => {
            try { await api.post('/auth/logout'); } catch { /* ignore */ }
            window.location.href = REDIRECT_AFTER_LOGOUT;
        });
    });
}

function formatTotal(total) {
    if (!total) return '';
    if (typeof total === 'string') return total;
    const dp = total.currency?.decimal_places ?? 2;
    const amount = (total.value ?? 0) / 10 ** dp;
    return `${amount.toFixed(dp)} ${total.currency?.code ?? ''}`.trim();
}

function rowHtml(order) {
    const date = order.placed_at ? new Date(order.placed_at).toLocaleDateString() : '—';
    return `
<tr>
    <td class="fw-semibold">${order.reference ?? '—'}</td>
    <td>${date}</td>
    <td><span class="badge bg-light text-dark text-capitalize">${(order.status ?? '').replace(/-/g, ' ')}</span></td>
    <td class="text-end">${formatTotal(order.total)}</td>
</tr>`;
}

async function loadOrders(root) {
    const loading = root.querySelector('[data-orders-loading]');
    const empty = root.querySelector('[data-orders-empty]');
    const wrap = root.querySelector('[data-orders-wrap]');
    const body = root.querySelector('[data-orders-body]');
    try {
        const { data } = await api.get('/customer/orders');
        const orders = data.data ?? [];
        if (loading) loading.hidden = true;
        if (!orders.length) { if (empty) empty.hidden = false; return; }
        body.innerHTML = orders.map(rowHtml).join('');
        if (wrap) wrap.hidden = false;
    } catch (e) {
        if (loading) loading.textContent = 'Could not load orders.';
    }
}

export default function (root = document) {
    root.querySelectorAll('[data-auth-form]').forEach(bindAuthForm);
    bindLogout();

    const account = root.querySelector('[data-account]');
    if (account && !account.dataset.bound) {
        account.dataset.bound = '1';
        loadOrders(account);
    }
}
