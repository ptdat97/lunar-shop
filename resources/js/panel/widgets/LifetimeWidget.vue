<script setup>
import { KpiCard, TimeSeriesChart } from '@lunarphp/panel';

// The dashboard grid owns the card chrome and passes the widget's PHP payload
// as `data`; `range` is deliberately unused here — this card exists to show the
// figures that do NOT move when the range picker does.
defineProps({
    data: { type: Object, required: true },
    range: { type: String, default: null },
});
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr))">
            <KpiCard
                v-for="tile in data.tiles"
                :key="tile.label"
                :label="tile.label"
                :value="tile.value"
                :icon="tile.icon"
                :tone="tile.tone"
            />
        </div>

        <div v-if="data.points?.length">
            <div class="text-xs font-medium text-ink-700 mb-1.5">{{ data.trendHeading }}</div>
            <TimeSeriesChart :points="data.points" :aria-label="data.trendHeading" :height="160" />
        </div>
    </div>
</template>
