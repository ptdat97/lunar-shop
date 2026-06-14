<script setup>
import { ref, computed } from 'vue';
import { addToCart } from '../cart-store';

const props = defineProps({
    variants: { type: [String, Array], default: () => [] },
});

// data-variants arrives as a JSON string from Blade.
const list = computed(() =>
    typeof props.variants === 'string' ? JSON.parse(props.variants) : props.variants
);

const selected = ref(list.value[0] ?? null);
const quantity = ref(1);
const busy = ref(false);
const message = ref('');

async function add() {
    if (!selected.value) return;
    busy.value = true;
    message.value = '';
    try {
        await addToCart(selected.value.id, quantity.value);
        message.value = 'Added to cart';
        window.dispatchEvent(new CustomEvent('cart:open'));
    } catch (e) {
        message.value = 'Could not add to cart';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="variant-picker">
        <div v-if="list.length > 1" class="variant-picker__options">
            <label v-for="v in list" :key="v.id" class="variant-picker__option">
                <input type="radio" :value="v.id" :checked="selected?.id === v.id"
                       @change="selected = list.find(x => x.id === v.id)">
                {{ v.sku }}
            </label>
        </div>

        <p v-if="selected" class="variant-picker__price">{{ selected.price }}</p>

        <div class="variant-picker__actions">
            <input type="number" min="1" v-model.number="quantity" class="variant-picker__qty">
            <button type="button" class="btn btn--primary" :disabled="busy || !selected" @click="add">
                {{ busy ? 'Adding…' : 'Add to cart' }}
            </button>
        </div>

        <p v-if="message" class="variant-picker__msg">{{ message }}</p>
    </div>
</template>
