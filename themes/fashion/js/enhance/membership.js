// Membership tier card on the account dashboard. Loads the signed-in
// customer's loyalty tier + progress from /api/v1/promotions/membership and
// fills in the [data-membership] card. Auth-only; failures hide the card.

import api from '../api.js';
import { t } from '../i18n.js';

// API returns spend/remaining in minor units of the default currency (VND has
// no minor unit subdivision in practice; factor 100 in Lunar). Format as VND.
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
            perkEl.textContent = t('membership.discount_every_order',
                { percent: info.tier.discount_percentage },
                `You get ${info.tier.discount_percentage}% off every order.`);
            perkEl.hidden = false;
        }
    } else {
        tierEl.textContent = t('membership.not_a_member', {}, 'Not a member yet');
        tierEl.classList.replace('bg-dark', 'bg-secondary');
    }

    if (info.next_tier) {
        // Số tiền đã được SERVER định dạng theo đúng tiền tệ của shop.
        // Trước đây file này tự định dạng bằng `formatVnd()`: ghim cứng VND bất
        // kể shop dùng loại tiền nào, và chia cho 100 — tức giả định 2 chữ số
        // thập phân, trong khi VND có 0. Với shop VND thật nó hiện đúng 1% số
        // tiền, và không có gì báo lỗi.
        const remaining = info.next_tier.remaining_formatted ?? '';

        nextEl.textContent = t('membership.spend_to_reach',
            { amount: remaining, tier: info.next_tier.name },
            `Spend ${remaining} more to reach ${info.next_tier.name}.`);
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
