<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    confirmUrl: { type: String, default: '/checkout/confirmation' },
});

// Read the country list from the embedded JSON script block (no attribute escaping).
function readCountries() {
    const el = document.querySelector('[data-checkout-countries]');
    if (!el) return [];
    try {
        return JSON.parse(el.textContent.trim());
    } catch (e) {
        return [];
    }
}

const countryList = computed(() => readCountries());

const step = ref('address'); // address → shipping → payment
const busy = ref(false);
const error = ref('');

const shipping = ref({
    first_name: '', last_name: '', line_one: '', city: '',
    postcode: '', country_id: countryList.value[0]?.id ?? null,
    contact_email: '', contact_phone: '',
});

const shippingOptions = ref([]);
const chosenShipping = ref(null);
const paymentType = ref('cod');
const cart = ref(null);

async function submitAddress() {
    busy.value = true; error.value = '';
    try {
        const { data } = await window.axios.post('/api/v1/checkout/addresses', { shipping: shipping.value });
        cart.value = data.data;
        const opts = await window.axios.get('/api/v1/checkout/shipping-options');
        shippingOptions.value = opts.data.data;
        chosenShipping.value = shippingOptions.value[0]?.identifier ?? null;
        step.value = 'shipping';
    } catch (e) {
        error.value = firstError(e) ?? 'Please check your address details.';
    } finally {
        busy.value = false;
    }
}

async function submitShipping() {
    busy.value = true; error.value = '';
    try {
        const { data } = await window.axios.post('/api/v1/checkout/shipping', { identifier: chosenShipping.value });
        cart.value = data.data;
        step.value = 'payment';
    } catch (e) {
        error.value = firstError(e) ?? 'Could not set shipping.';
    } finally {
        busy.value = false;
    }
}

async function placeOrder() {
    busy.value = true; error.value = '';
    try {
        const { data } = await window.axios.post('/api/v1/checkout', { payment_type: paymentType.value });
        window.location.href = `${props.confirmUrl}/${data.data.reference}`;
    } catch (e) {
        error.value = firstError(e) ?? 'Could not place order.';
    } finally {
        busy.value = false;
    }
}

function firstError(e) {
    const errors = e?.response?.data?.errors;
    if (errors) return Object.values(errors)[0]?.[0];
    return e?.response?.data?.message;
}
</script>

<template>
    <div class="checkout-flow">
        <p v-if="error" class="checkout-flow__error">{{ error }}</p>

        <!-- Step 1: Address -->
        <form v-if="step === 'address'" class="checkout-step" @submit.prevent="submitAddress">
            <h2>Shipping address</h2>
            <div class="form-row">
                <input v-model="shipping.first_name" placeholder="First name" required>
                <input v-model="shipping.last_name" placeholder="Last name" required>
            </div>
            <input v-model="shipping.line_one" placeholder="Address" required>
            <div class="form-row">
                <input v-model="shipping.city" placeholder="City" required>
                <input v-model="shipping.postcode" placeholder="Postcode" required>
            </div>
            <select v-model="shipping.country_id" required>
                <option v-for="c in countryList" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div class="form-row">
                <input v-model="shipping.contact_email" type="email" placeholder="Email">
                <input v-model="shipping.contact_phone" placeholder="Phone">
            </div>
            <button class="btn btn--primary" :disabled="busy">{{ busy ? 'Saving…' : 'Continue to shipping' }}</button>
        </form>

        <!-- Step 2: Shipping -->
        <form v-else-if="step === 'shipping'" class="checkout-step" @submit.prevent="submitShipping">
            <h2>Shipping method</h2>
            <label v-for="o in shippingOptions" :key="o.identifier" class="ship-option">
                <input type="radio" :value="o.identifier" v-model="chosenShipping">
                <span>{{ o.name }} — {{ o.price }}</span>
                <small>{{ o.description }}</small>
            </label>
            <button class="btn btn--primary" :disabled="busy || !chosenShipping">Continue to payment</button>
        </form>

        <!-- Step 3: Payment -->
        <form v-else class="checkout-step" @submit.prevent="placeOrder">
            <h2>Payment</h2>
            <label class="pay-option">
                <input type="radio" value="cod" v-model="paymentType"> Cash on delivery
            </label>
            <label class="pay-option">
                <input type="radio" value="bank-transfer" v-model="paymentType"> Bank transfer
            </label>

            <div v-if="cart" class="checkout-summary">
                <div><span>Subtotal</span><strong>{{ cart.totals.sub_total }}</strong></div>
                <div><span>Total</span><strong>{{ cart.totals.total }}</strong></div>
            </div>

            <button class="btn btn--primary" :disabled="busy">{{ busy ? 'Placing…' : 'Place order' }}</button>
        </form>
    </div>
</template>
