<script setup>
import { computed } from 'vue';
import { Button } from '@lunarphp/panel';
import PanelField from './PanelField.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const rows = computed(() => props.modelValue ?? []);

const commit = (next) => emit('update:modelValue', next);

// A blank row starts from the sub-fields' own defaults, so adding a slide gives
// the same shape the storefront's section partial expects.
const blankRow = () =>
    Object.fromEntries(props.field.children.map((child) => [child.name, child.default ?? null]));

// The panel's icon set has a down chevron but no up one, and an unknown name
// renders an empty <svg> with no error — so "up" is the same glyph, turned.
const UPSIDE_DOWN = { transform: 'rotate(180deg)' };

const add = () => commit([...rows.value, blankRow()]);

const remove = (index) => commit(rows.value.filter((_, i) => i !== index));

const move = (index, delta) => {
    const target = index + delta;

    if (target < 0 || target >= rows.value.length) {
        return;
    }

    const next = [...rows.value];
    [next[index], next[target]] = [next[target], next[index]];
    commit(next);
};

const setCell = (index, name, value) => {
    const next = [...rows.value];
    next[index] = { ...next[index], [name]: value };
    commit(next);
};

// Collapsed rows are identified by whichever sub-field the schema nominated;
// without it a list of slides is a list of "Slide, Slide, Slide".
const rowLabel = (row, index) => row?.[props.field.itemLabel] || `#${index + 1}`;

// Laravel reports a repeater's errors against `settings.slides.2.title`, so a
// row's messages are the ones prefixed with its own index.
const cellError = (index, name) => props.errors[`${props.field.name}.${index}.${name}`];
</script>

<template>
    <div :style="{ gridColumn: `span ${field.columns} / span ${field.columns}` }">
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-medium text-ink-700">{{ field.label }}</span>
            <Button size="sm" icon="plus" @click="add">{{ field.addLabel ?? '+' }}</Button>
        </div>

        <div v-if="!rows.length" class="text-[11px] text-ink-500 border border-line rounded-md p-3">
            {{ field.help ?? '—' }}
        </div>

        <div v-for="(row, index) in rows" :key="index" class="border border-line rounded-md mb-2 bg-surface-2">
            <div class="flex items-center gap-1 px-3 py-2 border-b border-line">
                <span class="text-xs font-medium text-ink-900 flex-1 truncate">{{ rowLabel(row, index) }}</span>
                <span :style="UPSIDE_DOWN" class="inline-flex">
                    <Button size="sm" variant="ghost" icon="chevDown" @click="move(index, -1)" />
                </span>
                <Button size="sm" variant="ghost" icon="chevDown" @click="move(index, 1)" />
                <Button size="sm" variant="ghost" icon="trash" @click="remove(index)" />
            </div>

            <div
                class="p-3 grid gap-4"
                style="grid-template-columns: repeat(12, minmax(0, 1fr))"
            >
                <PanelField
                    v-for="child in field.children"
                    :key="child.name"
                    :field="child"
                    :model-value="row[child.name] ?? null"
                    :error="cellError(index, child.name)"
                    @update:model-value="setCell(index, child.name, $event)"
                />
            </div>
        </div>
    </div>
</template>
