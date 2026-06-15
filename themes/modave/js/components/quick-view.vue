<script setup>
import { ref, onMounted } from 'vue';

const open = ref(false);
const loading = ref(false);
const product = ref(null);
const selectedVariant = ref(null);
const quantity = ref(1);
const busy = ref(false);
const message = ref('');

async function openView(slug) {
    if (!slug) return;
    open.value = true;
    loading.value = true;
    message.value = '';
    try {
        const { data } = await window.axios.get(`/api/v1/products/${slug}`);
        product.value = data.data;
        selectedVariant.value = product.value.variants?.[0] ?? null;
        quantity.value = 1;
    } catch (e) {
        product.value = null;
    } finally {
        loading.value = false;
    }
}

function close() {
    open.value = false;
    product.value = null;
}

async function addToCart() {
    if (!selectedVariant.value || busy.value) return;
    busy.value = true;
    try {
        await window.axios.post('/api/v1/cart', {
            variant_id: selectedVariant.value.id,
            quantity: quantity.value,
        });
        message.value = 'Added to cart';
        window.dispatchEvent(new CustomEvent('cart:updated'));
    } catch (e) {
        message.value = 'Could not add to cart';
    } finally {
        busy.value = false;
    }
}

onMounted(() => {
    window.addEventListener('quickview:open', (e) => openView(e.detail?.slug));
});
</script>

<template>
    <div v-if="open" class="quickview-overlay" @click.self="close">
        <div class="quickview-panel">
            <button type="button" class="quickview-close" @click="close" aria-label="Close">&times;</button>

            <p v-if="loading" class="quickview-loading">Loading…</p>
            <p v-else-if="!product" class="quickview-loading">Product not found.</p>

            <div v-else class="quickview-body">
                <div class="quickview-media">
                    <img :src="product.thumbnail || '/themes/modave/images/products/womens/women-3.jpg'" :alt="product.name">
                </div>
                <div class="quickview-info">
                    <p v-if="product.brand" class="quickview-brand text-btn-uppercase">{{ product.brand }}</p>
                    <h4 class="quickview-name">{{ product.name }}</h4>
                    <div class="quickview-price price-on-sale font-2">{{ selectedVariant?.price?.formatted }}</div>

                    <p v-if="product.description" class="quickview-desc text-secondary">{{ product.description }}</p>

                    <div v-if="product.variants?.length > 1" class="quickview-variants mb_15">
                        <span class="d-block mb_8">Variant</span>
                        <button
                            v-for="v in product.variants" :key="v.id" type="button"
                            class="tf-btn btn-sm me-2 mb-2"
                            :class="selectedVariant?.id === v.id ? 'btn-fill' : 'btn-outline'"
                            @click="selectedVariant = v"
                        >{{ v.sku }}</button>
                    </div>

                    <div class="quickview-actions d-flex align-items-center gap-3">
                        <div class="wg-quantity">
                            <input class="quantity-product" type="number" min="1" v-model.number="quantity">
                        </div>
                        <button type="button" class="btn-style-2 flex-grow-1 text-btn-uppercase fw-6"
                                :disabled="busy || !selectedVariant" @click="addToCart">
                            {{ busy ? 'Adding…' : 'Add to cart' }}
                        </button>
                    </div>

                    <p v-if="message" class="quickview-msg mt_10">{{ message }}</p>

                    <a :href="`/products/${product.slug}`" class="quickview-full link mt_10 d-inline-block">View full details</a>
                </div>
            </div>
        </div>
    </div>
</template>
