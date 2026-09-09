import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import lunarPanel from '@lunarphp/panel-vite-plugin';

/**
 * Build for the shop's Lunar panel add-on bundle — a second, separate Vite
 * config from the storefront's.
 *
 * They cannot share one: the storefront is Blade + vanilla JS served through
 * laravel-vite-plugin's manifest, while this is an IIFE that externalises Vue
 * to the panel's own runtime globals. One config would have to be both.
 *
 * Output goes straight into public/vendor/lunar-panel/shop/build, which is what
 * ShopSection::vite() names as its buildDirectory — so `lunar:panel:link` has
 * nothing to symlink and there is no publish step to forget.
 */
export default defineConfig({
    plugins: [vue(), lunarPanel({ name: 'ShopPanelAddon' })],
    // outDir sits inside public/, and Vite's default publicDir IS public/ — it
    // would copy the whole public tree into the build on every run (recursively,
    // until the path length blows up). Nothing here needs a static public dir.
    publicDir: false,
    build: {
        outDir: 'public/vendor/lunar-panel/shop/build',
        emptyOutDir: true,
        rollupOptions: {
            input: 'resources/js/panel/index.js',
        },
    },
});
