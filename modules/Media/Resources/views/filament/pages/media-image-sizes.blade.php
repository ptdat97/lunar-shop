<x-filament-panels::page>
    {{-- While a batch is running, poll the server every 2s to refresh progress. --}}
    <div @if($batch && ! ($batch['finished'] ?? false) && ! ($batch['cancelled'] ?? false)) wire:poll.2s="refreshBatch" @endif>
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button type="submit">
                    Save sizes
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="regenerateMissing"
                    wire:loading.attr="disabled"
                    wire:target="regenerateMissing"
                >
                    Regenerate missing
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="danger"
                    wire:click="regenerateAll"
                    wire:loading.attr="disabled"
                    wire:target="regenerateAll"
                    wire:confirm="Rebuild ALL conversions in the background? This overwrites existing generated images."
                >
                    Regenerate all
                </x-filament::button>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Saving applies new sizes to <em>newly</em> generated images. To rebuild
                existing media with the new sizes, use <strong>Regenerate all</strong> — it
                runs in the background on the queue (no request timeout, safe for large
                libraries) and needs a running queue worker
                (<code>php&nbsp;artisan&nbsp;queue:work</code>).
            </p>
        </form>

        {{-- Live batch progress --}}
        @if($batch)
            <x-filament::section class="mt-6">
                <x-slot name="heading">Regeneration progress</x-slot>

                @php
                    $pct = (int) ($batch['progress'] ?? 0);
                    $finished = $batch['finished'] ?? false;
                    $cancelled = $batch['cancelled'] ?? false;
                    $failed = (int) ($batch['failed'] ?? 0);
                @endphp

                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium">
                            @if($cancelled) Cancelled
                            @elseif($finished) Completed
                            @else Processing…
                            @endif
                        </span>
                        <span class="tabular-nums text-gray-500">
                            {{ $batch['processed'] ?? 0 }} / {{ $batch['total'] ?? 0 }} batches
                            ({{ $pct }}%)
                        </span>
                    </div>

                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            class="h-full rounded-full transition-all duration-500 {{ $cancelled ? 'bg-warning-500' : ($failed ? 'bg-danger-500' : 'bg-primary-500') }}"
                            style="width: {{ $finished ? 100 : $pct }}%"
                        ></div>
                    </div>

                    @if($failed)
                        <p class="text-sm text-danger-600">{{ $failed }} batch(es) failed — check the queue/logs.</p>
                    @endif

                    @if(! $finished && ! $cancelled)
                        <x-filament::button
                            type="button"
                            size="sm"
                            color="gray"
                            wire:click="cancelRegenerate"
                        >
                            Cancel
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
