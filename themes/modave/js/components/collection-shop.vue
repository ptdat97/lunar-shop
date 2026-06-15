<script setup>
import { ref, reactive, onMounted, computed } from 'vue';

const props = defineProps({
    scope: { type: String, default: '' },        // collection slug
    initialSort: { type: String, default: 'best-selling' },
});

const products = ref([]);
const facets = ref({ size: [], color: [] });
const meta = ref({ total: 0, page: 1, last_page: 1 });
const loading = ref(false);

const selected = reactive({ size: [], color: [] });
const sort = ref(props.initialSort);
const page = ref(1);

const sortOptions = [
    { value: 'best-selling', label: 'Best selling' },
    { value: 'a-z', label: 'Alphabetically, A-Z' },
    { value: 'z-a', label: 'Alphabetically, Z-A' },
    { value: 'price-low-high', label: 'Price, low to high' },
    { value: 'price-high-low', label: 'Price, high to low' },
];

// Map storefront sort → search engine sort keys.
const sortMap = {
    'best-selling': null,
    'a-z': 'a-z',
    'z-a': 'z-a',
    'price-low-high': 'price-low-high',
    'price-high-low': 'price-high-low',
};

const hasFilters = computed(() => selected.size.length || selected.color.length);

async function load() {
    loading.value = true;
    try {
        const params = {
            scope: props.scope,
            page: page.value,
            per_page: 24,
            sort: sortMap[sort.value] ?? undefined,
            filters: { size: selected.size, color: selected.color },
        };
        const { data } = await window.axios.get('/api/v1/search', { params });
        products.value = data.data ?? [];
        facets.value = data.facets ?? { size: [], color: [] };
        meta.value = data.meta ?? meta.value;
    } catch (e) {
        products.value = [];
    } finally {
        loading.value = false;
    }
}

function toggle(group, value) {
    const arr = selected[group];
    const i = arr.indexOf(value);
    i === -1 ? arr.push(value) : arr.splice(i, 1);
    page.value = 1;
    load();
}

function clearAll() {
    selected.size = [];
    selected.color = [];
    page.value = 1;
    load();
}

function changeSort(v) {
    sort.value = v;
    page.value = 1;
    load();
}

function goPage(p) {
    page.value = p;
    load();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function priceText(p) {
    return p.variants?.[0]?.price?.formatted ?? '';
}

onMounted(load);
</script>

<template>
    <div class="collection-shop">
        <div class="tf-shop-control">
            <div class="tf-control-filter">
                <span class="text-caption-1">{{ meta.total }} products</span>
                <button v-if="hasFilters" type="button" class="btn-clear-filter ms-3 text-button" @click="clearAll">Clear filters</button>
            </div>
            <div class="tf-control-sorting d-flex align-items-center gap-2">
                <p class="d-none d-lg-block text-caption-1 mb-0">Sort by:</p>
                <select class="form-select w-auto" :value="sort" @change="changeSort($event.target.value)">
                    <option v-for="o in sortOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar facets -->
            <aside class="col-lg-3 mb_20">
                <div class="filter-group mb_20" v-if="facets.color.length">
                    <h6 class="mb_12">Color</h6>
                    <label v-for="f in facets.color" :key="f.value" class="d-flex align-items-center gap-2 mb_8">
                        <input type="checkbox" :checked="selected.color.includes(f.value)" @change="toggle('color', f.value)">
                        <span>{{ f.value }} ({{ f.count }})</span>
                    </label>
                </div>
                <div class="filter-group" v-if="facets.size.length">
                    <h6 class="mb_12">Size</h6>
                    <label v-for="f in facets.size" :key="f.value" class="d-flex align-items-center gap-2 mb_8">
                        <input type="checkbox" :checked="selected.size.includes(f.value)" @change="toggle('size', f.value)">
                        <span>{{ f.value }} ({{ f.count }})</span>
                    </label>
                </div>
            </aside>

            <!-- Product grid -->
            <div class="col-lg-9">
                <p v-if="loading" class="text-center">Loading…</p>
                <p v-else-if="!products.length" class="text-center">No products match your filters.</p>
                <div v-else class="tf-grid-layout tf-col-2 md-col-3">
                    <product-card v-for="p in products" :key="p.id" :product="p" />
                </div>

                <ul v-if="meta.last_page > 1" class="wg-pagination justify-content-center mt_20">
                    <li v-for="p in meta.last_page" :key="p" :class="{ active: p === meta.page }">
                        <a v-if="p !== meta.page" href="#" class="pagination-item text-button" @click.prevent="goPage(p)">{{ p }}</a>
                        <div v-else class="pagination-item text-button">{{ p }}</div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
