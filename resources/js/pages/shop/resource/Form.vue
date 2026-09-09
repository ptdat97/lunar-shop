<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { PageHeader, Button, ConfirmDialog } from '@lunarphp/panel';
import PanelField from '../../../panel/components/PanelField.vue';
import PanelRepeater from '../../../panel/components/PanelRepeater.vue';

const props = defineProps({
    resource: { type: Object, required: true },
    fields: { type: Array, required: true },
    record: { type: Object, required: true },
    isNew: { type: Boolean, required: true },
    actions: { type: Object, default: () => ({}) },
});

// Seeded from the declared fields only, so `id`, `_actions` and any computed
// column stay out of the payload — a column the schema does not name can never
// be written from the browser.
// Dot names address a path inside a JSON column ('settings.slides'), so the
// form is keyed by the top-level segment and read back through the same path.
const at = (source, path) => path.split('.').reduce((carry, key) => carry?.[key], source);

const setAt = (target, path, value) => {
    const keys = path.split('.');
    const last = keys.pop();
    const parent = keys.reduce((carry, key) => (carry[key] ??= {}), target);
    parent[last] = value;
};

const seed = {};
props.fields.forEach((field) => setAt(seed, field.name, at(props.record, field.name) ?? null));

const form = useForm(seed);

const valueOf = (field) => at(form.data(), field.name);

const assign = (field, value) => setAt(form, field.name, value);

// A hidden field is simply not rendered and not submitted — how a page-section
// form shows the slides of a hero slider and nothing else.
const isVisible = (field) => {
    const rule = field.visibleWhen;

    return !rule || rule.values.includes(String(at(form.data(), rule.field)));
};

const visibleFields = computed(() => props.fields.filter(isVisible));

// A conditional branch carries no value until its type is chosen — the server's
// blank record deliberately omits it, because branches share field names and
// whichever was declared last would otherwise win. Seed a branch's defaults the
// moment it becomes visible, which is what the old admin did on type change.
watch(visibleFields, (fields) => {
    fields.forEach((field) => {
        if (field.visibleWhen && at(form.data(), field.name) == null && field.default != null) {
            setAt(form, field.name, field.default);
        }
    });
});

// The first field is the resource's name-ish one by convention (title, old_url);
// showing it beats a generic "Edit" on a list of near-identical records.
const title = computed(() =>
    props.isNew ? props.resource.newLabel : at(form.data(), props.fields[0].name) || props.resource.singular,
);

const confirmingDelete = ref(false);

const submit = () => {
    if (props.isNew) {
        form.post(props.resource.routes.store, { preserveScroll: true });

        return;
    }

    form.put(props.actions.update, { preserveScroll: true });
};

// A slug left untouched follows its source field, mirroring the model's own
// `creating` hook so the admin sees the value that will actually be stored.
const slugField = computed(() => props.fields.find((field) => field.type === 'slug'));

const slugify = (input) =>
    String(input ?? '')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

const onFieldInput = (field) => {
    const slug = slugField.value;

    if (!props.isNew || !slug || slug.from !== field.name) {
        return;
    }

    setAt(form, slug.name, slugify(at(form.data(), field.name)));
};
</script>

<template>
    <PageHeader :title="title" :icon="resource.icon">
        <template #actions>
            <Link :href="resource.routes.index">
                <Button variant="ghost">{{ resource.backLabel }}</Button>
            </Link>
            <Button v-if="!isNew" variant="ghost" icon="trash" @click="confirmingDelete = true" />
            <Button variant="primary" :disabled="form.processing" @click="submit">{{ resource.saveLabel }}</Button>
        </template>
    </PageHeader>

    <div class="px-4 sm:px-5 lg:px-7 max-w-[1400px] w-full mx-auto pt-5 pb-7">
        <!-- The 12-column track is an inline style, not `grid-cols-12`: the
             panel ships a compiled stylesheet holding only the utilities its own
             pages use, and the column utilities are not among them. -->
        <form
            class="bg-surface border border-line rounded-xl shadow-sm p-4 grid gap-4"
            style="grid-template-columns: repeat(12, minmax(0, 1fr))"
            @submit.prevent="submit"
        >
            <template v-for="field in visibleFields" :key="field.name">
                <PanelRepeater
                    v-if="field.type === 'repeater'"
                    :field="field"
                    :model-value="valueOf(field) ?? []"
                    :errors="form.errors"
                    @update:model-value="assign(field, $event)"
                />
                <PanelField
                    v-else
                    :field="field"
                    :model-value="valueOf(field)"
                    :error="form.errors[field.name]"
                    @update:model-value="assign(field, $event); onFieldInput(field)"
                />
            </template>
        </form>
    </div>

    <ConfirmDialog
        v-if="!isNew"
        :open="confirmingDelete"
        :title="resource.deleteLabel"
        :confirm-label="resource.deleteLabel"
        tone="danger"
        @update:open="confirmingDelete = $event"
        @confirm="router.delete(actions.destroy)"
    />
</template>
