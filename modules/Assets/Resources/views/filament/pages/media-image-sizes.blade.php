<x-filament-panels::page>
    {{-- While a batch is running, poll the server every 2s to refresh progress.
         Also poll while sizes are stale/no worker, so the banner clears itself
         once Horizon comes up. --}}
    <div @if(($batch && ! ($batch['finished'] ?? false) && ! ($batch['cancelled'] ?? false)) || $sizesStale) wire:poll.2s="refreshBatch" @endif>

        {{-- No queue worker → images can't be (re)built in the background. --}}
        @unless($workerAvailable)
            <div class="mb-4 rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950/50 dark:text-warning-200">
                <strong>{{ __('admin.media.no_worker') }}</strong> {{ __('admin.media.no_worker_desc', ['command1' => 'php&nbsp;artisan&nbsp;horizon', 'command2' => 'php&nbsp;artisan&nbsp;queue:work']) }}
            </div>
        @endunless

        {{-- Sizes were just changed → existing conversions are stale. --}}
        @if($sizesStale)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary-300 bg-primary-50 p-4 text-sm text-primary-800 dark:border-primary-700 dark:bg-primary-950/50 dark:text-primary-200">
                <span><strong>{{ __('admin.media.sizes_changed') }}</strong> {{ __('admin.media.sizes_changed_desc') }}</span>
                <x-filament::button type="button" size="sm" color="primary"
                    wire:click="regenerateAll" wire:loading.attr="disabled" wire:target="regenerateAll">
                    {{ __('admin.media.rebuild_all_now') }}
                </x-filament::button>
            </div>
        @endif

        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button type="submit">
                    {{ __('admin.media.save') }}
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="regenerateMissing"
                    wire:loading.attr="disabled"
                    wire:target="regenerateMissing"
                >
                    {{ __('admin.media.regenerate_missing') }}
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="danger"
                    wire:click="regenerateAll"
                    wire:loading.attr="disabled"
                    wire:target="regenerateAll"
                    wire:confirm="{{ __('admin.media.regenerate_all_confirm') }}"
                >
                    {{ __('admin.media.regenerate_all') }}
                </x-filament::button>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('admin.media.save_note', ['newly' => '<em>tạo mới</em>', 'regenerate_all' => '<strong>Tạo lại tất cả</strong>', 'queue_work' => 'php&nbsp;artisan&nbsp;queue:work']) }}
            </p>
        </form>

        {{-- Live batch progress --}}
        @if($batch)
            <x-filament::section class="mt-6">
                <x-slot name="heading">{{ __('admin.media.regeneration_progress') }}</x-slot>

                @php
                    $pct = (int) ($batch['progress'] ?? 0);
                    $finished = $batch['finished'] ?? false;
                    $cancelled = $batch['cancelled'] ?? false;
                    $failed = (int) ($batch['failed'] ?? 0);
                    $stalled = $batch['stalled'] ?? false;
                    $perMin = $batch['per_min'] ?? null;
                    $eta = $batch['eta_seconds'] ?? null;
                    $etaLabel = null;
                    if ($eta !== null) {
                        $etaLabel = $eta >= 60 ? (int) ceil($eta / 60) . 'm' : $eta . 's';
                    }
                @endphp

                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium">
                            @if($cancelled) {{ __('admin.media.cancelled') }}
                            @elseif($finished) {{ __('admin.media.completed') }}
                            @elseif($stalled) {{ __('admin.media.waiting_for_worker') }}
                            @else {{ __('admin.media.processing') }}
                            @endif
                        </span>
                        <span class="tabular-nums text-gray-500">
                            {{ $batch['processed'] ?? 0 }} / {{ $batch['total'] ?? 0 }} {{ __('admin.media.batches') }}
                            ({{ $pct }}%)
                        </span>
                    </div>

                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            class="h-full rounded-full transition-all duration-500 {{ $cancelled ? 'bg-warning-500' : ($failed ? 'bg-danger-500' : ($stalled ? 'bg-warning-500' : 'bg-primary-500')) }}"
                            style="width: {{ $finished ? 100 : $pct }}%"
                        ></div>
                    </div>

                    {{-- Throughput + ETA (Horizon-drained). --}}
                    @if(! $finished && ! $cancelled && ($perMin || $etaLabel))
                        <p class="text-xs tabular-nums text-gray-500">
                            @if($perMin) {{ __('admin.media.throughput', ['perMin' => $perMin]) }} @endif
                            @if($etaLabel) {{ __('admin.media.eta_remaining', ['etaLabel' => $etaLabel]) }} @endif
                        </p>
                    @endif

                    {{-- Batch queued but no worker draining it. --}}
                    @if($stalled)
                        <p class="text-sm text-warning-600">
                            {{ __('admin.media.batch_queued_no_worker') }}
                        </p>
                    @endif

                    @if($failed)
                        <p class="text-sm text-danger-600">{{ __('admin.media.batch_failed', ['count' => $failed]) }}</p>
                    @endif

                    @if(! $finished && ! $cancelled)
                        <x-filament::button
                            type="button"
                            size="sm"
                            color="gray"
                            wire:click="cancelRegenerate"
                        >
                            {{ __('admin.media.cancel') }}
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>