<script setup>
import { ref, onMounted } from 'vue';
import { cart, fetchCart, updateLine, removeLine } from '../cart-store';

const open = ref(false);

onMounted(fetchCart);

// Allow other islands to open the drawer (e.g. after add-to-cart).
window.addEventListener('cart:open', () => { open.value = true; });
</script>

<template>
    <div class="cart-drawer">
        <button type="button" class="cart-trigger" @click="open = true">
            Cart<span v-if="cart.linesCount"> ({{ cart.linesCount }})</span>
        </button>

        <div v-if="open" class="cart-drawer__overlay" @click.self="open = false">
            <aside class="cart-drawer__panel">
                <header class="cart-drawer__head">
                    <strong>Your Cart</strong>
                    <button type="button" @click="open = false" aria-label="Close">&times;</button>
                </header>

                <p v-if="!cart.lines.length" class="cart-drawer__empty">Your cart is empty.</p>

                <ul v-else class="cart-drawer__lines">
                    <li v-for="line in cart.lines" :key="line.id" class="cart-line">
                        <span class="cart-line__name">{{ line.name }}</span>
                        <input
                            type="number" min="1" :value="line.quantity"
                            @change="updateLine(line.id, Number($event.target.value))"
                            class="cart-line__qty"
                        >
                        <span class="cart-line__sub">{{ line.sub_total }}</span>
                        <button type="button" @click="removeLine(line.id)" aria-label="Remove">&times;</button>
                    </li>
                </ul>

                <footer v-if="cart.lines.length" class="cart-drawer__foot">
                    <div class="cart-drawer__total">
                        <span>Total</span><strong>{{ cart.totals.total }}</strong>
                    </div>
                    <a href="/checkout" class="btn btn--primary">Checkout</a>
                </footer>
            </aside>
        </div>
    </div>
</template>
