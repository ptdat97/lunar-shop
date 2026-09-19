<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Button, TextInput, Select, Icon, Dialog, ConfirmDialog, FieldLabel, useToasts } from '@lunarphp/panel';
import { ApiError, fmt, mediaApi } from './api';

/**
 * The shop's file manager (modules/Assets).
 *
 * One component, two places: the Thư viện media page (`mode="manage"`) and the
 * chrome-less picker every image field opens in an iframe (`mode="pick"`). The
 * picker is the whole manager plus a Choose button — an admin filling in a
 * banner can upload, sort into a folder and fix alt text without leaving it,
 * and whatever they do lands in the same library under the same rules.
 *
 * Plain scoped CSS over panel colour tokens rather than Tailwind utilities:
 * the panel's stylesheet is prebuilt and only carries the utilities its own
 * pages use (docs/architecture/panel-addon.md §2).
 */
const props = defineProps({
    labels: { type: Object, required: true },
    base: { type: String, required: true },
    maxUploadKb: { type: Number, default: 8192 },
    unfiled: { type: String, default: '-' },
    mode: { type: String, default: 'manage' },
    type: { type: String, default: 'all' },
    multiple: { type: Boolean, default: false },
    initialFolder: { type: String, default: '' },
    // Where uploads go while "All files" or "Unfiled" is open. The opener's
    // context: a picker opened from a product page files new photos under
    // `products` instead of leaving them unfiled.
    defaultUploadFolder: { type: String, default: '' },
});

const emit = defineEmits(['choose', 'cancel']);

const api = mediaApi(props.base);
const toasts = useToasts();
const t = (key, params) => fmt(props.labels[key] ?? key, params);

const picking = computed(() => props.mode === 'pick');
// In the picker a click adds to the pick; a manager click selects one, with
// Ctrl/⌘ and Shift for more — the file-browser convention.
const toggleOnClick = computed(() => picking.value && props.multiple);

// Mirrors the server's `image` rule. SVG is out on purpose: served from the
// shop's own origin it is a script.
const ACCEPT = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
const PARALLEL_UPLOADS = 3;

/* ------------------------------------------------------------------ list */

const items = ref([]);
const serverFolders = ref([]);
const total = ref(0);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const loadFailed = ref(false);

const filters = reactive({
    folder: props.initialFolder,
    search: '',
    type: props.type,
    sort: 'newest',
});

// Folders only exist while a file is in them. One just created here lives on
// the client until the first upload or move gives it a file.
const pendingFolders = ref([]);

const folderList = computed(() => {
    const known = new Set(serverFolders.value.map((f) => f.name));

    return [
        ...serverFolders.value,
        ...pendingFolders.value.filter((name) => !known.has(name)).map((name) => ({ name, count: 0 })),
    ].sort((a, b) => a.name.localeCompare(b.name));
});

const isRealFolder = (folder) => !!folder && folder !== props.unfiled;
const uploadFolder = computed(() => {
    if (isRealFolder(filters.folder)) {
        return filters.folder;
    }

    return filters.folder === '' && props.defaultUploadFolder ? props.defaultUploadFolder : null;
});

let requestId = 0;

const load = async () => {
    const id = ++requestId;

    loading.value = true;
    loadFailed.value = false;

    try {
        const result = await api.list({
            type: filters.type === 'all' ? null : filters.type,
            folder: filters.folder,
            search: filters.search.trim(),
            sort: filters.sort,
            page: page.value,
        });

        // A slower earlier request must not overwrite a newer one.
        if (id !== requestId) {
            return;
        }

        items.value = result.items;
        serverFolders.value = result.folders;
        total.value = result.total;
        lastPage.value = result.lastPage;

        // A page emptied by a delete: step back rather than show nothing.
        if (!result.items.length && page.value > 1 && page.value > result.lastPage) {
            page.value = result.lastPage;
        }

        result.items.forEach((asset) => {
            if (selected.value.has(asset.id)) {
                selected.value.set(asset.id, asset);
            }
        });
    } catch {
        if (id === requestId) {
            loadFailed.value = true;
        }
    } finally {
        if (id === requestId) {
            loading.value = false;
        }
    }
};

const reload = (resetPage = false) => {
    if (resetPage && page.value !== 1) {
        page.value = 1; // the page watcher loads

        return;
    }

    load();
};

let searchTimer = null;

watch(() => filters.search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => reload(true), 300);
});

watch(() => [filters.folder, filters.type, filters.sort], () => {
    anchor = null;
    reload(true);
});

watch(page, load);

const setFolder = (folder) => {
    filters.folder = folder;
};

/* ------------------------------------------------------------- selection */

// Kept across pages and filters, so a multi-pick can gather files from two
// folders. Values are the asset payloads, which is what a pick returns.
const selected = ref(new Map());
let anchor = null;

const isSelected = (asset) => selected.value.has(asset.id);
const selectedList = computed(() => [...selected.value.values()]);
const single = computed(() => (selected.value.size === 1 ? selectedList.value[0] : null));

const setSelection = (assets) => {
    selected.value = new Map(assets.map((asset) => [asset.id, asset]));
};

const toggle = (asset) => {
    const next = new Map(selected.value);

    if (next.has(asset.id)) {
        next.delete(asset.id);
    } else {
        if (picking.value && !props.multiple) {
            next.clear();
        }

        next.set(asset.id, asset);
    }

    selected.value = next;
};

const clearSelection = () => {
    selected.value = new Map();
    anchor = null;
};

const selectPage = () => {
    const next = new Map(selected.value);
    items.value.forEach((asset) => next.set(asset.id, asset));
    selected.value = next;
};

const onTileClick = (asset, index, event) => {
    if (picking.value && !props.multiple) {
        setSelection([asset]);
    } else if (toggleOnClick.value || event.metaKey || event.ctrlKey) {
        toggle(asset);
    } else if (event.shiftKey && anchor !== null) {
        const [from, to] = [Math.min(anchor, index), Math.max(anchor, index)];
        const next = new Map(selected.value);
        items.value.slice(from, to + 1).forEach((item) => next.set(item.id, item));
        selected.value = next;

        return; // keep the anchor where the range started
    } else {
        setSelection([asset]);
    }

    anchor = index;
};

// The corner checkbox always adds/removes, whatever the click mode.
const onCheck = (asset, index) => {
    toggle(asset);
    anchor = index;
};

const onTileDoubleClick = (asset) => {
    if (!picking.value) {
        return;
    }

    if (!props.multiple) {
        emit('choose', [asset]);

        return;
    }

    if (!isSelected(asset)) {
        toggle(asset);
    }

    choose();
};

const choose = () => {
    if (selected.value.size) {
        emit('choose', selectedList.value);
    }
};

const onBackgroundClick = (event) => {
    if (event.target === event.currentTarget && !picking.value) {
        clearSelection();
    }
};

/* --------------------------------------------------------------- uploads */

const fileInput = ref(null);
const replaceInput = ref(null);
const uploads = ref([]);
const dropActive = ref(false);
let dragDepth = 0;

const uploadRunning = computed(() => uploads.value.some((u) => u.status === 'uploading'));
const uploadDone = computed(() => uploads.value.filter((u) => u.status !== 'uploading').length);

const rejectReason = (file) => {
    if (!ACCEPT.includes(file.type)) {
        return t('not_image', { name: file.name });
    }

    if (file.size > props.maxUploadKb * 1024) {
        return t('too_large', { name: file.name, size: Math.round(props.maxUploadKb / 1024) });
    }

    return null;
};

const errorText = (error, name) => {
    if (error instanceof ApiError && error.firstError) {
        return `${name}: ${error.firstError}`;
    }

    if (error instanceof ApiError && error.status === 413) {
        return t('too_large', { name, size: Math.round(props.maxUploadKb / 1024) });
    }

    return t('upload_failed', { name });
};

const uploadFiles = async (fileList) => {
    const files = Array.from(fileList ?? []);

    if (!files.length) {
        return;
    }

    const folder = uploadFolder.value;
    const queue = [];

    // A finished batch's rows are cleared when the next one starts.
    if (!uploadRunning.value) {
        uploads.value = [];
    }

    files.forEach((file) => {
        const reason = rejectReason(file);
        const row = reactive({ key: `${Date.now()}-${Math.random()}`, name: file.name, progress: 0, status: reason ? 'error' : 'uploading', error: reason });

        uploads.value.push(row);

        if (!reason) {
            queue.push({ file, row });
        }
    });

    const created = [];

    const worker = async () => {
        while (queue.length) {
            const { file, row } = queue.shift();

            try {
                const asset = await api.upload(file, folder, (progress) => {
                    row.progress = progress;
                });

                row.status = 'done';
                row.progress = 100;
                created.push(asset);
            } catch (error) {
                row.status = 'error';
                row.error = errorText(error, file.name);
            }
        }
    };

    await Promise.all(Array.from({ length: Math.min(PARALLEL_UPLOADS, queue.length) }, worker));

    if (created.length) {
        toasts.success(t('uploaded', { count: created.length }));

        // What was just uploaded is almost always what is about to be picked.
        if (picking.value) {
            setSelection(props.multiple ? [...selectedList.value, ...created] : [created[created.length - 1]]);
        } else {
            setSelection(created);
        }

        pendingFolders.value = pendingFolders.value.filter((name) => name !== folder);

        // New files sort first under "newest"; show them.
        if (filters.sort !== 'newest') {
            filters.sort = 'newest';
        } else {
            reload(true);
        }
    }

    if (uploads.value.every((u) => u.status === 'done')) {
        setTimeout(() => {
            if (!uploadRunning.value) {
                uploads.value = [];
            }
        }, 2500);
    }
};

const onFileInput = (event) => {
    uploadFiles(event.target.files);
    event.target.value = '';
};

// Only a drag carrying files is an upload; dragging a tile to a folder is not.
const carriesFiles = (event) => Array.from(event.dataTransfer?.types ?? []).includes('Files');

const onDragEnter = (event) => {
    if (!carriesFiles(event)) {
        return;
    }

    dragDepth += 1;
    dropActive.value = true;
};

const onDragLeave = (event) => {
    if (!carriesFiles(event)) {
        return;
    }

    dragDepth = Math.max(0, dragDepth - 1);
    dropActive.value = dragDepth > 0;
};

const onDrop = (event) => {
    dragDepth = 0;
    dropActive.value = false;

    if (carriesFiles(event)) {
        uploadFiles(event.dataTransfer.files);
    }
};

/* ------------------------------------------------ drag tiles into folders */

const TILE_TYPE = 'application/x-shop-assets';
const dropFolder = ref(null);

const onTileDragStart = (asset, event) => {
    const ids = isSelected(asset) ? [...selected.value.keys()] : [asset.id];

    event.dataTransfer.setData(TILE_TYPE, JSON.stringify(ids));
    event.dataTransfer.effectAllowed = 'move';
};

const carriesTiles = (event) => Array.from(event.dataTransfer?.types ?? []).includes(TILE_TYPE);

const onFolderDragOver = (folder, event) => {
    if (carriesTiles(event)) {
        event.preventDefault();
        dropFolder.value = folder;
    }
};

const onFolderDrop = (folder, event) => {
    dropFolder.value = null;

    if (!carriesTiles(event)) {
        return;
    }

    try {
        moveIds(JSON.parse(event.dataTransfer.getData(TILE_TYPE)), folder);
    } catch {
        // Not our payload.
    }
};

/* ---------------------------------------------------- move / delete / edit */

const busy = ref(false);
const bulkTarget = ref('');

const moveIds = async (ids, folder) => {
    if (!ids.length) {
        return;
    }

    busy.value = true;

    try {
        const { moved } = await api.move(ids, isRealFolder(folder) ? folder : null);
        toasts.success(t('moved', { count: moved }));
        pendingFolders.value = pendingFolders.value.filter((name) => name !== folder);
        load();
    } catch {
        toasts.error(t('error'));
    } finally {
        busy.value = false;
    }
};

watch(bulkTarget, (folder) => {
    if (folder) {
        moveIds([...selected.value.keys()], folder);
        bulkTarget.value = '';
    }
});

const confirmDelete = ref(false);

const destroySelected = async () => {
    const ids = [...selected.value.keys()];

    busy.value = true;

    try {
        const { deleted, unlinked } = await api.destroy(ids);
        toasts.success(t('deleted', { count: deleted }));

        if (unlinked) {
            toasts.info(t('unlinked', { count: unlinked }));
        }
        clearSelection();
        load();
    } catch {
        toasts.error(t('error'));
    } finally {
        busy.value = false;
    }
};

// The details panel edits a copy; nothing is written until Save.
const form = reactive({ name: '', alt: '', title: '', folder: '' });
const saving = ref(false);

// Which galleries show the selected file (product, collection, brand…). The
// list is fetched per file rather than shipped with every tile: the grid only
// needs the count.
const usages = ref(null);

watch(() => single.value?.id, async (id) => {
    usages.value = null;

    if (!id) {
        return;
    }

    try {
        const detail = await api.show(id);

        if (single.value?.id === id) {
            usages.value = detail.usages ?? [];
        }
    } catch {
        usages.value = [];
    }
});

const usedInSelection = computed(() => selectedList.value.reduce((sum, asset) => sum + (asset.used ?? 0), 0));

const fill = (asset) => {
    form.name = asset?.name ?? '';
    form.alt = asset?.alt ?? '';
    form.title = asset?.title ?? '';
    form.folder = asset?.folder ?? '';
};

watch(() => single.value?.id, () => fill(single.value), { immediate: true });

const dirty = computed(() => single.value && (
    form.name !== (single.value.name ?? '')
    || form.alt !== (single.value.alt ?? '')
    || form.title !== (single.value.title ?? '')
    || form.folder !== (single.value.folder ?? '')
));

const refreshAsset = (asset) => {
    items.value = items.value.map((item) => (item.id === asset.id ? asset : item));

    if (selected.value.has(asset.id)) {
        const next = new Map(selected.value);
        next.set(asset.id, asset);
        selected.value = next;
    }
};

const saveDetails = async () => {
    const asset = single.value;

    if (!asset) {
        return;
    }

    saving.value = true;

    try {
        const folderChanged = form.folder !== (asset.folder ?? '');
        const updated = await api.update(asset.id, {
            name: form.name,
            alt: form.alt,
            title: form.title,
            folder: form.folder || null,
        });

        refreshAsset(updated);
        fill(updated);
        toasts.success(t('saved'));

        if (folderChanged) {
            pendingFolders.value = pendingFolders.value.filter((name) => name !== form.folder);
            load();
        }
    } catch (error) {
        toasts.error(error instanceof ApiError && error.firstError ? error.firstError : t('error'));
    } finally {
        saving.value = false;
    }
};

const replacing = ref(0);

const onReplaceInput = async (event) => {
    const file = event.target.files?.[0];
    const asset = single.value;

    event.target.value = '';

    if (!file || !asset) {
        return;
    }

    const reason = rejectReason(file);

    if (reason) {
        toasts.error(reason);

        return;
    }

    replacing.value = 1;

    try {
        const updated = await api.replace(asset.id, file, (progress) => {
            replacing.value = Math.max(1, progress);
        });

        refreshAsset(updated);
        toasts.success(t('replaced'));
    } catch (error) {
        toasts.error(errorText(error, file.name));
    } finally {
        replacing.value = 0;
    }
};

const absoluteUrl = (url) => (url ? new URL(url, window.location.origin).toString() : '');

const copyUrl = async (asset) => {
    try {
        await navigator.clipboard.writeText(absoluteUrl(asset.large ?? asset.url));
        toasts.success(t('copied'));
    } catch {
        toasts.error(t('error'));
    }
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString(document.documentElement.lang || undefined) : '—');

/* --------------------------------------------------------------- folders */

const folderDialog = reactive({ open: false, mode: 'create', from: '', name: '', error: '' });

// Same idea as Str::slug on the server — close enough to name the pending
// folder; the server's own slug replaces it on the first write.
const slugify = (text) => String(text)
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/đ/gi, 'd')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

const openCreateFolder = () => Object.assign(folderDialog, { open: true, mode: 'create', from: '', name: '', error: '' });
const openRenameFolder = (folder) => Object.assign(folderDialog, { open: true, mode: 'rename', from: folder, name: folder, error: '' });

const submitFolder = async () => {
    const slug = slugify(folderDialog.name);

    if (!slug) {
        folderDialog.error = t('folder_invalid');

        return;
    }

    if (folderDialog.mode === 'create') {
        if (!pendingFolders.value.includes(slug)) {
            pendingFolders.value = [...pendingFolders.value, slug];
        }

        folderDialog.open = false;
        setFolder(slug);

        return;
    }

    if (!serverFolders.value.some((f) => f.name === folderDialog.from)) {
        // Still pending: nothing on the server to rename.
        pendingFolders.value = pendingFolders.value.map((name) => (name === folderDialog.from ? slug : name));
        folderDialog.open = false;
        setFolder(slug);

        return;
    }

    try {
        const { folder } = await api.renameFolder(folderDialog.from, folderDialog.name);
        const wasOpen = filters.folder === folderDialog.from;

        folderDialog.open = false;

        if (wasOpen && folder !== filters.folder) {
            setFolder(folder);
        } else {
            load();
        }
    } catch (error) {
        folderDialog.error = error instanceof ApiError && error.firstError ? error.firstError : t('error');
    }
};

/* -------------------------------------------------------------- keyboard */

const inField = (event) => ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target?.tagName) || event.target?.isContentEditable;

const onKeydown = (event) => {
    if (folderDialog.open || confirmDelete.value) {
        return;
    }

    if (event.key === 'Escape' && picking.value) {
        event.preventDefault();
        emit('cancel');

        return;
    }

    if (inField(event)) {
        return;
    }

    if (event.key === 'Enter' && picking.value && selected.value.size) {
        event.preventDefault();
        choose();
    } else if ((event.key === 'Delete' || event.key === 'Backspace') && selected.value.size && !picking.value) {
        event.preventDefault();
        confirmDelete.value = true;
    } else if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'a' && (!picking.value || props.multiple)) {
        event.preventDefault();
        selectPage();
    }
};

onMounted(() => {
    load();
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    clearTimeout(searchTimer);
});

const typeOptions = ['all', 'image', 'video', 'document'];
const sortOptions = ['newest', 'oldest', 'name'];
</script>

<template>
    <div class="fm" :class="{ 'fm--pick': picking, 'fm--multi': toggleOnClick }">
        <!-- Toolbar -->
        <div class="fm-toolbar">
            <div class="fm-search">
                <TextInput v-model="filters.search" clearable :placeholder="labels.search" data-fm="search">
                    <template #prefix><Icon name="search" cls="sm" /></template>
                </TextInput>
            </div>

            <!-- On a narrow screen the folder sidebar folds into this. -->
            <div class="fm-folder-select">
                <Select v-model="filters.folder">
                    <option value="">{{ labels.all_files }}</option>
                    <option :value="unfiled">{{ labels.unfiled }}</option>
                    <option v-for="folder in folderList" :key="folder.name" :value="folder.name">{{ folder.name }}</option>
                </Select>
            </div>

            <div v-if="!picking || type === 'all'" class="fm-compact">
                <Select v-model="filters.type" data-fm="type">
                    <option v-for="option in typeOptions" :key="option" :value="option">{{ labels[`type_${option}`] }}</option>
                </Select>
            </div>

            <div class="fm-compact">
                <Select v-model="filters.sort" data-fm="sort">
                    <option v-for="option in sortOptions" :key="option" :value="option">{{ labels[`sort_${option}`] }}</option>
                </Select>
            </div>

            <div class="fm-toolbar-end">
                <Button icon="folder" @click="openCreateFolder">{{ labels.new_folder }}</Button>
                <Button variant="primary" icon="upload" data-fm="upload" @click="fileInput?.click()">{{ labels.upload }}</Button>
                <!-- data-fm-input: the native-upload bridge must never
                     intercept the manager's own inputs. -->
                <input ref="fileInput" type="file" multiple :accept="ACCEPT.join(',')" class="fm-hidden" data-fm-input @change="onFileInput" />
            </div>
        </div>

        <div class="fm-body">
            <!-- Folders -->
            <nav class="fm-folders" :aria-label="labels.folders">
                <button
                    type="button"
                    class="fm-folder"
                    :class="{ 'is-active': filters.folder === '' }"
                    @click="setFolder('')"
                >
                    <Icon name="image" cls="sm" />
                    <span class="fm-folder-name">{{ labels.all_files }}</span>
                </button>

                <button
                    type="button"
                    class="fm-folder"
                    :class="{ 'is-active': filters.folder === unfiled, 'is-drop': dropFolder === unfiled }"
                    @click="setFolder(unfiled)"
                    @dragover="onFolderDragOver(unfiled, $event)"
                    @dragleave="dropFolder = null"
                    @drop.prevent="onFolderDrop(unfiled, $event)"
                >
                    <Icon name="box" cls="sm" />
                    <span class="fm-folder-name">{{ labels.unfiled }}</span>
                </button>

                <div class="fm-folders-title">{{ labels.folders }}</div>

                <div
                    v-for="folder in folderList"
                    :key="folder.name"
                    class="fm-folder"
                    :class="{ 'is-active': filters.folder === folder.name, 'is-drop': dropFolder === folder.name, 'is-pending': !folder.count }"
                    role="button"
                    tabindex="0"
                    :data-fm-folder="folder.name"
                    @click="setFolder(folder.name)"
                    @keydown.enter="setFolder(folder.name)"
                    @dragover="onFolderDragOver(folder.name, $event)"
                    @dragleave="dropFolder = null"
                    @drop.prevent="onFolderDrop(folder.name, $event)"
                >
                    <Icon name="folder" cls="sm" />
                    <span class="fm-folder-name">{{ folder.name }}</span>
                    <span class="fm-folder-count">{{ folder.count || '' }}</span>
                    <button type="button" class="fm-folder-edit" :title="labels.rename_folder" @click.stop="openRenameFolder(folder.name)">
                        <Icon name="edit" cls="sm" />
                    </button>
                </div>

                <button type="button" class="fm-folder fm-folder--add" @click="openCreateFolder">
                    <Icon name="plus" cls="sm" />
                    <span class="fm-folder-name">{{ labels.new_folder }}</span>
                </button>
            </nav>

            <!-- Files -->
            <section
                class="fm-main"
                @dragenter.prevent="onDragEnter"
                @dragover.prevent
                @dragleave="onDragLeave"
                @drop.prevent="onDrop"
            >
                <div v-if="selected.size" class="fm-bulkbar" data-fm="bulkbar">
                    <strong>{{ t('selected', { count: selected.size }) }}</strong>
                    <Button size="sm" variant="ghost" @click="clearSelection">{{ labels.clear_selection }}</Button>
                    <div class="fm-bulkbar-end">
                        <div class="fm-compact fm-compact--wide">
                            <Select v-model="bulkTarget" :disabled="busy">
                                <option value="">{{ labels.move_to }}</option>
                                <option :value="unfiled">{{ labels.unfiled }}</option>
                                <option v-for="folder in folderList" :key="folder.name" :value="folder.name">{{ folder.name }}</option>
                            </Select>
                        </div>
                        <Button size="sm" icon="trash" :disabled="busy" data-fm="delete" @click="confirmDelete = true">{{ labels.delete }}</Button>
                    </div>
                </div>

                <div v-else-if="uploadFolder" class="fm-hintbar">
                    <Icon name="folder" cls="sm" /> {{ t('upload_into', { folder: uploadFolder }) }}
                </div>

                <div class="fm-grid-wrap" @click="onBackgroundClick">
                    <p v-if="loadFailed" class="fm-empty">
                        {{ labels.error }}
                        <Button size="sm" icon="refresh" :aria-label="labels.error" @click="load" />
                    </p>

                    <p v-else-if="loading && !items.length" class="fm-empty">{{ labels.loading }}</p>

                    <div v-else-if="!items.length" class="fm-empty fm-empty--drop" @click="fileInput?.click()">
                        <Icon name="upload" />
                        <span>{{ filters.search || (filters.type !== 'all' && !picking) ? labels.no_results : labels.empty }}</span>
                    </div>

                    <ul v-else class="fm-grid" :class="{ 'is-loading': loading }" data-fm="grid">
                        <li v-for="(asset, index) in items" :key="asset.id">
                            <button
                                type="button"
                                class="fm-tile"
                                :class="{ 'is-selected': isSelected(asset) }"
                                :title="asset.name"
                                :aria-pressed="isSelected(asset)"
                                :data-fm-asset="asset.id"
                                draggable="true"
                                @click="onTileClick(asset, index, $event)"
                                @dblclick="onTileDoubleClick(asset)"
                                @dragstart="onTileDragStart(asset, $event)"
                            >
                                <span class="fm-thumb">
                                    <img v-if="asset.thumb" :src="asset.thumb" :alt="asset.alt ?? ''" loading="lazy" draggable="false" />
                                    <Icon v-else :name="asset.type === 'video' ? 'play' : 'box'" />
                                </span>
                                <span class="fm-tile-name">{{ asset.name }}</span>
                                <span v-if="asset.used" class="fm-used" :title="t('used_count', { count: asset.used })">
                                    <Icon name="link" cls="sm" />{{ asset.used }}
                                </span>
                                <span class="fm-check" @click.stop="onCheck(asset, index)">
                                    <Icon v-if="isSelected(asset)" name="check" cls="sm" />
                                </span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div v-if="dropActive" class="fm-drop">
                    <Icon name="upload" />
                    <span>{{ labels.drop_hint }}</span>
                    <small v-if="uploadFolder">{{ t('upload_into', { folder: uploadFolder }) }}</small>
                </div>

                <div v-if="uploads.length" class="fm-uploads" data-fm="uploads">
                    <div class="fm-uploads-head">
                        <span>{{ t('uploading', { done: uploadDone, total: uploads.length }) }}</span>
                        <button v-if="!uploadRunning" type="button" class="fm-icon-btn" :aria-label="labels.close" @click="uploads = []">
                            <Icon name="x" cls="sm" />
                        </button>
                    </div>
                    <div v-for="row in uploads" :key="row.key" class="fm-upload" :class="`is-${row.status}`">
                        <span class="fm-upload-name">{{ row.name }}</span>
                        <span v-if="row.status === 'error'" class="fm-upload-error">{{ row.error }}</span>
                        <span v-else class="fm-progress"><span :style="{ width: `${row.progress}%` }" /></span>
                    </div>
                </div>
            </section>

            <!-- Details -->
            <aside class="fm-details" :class="{ 'is-empty': !selected.size }" :aria-label="labels.details">
                <template v-if="single">
                    <div class="fm-preview">
                        <img v-if="single.thumb" :src="single.large ?? single.thumb" :alt="single.alt ?? ''" />
                        <Icon v-else name="box" />
                    </div>

                    <dl class="fm-meta">
                        <dt>{{ labels.file_name }}</dt><dd :title="single.file_name">{{ single.file_name }}</dd>
                        <dt>{{ labels.size }}</dt><dd>{{ single.size }}</dd>
                        <dt>{{ labels.uploaded_at }}</dt><dd>{{ formatDate(single.created_at) }}</dd>
                    </dl>

                    <div class="fm-usages" data-fm="usages">
                        <div class="fm-usages-title">{{ labels.used_in }}</div>
                        <p v-if="usages === null" class="fm-help">{{ labels.loading }}</p>
                        <p v-else-if="!usages.length" class="fm-help">{{ labels.not_used }}</p>
                        <ul v-else>
                            <li v-for="(usage, index) in usages" :key="index">
                                <span class="fm-usage-type">{{ labels[`usage_${usage.type}`] ?? usage.type }}</span>
                                <a v-if="usage.url" :href="usage.url" target="_blank" rel="noopener">{{ usage.name }}</a>
                                <span v-else>{{ usage.name }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="fm-links">
                        <Button size="sm" icon="copy" @click="copyUrl(single)">{{ labels.copy_url }}</Button>
                        <a :href="single.url" target="_blank" rel="noopener" class="fm-link">
                            <Icon name="eye" cls="sm" /> {{ labels.open_original }}
                        </a>
                    </div>

                    <form class="fm-form" data-fm="details" @submit.prevent="saveDetails">
                        <div>
                            <FieldLabel for="fm-name">{{ labels.name }}</FieldLabel>
                            <TextInput id="fm-name" v-model="form.name" />
                        </div>
                        <div>
                            <FieldLabel for="fm-alt">{{ labels.alt }}</FieldLabel>
                            <TextInput id="fm-alt" v-model="form.alt" />
                            <p class="fm-help">{{ labels.alt_help }}</p>
                        </div>
                        <div>
                            <FieldLabel for="fm-title">{{ labels.title_attr }}</FieldLabel>
                            <TextInput id="fm-title" v-model="form.title" />
                        </div>
                        <div>
                            <FieldLabel>{{ labels.folder }}</FieldLabel>
                            <Select v-model="form.folder">
                                <option value="">{{ labels.unfiled }}</option>
                                <option v-for="folder in folderList" :key="folder.name" :value="folder.name">{{ folder.name }}</option>
                            </Select>
                        </div>
                        <Button type="submit" variant="primary" :disabled="!dirty || saving">{{ labels.save }}</Button>
                    </form>

                    <div class="fm-danger">
                        <Button size="sm" icon="refresh" :disabled="!!replacing" @click="replaceInput?.click()">
                            {{ replacing ? `${replacing}%` : labels.replace }}
                        </Button>
                        <input ref="replaceInput" type="file" :accept="ACCEPT.join(',')" class="fm-hidden" data-fm-input @change="onReplaceInput" />
                        <p class="fm-help">{{ labels.replace_help }}</p>
                        <Button v-if="!picking" size="sm" icon="trash" @click="confirmDelete = true">{{ labels.delete }}</Button>
                    </div>
                </template>

                <p v-else-if="selected.size > 1" class="fm-help">{{ t('details_many', { count: selected.size }) }}</p>
                <p v-else class="fm-help">{{ labels.details_empty }}</p>
            </aside>
        </div>

        <!-- Footer -->
        <div class="fm-footer">
            <div class="fm-pager">
                <span>{{ t('total', { count: total }) }}</span>
                <template v-if="lastPage > 1">
                    <Button size="sm" variant="ghost" icon="chevronLeft" :aria-label="labels.prev" :disabled="page <= 1" @click="page -= 1" />
                    <span>{{ t('page_of', { page, last: lastPage }) }}</span>
                    <Button size="sm" variant="ghost" icon="chevronRight" :aria-label="labels.next" :disabled="page >= lastPage" @click="page += 1" />
                </template>
                <Button v-if="!picking || multiple" size="sm" variant="ghost" :disabled="!items.length" @click="selectPage">{{ labels.select_page }}</Button>
            </div>

            <div v-if="picking" class="fm-pick-actions">
                <span class="fm-help">{{ multiple ? labels.pick_hint_multiple : labels.pick_hint }}</span>
                <Button data-fm="cancel" @click="emit('cancel')">{{ labels.cancel }}</Button>
                <Button variant="primary" icon="check" data-fm="choose" :disabled="!selected.size" @click="choose">
                    {{ multiple && selected.size > 1 ? t('choose_count', { count: selected.size }) : labels.choose }}
                </Button>
            </div>
        </div>

        <Dialog
            :open="folderDialog.open"
            :title="folderDialog.mode === 'create' ? labels.new_folder : labels.rename_folder"
            :description="folderDialog.mode === 'create' ? labels.new_folder_hint : labels.rename_folder_hint"
            size="sm"
            @update:open="folderDialog.open = $event"
        >
            <form class="fm-form" @submit.prevent="submitFolder">
                <div>
                    <FieldLabel for="fm-folder-name">{{ labels.folder_name }}</FieldLabel>
                    <TextInput id="fm-folder-name" v-model="folderDialog.name" :invalid="!!folderDialog.error" data-fm="folder-name" />
                    <p v-if="folderDialog.error" class="fm-error">{{ folderDialog.error }}</p>
                </div>
            </form>
            <template #footer>
                <Button @click="folderDialog.open = false">{{ labels.cancel }}</Button>
                <Button variant="primary" data-fm="folder-save" @click="submitFolder">{{ labels.save }}</Button>
            </template>
        </Dialog>

        <ConfirmDialog
            :open="confirmDelete"
            :title="t('delete_title', { count: selected.size })"
            :description="usedInSelection ? t('delete_desc_used', { count: usedInSelection }) : labels.delete_desc"
            :confirm-label="labels.delete"
            :cancel-label="labels.cancel"
            tone="danger"
            @update:open="confirmDelete = $event"
            @confirm="destroySelected"
        />
    </div>
</template>

<style scoped>
.fm {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    background: var(--color-paper);
    color: var(--color-ink-900);
    font-size: 13px;
}

.fm-hidden { display: none; }

/* Toolbar */
.fm-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-bottom: 1px solid var(--color-line);
}
.fm-search { flex: 1 1 220px; max-width: 320px; }
.fm-compact { width: 150px; }
.fm-compact--wide { width: 200px; }
.fm-folder-select { display: none; width: 170px; }
.fm-toolbar-end { display: flex; gap: 8px; margin-left: auto; }

/* Three columns */
.fm-body {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: 210px minmax(0, 1fr) 290px;
}

.fm-folders {
    overflow-y: auto;
    padding: 10px 8px;
    border-right: 1px solid var(--color-line);
    background: var(--color-surface);
}
.fm-folders-title {
    margin: 14px 8px 6px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--color-ink-500);
}
.fm-folder {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 6px 8px;
    border: 1px solid transparent;
    border-radius: 6px;
    background: none;
    color: var(--color-ink-700);
    text-align: left;
    cursor: pointer;
}
.fm-folder:hover { background: var(--color-surface-2); color: var(--color-ink-900); }
.fm-folder.is-active { background: var(--color-sage-soft); color: var(--color-sage-ink); font-weight: 500; }
.fm-folder.is-drop { border-color: var(--color-sage); border-style: dashed; }
.fm-folder.is-pending .fm-folder-name { font-style: italic; }
.fm-folder--add { color: var(--color-ink-500); }
.fm-folder-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fm-folder-count { font-size: 11px; color: var(--color-ink-500); }
.fm-folder-edit {
    display: none;
    padding: 2px;
    border: 0;
    border-radius: 4px;
    background: none;
    color: var(--color-ink-500);
    cursor: pointer;
}
.fm-folder:hover .fm-folder-edit,
.fm-folder:focus-within .fm-folder-edit { display: inline-grid; }
.fm-folder:hover .fm-folder-edit ~ .fm-folder-count { display: none; }
.fm-folder-edit:hover { color: var(--color-ink-900); background: var(--color-surface-3); }

/* Files */
.fm-main {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
}
.fm-bulkbar,
.fm-hintbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-bottom: 1px solid var(--color-line);
}
.fm-bulkbar { background: var(--color-sage-soft); color: var(--color-sage-ink); }
.fm-hintbar { color: var(--color-ink-500); font-size: 12px; }
.fm-bulkbar-end { display: flex; gap: 8px; margin-left: auto; }

.fm-grid-wrap { flex: 1; min-height: 0; overflow-y: auto; padding: 14px 16px; }
.fm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(128px, 1fr));
    gap: 12px;
    margin: 0;
    padding: 0;
    list-style: none;
    transition: opacity .15s;
}
.fm-grid.is-loading { opacity: .55; }

.fm-tile {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--color-line);
    border-radius: 8px;
    background: var(--color-surface);
    text-align: left;
    cursor: pointer;
    user-select: none;
}
.fm-tile:hover { border-color: var(--color-ink-300); }
.fm-tile:focus-visible { outline: none; box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-sage) 35%, transparent); }
.fm-tile.is-selected {
    border-color: var(--color-sage);
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-sage) 55%, transparent);
}
.fm-thumb {
    display: grid;
    place-items: center;
    aspect-ratio: 1;
    color: var(--color-ink-400);
    /* Checkerboard, so a transparent PNG reads as transparent. */
    background-color: var(--color-surface-2);
    background-image:
        linear-gradient(45deg, var(--color-surface-3) 25%, transparent 25%),
        linear-gradient(-45deg, var(--color-surface-3) 25%, transparent 25%),
        linear-gradient(45deg, transparent 75%, var(--color-surface-3) 75%),
        linear-gradient(-45deg, transparent 75%, var(--color-surface-3) 75%);
    background-size: 16px 16px;
    background-position: 0 0, 0 8px, 8px -8px, -8px 0;
}
.fm-thumb img { width: 100%; height: 100%; object-fit: contain; }
.fm-tile-name {
    padding: 6px 8px;
    overflow: hidden;
    font-size: 11.5px;
    color: var(--color-ink-700);
    white-space: nowrap;
    text-overflow: ellipsis;
    border-top: 1px solid var(--color-line);
}
.fm-used {
    position: absolute;
    top: 6px;
    right: 6px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 1px 6px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--color-paper) 92%, transparent);
    border: 1px solid var(--color-line);
    color: var(--color-ink-700);
    font-size: 10.5px;
    font-weight: 600;
}
.fm-usages { display: grid; gap: 4px; font-size: 12px; }
.fm-usages-title { color: var(--color-ink-500); }
.fm-usages ul { display: grid; gap: 3px; margin: 0; padding: 0; list-style: none; }
.fm-usages li { display: flex; gap: 6px; min-width: 0; }
.fm-usages a { overflow: hidden; color: var(--color-ink-900); text-overflow: ellipsis; white-space: nowrap; }
.fm-usages a:hover { text-decoration: underline; }
.fm-usage-type { flex-shrink: 0; color: var(--color-ink-500); }
.fm-check {
    position: absolute;
    top: 6px;
    left: 6px;
    display: grid;
    place-items: center;
    width: 20px;
    height: 20px;
    border: 1px solid var(--color-line-strong);
    border-radius: 5px;
    background: var(--color-paper);
    color: var(--color-white);
    opacity: 0;
    transition: opacity .1s;
}
.fm-tile:hover .fm-check,
.fm--multi .fm-check,
.fm-tile.is-selected .fm-check { opacity: 1; }
.fm-tile.is-selected .fm-check { background: var(--color-sage); border-color: var(--color-sage); }

.fm-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 220px;
    margin: 0;
    color: var(--color-ink-500);
    text-align: center;
}
.fm-empty--drop {
    border: 2px dashed var(--color-line-strong);
    border-radius: 10px;
    cursor: pointer;
}
.fm-empty--drop:hover { border-color: var(--color-sage); color: var(--color-sage-ink); }

.fm-drop {
    position: absolute;
    inset: 8px;
    z-index: 5;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 2px dashed var(--color-sage);
    border-radius: 10px;
    background: color-mix(in srgb, var(--color-sage-soft) 88%, transparent);
    color: var(--color-sage-ink);
    font-weight: 600;
    pointer-events: none;
}
.fm-drop small { font-weight: 400; }

.fm-uploads {
    position: absolute;
    right: 16px;
    bottom: 16px;
    z-index: 6;
    width: 300px;
    max-height: 45%;
    overflow-y: auto;
    padding: 10px 12px;
    border: 1px solid var(--color-line);
    border-radius: 10px;
    background: var(--color-paper);
    box-shadow: 0 10px 30px rgba(0, 0, 0, .14);
}
.fm-uploads-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; font-weight: 600; }
.fm-upload { display: grid; gap: 3px; padding: 5px 0; font-size: 12px; }
.fm-upload-name { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.fm-upload-error { color: var(--color-danger); font-size: 11.5px; }
.fm-upload.is-done .fm-upload-name { color: var(--color-ink-500); }
.fm-progress { display: block; height: 4px; overflow: hidden; border-radius: 2px; background: var(--color-surface-3); }
.fm-progress span { display: block; height: 100%; background: var(--color-sage); transition: width .15s; }
.fm-icon-btn { display: inline-grid; padding: 2px; border: 0; border-radius: 4px; background: none; color: var(--color-ink-500); cursor: pointer; }
.fm-icon-btn:hover { color: var(--color-ink-900); background: var(--color-surface-2); }

/* Details */
.fm-details {
    display: flex;
    flex-direction: column;
    gap: 14px;
    overflow-y: auto;
    padding: 14px 16px;
    border-left: 1px solid var(--color-line);
    background: var(--color-surface);
}
/* A scrolling flex column shrinks its children to fit; the aspect-ratio
   preview (overflow hidden → no min-height) would collapse to nothing. */
.fm-details > * { flex-shrink: 0; }
.fm-preview {
    display: grid;
    place-items: center;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    border: 1px solid var(--color-line);
    border-radius: 8px;
    background: var(--color-surface-2);
    color: var(--color-ink-400);
}
.fm-preview img { width: 100%; height: 100%; object-fit: contain; }
.fm-meta { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 4px 10px; margin: 0; font-size: 12px; }
.fm-meta dt { color: var(--color-ink-500); }
.fm-meta dd { margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fm-links { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.fm-link { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: var(--color-ink-700); text-decoration: none; }
.fm-link:hover { color: var(--color-ink-900); text-decoration: underline; }
.fm-form { display: grid; gap: 10px; }
.fm-help { margin: 4px 0 0; font-size: 11.5px; color: var(--color-ink-500); }
.fm-error { margin: 4px 0 0; font-size: 11.5px; color: var(--color-danger); }
.fm-danger { display: grid; justify-items: start; gap: 6px; padding-top: 12px; border-top: 1px solid var(--color-line); }

/* Footer */
.fm-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px 16px;
    border-top: 1px solid var(--color-line);
}
.fm-pager { display: flex; align-items: center; gap: 8px; color: var(--color-ink-500); font-size: 12px; }
.fm-pick-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
.fm-pick-actions .fm-help { margin: 0 6px 0 0; }

/* Narrow: folders fold into the toolbar select, details drop under the grid. */
@media (max-width: 1080px) {
    .fm-body { grid-template-columns: 180px minmax(0, 1fr); grid-template-rows: minmax(0, 1fr) auto; }
    .fm-details { grid-column: 1 / -1; max-height: 34dvh; border-left: 0; border-top: 1px solid var(--color-line); }
    /* Stacked under the grid, an empty details panel is just lost space. */
    .fm-details.is-empty { display: none; }
    .fm-details .fm-preview { width: 160px; }
}

/* Phone: no fixed height to share between toolbar, grid and details — there is
   not enough of it. The manager flows and the page scrolls; the footer (and
   its Choose button) stays pinned to the bottom. */
@media (max-width: 760px) {
    .fm { height: auto; min-height: 100%; }
    .fm-body { display: block; }
    .fm-main { min-height: 240px; }
    .fm-grid-wrap { overflow: visible; }
    .fm-details { max-height: none; }
    .fm-footer { position: sticky; bottom: 0; z-index: 7; background: var(--color-paper); }
    .fm-uploads { position: fixed; right: 12px; bottom: 64px; left: 12px; width: auto; }
    .fm-folders { display: none; }
    .fm-toolbar { gap: 6px; padding: 10px 12px; }
    .fm-search { flex-basis: 100%; max-width: none; }
    .fm-folder-select { display: block; }
    .fm-folder-select,
    .fm-compact { flex: 1 1 0; width: auto; min-width: 0; }
    .fm-toolbar-end { width: 100%; }
    .fm-toolbar-end > * { flex: 1; }
    .fm-bulkbar { padding: 8px 12px; }
    .fm-bulkbar-end { width: 100%; margin-left: 0; }
    .fm-bulkbar-end .fm-compact { flex: 1; }
    .fm-grid-wrap { padding: 10px 12px; }
    .fm-grid { grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; }
    .fm-pick-actions .fm-help { display: none; }
}
</style>
