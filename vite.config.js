import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import fs from 'node:fs';

const theme = process.env.THEME ?? 'fashion';

// Only include entries the active theme actually ships (CSS entry is optional).
const input = [`themes/${theme}/js/app.js`];
if (fs.existsSync(`themes/${theme}/css/app.css`)) {
    input.unshift(`themes/${theme}/css/app.css`);
}

export default defineConfig({
    plugins: [
        laravel({
            input,
            refresh: [`themes/${theme}/**`],
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
