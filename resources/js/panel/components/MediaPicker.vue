<script setup>
import { onMounted, ref, watch } from 'vue';
import { Slideout, TextInput, Button, Select, http } from '@lunarphp/panel';

// The value is a Lunar Asset id, which is what the storefront resolves through
// MediaLibraryService — not a path. Typing one blind is what this replaces.
const props = defineProps({
    modelValue: { default: null },
    labels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const preview = ref(null);
const open = ref(false);
const items = ref([]);
const folders = ref([]);
const search = ref('');
const folder = ref('');
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const uploading = ref(false);
const fileInput = ref(null);

const loadPreview = async () => {
    if (!props.modelValue) {
        preview.value = null;

        return;
    }

    try {
        preview.value = await http.get(`/panel/shop/media/${props.modelValue}`);
    } catch {
        // A deleted asset leaves the id behind; showing nothing beats a broken
        // <img> that looks like the picker is failing.
        preview.value = null;
    }
};

const browse = async () => {
    loading.value = true;

    try {
        const params = new URLSearchParams({ type: 'image', page: String(page.value) });

        if (search.value) {
            params.set('search', search.value);
        }

        if (folder.value) {
            params.set('folder', folder.value);
        }

        const result = await http.get(`/panel/shop/media?${params}`);

        items.value = result.items;
        folders.value = result.folders;
        lastPage.value = result.lastPage;
    } finally {
        loading.value = false;
    }
};

const choose = (asset) => {
    emit('update:modelValue', asset.id);
    preview.value = asset;
    open.value = false;
};

const clear = () => {
    emit('update:modelValue', null);
    preview.value = null;
};

const upload = async (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    uploading.value = true;

    try {
        const body = new FormData();
        body.append('file', file);

        if (folder.value) {
            body.append('folder', folder.value);
        }

        choose(await http.post('/panel/shop/media', body));
    } finally {
        uploading.value = false;

        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
};

onMounted(loadPreview);

watch(() => props.modelValue, loadPreview);

watch(open, (isOpen) => {
    if (isOpen) {
        browse();
    }
});

watch([search, folder], () => {
    page.value = 1;
    browse();
});

watch(page, browse);
</script>

<template>
    <div class="flex items-start gap-2">
        <button
            type="button"
            class="w-20 h-20 shrink-0 rounded-md border border-line bg-surface-2 grid place-items-center overflow-hidden hover:border-ink-300"
            :data-media-picker="true"
            @click="open = true"
        >
            <img v-if="preview?.thumb ?? preview?.url" :src="preview.thumb ?? preview.url" alt="" class="w-full h-full object-cover" />
            <span v-else class="text-[11px] text-ink-500 px-1 text-center">{{ labels.pick }}</span>
        </button>

        <div class="grid gap-1 pt-1">
            <span class="text-[11px] text-ink-700 truncate max-w-[220px]">{{ preview?.name ?? '—' }}</span>
            <div class="flex gap-1">
                <Button size="sm" @click="open = true">{{ labels.pick }}</Button>
                <Button v-if="modelValue" size="sm" variant="ghost" icon="trash" @click="clear" />
            </div>
        </div>

        <Slideout :open="open" :title="labels.pick" @update:open="open = $event">
            <div class="grid gap-3">
                <div class="flex flex-wrap gap-2">
                    <div class="flex-1 min-w-[160px]">
                        <TextInput v-model="search" :placeholder="labels.search" clearable />
                    </div>
                    <Select v-model="folder">
                        <option value="">{{ labels.allFolders }}</option>
                        <option v-for="name in folders" :key="name" :value="name">{{ name }}</option>
                    </Select>
                    <Button size="sm" :disabled="uploading" @click="fileInput?.click()">
                        {{ labels.upload }}
                    </Button>
                    <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="upload" />
                </div>

                <div v-if="loading" class="text-[11px] text-ink-500">…</div>

                <div v-else-if="!items.length" class="text-[11px] text-ink-500 border border-line rounded-md p-3">
                    {{ labels.empty }}
                </div>

                <div v-else class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(96px, 1fr))">
                    <button
                        v-for="asset in items"
                        :key="asset.id"
                        type="button"
                        class="aspect-square rounded-md border border-line overflow-hidden hover:border-sage focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-sage/35"
                        :title="asset.name"
                        @click="choose(asset)"
                    >
                        <img :src="asset.thumb ?? asset.url" :alt="asset.alt ?? ''" class="w-full h-full object-cover" />
                    </button>
                </div>

                <div v-if="lastPage > 1" class="flex items-center justify-between text-xs text-ink-500">
                    <Button size="sm" variant="ghost" :disabled="page <= 1" @click="page -= 1">←</Button>
                    <span>{{ page }} / {{ lastPage }}</span>
                    <Button size="sm" variant="ghost" :disabled="page >= lastPage" @click="page += 1">→</Button>
                </div>
            </div>
        </Slideout>
    </div>
</template>
