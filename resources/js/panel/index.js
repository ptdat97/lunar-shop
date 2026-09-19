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
import MediaIndex from '../pages/shop/media/Index.vue';
import MediaPicker from '../pages/shop/media/Picker.vue';
import { openFileManager } from './media/openFileManager';
import { installNativeUploadBridge } from './media/nativeUploadBridge';
import LifetimeWidget from './widgets/LifetimeWidget.vue';
import ProductSizing from './slots/ProductSizing.vue';
import ColourImages from './slots/ColourImages.vue';
import StaleCommitmentsWidget from './widgets/StaleCommitmentsWidget.vue';

// The panel's page resolver auto-applies its shell layout to add-on pages, but
// only when the page declares none (`layout ??=`). A settings page brings its
// own full-page chrome via SettingsShell, so it declares this passthrough to
// keep the sidebar from being nested inside itself.
const Bare = { render() { return this.$slots.default?.(); } };

SettingsEdit.layout = Bare;

// The file manager's picker is loaded inside an iframe by openFileManager();
// a sidebar in there would be a panel inside the panel.
MediaPicker.layout = Bare;

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
    'shop/media/Index': MediaIndex,
    'shop/media/Picker': MediaPicker,
});

// Lunar's own gallery and swatch uploaders open the file manager too, instead
// of the computer's file dialog — see nativeUploadBridge.js.
installNativeUploadBridge();

// The one way to get an image into anything on the panel: open the file
// manager and take what the admin chose. Published for scripts outside this
// bundle; the URL defaults to the one AssetsServiceProvider shares.
window.ShopFileManager = {
    open: (options = {}) => openFileManager({
        url: window.InertiaVue3?.usePage?.().props?.fileManager?.url,
        ...options,
    }),
};

// Dashboard widgets are components, not pages: the dashboard resolves them by
// the namespaced name the PHP Widget's component() returns.
// Dashboard widgets and page slots are both components under one namespace —
// the name a PHP Widget's component() or a Slot's component returns.
window.LunarPanel.registerComponents('shop', { LifetimeWidget, ProductSizing, ColourImages, StaleCommitmentsWidget });
