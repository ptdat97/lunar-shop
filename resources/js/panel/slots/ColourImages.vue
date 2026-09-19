<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Button, Icon, http, useToasts } from '@lunarphp/panel';
import { openFileManager } from '../media/openFileManager';
import { fmt } from '../media/api';

/**
 * "Photos by colour" on Lunar's product editor (Catalog, ProductColourImagesController).
 *
 * Every colour of the product gets one ordered photo set, shared by all its
 * sizes, picked from the product's gallery or from the shop's file manager.
 * Each change is saved straight away for that colour alone, so the product
 * form around this card — which may hold unsaved edits — is never touched.
 *
 * The sets are written to Lunar's own variant-image pivot, which is what the
 * storefront and Lunar's per-variant picker both read.
 */
const props = defineProps({
    // From the page zone: the product the editor is showing.
    product: { type: Object, default: null },
    labels: { type: Object, required: true },
});

const t = (key, params) => fmt(props.labels[key] ?? key, params);
const toasts = useToasts();
const fileManager = computed(() => usePage().props.fileManager ?? null);

const loading = ref(true);
const state = ref({ axis: null, groups: [], gallery: [] });
const busy = reactive({});
const galleryOpen = reactive({});

const base = computed(() => (props.product?.id ? `/panel/shop/products/${props.product.id}/colour-images` : null));

const load = async () => {
    if (!base.value) {
        loading.value = false;

        return;
    }

    try {
        state.value = await http.get(base.value);
    } finally {
        loading.value = false;
    }
};

onMounted(load);

const inSet = (group, mediaId) => group.images.some((image) => image.media_id === mediaId);

/**
 * Save one colour's set. Items are the current images by gallery id, plus any
 * newly chosen library files by asset id — the server links those into the
 * gallery (or finds the gallery's copy) without duplicating them.
 */
const save = async (group, items) => {
    busy[group.value_id] = true;

    const galleryBefore = state.value.gallery.map((image) => image.media_id).join(',');

    try {
        state.value = await http.put(`${base.value}/${group.value_id}`, { items });
        toasts.success(t('saved', { name: group.name }));

        // A library pick may have added images to Lunar's gallery above;
        // refresh just that prop so its card shows them.
        if (state.value.gallery.map((image) => image.media_id).join(',') !== galleryBefore) {
            router.reload({ only: ['mediaGroups'], preserveScroll: true });
        }
    } catch {
        toasts.error(t('error'));
    } finally {
        busy[group.value_id] = false;
    }
};

const current = (group) => group.images.map((image) => ({ media_id: image.media_id }));

const addFromGallery = (group, image) => {
    if (!inSet(group, image.media_id)) {
        save(group, [...current(group), { media_id: image.media_id }]);
    }
};

const addFromLibrary = async (group) => {
    const assets = await openFileManager({
        url: fileManager.value?.url,
        type: 'image',
        multiple: true,
        uploadFolder: 'products',
        title: props.labels.add_from_library,
    });

    if (assets?.length) {
        save(group, [...current(group), ...assets.map((asset) => ({ asset_id: asset.id }))]);
    }
};

const remove = (group, index) => {
    const items = current(group);
    items.splice(index, 1);
    save(group, items);
};

const move = (group, index, step) => {
    const items = current(group);
    const target = index + step;

    if (target < 0 || target >= items.length) {
        return;
    }

    [items[index], items[target]] = [items[target], items[index]];
    save(group, items);
};
</script>

<template>
    <section class="py-6 border-b border-line first:pt-1 last:border-b-0 last:pb-7" data-colour-images>
        <div class="flex items-start gap-4 mb-4">
            <div class="flex-1 min-w-0">
                <h2 class="m-0 mb-1 text-sm font-semibold tracking-[-0.01em] text-ink-900">{{ labels.title }}</h2>
                <div class="text-xs text-ink-500 leading-normal max-w-[640px]">{{ labels.description }}</div>
            </div>
        </div>

        <p v-if="loading" class="ci-note">{{ labels.loading }}</p>

        <p v-else-if="!state.axis" class="ci-note">{{ labels.no_axis }}</p>

        <div v-else class="ci-groups">
            <div
                v-for="group in state.groups"
                :key="group.value_id"
                class="ci-group"
                :class="{ 'is-busy': busy[group.value_id] }"
                :data-colour="group.value_id"
            >
                <div class="ci-head">
                    <span class="ci-chip" :style="group.colour ? { background: group.colour } : null" />
                    <strong class="ci-name">{{ group.name }}</strong>
                    <span class="ci-count">{{ t('variants', { count: group.variants }) }}</span>

                    <div class="ci-actions">
                        <Button
                            size="sm"
                            variant="ghost"
                            icon="image"
                            :disabled="!state.gallery.length || busy[group.value_id]"
                            @click="galleryOpen[group.value_id] = !galleryOpen[group.value_id]"
                        >
                            {{ labels.add_from_gallery }}
                        </Button>
                        <Button
                            size="sm"
                            icon="folder"
                            :disabled="!fileManager || busy[group.value_id]"
                            :title="fileManager ? '' : labels.library_unavailable"
                            data-ci="library"
                            @click="addFromLibrary(group)"
                        >
                            {{ labels.add_from_library }}
                        </Button>
                    </div>
                </div>

                <p v-if="group.mixed" class="ci-warn">
                    <Icon name="alertTriangle" cls="sm" /> {{ labels.mixed }}
                </p>

                <p v-if="!group.images.length" class="ci-note">{{ labels.empty }}</p>

                <ul v-else class="ci-thumbs">
                    <li v-for="(image, index) in group.images" :key="image.media_id" class="ci-thumb" :data-media="image.media_id">
                        <img :src="image.thumb" :alt="image.name" loading="lazy" />
                        <span v-if="index === 0" class="ci-primary">{{ labels.primary }}</span>
                        <div class="ci-tools">
                            <button type="button" :disabled="index === 0" :aria-label="labels.move_left" :title="labels.move_left" @click="move(group, index, -1)">
                                <Icon name="chevronLeft" cls="sm" />
                            </button>
                            <button type="button" :disabled="index === group.images.length - 1" :aria-label="labels.move_right" :title="labels.move_right" @click="move(group, index, 1)">
                                <Icon name="chevronRight" cls="sm" />
                            </button>
                            <button type="button" :aria-label="labels.remove" :title="labels.remove" data-ci="remove" @click="remove(group, index)">
                                <Icon name="x" cls="sm" />
                            </button>
                        </div>
                    </li>
                </ul>

                <div v-if="galleryOpen[group.value_id]" class="ci-gallery">
                    <button
                        v-for="image in state.gallery"
                        :key="image.media_id"
                        type="button"
                        class="ci-pick"
                        :class="{ 'is-in': inSet(group, image.media_id) }"
                        :disabled="inSet(group, image.media_id) || busy[group.value_id]"
                        :title="inSet(group, image.media_id) ? labels.in_set : image.name"
                        :data-ci-gallery="image.media_id"
                        @click="addFromGallery(group, image)"
                    >
                        <img :src="image.thumb" :alt="image.name" loading="lazy" />
                        <Icon v-if="inSet(group, image.media_id)" name="check" cls="sm" />
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.ci-note { margin: 0; font-size: 12px; color: var(--color-ink-500); }

.ci-groups { display: grid; gap: 14px; }

.ci-group {
    padding: 12px;
    border: 1px solid var(--color-line);
    border-radius: 10px;
    background: var(--color-surface);
    transition: opacity .15s;
}
.ci-group.is-busy { opacity: .6; pointer-events: none; }

.ci-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; }
.ci-chip {
    width: 16px;
    height: 16px;
    border: 1px solid var(--color-line-strong);
    border-radius: 999px;
    background: var(--color-surface-3);
}
.ci-name { font-size: 13px; color: var(--color-ink-900); }
.ci-count { font-size: 11.5px; color: var(--color-ink-500); }
.ci-actions { display: flex; gap: 6px; margin-left: auto; }

.ci-warn {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0 0 10px;
    padding: 6px 8px;
    border: 1px solid var(--color-warn-border);
    border-radius: 6px;
    background: var(--color-warn-soft);
    color: var(--color-warn-ink);
    font-size: 11.5px;
}

.ci-thumbs {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}
.ci-thumb {
    position: relative;
    aspect-ratio: 2 / 3;
    overflow: hidden;
    border: 1px solid var(--color-line);
    border-radius: 6px;
    background: var(--color-surface-2);
}
.ci-thumb img { width: 100%; height: 100%; object-fit: cover; }
.ci-primary {
    position: absolute;
    top: 5px;
    left: 5px;
    padding: 1px 6px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--color-ink-900) 85%, transparent);
    color: var(--color-paper);
    font-size: 10px;
    font-weight: 600;
}
.ci-tools {
    position: absolute;
    right: 4px;
    bottom: 4px;
    left: 4px;
    display: flex;
    justify-content: center;
    gap: 3px;
    opacity: 0;
    transition: opacity .1s;
}
.ci-thumb:hover .ci-tools,
.ci-thumb:focus-within .ci-tools { opacity: 1; }
.ci-tools button {
    display: inline-grid;
    place-items: center;
    width: 24px;
    height: 24px;
    border: 1px solid var(--color-line);
    border-radius: 5px;
    background: var(--color-paper);
    color: var(--color-ink-700);
    cursor: pointer;
}
.ci-tools button:hover:not(:disabled) { color: var(--color-ink-900); border-color: var(--color-ink-300); }
.ci-tools button:disabled { opacity: .4; cursor: default; }

.ci-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(64px, 1fr));
    gap: 6px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed var(--color-line);
}
.ci-pick {
    position: relative;
    display: grid;
    place-items: center;
    aspect-ratio: 1;
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--color-line);
    border-radius: 6px;
    background: var(--color-surface-2);
    cursor: pointer;
}
.ci-pick:hover:not(:disabled) { border-color: var(--color-sage); }
.ci-pick img { width: 100%; height: 100%; object-fit: cover; }
.ci-pick.is-in { opacity: .45; cursor: default; }
.ci-pick.is-in :deep(svg) { position: absolute; color: var(--color-paper); }
</style>
