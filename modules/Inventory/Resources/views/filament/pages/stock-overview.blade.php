<x-filament-panels::page>
    {{-- Inline stats (server-rendered — not a Livewire header widget, which
         would defer-load and trip a 419 on the database session). --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($this->stats() as $stat)
            <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-x-2">
                    <x-filament::icon :icon="$stat['icon']" @class([
                        'h-5 w-5',
                        'text-primary-500' => $stat['color'] === 'primary',
                        'text-warning-500' => $stat['color'] === 'warning',
                        'text-danger-500' => $stat['color'] === 'danger',
                        'text-gray-400' => $stat['color'] === 'gray',
                    ]) />
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $stat['label'] }}
                    </span>
                </div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    {{ $stat['value'] }}
                </div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $stat['description'] }}
                </div>
            </div>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
