<script setup>
import { Link } from '@inertiajs/vue3';
import { StatusBadge } from '@lunarphp/panel';

// The dashboard grid owns the card chrome and passes the PHP payload as `data`.
// `range` is deliberately unused: an order stuck since March is stuck whatever
// the range picker says.
defineProps({
    data: { type: Object, required: true },
    range: { type: String, default: null },
});
</script>

<template>
    <div v-if="!data.count" class="text-[12.5px] text-ink-500">
        {{ data.emptyLabel }}
    </div>

    <div v-else class="grid gap-2">
        <div class="flex items-baseline gap-2">
            <span class="text-2xl font-semibold text-warn-ink">{{ data.count }}</span>
            <span class="text-xs text-ink-500">{{ data.heldLabel }}</span>
        </div>

        <ul class="grid gap-1">
            <li v-for="order in data.orders" :key="order.id">
                <Link
                    :href="order.url"
                    class="flex items-center justify-between gap-2 rounded-md px-2 py-1 hover:bg-surface-2"
                >
                    <span class="text-[12.5px] text-ink-900 truncate">{{ order.reference }}</span>
                    <StatusBadge size="sm">{{ order.days }}d</StatusBadge>
                </Link>
            </li>
        </ul>
    </div>
</template>
