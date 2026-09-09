<script setup>
import { computed } from 'vue';
import { Button } from '@lunarphp/panel';
import PanelField from './PanelField.vue';

const props = defineProps({
    field: { type: Object, required: true },
    // The full dotted path to this list in the form payload. Nested repeaters
    // get `tree.0.children`, which is exactly how Laravel addresses the row's
    // validation errors.
    path: { type: String, required: true },
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

// A sub-field's condition reads its own row, not the form: a menu item shows
// its links only when that item is a dropdown.
const fieldsFor = (row) =>
    props.field.children.filter(
        (child) => !child.visibleWhen || child.visibleWhen.values.includes(String(row?.[child.visibleWhen.field])),
    );

const cellPath = (index, name) => `${props.path}.${index}.${name}`;
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

            <div class="p-3 grid gap-4" style="grid-template-columns: repeat(12, minmax(0, 1fr))">
                <template v-for="child in fieldsFor(row)" :key="child.name">
                    <!-- A repeater inside a repeater: a menu is items → columns
                         → links. The component renders itself, carrying the
                         path down so error addressing keeps working. -->
                    <PanelRepeater
                        v-if="child.type === 'repeater'"
                        :field="child"
                        :path="cellPath(index, child.name)"
                        :model-value="row[child.name] ?? []"
                        :errors="errors"
                        @update:model-value="setCell(index, child.name, $event)"
                    />
                    <PanelField
                        v-else
                        :field="child"
                        :model-value="row[child.name] ?? null"
                        :error="errors[cellPath(index, child.name)]"
                        @update:model-value="setCell(index, child.name, $event)"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
