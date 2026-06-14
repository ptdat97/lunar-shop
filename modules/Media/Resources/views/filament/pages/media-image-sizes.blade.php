<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit">
                Save sizes
            </x-filament::button>

            <x-filament::button type="button" color="gray" wire:click="regenerate">
                Regenerate missing
            </x-filament::button>

            <x-filament::button
                type="button"
                color="danger"
                wire:click="forceRegenerate"
                wire:confirm="Force-regenerate ALL conversions? Existing images will be overwritten."
            >
                Force regenerate all
            </x-filament::button>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            After changing sizes, use <strong>Force regenerate all</strong> to rebuild
            previously generated images. Runs in the background (requires a queue worker).
        </p>
    </form>
</x-filament-panels::page>
