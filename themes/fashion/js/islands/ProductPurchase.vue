<script setup>
// Variant picker + add-to-cart. Hydrates from the product page's embedded
// ProductResource ($state) — no fetch on mount. Picking option values resolves
// the matching variant (price/stock), and add-to-cart posts to /api/v1/cart and
// emits cart:updated so the mini-cart drawer refreshes.
import { computed, reactive, ref } from 'vue';
import api from '../api.js';
import { CART_UPDATED, emit } from '../events.js';

// Props come from data-island-state (the whole ProductResource object).
const props = defineProps({
    id: { type: Number, default: null },
    name: { type: String, default: '' },
    slug: { type: String, default: '' },
    variants: { type: Array, default: () => [] },
});

// Build the option groups (e.g. Size → [S,M,L], Color → [Black,White]) in a
// stable order, from the variants' option values.
const optionGroups = computed(() => {
    const groups = new Map();
    props.variants.forEach((v) => {
        (v.options || []).forEach(({ option, value }) => {
            if (!option) return;
            if (!groups.has(option)) groups.set(option, new Set());
            groups.get(option).add(value);
        });
    });
    return [...groups.entries()].map(([name, values]) => ({ name, values: [...values] }));
});

const hasOptions = computed(() => optionGroups.value.length > 0);
const selected = reactive({}); // option name → chosen value

// The variant whose option values match every current selection.
const currentVariant = computed(() => {
    if (!hasOptions.value) return props.variants[0] ?? null;
    return props.variants.find((v) =>
        optionGroups.value.every((g) => {
            const chosen = selected[g.name];
            if (!chosen) return false;
            return (v.options || []).some((o) => o.option === g.name && o.value === chosen);
        })
    ) ?? null;
});

// Whether a given option value is still available with the current selection
// (so we can disable impossible combinations).
function isAvailable(optionName, value) {
    return props.variants.some((v) => {
        const matchesThis = (v.options || []).some((o) => o.option === optionName && o.value === value);
        if (!matchesThis) return false;
        return optionGroups.value.every((g) => {
            if (g.name === optionName) return true;
            const chosen = selected[g.name];
            return !chosen || (v.options || []).some((o) => o.option === g.name && o.value === chosen);
        });
    });
}

function pick(optionName, value) {
    selected[optionName] = selected[optionName] === value ? undefined : value;
}

const qty = ref(1);
const adding = ref(false);
const error = ref('');

const inStock = computed(() => (currentVariant.value?.stock ?? 0) > 0);
const canAdd = computed(() => currentVariant.value && inStock.value && (!hasOptions.value || optionGroups.value.every((g) => selected[g.name])));
const priceText = computed(() => currentVariant.value?.price?.formatted ?? '');

async function addToCart() {
    if (!canAdd.value || adding.value) return;
    adding.value = true;
    error.value = '';
    try {
        await api.post('/cart', { variant_id: currentVariant.value.id, quantity: qty.value });
        emit(CART_UPDATED, { variantId: currentVariant.value.id });
        const el = document.getElementById('shoppingCart');
        if (el && window.bootstrap?.Offcanvas) window.bootstrap.Offcanvas.getOrCreateInstance(el).show();
    } catch (e) {
        error.value = 'Could not add to cart. Please try again.';
    } finally {
        adding.value = false;
    }
}
</script>

<template>
    <div>
        <div class="h4 mb-3" v-if="priceText">{{ priceText }}</div>

        <div v-for="group in optionGroups" :key="group.name" class="mb-3">
            <label class="form-label small text-uppercase d-block">{{ group.name }}</label>
            <div class="d-flex flex-wrap gap-2">
                <button v-for="value in group.values" :key="value" type="button"
                        class="btn btn-sm"
                        :class="selected[group.name] === value ? 'btn-dark' : 'btn-outline-dark'"
                        :disabled="!isAvailable(group.name, value)"
                        @click="pick(group.name, value)">
                    {{ value }}
                </button>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 mb-3">
            <label class="small text-muted">Qty</label>
            <input type="number" min="1" v-model.number="qty" class="form-control form-control-sm" style="width:72px">
            <span class="small text-muted" v-if="currentVariant">
                {{ inStock ? currentVariant.stock + ' in stock' : 'Out of stock' }}
            </span>
        </div>

        <button class="btn btn-dark btn-lg w-100" :disabled="!canAdd || adding" @click="addToCart">
            <span v-if="adding">Adding…</span>
            <span v-else-if="!currentVariant && hasOptions">Select options</span>
            <span v-else-if="!inStock">Out of stock</span>
            <span v-else>Add to cart</span>
        </button>

        <p class="text-danger small mt-2 mb-0" v-if="error">{{ error }}</p>
    </div>
</template>
