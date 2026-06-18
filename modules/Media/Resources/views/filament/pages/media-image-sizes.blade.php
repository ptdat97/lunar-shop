<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit">
                Save sizes
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                wire:click="regenerate"
                wire:target="regenerate"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="regenerate">Regenerate missing</span>
                <span wire:loading wire:target="regenerate">Regenerating…</span>
            </x-filament::button>

            <x-filament::button
                type="button"
                color="danger"
                wire:click="forceRegenerate"
                wire:target="forceRegenerate"
                wire:loading.attr="disabled"
                wire:confirm="Force-regenerate ALL conversions? Existing images will be overwritten."
            >
                <span wire:loading.remove wire:target="forceRegenerate">Force regenerate all</span>
                <span wire:loading wire:target="forceRegenerate">Regenerating…</span>
            </x-filament::button>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            After changing sizes, use <strong>Force regenerate all</strong> to rebuild
            previously generated images. Runs immediately on click — no queue worker
            needed. For large media libraries it may take a moment to finish.
        </p>
    </form>
</x-filament-panels::page>
