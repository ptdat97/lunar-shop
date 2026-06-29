import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import fs from 'node:fs';

const theme = process.env.THEME ?? 'fashion';

// Only include entries the active theme actually ships (SCSS entry is optional).
const input = [`themes/${theme}/js/app.js`];
if (fs.existsSync(`themes/${theme}/css/app.scss`)) {
    input.unshift(`themes/${theme}/css/app.scss`);
}

export default defineConfig({
    plugins: [
        laravel({
            input,
            refresh: [`themes/${theme}/**`],
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.x uses @import internally (deprecated in Dart Sass 3.0).
                // Silence known deprecations from node_modules until Bootstrap 6 ships.
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
