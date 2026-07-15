{{-- Marketplace-style variant builder (SimpleVariantsWidget): a "multiple
     variants" toggle, the option designer and the variant matrix table.
     The two big sections are split into partials to stay under 300 lines. --}}
<x-filament-widgets::widget>
  <div class="space-y-4">

    {{-- ============ Enable multiple variants toggle ============ --}}
    <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:px-6">
      <x-filament::input.checkbox wire:model.live="multipleEnabled" id="multiple-enabled" />
      <label for="multiple-enabled" class="text-sm font-semibold text-gray-950 dark:text-white cursor-pointer">
        {{ __('admin.variants.enable_multiple') }}
      </label>
    </div>

    @if($this->multipleEnabled)
      @include('catalog-admin::filament.widgets.partials.option-designer')
      @include('catalog-admin::filament.widgets.partials.variant-table')

      <div class="flex">
        {{ $this->saveVariantsAction }}
      </div>
    @else
      {{-- Single product: SKU/price/stock are edited on the "Chi tiết" tab. --}}
      <div class="rounded-xl bg-white p-4 text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10 sm:px-6">
        {{ __('admin.variants.single_product_hint') }}

        <div class="mt-3">
          {{ $this->saveVariantsAction }}
        </div>
      </div>
    @endif
  </div>

  <x-filament-actions::modals />
</x-filament-widgets::widget>
