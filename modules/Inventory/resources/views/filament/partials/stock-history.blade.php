{{-- Recent stock movements for one variant (StockOverview history modal). --}}
<div class="overflow-x-auto">
  @if($movements->isEmpty())
    <p class="p-4 text-sm text-gray-500 dark:text-gray-400">
      {{ __('admin.inventory.history_empty') }}
    </p>
  @else
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-200 text-start dark:border-white/10">
          @foreach([
            __('admin.inventory.history_time'),
            __('admin.inventory.history_type'),
            __('admin.inventory.history_change'),
            __('admin.inventory.history_after'),
            __('admin.inventory.reason'),
            __('admin.inventory.history_by'),
          ] as $heading)
            <th class="px-3 py-2 text-start font-semibold text-gray-950 dark:text-white whitespace-nowrap">{{ $heading }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-white/5">
        @foreach($movements as $m)
          <tr>
            <td class="px-3 py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">
              {{ $m->created_at?->format('d/m/Y H:i') }}
            </td>
            <td class="px-3 py-2">
              <x-filament::badge size="sm" :color="match($m->type->value) {
                'sale' => 'info',
                'release' => 'success',
                'adjustment','manual' => 'warning',
                'restock' => 'primary',
                default => 'gray',
              }">{{ $m->type->label() }}</x-filament::badge>
            </td>
            <td @class([
              'px-3 py-2 font-medium whitespace-nowrap',
              'text-success-600' => $m->quantity > 0,
              'text-danger-600' => $m->quantity < 0,
            ])>
              {{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}
            </td>
            <td class="px-3 py-2 whitespace-nowrap text-gray-950 dark:text-white">{{ $m->stock_after }}</td>
            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
              {{ $m->reason ? (__('admin.inventory.reason_'.$m->reason) ?: $m->reason) : '—' }}
              @if($m->meta['note'] ?? null)
                <span class="text-xs">— {{ $m->meta['note'] }}</span>
              @endif
            </td>
            <td class="px-3 py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">
              {{ $m->causer?->name ?? __('admin.inventory.by_system') }}
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
