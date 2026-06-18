/*
| Auth enhancement (vanilla, progressive).
| - [data-auth-form]  → login / register forms submit via the JSON API, show
|                       inline 422 errors, then redirect on success.
| - [data-logout]     → posts to the logout endpoint and redirects.
|
| The forms are real <form> elements rendered server-side; JS only intercepts
| submit to give inline validation + redirect without a full reload.
*/

function clearErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => { el.textContent = ''; });
    const msg = form.querySelector('[data-form-message]');
    if (msg) msg.textContent = '';
}

function showErrors(form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        const el = form.querySelector(`[data-error-for="${field}"]`);
        if (el) el.textContent = Array.isArray(messages) ? messages[0] : messages;
    });
}

function bindForm(form) {
    if (form.dataset.bound) return;
    form.dataset.bound = '1';

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors(form);

        const button = form.querySelector('button[type="submit"]');
        const data = Object.fromEntries(new FormData(form).entries());

        // Client-side password confirmation check (register).
        if (form.hasAttribute('data-confirm-password') && data.password !== data.password_confirmation) {
            showErrors(form, { password: ['Passwords do not match.'] });
            return;
        }

        if (button) button.disabled = true;

        try {
            await window.axios.post(form.dataset.endpoint, data);
            window.location.href = form.dataset.redirect || '/account';
        } catch (err) {
            if (err?.response?.status === 422) {
                showErrors(form, err.response.data.errors ?? {});
            } else {
                const msg = form.querySelector('[data-form-message]');
                if (msg) msg.textContent = 'Something went wrong. Please try again.';
            }
            if (button) button.disabled = false;
        }
    });
}

function bindLogout(btn) {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
            await window.axios.post('/api/v1/auth/logout');
        } catch (e) { /* redirect regardless */ }
        window.location.href = '/login';
    });
}

export default function auth(root = document) {
    root.querySelectorAll('[data-auth-form]').forEach(bindForm);
    root.querySelectorAll('[data-logout]').forEach(bindLogout);
}
