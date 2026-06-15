<script setup>
import { ref, onMounted } from 'vue';

const products = ref([]);
const loading = ref(true);
const authed = ref(true);

onMounted(async () => {
    try {
        const { data } = await window.axios.get('/api/v1/wishlist');
        products.value = data.data ?? [];
    } catch (e) {
        if (e?.response?.status === 401) authed.value = false;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="wishlist-page">
        <p v-if="loading" class="text-center">Loading…</p>
        <div v-else-if="!authed" class="text-center">
            <p>Please log in to view your wishlist.</p>
            <a href="/lunar/login" class="tf-btn btn-fill">Log in</a>
        </div>
        <p v-else-if="!products.length" class="text-center">Your wishlist is empty.</p>
        <div v-else class="tf-grid-layout tf-col-2 md-col-3 lg-col-4">
            <div v-for="p in products" :key="p.id" class="card-product">
                <div class="card-product-wrapper">
                    <a :href="`/products/${p.slug}`" class="product-img">
                        <img class="lazyload img-product" :src="p.thumbnail || '/themes/modave/images/products/womens/women-8.jpg'" :alt="p.name">
                    </a>
                </div>
                <div class="card-product-info">
                    <a :href="`/products/${p.slug}`" class="title link">{{ p.name }}</a>
                    <span class="price" v-if="p.variants?.[0]">{{ p.variants[0].price.formatted }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
