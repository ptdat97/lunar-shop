<x-filament-panels::page>
    {{-- Orders still holding stock long after they were paid for. Committed
         units only free up on dispatch or cancel, so a forgotten order keeps
         its stock off sale indefinitely. --}}
    @php($stale = $this->staleCommitments())
    @if ($stale->isNotEmpty())
        <div class="rounded-xl bg-warning-50 p-4 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:ring-warning-400/30">
            <div class="flex items-start gap-x-3">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 h-5 w-5 text-warning-500" />
                <div class="text-sm">
                    <p class="font-medium text-warning-800 dark:text-warning-200">
                        {{ __('admin.inventory.stale_commitment', ['days' => $this->staleCommitmentDays()]) }}
                    </p>
                    <p class="mt-1 text-warning-700 dark:text-warning-300">
                        @foreach ($stale->take(10) as $order)
                            <span class="inline-block after:content-['·'] after:mx-1 last:after:content-['']">
                                #{{ $order->reference }}
                            </span>
                        @endforeach
                        @if ($stale->count() > 10)
                            <span>… +{{ $stale->count() - 10 }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endif

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
