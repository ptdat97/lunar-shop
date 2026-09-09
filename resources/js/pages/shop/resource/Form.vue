<script setup>
import { computed, ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import { PageHeader, Button, ConfirmDialog } from '@lunarphp/panel';
import PanelField from '../../../panel/components/PanelField.vue';

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
const form = useForm(Object.fromEntries(props.fields.map((field) => [field.name, props.record[field.name] ?? null])));

// The first field is the resource's name-ish one by convention (title, old_url);
// showing it beats a generic "Edit" on a list of near-identical records.
const title = computed(() =>
    props.isNew ? props.resource.newLabel : form[props.fields[0].name] || props.resource.singular,
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

    form[slug.name] = slugify(form[field.name]);
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
            <PanelField
                v-for="field in fields"
                :key="field.name"
                v-model="form[field.name]"
                :field="field"
                :error="form.errors[field.name]"
                @update:model-value="onFieldInput(field)"
            />
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
