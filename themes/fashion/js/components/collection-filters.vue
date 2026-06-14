<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    scope: { type: String, default: '' },
});

// Phase 2 scaffold: facet UI driven by /api/v1/search?scope=. Facet buckets
// arrive once the database driver exposes them; sort works today.
const sort = ref('newest');
const facets = ref({});
const loading = ref(false);

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/api/v1/search', {
            params: { scope: props.scope, sort: sort.value },
        });
        facets.value = data.facets ?? {};
    } catch (e) {
        facets.value = {};
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="collection-filters">
        <label class="collection-filters__sort">
            Sort
            <select v-model="sort" @change="load">
                <option value="newest">Newest</option>
                <option value="oldest">Oldest</option>
            </select>
        </label>

        <div v-for="(buckets, name) in facets" :key="name" class="collection-filters__group">
            <strong>{{ name }}</strong>
            <label v-for="b in buckets" :key="b.value">
                <input type="checkbox" :value="b.value"> {{ b.label }} ({{ b.count }})
            </label>
        </div>
    </div>
</template>
