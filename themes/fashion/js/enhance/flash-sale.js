// Flash-sale countdown. Enhances every SSR [data-flash-sale] element (promo
// bar, home flash-sale section, promotion page header) by ticking a live
// "ends in" timer. The markup renders server-side regardless; this only fills
// in the countdown badge. No fetch — reads data-ends-at (and an optional
// localized data-ended-text).

function format(ms) {
    if (ms <= 0) return '00:00:00';
    const total = Math.floor(ms / 1000);
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const mins = Math.floor((total % 3600) / 60);
    const secs = total % 60;
    const pad = (n) => String(n).padStart(2, '0');

    const hms = `${pad(hours)}:${pad(mins)}:${pad(secs)}`;
    return days > 0 ? `${days}d ${hms}` : hms;
}

function enhance(bar) {
    if (bar.dataset.countdownInit) return;
    bar.dataset.countdownInit = '1';

    const endsAt = bar.dataset.endsAt;
    const badge = bar.querySelector('[data-flash-countdown]');
    if (!endsAt || !badge) return;

    const end = new Date(endsAt).getTime();
    if (Number.isNaN(end)) return;

    const tick = () => {
        const remaining = end - Date.now();
        if (remaining <= 0) {
            badge.textContent = bar.dataset.endedText || 'Ended';
            bar.classList.add('opacity-50');
            clearInterval(timer);
            return;
        }
        badge.textContent = format(remaining);
    };

    tick();
    const timer = setInterval(tick, 1000);
}

export default function flashSale(root = document) {
    const scope = root.querySelectorAll ? root : document;
    scope.querySelectorAll('[data-flash-sale]').forEach(enhance);
}
