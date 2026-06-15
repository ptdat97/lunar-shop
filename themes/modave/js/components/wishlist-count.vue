<script setup>
import { onMounted } from 'vue';
import { wishlist, loadWishlist } from '../wishlist-store';

onMounted(() => {
    if (!wishlist.loaded) loadWishlist();
});
</script>

<template>
    <span class="count-box" v-show="wishlist.count > 0">{{ wishlist.count }}</span>
</template>
