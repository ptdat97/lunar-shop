<script setup>
import { computed } from 'vue';
import { TextInput, Textarea, Select, Toggle, FieldLabel } from '@lunarphp/panel';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { default: null },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const value = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

// PHP sends select options as an object so JSON keeps their declared order.
const options = computed(() => Object.entries(props.field.options ?? {}));

const isTextLike = computed(() => ['text', 'image', 'slug'].includes(props.field.type));
const isMultiline = computed(() => ['textarea', 'html', 'json'].includes(props.field.type));
</script>

<template>
    <!-- `col-span-*` is not in the panel's compiled stylesheet, so the span is
         an inline style; everything else here uses utilities its own pages use. -->
    <div :style="{ gridColumn: `span ${field.columns} / span ${field.columns}` }">
        <FieldLabel :for="field.name" :required="field.required">{{ field.label }}</FieldLabel>

        <TextInput
            v-if="isTextLike"
            :id="field.name"
            v-model="value"
            :placeholder="field.placeholder"
            :invalid="!!error"
            :data-field="field.name"
        />

        <TextInput
            v-else-if="field.type === 'number'"
            :id="field.name"
            v-model="value"
            type="number"
            :invalid="!!error"
            :data-field="field.name"
        />

        <Textarea
            v-else-if="isMultiline"
            :id="field.name"
            v-model="value"
            :rows="field.type === 'textarea' ? 4 : 12"
            :invalid="!!error"
            :data-field="field.name"
        />

        <Select
            v-else-if="field.type === 'select'"
            :id="field.name"
            v-model="value"
            :invalid="!!error"
            :data-field="field.name"
        >
            <option v-for="[optionValue, label] in options" :key="optionValue" :value="optionValue">
                {{ label }}
            </option>
        </Select>

        <div v-else-if="field.type === 'toggle'" class="flex items-center h-[30px]">
            <Toggle :on="!!value" :data-field="field.name" @toggle="value = !value" />
        </div>

        <img
            v-if="field.type === 'image' && value"
            :src="value"
            alt=""
            class="mt-2 rounded-md border border-line"
            style="height: 6rem; width: auto; object-fit: cover"
        />

        <p v-if="field.help" class="mt-1 text-[11px] text-ink-500">{{ field.help }}</p>
        <p v-if="error" class="mt-1 text-[11px] text-danger" :data-error="field.name">{{ error }}</p>
    </div>
</template>
