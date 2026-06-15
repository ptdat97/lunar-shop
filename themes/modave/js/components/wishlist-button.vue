<script setup>
import { computed, onMounted } from 'vue';
import { wishlist, loadWishlist, toggleWishlist } from '../wishlist-store';

const props = defineProps({
    product: { type: [String, Number], required: true }, // product id
});

const pid = computed(() => Number(props.product));
const active = computed(() => wishlist.ids.has(pid.value));

onMounted(() => {
    if (!wishlist.loaded) loadWishlist();
});

function toggle() {
    toggleWishlist(pid.value);
}
</script>

<template>
    <a href="javascript:void(0);" class="box-icon wishlist btn-icon-action" :class="{ active }" @click="toggle">
        <span class="icon icon-heart"></span>
        <span class="tooltip">{{ active ? 'Remove from wishlist' : 'Add to wishlist' }}</span>
    </a>
</template>
