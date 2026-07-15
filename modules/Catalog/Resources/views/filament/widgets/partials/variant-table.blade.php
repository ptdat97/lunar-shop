{{-- Variant matrix table: wide grid (image / model / sku / prices / stock /
     weight / status) with a bulk-fill bar, first-option rowspan grouping, and
     per-variant image picker. Included by simple-variants.blade.php. --}}
@php
    use Modules\Catalog\Filament\Widgets\SimpleVariantsWidget;

    // Rows keyed by their first option value (e.g. Size), so we can rowspan the
    // first column. Guard: only group when there are ≥2 options AND every row
    // exposes a first value; otherwise fall back to a flat table.
    $firstOptionName = null;
    $canGroup = false;
    if (count($this->variants)) {
        $firstValues = collect($this->variants[0]['values'] ?? []);
        $firstOptionName = $firstValues->keys()->first();
        $optionCount = $firstValues->count();
        $canGroup = $optionCount >= 2 && $firstOptionName !== null;
    }

    // rowspan count per group (first index of each group renders the cell).
    $groupSpans = [];
    $groupSeen = [];
    if ($canGroup) {
        foreach ($this->variants as $i => $row) {
            $g = $row['values'][$firstOptionName] ?? '';
            $groupSpans[$g] = ($groupSpans[$g] ?? 0) + 1;
        }
    }

    $statuses = SimpleVariantsWidget::STATUSES;
    $bulkFields = SimpleVariantsWidget::BULK_FIELDS;
    $currency = \Lunar\Models\Currency::getDefault();
@endphp

<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
  <div class="fi-ta-header flex flex-col gap-3 p-4 sm:px-6 sm:flex-row sm:items-center sm:justify-between">
    <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
      {{ __('admin.variants.variants_title') }}
    </h3>

    {{-- Bulk-fill: pick a column, type a value, apply to every row. --}}
    @if(count($this->variants) > 1)
      <div class="flex flex-wrap items-center gap-1">
        <x-filament::input.wrapper class="!w-40">
          <x-filament::input.select wire:model="bulkField">
            @foreach($bulkFields as $field)
              <option value="{{ $field }}">{{ __('admin.variants.'.$field) }}</option>
            @endforeach
          </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper class="!w-40">
          <x-filament::input type="text"
              wire:model="bulkValue"
              wire:keydown.enter.prevent="applyBulkFill"
              placeholder="{{ __('admin.variants.bulk_value_placeholder') }}" />
        </x-filament::input.wrapper>

        <x-filament::button color="primary" size="sm" type="button" wire:click="applyBulkFill">
          {{ __('admin.variants.bulk_apply_all') }}
        </x-filament::button>
      </div>
    @endif
  </div>

  <div class="fi-ta-content divide-y divide-gray-200 overflow-x-auto dark:divide-white/10 dark:border-t-white/10">
    @if(count($this->variants))
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 dark:bg-white/5 text-start">
            @foreach([
              $firstOptionName ?? __('admin.variants.variant'),
              __('admin.variants.attributes'),
              __('admin.variants.image'),
              __('admin.variants.model'),
              __('admin.variants.sku'),
              __('admin.variants.price'),
              __('admin.variants.compare_price'),
              __('admin.variants.cost_price'),
              __('admin.variants.stock'),
              __('admin.variants.weight'),
              __('admin.variants.status'),
              '',
            ] as $heading)
              <th class="px-3 py-2 text-start font-semibold text-gray-950 dark:text-white whitespace-nowrap">{{ $heading }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5 align-top">
          @foreach($this->variants as $permutationIndex => $permutation)
            @php
              $values = $permutation['values'] ?? [];
              $firstVal = $canGroup ? ($values[$firstOptionName] ?? '') : null;
              $isGroupHead = $canGroup && (($groupSeen[$firstVal] ?? false) === false);
              if ($canGroup) { $groupSeen[$firstVal] = true; }
              // Remaining option values (Color, Material…) after the first.
              $rest = $canGroup
                ? collect($values)->except($firstOptionName)
                : collect($values);
            @endphp
            <tr wire:key="permutation_{{ $permutation['key'] }}">
              {{-- First option (rowspan-grouped) --}}
              @if(! $canGroup)
                <td class="px-3 py-3 whitespace-nowrap">
                  @foreach($values as $on => $vn)
                    <small class="block"><strong>{{ $on }}:</strong> {{ $vn }}</small>
                  @endforeach
                </td>
              @elseif($isGroupHead)
                <td class="px-3 py-3 font-medium text-gray-950 dark:text-white whitespace-nowrap align-middle border-e border-gray-200 dark:border-white/10"
                    rowspan="{{ $groupSpans[$firstVal] }}">
                  {{ $firstVal }}
                </td>
              @endif

              {{-- Remaining attributes (Color, Material…) --}}
              <td class="px-3 py-3 whitespace-nowrap">
                @forelse($rest as $on => $vn)
                  <small class="block">{{ $vn }}</small>
                @empty
                  <span class="text-gray-400">—</span>
                @endforelse
                @if(! $permutation['variant_id'])
                  <x-filament::badge color="info" size="sm">{{ __('admin.variants.new') }}</x-filament::badge>
                @endif
              </td>

              {{-- Image (per-variant, from product gallery) --}}
              <td class="px-3 py-3">
                @php($imgUrl = $this->rowImageUrl($permutationIndex))
                <button type="button" class="block"
                    x-on:click="$wire.mountAction('pickVariantImage', { rowIndex: {{ $permutationIndex }} })">
                  @if($imgUrl)
                    <img src="{{ $imgUrl }}" alt=""
                         class="h-12 w-12 rounded-lg object-cover ring-1 ring-gray-950/10 dark:ring-white/10" />
                  @else
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg border border-dashed border-gray-300 text-gray-400 dark:border-white/20">+</span>
                  @endif
                </button>
                @if(count($permutation['image_ids'] ?? []) > 1)
                  <span class="text-xs text-gray-400">+{{ count($permutation['image_ids']) - 1 }}</span>
                @endif
              </td>

              {{-- Model --}}
              <td class="px-3 py-3 w-32">
                <x-filament::input.wrapper>
                  <x-filament::input type="text" wire:model="variants.{{ $permutationIndex }}.model" />
                </x-filament::input.wrapper>
              </td>

              {{-- SKU --}}
              <td class="px-3 py-3 w-36">
                <x-filament::input.wrapper>
                  <x-filament::input type="text" wire:model="variants.{{ $permutationIndex }}.sku" />
                </x-filament::input.wrapper>
              </td>

              {{-- Price --}}
              <td class="px-3 py-3 w-28">
                <x-filament::input.wrapper>
                  <x-filament::input type="number" step="any" min="0" wire:model="variants.{{ $permutationIndex }}.price" />
                </x-filament::input.wrapper>
              </td>

              {{-- Compare price (giá gốc) --}}
              <td class="px-3 py-3 w-28">
                <x-filament::input.wrapper>
                  <x-filament::input type="number" step="any" min="0" wire:model="variants.{{ $permutationIndex }}.compare_price" />
                </x-filament::input.wrapper>
              </td>

              {{-- Cost price (giá vốn) --}}
              <td class="px-3 py-3 w-28">
                <x-filament::input.wrapper>
                  <x-filament::input type="number" step="any" min="0" wire:model="variants.{{ $permutationIndex }}.cost_price" />
                </x-filament::input.wrapper>
              </td>

              {{-- Stock --}}
              <td class="px-3 py-3 w-24">
                <x-filament::input.wrapper>
                  <x-filament::input type="number" step="1" min="0" wire:model="variants.{{ $permutationIndex }}.stock" />
                </x-filament::input.wrapper>
              </td>

              {{-- Weight --}}
              <td class="px-3 py-3 w-24">
                <x-filament::input.wrapper>
                  <x-filament::input type="number" step="any" min="0" wire:model="variants.{{ $permutationIndex }}.weight" />
                </x-filament::input.wrapper>
              </td>

              {{-- Status --}}
              <td class="px-3 py-3 w-32">
                <x-filament::input.wrapper>
                  <x-filament::input.select wire:model="variants.{{ $permutationIndex }}.status">
                    @foreach($statuses as $status)
                      <option value="{{ $status }}">{{ __('admin.variants.status_'.$status) }}</option>
                    @endforeach
                  </x-filament::input.select>
                </x-filament::input.wrapper>
              </td>

              {{-- Actions --}}
              <td class="px-3 py-3 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  @if($permutation['variant_id'])
                    <x-filament::link :href="$this->getVariantLink($permutation['variant_id'])" size="sm">
                      {{ __('admin.variants.edit') }}
                    </x-filament::link>
                  @endif
                  <button type="button" wire:click="removeVariant('{{ $permutationIndex }}')"
                          class="text-sm font-semibold text-danger-600 hover:underline">
                    {{ __('admin.variants.delete') }}
                  </button>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <x-filament-tables::empty-state
        :heading="__('admin.variants.empty')"
        icon="lucide-shapes"
      ></x-filament-tables::empty-state>
    @endif
  </div>
</div>
