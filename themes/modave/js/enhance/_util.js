/*
| Small shared helpers for the vanilla enhancement modules.
| No framework — just DOM + fetch (axios is on window from app.js).
*/

export function isAuthed() {
    return document.querySelector('meta[name="auth-check"]')?.getAttribute('content') === '1';
}

// Build a query string supporting array params: filters[size][]=M
export function buildQuery(obj, prefix) {
    const parts = [];
    const enc = encodeURIComponent;
    const walk = (key, val) => {
        if (Array.isArray(val)) {
            val.forEach((v) => walk(`${key}[]`, v));
        } else if (val && typeof val === 'object') {
            Object.entries(val).forEach(([k, v]) => walk(`${key}[${k}]`, v));
        } else if (val !== undefined && val !== null && val !== '') {
            parts.push(`${enc(key)}=${enc(val)}`);
        }
    };
    Object.entries(obj).forEach(([k, v]) => walk(prefix ? `${prefix}[${k}]` : k, v));
    return parts.join('&');
}

// Read filters[size][]/filters[color][] from the current URL.
export function urlFilters() {
    const params = new URLSearchParams(window.location.search);
    const out = { size: [], color: [] };
    for (const [key, value] of params.entries()) {
        const m = key.match(/^filters\[(size|color)\]/);
        if (m) out[m[1]].push(value);
    }
    return out;
}
