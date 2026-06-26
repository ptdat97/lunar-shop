// Search panel with autocomplete. The header search icon is a real link to
// /search (works with no JS); this enhancer upgrades it to open an inline panel
// with type-ahead suggestions from /api/v1/search/suggest. The form itself is a
// real GET to /search, so submitting always works — suggestions are a shortcut.

import api from '../api.js';

const DEBOUNCE = 220;
const MIN_CHARS = 2;

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

export default function (root = document) {
    const panel = root.querySelector('[data-search-panel]');
    const toggle = root.querySelector('[data-search-toggle]');
    if (!panel || !toggle || panel.dataset.searchInit) return;
    panel.dataset.searchInit = '1';

    const input = panel.querySelector('[data-search-input]');
    const list = panel.querySelector('[data-search-suggestions]');
    const closeBtn = panel.querySelector('[data-search-close]');
    const searchUrl = panel.querySelector('[data-search-form]')?.getAttribute('action') ?? '/search';

    let timer = null;
    let lastTerm = '';

    function open() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        // Focus after the panel paints so the caret lands correctly.
        requestAnimationFrame(() => input?.focus());
    }

    function close() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        hideList();
    }

    function hideList() {
        if (!list) return;
        list.hidden = true;
        list.innerHTML = '';
    }

    function renderSuggestions(items) {
        if (!list) return;
        if (!items.length) { hideList(); return; }

        list.innerHTML = items.map((name) => {
            const q = encodeURIComponent(name);
            return `<li role="option">
                <a href="${searchUrl}?q=${q}" class="search-suggestions__item d-block px-2 py-2 text-decoration-none text-dark">
                    <i class="bi bi-search me-2 text-muted"></i>${escapeHtml(name)}
                </a>
            </li>`;
        }).join('');
        list.hidden = false;
    }

    async function fetchSuggestions(term) {
        try {
            const { data } = await api.get('/search/suggest', { params: { q: term, limit: 8 } });
            // Guard against out-of-order responses (only render the latest term).
            if (term !== lastTerm) return;
            renderSuggestions(Array.isArray(data?.data) ? data.data : []);
        } catch {
            hideList();
        }
    }

    // Toggle open instead of navigating (icon stays a real link for no-JS).
    toggle.addEventListener('click', (e) => {
        e.preventDefault();
        panel.hidden ? open() : close();
    });

    closeBtn?.addEventListener('click', close);

    input?.addEventListener('input', () => {
        const term = input.value.trim();
        lastTerm = term;
        clearTimeout(timer);
        if (term.length < MIN_CHARS) { hideList(); return; }
        timer = setTimeout(() => fetchSuggestions(term), DEBOUNCE);
    });

    // Esc closes; click outside the panel closes.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.hidden) close();
    });
    document.addEventListener('click', (e) => {
        if (panel.hidden) return;
        if (!panel.contains(e.target) && !toggle.contains(e.target)) close();
    });
}
