<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    query: { type: String, default: '' },
});

const term = ref(props.query);
const products = ref([]);
const meta = ref({});
const loading = ref(false);

async function run() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/v1/search', { params: { q: term.value } });
        products.value = data.data ?? [];
        meta.value = data.meta ?? {};
    } catch (e) {
        products.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(() => { if (term.value) run(); });
</script>

<template>
    <div class="search-results">
        <form class="search-results__bar" @submit.prevent="run">
            <input type="search" v-model="term" placeholder="Search products…">
            <button type="submit" class="btn">Search</button>
        </form>

        <p v-if="loading" class="search-results__status">Searching…</p>
        <p v-else-if="!products.length && term" class="search-results__status">
            No results for “{{ term }}”.
        </p>

        <div v-else class="grid">
            <product-card v-for="p in products" :key="p.id" :product="p" />
        </div>
    </div>
</template>
