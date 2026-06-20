<script setup>
// Product detail island: owns the gallery + purchase panel together so picking a
// variant can swap the gallery images (shared `currentVariant` state). Hydrates
// from the embedded ProductResource payload — no fetch on mount. The static bits
// (size chart, related products) stay in Blade, outside this island.
import { ref } from 'vue';
import ProductGallery from './product/ProductGallery.vue';
import ProductPurchase from './ProductPurchase.vue';

const props = defineProps({
    id: { type: Number, default: null },
    name: { type: String, default: '' },
    slug: { type: String, default: '' },
    description: { type: String, default: '' },
    brand: { type: String, default: '' },
    variants: { type: Array, default: () => [] },
    images: { type: Array, default: () => [] },
});

// The variant currently chosen in the purchase panel; drives the gallery.
const currentVariant = ref(null);
</script>

<template>
    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <ProductGallery :images="images" :name="name" :variant="currentVariant" />
        </div>

        <div class="col-12 col-lg-5">
            <div v-if="brand" class="text-muted text-uppercase small">{{ brand }}</div>
            <h1 class="h3">{{ name }}</h1>

            <div class="my-4">
                <ProductPurchase
                    :id="id" :name="name" :slug="slug" :variants="variants"
                    @variant-change="currentVariant = $event" />
            </div>

            <div v-if="description" class="mt-4">
                <h2 class="h6 text-uppercase">Description</h2>
                <div class="text-muted" v-html="description"></div>
            </div>
        </div>
    </div>
</template>
