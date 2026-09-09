<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { PageHeader, DataTable, Pagination, PageEmpty, TextInput, Button, Icon } from '@lunarphp/panel';

const props = defineProps({
    resource: { type: Object, required: true },
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    actions: { type: Array, required: true },
    meta: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.q ?? '');

// Debounced so typing does not fire a request per keystroke; `replace` keeps the
// back button pointing at the page before the search rather than at every
// intermediate query.
let timer = null;

watch(search, (term) => {
    clearTimeout(timer);

    timer = setTimeout(() => {
        router.get(
            props.resource.routes.index,
            term ? { q: term } : {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <!-- No <PanelLayout> wrapper: the panel's page resolver applies the shell
         layout to add-on pages automatically (see runtime/pageResolver.ts).
         Wrapping it here would nest the sidebar inside itself. -->
    <PageHeader :title="resource.label" :icon="resource.icon">
        <template #actions>
            <Link :href="resource.routes.create">
                <Button variant="primary" icon="plus">{{ resource.newLabel }}</Button>
            </Link>
        </template>
    </PageHeader>

    <div class="px-4 sm:px-5 lg:px-7 max-w-[1400px] w-full mx-auto pt-5 pb-7">
        <div v-if="resource.searchable" class="flex flex-wrap items-center gap-2 mb-4 min-h-[34px]">
            <div class="flex-1 max-w-[280px] min-w-[180px]">
                <TextInput v-model="search" clearable :placeholder="resource.searchPlaceholder" data-field="search">
                    <template #prefix><Icon name="search" cls="sm" /></template>
                </TextInput>
            </div>
        </div>

        <DataTable
            :columns="columns"
            :rows="rows"
            :row-actions="actions"
            :row-to="(row) => row._actions.edit"
        >
            <template #empty>
                <PageEmpty :title="resource.emptyLabel" />
            </template>
        </DataTable>

        <div class="mt-4">
            <Pagination :meta="meta" />
        </div>
    </div>
</template>
