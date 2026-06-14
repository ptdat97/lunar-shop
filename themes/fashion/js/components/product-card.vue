<script setup>
defineProps({
    product: { type: Object, required: true },
});
</script>

<template>
    <article class="product-card">
        <a :href="product.slug ? `/products/${product.slug}` : '#'" class="product-card__link">
            <div class="product-card__media">
                <img v-if="product.thumbnail" :src="product.thumbnail" :alt="product.name" loading="lazy">
                <div v-else class="product-card__placeholder"></div>
            </div>
            <h3 class="product-card__name">{{ product.name }}</h3>
            <p v-if="product.variants?.[0]" class="product-card__price">
                {{ product.variants[0].price.formatted }}
            </p>
        </a>
    </article>
</template>
