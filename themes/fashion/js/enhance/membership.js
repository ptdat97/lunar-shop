// Membership tier card on the account dashboard. Loads the signed-in
// customer's loyalty tier + progress from /api/v1/promotions/membership and
// fills in the [data-membership] card. Auth-only; failures hide the card.

import api from '../api.js';

// API returns spend/remaining in minor units of the default currency (VND has
// no minor unit subdivision in practice; factor 100 in Lunar). Format as VND.
function formatVnd(minor) {
    const amount = Math.round((minor ?? 0) / 100);
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

export default async function membership(root = document) {
    const card = root.querySelector?.('[data-membership]') ?? document.querySelector('[data-membership]');
    if (!card) return;

    let info;
    try {
        const { data } = await api.get('/promotions/membership');
        info = data?.data;
    } catch {
        return; // guest / not linked / error → leave hidden
    }

    if (!info || !info.enabled) return;

    const tierEl = card.querySelector('[data-membership-tier]');
    const perkEl = card.querySelector('[data-membership-perk]');
    const nextEl = card.querySelector('[data-membership-next]');
    const progWrap = card.querySelector('[data-membership-progress-wrap]');
    const progBar = card.querySelector('[data-membership-progress]');

    if (info.tier) {
        tierEl.textContent = info.tier.name;
        if (info.tier.discount_percentage) {
            perkEl.textContent = `You get ${info.tier.discount_percentage}% off every order.`;
            perkEl.hidden = false;
        }
    } else {
        tierEl.textContent = 'Not a member yet';
        tierEl.classList.replace('bg-dark', 'bg-secondary');
    }

    if (info.next_tier) {
        nextEl.textContent = `Spend ${formatVnd(info.next_tier.remaining)} more to reach ${info.next_tier.name}.`;
        nextEl.hidden = false;

        // Rough progress within the current → next band based on remaining.
        const spend = info.lifetime_spend ?? 0;
        const target = spend + (info.next_tier.remaining ?? 0);
        const pct = target > 0 ? Math.min(100, Math.round((spend / target) * 100)) : 0;
        progBar.style.width = `${pct}%`;
        progWrap.hidden = false;
    }

    card.classList.remove('d-none');
}
