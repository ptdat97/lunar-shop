<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    variants: { type: [String, Array], default: () => [] },
});

const list = computed(() =>
    typeof props.variants === 'string' ? JSON.parse(props.variants) : props.variants
);

const selected = ref(list.value[0] ?? null);
const quantity = ref(1);
const busy = ref(false);
const message = ref('');

function selectVariant(v) {
    selected.value = v;
    // Keep the page price in sync with the picked variant.
    const el = document.querySelector('[data-vue-price]');
    if (el) el.textContent = v.price_formatted;
}

async function addToCart() {
    if (!selected.value || busy.value) return;
    busy.value = true;
    message.value = '';
    try {
        await window.axios.post('/api/v1/cart', {
            variant_id: selected.value.id,
            quantity: quantity.value,
        });
        message.value = 'Added to cart';
        window.dispatchEvent(new CustomEvent('cart:updated'));
    } catch (e) {
        message.value = 'Could not add to cart';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="product-purchase">
        <div v-if="list.length > 1" class="variant-picker-item mb_15">
            <div class="variant-picker-label mb_12">Variant</div>
            <div class="variant-picker-values d-flex flex-wrap gap-2">
                <button
                    v-for="v in list" :key="v.id" type="button"
                    class="btn-sm tf-btn"
                    :class="selected?.id === v.id ? 'btn-fill' : 'btn-outline'"
                    @click="selectVariant(v)"
                >
                    {{ v.sku }}
                </button>
            </div>
        </div>

        <p v-if="selected" class="stock-status text-secondary mb_10">
            <template v-if="selected.stock > 0">In stock ({{ selected.stock }})</template>
            <template v-else>Out of stock</template>
        </p>

        <div class="tf-product-info-quantity mb_15 d-flex align-items-center gap-3">
            <div class="wg-quantity">
                <input class="quantity-product" type="number" min="1" v-model.number="quantity">
            </div>
            <button
                type="button"
                class="btn-style-2 flex-grow-1 text-btn-uppercase fw-6 btn-add-to-cart"
                :disabled="busy || !selected || selected.stock <= 0"
                @click="addToCart"
            >
                <span>{{ busy ? 'Adding…' : 'Add to cart -&nbsp;' }}</span>
                <span class="tf-qty-price total-price">{{ selected?.price_formatted }}</span>
            </button>
        </div>

        <p v-if="message" class="add-to-cart-msg">{{ message }}</p>
    </div>
</template>
