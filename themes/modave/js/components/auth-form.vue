<script setup>
import { ref, reactive } from 'vue';

const props = defineProps({
    mode: { type: String, default: 'login' }, // 'login' | 'register'
    redirect: { type: String, default: '/account' },
});

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const errors = ref({});
const message = ref('');
const busy = ref(false);

async function submit() {
    busy.value = true;
    errors.value = {};
    message.value = '';

    const url = props.mode === 'register' ? '/api/v1/auth/register' : '/api/v1/auth/login';
    const payload = props.mode === 'register'
        ? { name: form.name, email: form.email, password: form.password }
        : { email: form.email, password: form.password };

    if (props.mode === 'register' && form.password !== form.password_confirmation) {
        errors.value = { password: ['Passwords do not match.'] };
        busy.value = false;
        return;
    }

    try {
        await window.axios.post(url, payload);
        window.location.href = props.redirect;
    } catch (e) {
        if (e?.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            message.value = 'Something went wrong. Please try again.';
        }
    } finally {
        busy.value = false;
    }
}

function err(field) {
    return errors.value[field]?.[0];
}
</script>

<template>
    <form class="form-login form-has-password" @submit.prevent="submit">
        <div class="wrap">
            <fieldset v-if="mode === 'register'">
                <input type="text" v-model="form.name" placeholder="Full name*" required>
                <span v-if="err('name')" class="text-danger small">{{ err('name') }}</span>
            </fieldset>

            <fieldset>
                <input type="email" v-model="form.email" placeholder="Email address*" required>
                <span v-if="err('email')" class="text-danger small">{{ err('email') }}</span>
            </fieldset>

            <fieldset class="position-relative password-item">
                <input type="password" v-model="form.password" class="input-password" placeholder="Password*" required>
                <span v-if="err('password')" class="text-danger small">{{ err('password') }}</span>
            </fieldset>

            <fieldset v-if="mode === 'register'" class="position-relative password-item">
                <input type="password" v-model="form.password_confirmation" class="input-password" placeholder="Confirm password*" required>
            </fieldset>
        </div>

        <p v-if="message" class="text-danger">{{ message }}</p>

        <div class="button-submit">
            <button class="tf-btn btn-fill" type="submit" :disabled="busy">
                <span class="text text-button">
                    {{ busy ? 'Please wait…' : (mode === 'register' ? 'Register' : 'Login') }}
                </span>
            </button>
        </div>
    </form>
</template>
