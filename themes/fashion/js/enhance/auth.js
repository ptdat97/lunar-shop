// Auth: login/register form submit + logout. Posts to /api/v1/auth/* (Sanctum
// SPA cookie session). Account page logic lives in enhance/account.js.

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
        const remember = form.querySelector('[name="remember"]');
        if (remember) payload.remember = remember.checked;

        try {
            await api.post(`/auth/${mode}`, payload);
            window.location.href = REDIRECT_AFTER_AUTH;
        } catch (err) {
            showError(form, errorMessage(err));
            if (btn) { btn.disabled = false; btn.textContent = label; }
        }
    });
}

// Logout is shared (auth pages + account sidebar).
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

export default function (root = document) {
    root.querySelectorAll('[data-auth-form]').forEach(bindAuthForm);
    bindLogout();
}
