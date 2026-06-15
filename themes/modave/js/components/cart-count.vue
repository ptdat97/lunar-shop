<script setup>
import { onMounted } from 'vue';
import { cart, fetchCart } from '../cart-store';

onMounted(fetchCart);
</script>

<template>
    <span class="count-box" v-show="cart.linesCount > 0">{{ cart.linesCount }}</span>
</template>
