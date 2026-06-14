import axios from 'axios';
import $ from 'jquery';
import { createApp } from 'vue';

/*
|--------------------------------------------------------------------------
| Axios — talks to /api/v1 (same domain → Sanctum cookie + CSRF)
|--------------------------------------------------------------------------
*/
window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

const csrf = document.querySelector('meta[name="csrf-token"]');
if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
}

/*
|--------------------------------------------------------------------------
| jQuery — small utilities / legacy plugins only (not primary state)
|--------------------------------------------------------------------------
*/
window.$ = window.jQuery = $;

/*
|--------------------------------------------------------------------------
| Vue islands — mount components onto [data-vue="name"] elements.
| Each island fetches its own data from /api/v1; no SPA.
|--------------------------------------------------------------------------
*/
const islands = import.meta.glob('./components/*.vue', { eager: true });

const registry = {};
for (const path in islands) {
    const name = path.split('/').pop().replace('.vue', '');
    registry[name] = islands[path].default;
}

document.querySelectorAll('[data-vue]').forEach((el) => {
    const name = el.dataset.vue;
    const component = registry[name];

    if (!component) {
        console.warn(`[theme] No Vue island registered for "${name}"`);
        return;
    }

    // data-* attributes (besides data-vue) become props.
    const props = { ...el.dataset };
    delete props.vue;

    const app = createApp(component, props);

    // Make every island available as a child component (e.g. <product-card>).
    for (const [childName, child] of Object.entries(registry)) {
        app.component(childName, child);
    }

    app.mount(el);
});
