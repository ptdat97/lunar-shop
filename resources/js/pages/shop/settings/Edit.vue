<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { SettingsShell, Button } from '@lunarphp/panel';
import PanelField from '../../../panel/components/PanelField.vue';
import PanelRepeater from '../../../panel/components/PanelRepeater.vue';

const props = defineProps({
    group: { type: Object, required: true },
    tabs: { type: Array, required: true },
    fields: { type: Array, required: true },
    values: { type: Object, required: true },
    secretsPresent: { type: Object, default: () => ({}) },
});

// Field names are dotted paths into the group's own shape ('vnpay.hash_secret'),
// so the form is nested the same way the stored settings are.
const at = (source, path) => path.split('.').reduce((carry, key) => carry?.[key], source);

const setAt = (target, path, value) => {
    const keys = path.split('.');
    const last = keys.pop();
    const parent = keys.reduce((carry, key) => (carry[key] ??= {}), target);
    parent[last] = value;
};

const seed = {};
props.fields.forEach((field) => setAt(seed, field.name, at(props.values, field.name) ?? null));

const form = useForm(seed);

const valueOf = (field) => at(form.data(), field.name);

const assign = (field, value) => setAt(form, field.name, value);

const isVisible = (field) => {
    const rule = field.visibleWhen;

    return !rule || rule.values.includes(String(at(form.data(), rule.field)));
};

const visibleFields = computed(() => props.fields.filter(isVisible));

// A stored secret arrives blank. Saying so beats an empty box that reads as
// "not configured" and invites someone to retype a key they cannot see.
const hintFor = (field) =>
    field.type === 'secret' && props.secretsPresent[field.name] ? props.group.secretKeptLabel : field.help;

const submit = () => form.put(props.group.action, { preserveScroll: true });
</script>

<template>
    <SettingsShell :title="group.label" :description="group.description" wide>
        <!-- Tab strip: only the groups this user may open, which is also all
             the URLs the server will serve them. -->
        <div class="flex flex-wrap items-center gap-1 mb-4 border-b border-line pb-2">
            <Link v-for="tab in tabs" :key="tab.key" :href="tab.url">
                <Button :variant="tab.key === group.key ? 'primary' : 'ghost'" size="sm">
                    {{ tab.label }}
                </Button>
            </Link>
        </div>

        <form
            class="bg-surface border border-line rounded-xl shadow-sm p-4 grid gap-4"
            style="grid-template-columns: repeat(12, minmax(0, 1fr))"
            @submit.prevent="submit"
        >
            <template v-for="field in visibleFields" :key="field.name">
                <PanelRepeater
                    v-if="field.type === 'repeater'"
                    :field="field"
                    :path="field.name"
                    :model-value="valueOf(field) ?? []"
                    :errors="form.errors"
                    @update:model-value="assign(field, $event)"
                />
                <PanelField
                    v-else
                    :field="{ ...field, help: hintFor(field) }"
                    :model-value="valueOf(field)"
                    :error="form.errors[field.name]"
                    @update:model-value="assign(field, $event)"
                />
            </template>
        </form>

        <div class="mt-4 flex justify-end">
            <Button variant="primary" :disabled="form.processing" @click="submit">
                {{ group.saveLabel }}
            </Button>
        </div>
    </SettingsShell>
</template>
