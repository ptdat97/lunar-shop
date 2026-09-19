<script setup>
import { PageHeader } from '@lunarphp/panel';
import FileManager from '../../../panel/media/FileManager.vue';

defineProps({
    labels: { type: Object, required: true },
    base: { type: String, required: true },
    maxUploadKb: { type: Number, default: 8192 },
    unfiled: { type: String, default: '-' },
});
</script>

<template>
    <!-- No <PanelLayout> wrapper: the panel's page resolver applies the shell
         layout to add-on pages automatically (see runtime/pageResolver.ts). -->
    <PageHeader :title="labels.title" :description="labels.description" icon="image" />

    <div class="media-page">
        <div class="media-page-frame">
            <FileManager
                mode="manage"
                :labels="labels"
                :base="base"
                :max-upload-kb="maxUploadKb"
                :unfiled="unfiled"
            />
        </div>
    </div>
</template>

<style scoped>
/* The manager scrolls inside itself — grid, folders and details each on their
   own — so the page gives it a fixed height instead of letting it grow. */
.media-page {
    width: 100%;
    max-width: 1600px;
    margin: 0 auto;
    padding: 20px 16px 28px;
}

.media-page-frame {
    height: max(560px, calc(100dvh - 190px));
    overflow: hidden;
    border: 1px solid var(--color-line);
    border-radius: 12px;
    background: var(--color-paper);
    box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
}

@media (min-width: 1024px) {
    .media-page { padding: 20px 28px 28px; }
}

/* On a phone the manager flows with the page instead (see FileManager.vue). */
@media (max-width: 760px) {
    .media-page { padding: 12px 12px 20px; }
    .media-page-frame { height: auto; overflow: visible; }
}
</style>
