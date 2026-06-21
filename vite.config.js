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
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
