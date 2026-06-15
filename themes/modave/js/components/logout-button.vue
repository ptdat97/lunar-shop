<script setup>
import { ref } from 'vue';

const busy = ref(false);

async function logout() {
    busy.value = true;
    try {
        await window.axios.post('/api/v1/auth/logout');
        window.location.href = '/login';
    } catch (e) {
        window.location.href = '/login';
    }
}
</script>

<template>
    <button type="button" class="tf-btn btn-outline" :disabled="busy" @click="logout">
        <span class="text text-button">{{ busy ? 'Signing out…' : 'Log out' }}</span>
    </button>
</template>
