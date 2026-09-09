<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { SideCard, FieldLabel, TextInput, Textarea, Select, Button, http } from '@lunarphp/panel';

// `product` comes from the page's slot zone (PageZone passes it down); the rest
// are the props the PHP Slot declared.
const props = defineProps({
    product: { type: Object, default: null },
    charts: { type: Object, default: () => ({}) },
    labels: { type: Object, required: true },
    options: { type: Object, required: true },
});

const blank = () => ({
    size_chart_id: '',
    material: {
        material: '',
        composition: '',
        stretch: '',
        transparency: '',
        fabric_weight: '',
        lining: '',
        care_instruction: '',
    },
});

const form = ref(blank());
const saving = ref(false);
const loading = ref(true);

// A slot's props are fixed for the whole request and cannot know which product
// the page is showing, so this asks for its own state instead of the product
// payload growing fields the panel knows nothing about.
onMounted(async () => {
    if (!props.product?.id) {
        loading.value = false;

        return;
    }

    try {
        const current = await http.get(`/panel/shop/products/${props.product.id}/sizing`);

        form.value = {
            size_chart_id: current.size_chart_id ?? '',
            material: { ...blank().material, ...(current.material ?? {}) },
        };
    } finally {
        loading.value = false;
    }
});

const chartOptions = computed(() => Object.entries(props.charts));

const optionsOf = (key) => Object.entries(props.options[key] ?? {});

const submit = () => {
    if (!props.product?.id) {
        return;
    }

    saving.value = true;

    router.put(`/panel/shop/products/${props.product.id}/sizing`, form.value, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
    });
};
</script>

<template>
    <SideCard :title="labels.heading">
        <div class="grid gap-3">
            <div>
                <FieldLabel for="size_chart_id">{{ labels.chart }}</FieldLabel>
                <Select id="size_chart_id" v-model="form.size_chart_id" data-field="size_chart_id">
                    <option value="">{{ labels.noChart }}</option>
                    <option v-for="[id, name] in chartOptions" :key="id" :value="id">{{ name }}</option>
                </Select>
                <p class="mt-1 text-[11px] text-ink-500">{{ labels.chartHelp }}</p>
            </div>

            <div class="border-t border-line pt-3 text-xs font-medium text-ink-700">
                {{ labels.materialSection }}
            </div>

            <div>
                <FieldLabel for="sizing_material">{{ labels.material }}</FieldLabel>
                <TextInput id="sizing_material" v-model="form.material.material" data-field="material" />
            </div>

            <div>
                <FieldLabel for="sizing_composition">{{ labels.composition }}</FieldLabel>
                <TextInput id="sizing_composition" v-model="form.material.composition" data-field="composition" />
            </div>

            <div v-for="key in ['stretch', 'transparency', 'lining']" :key="key">
                <FieldLabel :for="`sizing_${key}`">{{ labels[key] ?? key }}</FieldLabel>
                <Select :id="`sizing_${key}`" v-model="form.material[key]" :data-field="key">
                    <option value="">—</option>
                    <option v-for="[value, label] in optionsOf(key)" :key="value" :value="value">{{ label }}</option>
                </Select>
            </div>

            <div>
                <FieldLabel for="sizing_weight">{{ labels.fabricWeight }}</FieldLabel>
                <TextInput id="sizing_weight" v-model="form.material.fabric_weight" data-field="fabric_weight" />
            </div>

            <div>
                <FieldLabel for="sizing_care">{{ labels.care }}</FieldLabel>
                <Textarea id="sizing_care" v-model="form.material.care_instruction" :rows="3" data-field="care_instruction" />
            </div>

            <Button variant="primary" size="sm" :disabled="saving || loading" @click="submit">
                {{ labels.save }}
            </Button>
        </div>
    </SideCard>
</template>
