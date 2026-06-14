import axios from 'axios';
import { createApp } from 'vue';

/*
| Modave theme uses Bootstrap 5 + jQuery (loaded as vendor <script> tags).
| Vite only bundles our Vue islands + axios for the dynamic bits (cart, etc.).
*/
window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

const csrf = document.querySelector('meta[name="csrf-token"]');
if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
}

const islands = import.meta.glob('./components/*.vue', { eager: true });

const registry = {};
for (const path in islands) {
    registry[path.split('/').pop().replace('.vue', '')] = islands[path].default;
}

document.querySelectorAll('[data-vue]').forEach((el) => {
    const component = registry[el.dataset.vue];
    if (!component) return;

    const props = { ...el.dataset };
    delete props.vue;

    const app = createApp(component, props);
    for (const [name, child] of Object.entries(registry)) {
        app.component(name, child);
    }
    app.mount(el);
});
