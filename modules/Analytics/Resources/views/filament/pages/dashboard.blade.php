<x-filament-panels::page>
    {{-- Headline KPIs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => 'Revenue', 'value' => $stats['revenue'], 'icon' => 'heroicon-o-banknotes'],
            ['label' => 'Paid orders', 'value' => $stats['orders'], 'icon' => 'heroicon-o-shopping-bag'],
            ['label' => 'Avg. order value', 'value' => $stats['aov'], 'icon' => 'heroicon-o-calculator'],
            ['label' => 'Products', 'value' => $stats['products'], 'icon' => 'heroicon-o-squares-2x2'],
        ] as $stat)
            <div class="fi-wi-stats-overview-stat rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</span>
                    <x-filament::icon :icon="$stat['icon']" class="h-5 w-5 text-gray-400" />
                </div>
                <div class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    {{ $stat['value'] }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- Monthly revenue trend --}}
    <x-filament::section>
        <x-slot name="heading">Revenue — last {{ count($monthly) }} months</x-slot>

        <div class="flex items-end gap-3" style="height: 12rem;">
            @php($peak = $this->peakRevenue())
            @foreach($monthly as $row)
                <div class="flex flex-1 flex-col items-center justify-end gap-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['formatted'] }}</span>
                    <div
                        class="w-full rounded-t-md bg-primary-500"
                        style="height: {{ max(2, (int) round($row['revenue'] / $peak * 160)) }}px;"
                        title="{{ $row['orders'] }} orders"
                    ></div>
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $row['label'] }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Recent orders --}}
        <x-filament::section>
            <x-slot name="heading">Recent orders</x-slot>

            @if($recent->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No orders yet.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($recent as $order)
                        <li class="flex items-center justify-between gap-4 py-2 text-sm">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $order->reference ?? '#'.$order->id }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $order->status }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $order->total?->formatted() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        {{-- Best sellers --}}
        <x-filament::section>
            <x-slot name="heading">Best sellers</x-slot>

            @if(empty($topProducts))
                <p class="text-sm text-gray-500 dark:text-gray-400">No sales yet.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($topProducts as $product)
                        <li class="flex items-center justify-between gap-4 py-2 text-sm">
                            <span class="truncate font-medium text-gray-900 dark:text-white">{{ $product['name'] }}</span>
                            <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $product['quantity'] }} sold</span>
                            <span class="shrink-0 font-semibold text-gray-900 dark:text-white">{{ $product['formatted'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
