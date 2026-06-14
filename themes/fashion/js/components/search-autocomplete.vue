<script setup>
import { ref, watch } from 'vue';

const term = ref('');
const suggestions = ref([]);
let timer = null;

watch(term, (value) => {
    clearTimeout(timer);
    if (value.trim().length < 2) {
        suggestions.value = [];
        return;
    }
    timer = setTimeout(async () => {
        try {
            const { data } = await window.axios.get('/api/v1/search/suggest', { params: { q: value } });
            suggestions.value = data.data ?? [];
        } catch (e) {
            suggestions.value = [];
        }
    }, 200);
});

function submit() {
    if (term.value.trim()) {
        window.location.href = `/search?q=${encodeURIComponent(term.value)}`;
    }
}
</script>

<template>
    <div class="search-autocomplete">
        <input
            type="search" v-model="term" placeholder="Search products…"
            @keyup.enter="submit" class="search-autocomplete__input"
        >
        <ul v-if="suggestions.length" class="search-autocomplete__list">
            <li v-for="s in suggestions" :key="s">
                <a :href="`/search?q=${encodeURIComponent(s)}`">{{ s }}</a>
            </li>
        </ul>
    </div>
</template>
