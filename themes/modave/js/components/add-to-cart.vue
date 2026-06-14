<script setup>
import { ref } from 'vue';

const props = defineProps({
    variant: { type: [String, Number], default: null },
});

const busy = ref(false);
const added = ref(false);

async function add() {
    if (!props.variant || busy.value) return;
    busy.value = true;
    try {
        await window.axios.post('/api/v1/cart', {
            variant_id: Number(props.variant),
            quantity: 1,
        });
        added.value = true;
        window.dispatchEvent(new CustomEvent('cart:updated'));
        setTimeout(() => (added.value = false), 1500);
    } catch (e) {
        // keep label; could surface a toast here
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <button type="button" class="btn-main-product" :disabled="busy" @click="add">
        {{ busy ? 'Adding…' : (added ? 'Added ✓' : 'Add To cart') }}
    </button>
</template>
