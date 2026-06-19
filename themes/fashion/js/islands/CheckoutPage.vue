<script setup>
// Checkout flow: address → shipping method → place order.
// Cart + totals are session content (fetched from /api/v1/cart on mount, an
// allowed SSR-first exception). Countries come from embedded island state.
// Order placement redirects to the confirmation page.
import { onMounted, reactive, ref, watch } from 'vue';
import api from '../api.js';
import { CART_UPDATED, emit } from '../events.js';
import { useVnLocations } from '../composables/useVnLocations.js';

const props = defineProps({
    countries: { type: Array, default: () => [] },
    confirmationBase: { type: String, default: '/checkout/confirmation' },
    vnpayEnabled: { type: Boolean, default: false },
});

// Expose to the template.
const vnpayEnabled = props.vnpayEnabled;

const cart = ref(null);
const shippingOptions = ref([]);
const loading = ref(true);
const placing = ref(false);
const error = ref('');

// Vietnam 2-tier address (province → ward). province maps to address.state,
// ward to address.city; line_one is the street detail.
const { provinces, wards, loadProvinces, loadWards, provinceName, wardName } = useVnLocations();
const provinceId = ref(null);
const wardId = ref(null);

watch(provinceId, (id) => {
    wardId.value = null;
    loadWards(id);
});

const form = reactive({
    first_name: '', last_name: '', line_one: '',
    country_id: props.countries[0]?.id ?? null,
    contact_email: '', contact_phone: '',
});

// Build the address payload, mapping province/ward to Lunar's state/city.
function addressPayload() {
    return {
        ...form,
        state: provinceName(provinceId.value),
        city: wardName(wardId.value),
    };
}

const chosenShipping = ref(null);
const paymentType = ref('cod');
const addressSaved = ref(false);

function setCart(data) { cart.value = data.data ?? data; }

async function loadCart() {
    const { data } = await api.get('/cart');
    setCart(data);
}

async function saveAddress() {
    error.value = '';
    if (!provinceId.value || !wardId.value) {
        error.value = 'Vui lòng chọn Tỉnh/Thành và Phường/Xã.';
        return;
    }
    try {
        const { data } = await api.post('/checkout/addresses', { shipping: addressPayload() });
        setCart(data);
        addressSaved.value = true;
        // Shipping options depend on the destination address.
        const opts = await api.get('/checkout/shipping-options');
        shippingOptions.value = opts.data.data ?? [];
        if (shippingOptions.value.length) chosenShipping.value = shippingOptions.value[0].identifier;
    } catch (e) {
        error.value = validationMessage(e) || 'Please check the address fields.';
    }
}

async function chooseShipping(identifier) {
    chosenShipping.value = identifier;
    try {
        const { data } = await api.post('/checkout/shipping', { identifier });
        setCart(data);
    } catch (e) {
        error.value = 'Could not apply that shipping method.';
    }
}

async function placeOrder() {
    if (placing.value) return;
    if (!addressSaved.value) { error.value = 'Enter your address first.'; return; }
    if (!chosenShipping.value) { error.value = 'Choose a shipping method.'; return; }
    placing.value = true;
    error.value = '';
    try {
        const { data } = await api.post('/checkout', { payment_type: paymentType.value });
        const order = data.data;
        emit(CART_UPDATED); // cart is now empty → drawer/count refresh

        // Online gateways (VNPay) need a redirect to the provider; offline
        // methods (cod/bank) go straight to the confirmation page.
        if (paymentType.value === 'vnpay') {
            const { data: start } = await api.get(`/payment/vnpay/start/${order.id}`, { baseURL: '/' });
            window.location.href = start.data.redirect_url;
            return;
        }

        window.location.href = `${props.confirmationBase}/${order.reference}`;
    } catch (e) {
        error.value = 'Could not place the order. Please try again.';
        placing.value = false;
    }
}

function validationMessage(e) {
    const errors = e.response?.data?.errors;
    return errors ? Object.values(errors)[0]?.[0] : null;
}

onMounted(async () => {
    loadProvinces();
    try { await loadCart(); } finally { loading.value = false; }
});
</script>

<template>
    <div class="row g-4" v-if="!loading">
        <div class="col-12 col-lg-7">
            <!-- Step 1: address -->
            <section class="border rounded p-3 mb-3">
                <h2 class="h6 text-uppercase">Shipping address</h2>
                <div class="row g-2">
                    <div class="col-6"><input v-model="form.first_name" class="form-control" placeholder="Họ"></div>
                    <div class="col-6"><input v-model="form.last_name" class="form-control" placeholder="Tên"></div>
                    <div class="col-6">
                        <select v-model.number="provinceId" class="form-select">
                            <option :value="null" disabled>Tỉnh/Thành phố</option>
                            <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <select v-model.number="wardId" class="form-select" :disabled="!provinceId">
                            <option :value="null" disabled>Phường/Xã</option>
                            <option v-for="w in wards" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div class="col-12"><input v-model="form.line_one" class="form-control" placeholder="Số nhà, tên đường"></div>
                    <div class="col-12">
                        <select v-model.number="form.country_id" class="form-select">
                            <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="col-6"><input v-model="form.contact_email" type="email" class="form-control" placeholder="Email"></div>
                    <div class="col-6"><input v-model="form.contact_phone" class="form-control" placeholder="Số điện thoại"></div>
                </div>
                <button class="btn btn-dark mt-3" @click="saveAddress">
                    {{ addressSaved ? 'Update address' : 'Continue' }}
                </button>
            </section>

            <!-- Step 2: shipping -->
            <section class="border rounded p-3 mb-3" v-if="addressSaved">
                <h2 class="h6 text-uppercase">Shipping method</h2>
                <div v-if="!shippingOptions.length" class="text-muted small">No shipping options available.</div>
                <div v-for="opt in shippingOptions" :key="opt.identifier" class="form-check">
                    <input class="form-check-input" type="radio" :id="opt.identifier"
                           :value="opt.identifier" :checked="chosenShipping === opt.identifier"
                           @change="chooseShipping(opt.identifier)">
                    <label class="form-check-label d-flex justify-content-between" :for="opt.identifier">
                        <span>{{ opt.name }}</span><span>{{ opt.price }}</span>
                    </label>
                </div>
            </section>

            <!-- Step 3: payment -->
            <section class="border rounded p-3" v-if="addressSaved">
                <h2 class="h6 text-uppercase">Payment</h2>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="pay-cod" value="cod" v-model="paymentType">
                    <label class="form-check-label" for="pay-cod">Cash on delivery</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="pay-bank" value="bank-transfer" v-model="paymentType">
                    <label class="form-check-label" for="pay-bank">Bank transfer</label>
                </div>
                <div class="form-check" v-if="vnpayEnabled">
                    <input class="form-check-input" type="radio" id="pay-vnpay" value="vnpay" v-model="paymentType">
                    <label class="form-check-label" for="pay-vnpay">VNPay (online payment)</label>
                </div>
            </section>
        </div>

        <!-- Order summary -->
        <div class="col-12 col-lg-5">
            <div class="border rounded p-3">
                <h2 class="h6 text-uppercase">Order summary</h2>
                <div v-for="line in cart?.lines ?? []" :key="line.id" class="d-flex justify-content-between small py-1">
                    <span>{{ line.name }} × {{ line.quantity }}</span>
                    <span>{{ line.sub_total }}</span>
                </div>
                <dl class="row small border-top pt-2 mt-2 mb-0">
                    <dt class="col-7 fw-normal">Subtotal</dt><dd class="col-5 text-end">{{ cart?.totals?.sub_total }}</dd>
                    <dt class="col-7 fw-normal">Tax</dt><dd class="col-5 text-end">{{ cart?.totals?.tax_total }}</dd>
                    <dt class="col-7 fw-bold">Total</dt><dd class="col-5 text-end fw-bold">{{ cart?.totals?.total }}</dd>
                </dl>
                <button class="btn btn-dark w-100 mt-3" :disabled="placing" @click="placeOrder">
                    {{ placing ? 'Placing order…' : 'Place order' }}
                </button>
                <p class="text-danger small mt-2 mb-0" v-if="error">{{ error }}</p>
            </div>
        </div>
    </div>
    <div v-else class="text-center text-muted py-5">Loading checkout…</div>
</template>
