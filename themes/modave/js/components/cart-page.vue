<script setup>
import { ref, onMounted } from 'vue';
import { cart, fetchCart, updateLine, removeLine, applyCoupon, removeCoupon } from '../cart-store';

const couponInput = ref('');
const couponError = ref('');
const couponBusy = ref(false);

onMounted(fetchCart);

async function apply() {
    if (!couponInput.value.trim()) return;
    couponBusy.value = true;
    couponError.value = '';
    try {
        await applyCoupon(couponInput.value);
        couponInput.value = '';
    } catch (e) {
        // Show the server's clear validation message (422), else a fallback.
        couponError.value = e?.response?.data?.errors?.code?.[0]
            ?? e?.response?.data?.message
            ?? 'Could not apply coupon.';
    } finally {
        couponBusy.value = false;
    }
}

async function clear() {
    couponBusy.value = true;
    try { await removeCoupon(); } finally { couponBusy.value = false; }
}
</script>

<template>
    <div class="row">
        <!-- Cart items table -->
        <div class="col-xl-8">
            <p v-if="!cart.lines.length" class="text-center py-5">
                Your cart is empty. <a href="/search" class="link">Continue shopping</a>
            </p>

            <table v-else class="tf-table-page-cart">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-center">Price</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-center">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in cart.lines" :key="line.id" class="tf-cart-item">
                        <td class="tf-cart-item_product">
                            <a :href="line.slug ? `/products/${line.slug}` : '#'" class="img-box">
                                <img :src="line.thumbnail || '/themes/modave/images/products/womens/women-8.jpg'" :alt="line.name">
                            </a>
                            <div class="cart-info">
                                <a :href="line.slug ? `/products/${line.slug}` : '#'" class="name text-button link">{{ line.name }}</a>
                                <div class="text-secondary small">{{ line.sku }}</div>
                            </div>
                        </td>
                        <td class="tf-cart-item_price text-center">
                            <div class="cart-price text-button">{{ line.unit_price ?? line.sub_total }}</div>
                        </td>
                        <td class="tf-cart-item_quantity text-center">
                            <div class="wg-quantity mx-auto">
                                <input type="number" min="1" :value="line.quantity"
                                       @change="updateLine(line.id, Number($event.target.value))"
                                       class="quantity-product">
                            </div>
                        </td>
                        <td class="tf-cart-item_total text-center">
                            <div class="cart-total text-button total-price">{{ line.sub_total }}</div>
                        </td>
                        <td>
                            <button type="button" class="remove icon-close" @click="removeLine(line.id)" aria-label="Remove"></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Order summary -->
        <div class="col-xl-4">
            <div class="fl-sidebar-cart">
                <div class="box-order bg-surface">
                    <h5 class="title">Order Summary</h5>

                    <!-- Coupon -->
                    <div class="coupon-box mb_16" v-if="cart.lines.length">
                        <div v-if="cart.couponCode" class="d-flex justify-content-between align-items-center">
                            <span class="text-button">Coupon: <strong>{{ cart.couponCode }}</strong></span>
                            <button type="button" class="link text-button" @click="clear">Remove</button>
                        </div>
                        <div v-else class="d-flex gap-2">
                            <input type="text" v-model="couponInput" placeholder="Coupon code" class="flex-grow-1">
                            <button type="button" class="tf-btn btn-outline" :disabled="couponBusy" @click="apply">Apply</button>
                        </div>
                        <p v-if="couponError" class="text-danger small mt_8">{{ couponError }}</p>
                    </div>

                    <div class="subtotal text-button d-flex justify-content-between align-items-center">
                        <span>Subtotal</span>
                        <span class="total">{{ cart.totals.sub_total ?? '—' }}</span>
                    </div>
                    <div class="discount text-button d-flex justify-content-between align-items-center" v-if="cart.couponCode && cart.totals.discount_total">
                        <span>Discount</span>
                        <span class="total">-{{ cart.totals.discount_total }}</span>
                    </div>
                    <div class="tax text-button d-flex justify-content-between align-items-center" v-if="cart.totals.tax_total">
                        <span>Tax</span>
                        <span class="total">{{ cart.totals.tax_total }}</span>
                    </div>
                    <div class="total-cart text-button d-flex justify-content-between align-items-center mt_12">
                        <span>Total</span>
                        <span class="total">{{ cart.totals.total ?? '—' }}</span>
                    </div>
                    <a href="/checkout" class="tf-btn btn-fill w-100 mt_16" :class="{ disabled: !cart.lines.length }">
                        <span class="text">Check out</span>
                    </a>
                    <a href="/search" class="tf-btn btn-outline w-100 mt_8">
                        <span class="text">Continue shopping</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
