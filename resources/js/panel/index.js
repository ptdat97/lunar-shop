/**
 * The shop's panel add-on bundle.
 *
 * Built as an IIFE by vite.panel.config.js, with `vue`, `@inertiajs/vue3`,
 * `vue-i18n` and `@lunarphp/panel` externalised to the globals the panel's own
 * app.ts publishes — sharing one Vue and one Inertia module instance is what
 * makes `usePage()`, `<Link>` and the panel's components work in these pages.
 *
 * Registration happens at the top level, not in `booting()`: the panel holds
 * its first render until DOMContentLoaded so anything registered here is
 * present before the first page resolve.
 */
import ResourceIndex from '../pages/shop/resource/Index.vue';
import ResourceForm from '../pages/shop/resource/Form.vue';
import SettingsEdit from '../pages/shop/settings/Edit.vue';
import LifetimeWidget from './widgets/LifetimeWidget.vue';

// The panel's page resolver auto-applies its shell layout to add-on pages, but
// only when the page declares none (`layout ??=`). A settings page brings its
// own full-page chrome via SettingsShell, so it declares this passthrough to
// keep the sidebar from being nested inside itself.
const Bare = { render() { return this.$slots.default?.(); } };

SettingsEdit.layout = Bare;

// The names ResourceController renders. Each is the component's own path under
// resources/js/pages — the convention Inertia's page finder follows — so a
// renamed file fails a test instead of 404-ing in the browser.
//
// Two pages serve every declared resource: banners, pages, redirects, and
// whatever a module declares next.
window.LunarPanel.registerPages({
    'shop/resource/Index': ResourceIndex,
    'shop/resource/Form': ResourceForm,
    'shop/settings/Edit': SettingsEdit,
});

// Dashboard widgets are components, not pages: the dashboard resolves them by
// the namespaced name the PHP Widget's component() returns.
window.LunarPanel.registerComponents('shop', { LifetimeWidget });
