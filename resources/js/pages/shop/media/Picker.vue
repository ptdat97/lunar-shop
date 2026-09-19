<script setup>
import { Head } from '@inertiajs/vue3';
import { Icon, Toaster } from '@lunarphp/panel';
import FileManager from '../../../panel/media/FileManager.vue';
import { pickerTarget, postPick } from '../../../panel/media/openFileManager';

/**
 * The file manager as a picker, for openFileManager()'s iframe.
 *
 * Declares a passthrough layout in index.js, so no sidebar is drawn inside the
 * popup. Everything the manager page can do works here too; the only addition
 * is the answer — the chosen files, posted to whoever opened the picker.
 *
 * Opened on its own (a bookmarked URL, a new tab) there is nobody to answer,
 * so it behaves as the plain manager instead of offering a Choose that could
 * never go anywhere.
 */
const props = defineProps({
    labels: { type: Object, required: true },
    base: { type: String, required: true },
    maxUploadKb: { type: Number, default: 8192 },
    unfiled: { type: String, default: '-' },
    pick: { type: Object, required: true },
});

const embedded = pickerTarget() !== null;

const choose = (assets) => postPick(props.pick.channel, { type: 'select', assets });

const cancel = () => {
    postPick(props.pick.channel, { type: 'cancel' });

    // A window.open() popup closes itself; an iframe is removed by its opener.
    if (window.opener && window.parent === window) {
        window.close();
    }
};
</script>

<template>
    <Head :title="labels.picker_title" />

    <div class="picker">
        <header class="picker-head">
            <Icon name="image" cls="sm" />
            <h1>{{ embedded ? labels.picker_title : labels.title }}</h1>
            <button v-if="embedded" type="button" class="picker-close" :aria-label="labels.close" data-fm="close" @click="cancel">
                <Icon name="x" cls="sm" />
            </button>
        </header>

        <div class="picker-body">
            <FileManager
                :mode="embedded ? 'pick' : 'manage'"
                :type="pick.type"
                :multiple="pick.multiple"
                :initial-folder="pick.folder"
                :default-upload-folder="pick.uploadFolder ?? ''"
                :labels="labels"
                :base="base"
                :max-upload-kb="maxUploadKb"
                :unfiled="unfiled"
                @choose="choose"
                @cancel="cancel"
            />
        </div>

        <!-- The shell layout carries the toaster on normal pages; this one has
             no shell. -->
        <Toaster />
    </div>
</template>

<style scoped>
.picker {
    display: flex;
    flex-direction: column;
    height: 100dvh;
    background: var(--color-paper);
    color: var(--color-ink-900);
}

.picker-head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--color-line);
    color: var(--color-ink-700);
}

.picker-head h1 {
    flex: 1;
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--color-ink-900);
}

.picker-close {
    display: inline-grid;
    padding: 4px;
    border: 0;
    border-radius: 6px;
    background: none;
    color: var(--color-ink-500);
    cursor: pointer;
}

.picker-close:hover {
    color: var(--color-ink-900);
    background: var(--color-surface-2);
}

.picker-body {
    flex: 1;
    min-height: 0;
    /* Desktop: the manager fills this exactly. Phone: it flows, and this scrolls. */
    overflow-y: auto;
}
</style>
