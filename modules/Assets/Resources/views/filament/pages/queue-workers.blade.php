<x-filament-panels::page>
    {{-- Poll every 5s so worker status + queue depth stay live while the page is open. --}}
    <div wire:poll.5s="refreshStatus" class="space-y-6">

        {{-- Horizon not installed at all → scaling settings have no effect. --}}
        @unless($horizonInstalled)
            <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950/50 dark:text-warning-200">
                {{ __('admin.queue_workers.horizon_missing') }}
            </div>
        @endunless

        {{-- Worker status banner. --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4 text-sm
            {{ $workerAvailable
                ? 'border-success-300 bg-success-50 text-success-800 dark:border-success-700 dark:bg-success-950/50 dark:text-success-200'
                : 'border-warning-300 bg-warning-50 text-warning-800 dark:border-warning-700 dark:bg-warning-950/50 dark:text-warning-200' }}">
            <span>
                <strong>
                    @if($workerAvailable) {{ __('admin.queue_workers.worker_up') }}
                    @else {{ __('admin.queue_workers.worker_down') }}
                    @endif
                </strong>
                @unless($workerAvailable) {{ __('admin.queue_workers.worker_down_desc') }} @endunless
            </span>

            @if($horizonInstalled)
                <x-filament::button tag="a" href="{{ url($horizonPath) }}" target="_blank" size="sm" color="gray">
                    {{ __('admin.queue_workers.open_dashboard') }}
                </x-filament::button>
            @endif
        </div>

        {{-- Live queue workload + job counts. --}}
        @if($horizonInstalled)
            <x-filament::section>
                <x-slot name="heading">{{ __('admin.queue_workers.queue_status') }}</x-slot>

                @if(count($queues))
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                    <th class="py-1 pr-4 font-medium">{{ __('admin.queue_workers.queue') }}</th>
                                    <th class="py-1 pr-4 font-medium tabular-nums">{{ __('admin.queue_workers.pending') }}</th>
                                    <th class="py-1 pr-4 font-medium tabular-nums">{{ __('admin.queue_workers.wait') }}</th>
                                    <th class="py-1 font-medium tabular-nums">{{ __('admin.queue_workers.processes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($queues as $queue)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="py-1 pr-4 font-mono">{{ $queue['name'] }}</td>
                                        <td class="py-1 pr-4 tabular-nums">{{ $queue['length'] }}</td>
                                        <td class="py-1 pr-4 tabular-nums">{{ $queue['wait'] }}s</td>
                                        <td class="py-1 tabular-nums">{{ $queue['processes'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.queue_workers.no_queue_data') }}</p>
                @endif

                @if($recentJobs !== null || $failedJobs !== null)
                    <p class="mt-4 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                        {{ __('admin.queue_workers.recent_jobs', ['count' => $recentJobs ?? 0]) }} ·
                        <span class="{{ ($failedJobs ?? 0) > 0 ? 'text-danger-600 dark:text-danger-400' : '' }}">
                            {{ __('admin.queue_workers.failed_jobs', ['count' => $failedJobs ?? 0]) }}
                        </span>
                    </p>
                @endif
            </x-filament::section>
        @endif

        {{-- Editable scaling per supervisor. --}}
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button type="submit">
                    {{ __('admin.queue_workers.save') }}
                </x-filament::button>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('admin.queue_workers.save_note', ['terminate' => 'php&nbsp;artisan&nbsp;horizon:terminate']) }}
            </p>
        </form>
    </div>
</x-filament-panels::page>
