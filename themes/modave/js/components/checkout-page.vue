<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { cart, fetchCart } from '../cart-store';

const props = defineProps({
    confirmUrl: { type: String, default: '/checkout/confirmation' },
});

const countries = computed(() => {
    const el = document.querySelector('[data-checkout-countries]');
    try { return el ? JSON.parse(el.textContent.trim()) : []; } catch { return []; }
});

const shipping = reactive({
    first_name: '', last_name: '', line_one: '', city: '',
    postcode: '', country_id: null, contact_email: '', contact_phone: '',
});
const shippingOptions = ref([]);
const chosenShipping = ref(null);
const paymentType = ref('cod');
const placing = ref(false);
const error = ref('');
const addressSaved = ref(false);

onMounted(async () => {
    await fetchCart();
    shipping.country_id = countries.value[0]?.id ?? null;
});

async function saveAddressAndShipping() {
    error.value = '';
    try {
        await window.axios.post('/api/v1/checkout/addresses', { shipping });
        const { data } = await window.axios.get('/api/v1/checkout/shipping-options');
        shippingOptions.value = data.data;
        chosenShipping.value = data.data[0]?.identifier ?? null;
        if (chosenShipping.value) {
            const res = await window.axios.post('/api/v1/checkout/shipping', { identifier: chosenShipping.value });
            applyCart(res.data.data);
        }
        addressSaved.value = true;
    } catch (e) {
        error.value = firstError(e) ?? 'Please check your details.';
    }
}

async function changeShipping() {
    try {
        const res = await window.axios.post('/api/v1/checkout/shipping', { identifier: chosenShipping.value });
        applyCart(res.data.data);
    } catch (e) { /* noop */ }
}

async function placeOrder() {
    placing.value = true;
    error.value = '';
    try {
        if (!addressSaved.value) await saveAddressAndShipping();
        const { data } = await window.axios.post('/api/v1/checkout', { payment_type: paymentType.value });
        window.location.href = `${props.confirmUrl}/${data.data.reference}`;
    } catch (e) {
        error.value = firstError(e) ?? 'Could not place order.';
    } finally {
        placing.value = false;
    }
}

function applyCart(data) {
    cart.totals = data.totals ?? cart.totals;
    cart.lines = data.lines ?? cart.lines;
    cart.linesCount = data.lines_count ?? cart.linesCount;
}

function firstError(e) {
    const errors = e?.response?.data?.errors;
    if (errors) return Object.values(errors)[0]?.[0];
    return e?.response?.data?.message;
}
</script>

<template>
    <div class="row">
        <!-- Billing / shipping form -->
        <div class="col-xl-6">
            <div class="flat-spacing tf-page-checkout">
                <div class="wrap">
                    <h5 class="title">Shipping information</h5>
                    <form class="info-box" @submit.prevent="saveAddressAndShipping">
                        <div class="grid-2">
                            <input type="text" v-model="shipping.first_name" placeholder="First Name*" required>
                            <input type="text" v-model="shipping.last_name" placeholder="Last Name*" required>
                        </div>
                        <input type="email" v-model="shipping.contact_email" placeholder="Email Address*">
                        <input type="text" v-model="shipping.contact_phone" placeholder="Phone Number*">
                        <select v-model="shipping.country_id" required>
                            <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <input type="text" v-model="shipping.line_one" placeholder="Street, house number...*" required>
                        <div class="grid-2">
                            <input type="text" v-model="shipping.city" placeholder="Town/City*" required>
                            <input type="text" v-model="shipping.postcode" placeholder="Postal Code*" required>
                        </div>
                        <button class="tf-btn btn-outline" type="submit">
                            <span class="text">{{ addressSaved ? 'Update shipping' : 'Save & get shipping' }}</span>
                        </button>
                    </form>
                </div>

                <div class="wrap" v-if="shippingOptions.length">
                    <h5 class="title">Shipping method</h5>
                    <label v-for="o in shippingOptions" :key="o.identifier" class="ship-item d-flex justify-content-between align-items-center">
                        <span>
                            <input type="radio" :value="o.identifier" v-model="chosenShipping" @change="changeShipping" class="tf-check-rounded">
                            {{ o.name }} — {{ o.description }}
                        </span>
                        <span class="price">{{ o.price }}</span>
                    </label>
                </div>

                <div class="wrap">
                    <h5 class="title">Payment</h5>
                    <label class="d-block mb_8"><input type="radio" value="cod" v-model="paymentType" class="tf-check-rounded"> Cash on delivery</label>
                    <label class="d-block"><input type="radio" value="bank-transfer" v-model="paymentType" class="tf-check-rounded"> Bank transfer</label>
                </div>

                <p v-if="error" class="text-danger">{{ error }}</p>
            </div>
        </div>

        <!-- Order summary -->
        <div class="col-xl-6">
            <div class="fl-sidebar-cart">
                <div class="box-order bg-surface">
                    <h5 class="title">Order Summary</h5>
                    <ul class="list-order mb_16">
                        <li v-for="line in cart.lines" :key="line.id" class="d-flex justify-content-between">
                            <span>{{ line.name }} × {{ line.quantity }}</span>
                            <span>{{ line.sub_total }}</span>
                        </li>
                    </ul>
                    <div class="subtotal d-flex justify-content-between">
                        <span>Subtotal</span><span>{{ cart.totals.sub_total }}</span>
                    </div>
                    <div class="total-cart d-flex justify-content-between mt_8 text-button">
                        <span>Total</span><span>{{ cart.totals.total }}</span>
                    </div>
                    <button type="button" class="tf-btn btn-fill w-100 mt_16" :disabled="placing || !cart.lines.length" @click="placeOrder">
                        <span class="text">{{ placing ? 'Placing order…' : 'Place order' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
