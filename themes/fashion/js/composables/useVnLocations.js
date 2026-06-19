// Vietnam province → ward dependent lookups for address forms.
// Fetches the ~34 provinces once, then wards on demand per province.
import { ref } from 'vue';
import api from '../api.js';

export function useVnLocations() {
    const provinces = ref([]);
    const wards = ref([]);

    async function loadProvinces() {
        if (provinces.value.length) return;
        const { data } = await api.get('/locations/provinces', { baseURL: '/api/v1' });
        provinces.value = data.data ?? [];
    }

    async function loadWards(provinceId) {
        wards.value = [];
        if (!provinceId) return;
        const { data } = await api.get(`/locations/provinces/${provinceId}/wards`, { baseURL: '/api/v1' });
        wards.value = data.data ?? [];
    }

    const provinceName = (id) => provinces.value.find((p) => p.id === Number(id))?.name ?? '';
    const wardName = (id) => wards.value.find((w) => w.id === Number(id))?.name ?? '';

    return { provinces, wards, loadProvinces, loadWards, provinceName, wardName };
}
