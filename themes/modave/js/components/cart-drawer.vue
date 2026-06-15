<script setup>
import { onMounted } from 'vue';
import { cart, fetchCart, updateLine, removeLine } from '../cart-store';

onMounted(fetchCart);
</script>

<template>
    <div class="tf-mini-cart-wrap">
        <div class="tf-mini-cart-main">
            <div class="header">
                <span class="title">Shopping cart</span>
                <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
            </div>

            <div class="tf-mini-cart-sroll">
                <p v-if="!cart.lines.length" class="text-center mt_20">Your cart is empty.</p>

                <div v-else class="tf-mini-cart-items">
                    <div v-for="line in cart.lines" :key="line.id" class="tf-mini-cart-item file-delete">
                        <div class="tf-mini-cart-image">
                            <a :href="line.slug ? `/products/${line.slug}` : '#'">
                                <img class="lazyload" :src="line.thumbnail || '/themes/modave/images/products/womens/women-8.jpg'" :alt="line.name">
                            </a>
                        </div>
                        <div class="tf-mini-cart-info flex-grow-1">
                            <div class="mb_12 d-flex align-items-center justify-content-between flex-wrap gap-12">
                                <div class="text-title">
                                    <a :href="line.slug ? `/products/${line.slug}` : '#'" class="link text-line-clamp-1">{{ line.name }}</a>
                                </div>
                                <div class="text-button tf-btn-remove remove" @click="removeLine(line.id)">Remove</div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-12">
                                <div class="wg-quantity small">
                                    <span class="btn-quantity minus-btn" @click="updateLine(line.id, Math.max(1, line.quantity - 1))">-</span>
                                    <input type="text" :value="line.quantity" readonly>
                                    <span class="btn-quantity plus-btn" @click="updateLine(line.id, line.quantity + 1)">+</span>
                                </div>
                                <div class="text-button">{{ line.sub_total }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tf-mini-cart-bottom">
            <div class="tf-mini-cart-bottom-wrap">
                <div class="tf-cart-totals-discounts">
                    <h5>Subtotal</h5>
                    <h5 class="tf-totals-total-value">{{ cart.totals.sub_total ?? '$0.00' }}</h5>
                </div>
                <div class="tf-cart-tax">
                    <p>Taxes and <a href="/cart" class="cart-checkbox">shipping</a> calculated at checkout</p>
                </div>
                <div class="tf-mini-cart-view-checkout">
                    <a href="/cart" class="tf-btn btn-outline radius-3 link w-100 justify-content-center">
                        <span>View cart</span>
                    </a>
                    <a href="/checkout" class="tf-btn btn-fill animate-hover-btn radius-3 w-100 justify-content-center" :class="{ disabled: !cart.lines.length }">
                        <span>Check out</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
