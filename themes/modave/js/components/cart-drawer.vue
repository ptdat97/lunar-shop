<script setup>
import { onMounted } from 'vue';
import { cart, fetchCart, updateLine, removeLine } from '../cart-store';

onMounted(fetchCart);
</script>

<template>
    <div class="tf-minicart-main">
        <div class="header">
            <h5>Shopping cart</h5>
            <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
        </div>

        <div class="tf-mini-cart-wrap">
            <div class="tf-mini-cart-main">
                <div class="tf-mini-cart-sroll">
                    <p v-if="!cart.lines.length" class="text-center mt_20">Your cart is empty.</p>

                    <div v-else class="tf-mini-cart-items">
                        <div v-for="line in cart.lines" :key="line.id" class="tf-mini-cart-item d-flex gap-3 mb_16">
                            <div class="tf-mini-cart-info flex-grow-1">
                                <a class="title link" :href="line.slug ? `/products/${line.slug}` : '#'">{{ line.name }}</a>
                                <div class="text-secondary small">{{ line.sku }}</div>
                                <div class="d-flex align-items-center justify-content-between mt_8">
                                    <div class="wg-quantity small">
                                        <input type="number" min="1" :value="line.quantity"
                                               @change="updateLine(line.id, Number($event.target.value))"
                                               class="quantity-product">
                                    </div>
                                    <span class="price">{{ line.sub_total }}</span>
                                    <button type="button" class="remove icon-close" @click="removeLine(line.id)" aria-label="Remove"></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="cart.lines.length" class="tf-mini-cart-bottom">
                <div class="tf-mini-cart-bottom-wrap">
                    <div class="tf-cart-totals-discounts d-flex justify-content-between">
                        <span>Subtotal</span>
                        <span class="total-value">{{ cart.totals.sub_total }}</span>
                    </div>
                    <div class="tf-mini-cart-line"></div>
                    <div class="tf-mini-cart-view-checkout d-flex gap-2 mt_12">
                        <a href="/search" class="tf-btn btn-outline flex-grow-1">Continue</a>
                        <a href="/checkout" class="tf-btn btn-fill flex-grow-1">Checkout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
