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
        <form class="d-flex gap-2 mb_20" @submit.prevent="run">
            <input type="search" class="form-control" v-model="term" placeholder="Search products…">
            <button type="submit" class="tf-btn btn-fill">Search</button>
        </form>

        <p v-if="loading" class="text-center">Searching…</p>
        <p v-else-if="!products.length && term" class="text-center">No results for “{{ term }}”.</p>

        <div v-else class="tf-grid-layout tf-col-2 md-col-3 lg-col-4">
            <product-card v-for="p in products" :key="p.id" :product="p" />
        </div>
    </div>
</template>
