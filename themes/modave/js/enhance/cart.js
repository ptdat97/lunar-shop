/*
| Mini-cart (vanilla). Replaces the old Vue cart-drawer + cart-count.
| - Renders #shoppingCart drawer items / threshold / totals from /api/v1/cart
| - Updates the header [data-cart-count] badge
| - Qty +/- , remove, coupon, note, and slide-up tool panels (.open)
|
| Server-side (Lunar) is source of truth. Any add-to-cart elsewhere dispatches
| `cart:updated`; this module listens and refreshes. It also re-dispatches the
| event so the Vue cart page / checkout store (if mounted) stay in sync.
*/

const PLACEHOLDER = '/themes/modave/images/products/womens/women-8.jpg';

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
}

let state = { lines: [], totals: {}, lines_count: 0, coupon_code: null, free_shipping: null };
let refreshing = false;

function lineHtml(line) {
    const url = line.slug ? `/products/${encodeURIComponent(line.slug)}` : '#';
    const img = line.thumbnail || PLACEHOLDER;
    return `
    <div class="tf-mini-cart-item" data-line="${line.id}">
        <div class="tf-mini-cart-image">
            <a href="${url}"><img class="lazyload" src="${esc(img)}" alt="${esc(line.name)}"></a>
        </div>
        <div class="tf-mini-cart-info flex-grow-1">
            <div class="mb_12 d-flex align-items-center justify-content-between flex-wrap gap-12">
                <div class="text-title"><a href="${url}" class="link text-line-clamp-1">${esc(line.name)}</a></div>
                <div class="text-button tf-btn-remove remove" data-cart-remove="${line.id}">Remove</div>
            </div>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-12">
                <div class="wg-quantity small">
                    <span class="btn-quantity minus-btn" data-cart-dec="${line.id}">-</span>
                    <input type="text" value="${line.quantity}" readonly>
                    <span class="btn-quantity plus-btn" data-cart-inc="${line.id}">+</span>
                </div>
                <div class="text-button">${esc(line.sub_total)}</div>
            </div>
        </div>
    </div>`;
}

function render(root) {
    const items = root.querySelector('[data-cart-items]');
    const empty = root.querySelector('[data-cart-empty]');
    const subtotal = root.querySelector('[data-cart-subtotal]');
    const checkout = root.querySelector('[data-cart-checkout]');
    const couponWrap = root.querySelector('[data-cart-coupon-applied]');
    const couponCode = root.querySelector('[data-cart-coupon-code]');

    if (items) items.innerHTML = state.lines.map(lineHtml).join('');
    if (empty) empty.style.display = state.lines.length ? 'none' : '';
    if (subtotal) subtotal.textContent = state.totals.sub_total ?? '$0.00';
    if (checkout) checkout.classList.toggle('disabled', !state.lines.length);

    if (couponWrap) {
        couponWrap.style.display = state.coupon_code ? '' : 'none';
        if (couponCode) couponCode.textContent = state.coupon_code ?? '';
    }

    // Free-shipping threshold bar.
    const threshold = root.querySelector('[data-cart-threshold]');
    const progress = root.querySelector('[data-cart-progress]');
    const thresholdText = root.querySelector('[data-cart-threshold-text]');
    const fs = state.free_shipping;
    if (threshold) {
        if (fs) {
            threshold.style.display = '';
            if (progress) { progress.style.width = `${fs.progress}%`; progress.setAttribute('data-progress', fs.progress); }
            if (thresholdText) {
                thresholdText.innerHTML = fs.qualified
                    ? "Congratulations! You've got free shipping!"
                    : `Buy <span class="fw-6">${esc(fs.remaining)}</span> more to get free shipping!`;
            }
        } else {
            threshold.style.display = 'none';
        }
    }

    // Header count badge(s).
    document.querySelectorAll('[data-cart-count]').forEach((el) => {
        el.textContent = state.lines_count;
        el.style.display = state.lines_count > 0 ? '' : 'none';
    });
}

async function refresh(root) {
    if (refreshing) return;
    refreshing = true;
    try {
        const { data } = await window.axios.get('/api/v1/cart');
        state = data.data ?? state;
        render(root);
    } catch (e) { /* cart may not exist yet */ } finally {
        refreshing = false;
    }
}

async function mutate(root, promise) {
    try {
        const { data } = await promise;
        state = data.data ?? state;
        render(root);
        // Keep other cart consumers (Vue cart page/checkout) in sync without a loop.
        window.dispatchEvent(new CustomEvent('cart:refreshed'));
    } catch (e) { /* noop */ }
}

export default function cart(root = document) {
    const drawer = root.querySelector('[data-cart-drawer]');
    // Even without the drawer on the page we still want the count badge to load.
    const target = drawer || document;
    if (document.body.dataset.cartBound) { refresh(target); return; }
    document.body.dataset.cartBound = '1';

    // Initial load (count badge + drawer contents).
    refresh(target);

    // Delegated interactions on the drawer.
    if (drawer) {
        drawer.addEventListener('click', (e) => {
            const inc = e.target.closest('[data-cart-inc]');
            const dec = e.target.closest('[data-cart-dec]');
            const rm = e.target.closest('[data-cart-remove]');
            const toolBtn = e.target.closest('[data-cart-tool-btn]');
            const toolClose = e.target.closest('.tf-mini-cart-tool-close');

            if (inc) {
                const line = state.lines.find((l) => l.id == inc.dataset.cartInc);
                if (line) mutate(drawer, window.axios.patch(`/api/v1/cart/lines/${line.id}`, { quantity: line.quantity + 1 }));
            } else if (dec) {
                const line = state.lines.find((l) => l.id == dec.dataset.cartDec);
                if (line) mutate(drawer, window.axios.patch(`/api/v1/cart/lines/${line.id}`, { quantity: Math.max(1, line.quantity - 1) }));
            } else if (rm) {
                mutate(drawer, window.axios.delete(`/api/v1/cart/lines/${rm.dataset.cartRemove}`));
            } else if (toolBtn) {
                openPanel(drawer, toolBtn.dataset.cartToolBtn);
            } else if (toolClose) {
                closePanels(drawer);
            }
        });

        // Coupon form.
        const couponForm = drawer.querySelector('[data-cart-coupon-form]');
        couponForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const code = couponForm.querySelector('input[name="code"]').value.trim();
            if (!code) return;
            await mutate(drawer, window.axios.post('/api/v1/cart/coupon', { code }));
            couponForm.reset();
            closePanels(drawer);
        });

        // Note form (UI-only for now — no backend field yet).
        const noteForm = drawer.querySelector('[data-cart-note-form]');
        noteForm?.addEventListener('submit', (e) => { e.preventDefault(); closePanels(drawer); });
    }

    // Refresh whenever something adds to the cart (e.g. product card / variant picker).
    window.addEventListener('cart:updated', () => refresh(target));

    // "You May Also Like" — load when the drawer opens (recommendations depend on
    // cart contents, so fetch fresh each open rather than caching).
    const modal = document.getElementById('shoppingCart');
    if (drawer && modal) {
        modal.addEventListener('shown.bs.modal', () => loadRecommendations(drawer));
    }
}

function recItemHtml(p) {
    const url = p.slug ? `/products/${encodeURIComponent(p.slug)}` : '#';
    const img = p.thumbnail || PLACEHOLDER;
    const variant = p.variants?.[0];
    const price = variant?.price?.formatted ?? '';
    return `
    <div class="list-cart-item">
        <div class="image">
            <a href="${url}"><img class="lazyload" src="${esc(img)}" alt="${esc(p.name)}"></a>
        </div>
        <div class="content">
            <div class="name"><a class="link text-line-clamp-1" href="${url}">${esc(p.name)}</a></div>
            <div class="cart-item-bot">
                <div class="text-button price">${esc(price)}</div>
                ${variant ? `<a class="link text-button" href="javascript:void(0);" data-add-to-cart data-variant="${variant.id}">Add to cart</a>` : ''}
            </div>
        </div>
    </div>`;
}

async function loadRecommendations(drawer) {
    const box = drawer.parentElement.querySelector('[data-cart-recommendations]');
    const list = drawer.parentElement.querySelector('[data-cart-recommendations-list]');
    if (!box || !list) return;
    try {
        const { data } = await window.axios.get('/api/v1/cart/recommendations', { params: { limit: 6 } });
        const items = data.data ?? [];
        if (items.length) {
            list.innerHTML = items.map(recItemHtml).join('');
            box.style.display = '';
        } else {
            box.style.display = 'none';
        }
    } catch (e) { box.style.display = 'none'; }
}

function openPanel(drawer, name) {
    const panel = drawer.querySelector(`[data-cart-tool-panel="${name}"]`);
    const isOpen = panel?.classList.contains('open');
    closePanels(drawer);
    if (panel && !isOpen) panel.classList.add('open');
}

function closePanels(drawer) {
    drawer.querySelectorAll('.tf-mini-cart-tool-openable').forEach((p) => p.classList.remove('open'));
}
