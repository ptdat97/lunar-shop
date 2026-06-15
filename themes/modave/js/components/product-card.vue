<script setup>
import { ref } from 'vue';

const props = defineProps({
    product: { type: Object, required: true },
});

const busy = ref(false);

function variant() {
    return props.product.variants?.[0];
}

async function addToCart() {
    const v = variant();
    if (!v || busy.value) return;
    busy.value = true;
    try {
        await window.axios.post('/api/v1/cart', { variant_id: v.id, quantity: 1 });
        window.dispatchEvent(new CustomEvent('cart:updated'));
    } catch (e) { /* noop */ } finally { busy.value = false; }
}

function quickView() {
    window.dispatchEvent(new CustomEvent('quickview:open', { detail: { slug: props.product.slug } }));
}
</script>

<template>
    <div class="card-product">
        <div class="card-product-wrapper">
            <a :href="product.slug ? `/products/${product.slug}` : '#'" class="product-img">
                <img class="lazyload img-product" :src="product.thumbnail || '/themes/modave/images/products/womens/women-19.jpg'" :alt="product.name">
            </a>
            <div class="list-product-btn">
                <a href="javascript:void(0);" class="box-icon quickview" @click="quickView">
                    <span class="icon icon-eye"></span><span class="tooltip">Quick View</span>
                </a>
            </div>
            <div class="list-btn-main">
                <button type="button" class="btn-main-product" :disabled="busy" @click="addToCart">
                    {{ busy ? 'Adding…' : 'Add To cart' }}
                </button>
            </div>
        </div>
        <div class="card-product-info">
            <a :href="product.slug ? `/products/${product.slug}` : '#'" class="title link">{{ product.name }}</a>
            <span class="price" v-if="product.variants?.[0]">{{ product.variants[0].price.formatted }}</span>
        </div>
    </div>
</template>
