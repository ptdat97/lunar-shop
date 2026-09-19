<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Button, Icon, http } from '@lunarphp/panel';
import { openFileManager } from '../media/openFileManager';

// The value is a Lunar Asset id, which is what the storefront resolves through
// MediaLibraryService — not a path.
//
// This field does not list or upload anything. It opens the shop's file
// manager (modules/Assets) and stores the id of whatever the admin chose
// there, so every image in the panel enters the library through one door.
const props = defineProps({
    modelValue: { default: null },
    labels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

// Shared on every panel page by AssetsServiceProvider.
const manager = computed(() => usePage().props.fileManager ?? null);

const preview = ref(null);
const missing = ref(false);

// Rows saved before the library held ids still carry a path or URL. The
// storefront resolves those (MediaUrl::imageUrl), so show it rather than
// calling it missing.
const isId = (value) => /^\d+$/.test(String(value ?? ''));

const loadPreview = async () => {
    missing.value = false;

    if (!props.modelValue) {
        preview.value = null;

        return;
    }

    if (!isId(props.modelValue)) {
        preview.value = { name: String(props.modelValue), thumb: null };

        return;
    }

    // Just chosen: the manager already handed over the payload.
    if (String(preview.value?.id) === String(props.modelValue)) {
        return;
    }

    try {
        preview.value = await http.get(`${manager.value.base}/files/${props.modelValue}`);
    } catch {
        // A deleted asset leaves the id behind on the row.
        preview.value = null;
        missing.value = true;
    }
};

const pick = async () => {
    const chosen = await openFileManager({
        url: manager.value?.url,
        type: 'image',
        title: props.labels.pick,
        folder: preview.value?.folder ?? '',
    });

    if (chosen?.length) {
        preview.value = chosen[0];
        missing.value = false;
        // A string: the column is a string and its rule is `string` — the form
        // posts JSON, so a bare number fails validation.
        emit('update:modelValue', String(chosen[0].id));
    }
};

const clear = () => {
    preview.value = null;
    missing.value = false;
    emit('update:modelValue', null);
};

onMounted(loadPreview);

watch(() => props.modelValue, loadPreview);
</script>

<template>
    <div class="flex items-start gap-2">
        <!-- Sized in scoped CSS: `w-20`/`h-20` are not in the panel's prebuilt
             stylesheet, which is how the old picker's tile collapsed when empty
             and stretched to the photo when filled. -->
        <button
            type="button"
            class="media-thumb"
            :data-media-picker="true"
            :disabled="!manager"
            :title="labels.pick"
            @click="pick"
        >
            <img v-if="preview?.thumb" :src="preview.thumb" :alt="preview.alt ?? ''" />
            <Icon v-else name="image" />
        </button>

        <div class="grid gap-1 pt-1 min-w-0">
            <span class="text-[11px] text-ink-700 truncate max-w-[220px]" :title="preview?.name">{{ preview?.name ?? '—' }}</span>
            <span v-if="missing" class="text-[11px] text-danger">{{ labels.missing }}</span>
            <div class="flex gap-1">
                <Button size="sm" icon="image" :disabled="!manager" @click="pick">
                    {{ modelValue ? labels.change : labels.pick }}
                </Button>
                <Button v-if="modelValue" size="sm" variant="ghost" icon="trash" :aria-label="labels.remove" :title="labels.remove" @click="clear" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.media-thumb {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 80px;
    height: 80px;
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--color-line);
    border-radius: 6px;
    background: var(--color-surface-2);
    color: var(--color-ink-400);
    cursor: pointer;
}

.media-thumb:hover { border-color: var(--color-ink-300); }

.media-thumb:disabled { cursor: not-allowed; opacity: .6; }

.media-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
