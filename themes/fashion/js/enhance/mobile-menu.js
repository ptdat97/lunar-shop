// Mobile off-canvas menu. The drawer, its accordions and the close button are
// all plain Bootstrap data-bs-* markup and work without this file — this only
// opens the section containing the current page, so a shopper browsing a
// category does not have to re-find their branch every time they open the menu.

export default function (root = document) {
    const menu = root.querySelector('[data-mobile-menu]');
    if (!menu || menu.dataset.mobileMenuInit) return;
    menu.dataset.mobileMenuInit = '1';

    const current = menu.querySelector('.mobile-menu__link.is-current');
    const panel = current?.closest('.collapse');

    if (panel) {
        const toggle = menu.querySelector(`[data-bs-target="#${CSS.escape(panel.id)}"]`);

        // Set the open state directly rather than via Collapse.show(): the
        // drawer is still hidden at boot, so an animated expand would run
        // against a zero-height element and settle at the wrong height.
        panel.classList.add('show');
        toggle?.classList.remove('collapsed');
        toggle?.setAttribute('aria-expanded', 'true');

        // Bring the active branch into view once the drawer has actually
        // opened — scrollIntoView is a no-op while the offcanvas is hidden.
        menu.addEventListener('shown.bs.offcanvas', () => {
            current.scrollIntoView({ block: 'center', behavior: 'auto' });
        }, { once: true });
    }
}
