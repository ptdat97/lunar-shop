// Home "product tabs" section. Every tab's product grid is rendered server-side
// (SSR, crawlable, works with no JS — the first panel is visible, the rest are
// [hidden]). This enhancer just switches which panel shows when a tab is
// clicked. No fetching: the products are hand-picked per tab in the admin and
// already on the page.
//
// Markup contract (themes/fashion/views/sections/product-tabs.blade.php):
//   [data-product-tabs]
//     button.product-tabs__tab[data-tab-target="i"]  (.is-active = current)
//     [data-tab-panel="i"]                            (grid; [hidden] unless active)

function activate(section, index) {
    section.querySelectorAll('.product-tabs__tab').forEach((btn) => {
        const on = btn.dataset.tabTarget === index;
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    section.querySelectorAll('[data-tab-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.tabPanel !== index;
    });
}

export default function (root = document) {
    root.querySelectorAll('[data-product-tabs]').forEach((section) => {
        if (section.dataset.tabsInit) return;
        section.dataset.tabsInit = '1';

        section.querySelectorAll('.product-tabs__tab').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (btn.classList.contains('is-active')) return;
                activate(section, btn.dataset.tabTarget);
            });
        });
    });
}
