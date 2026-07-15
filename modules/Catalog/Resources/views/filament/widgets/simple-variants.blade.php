{{-- Simplified Size/Color variant builder (SimpleVariantsWidget). --}}
<x-filament-widgets::widget>
  <div class="space-y-4">

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
      <div class="fi-ta-header flex flex-col gap-1 p-4 sm:px-6">
        <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
          {{ __('admin.variants.options_title') }}
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          {{ __('admin.variants.options_hint') }}
        </p>
      </div>

      @php
        $sizeHandle = \Modules\Catalog\Filament\Widgets\SimpleVariantsWidget::sizeHandle();
        $colorHandle = \Modules\Catalog\Filament\Widgets\SimpleVariantsWidget::colorHandle();
        $seedHandles = [$sizeHandle, $colorHandle];
      @endphp

      <div class="divide-y divide-gray-200 dark:divide-white/10">
        @foreach($this->configuredOptions as $optionIndex => $option)
          @php($handle = $option['handle'] ?? null)
          @php($isSeed = in_array($handle, $seedHandles, true))

          @if($handle === $colorHandle)
            {{-- ============ COLOR: name + hex picker + swatch image ============ --}}
            <div class="p-4 sm:px-6 space-y-3" wire:key="option_{{ $optionIndex }}_color">
              <div class="grid gap-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                  {{ __('admin.variants.color_label') }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ __('admin.variants.color_hint') }}
                </span>
              </div>

              <div class="space-y-2">
                @foreach($option['option_values'] as $valueIndex => $value)
                  @php($pendingUpload = $this->colorImages[$valueIndex] ?? null)
                  <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 p-2 dark:border-white/10"
                       wire:key="color_{{ $valueIndex }}">

                    {{-- enable toggle --}}
                    <x-filament::input.checkbox
                        wire:click="toggleOptionValue({{ $optionIndex }}, {{ $valueIndex }})"
                        @checked($value['enabled'] ?? false)
                        title="{{ __('admin.variants.enabled') }}"
                    />

                    {{-- swatch preview: image beats hex --}}
                    @if($pendingUpload)
                      <img src="{{ $pendingUpload->temporaryUrl() }}" alt=""
                           class="h-8 w-8 rounded-full object-cover ring-1 ring-gray-950/10" />
                    @elseif($value['image_url'] ?? null)
                      <img src="{{ $value['image_url'] }}" alt=""
                           class="h-8 w-8 rounded-full object-cover ring-1 ring-gray-950/10" />
                    @else
                      <span class="h-8 w-8 rounded-full ring-1 ring-gray-950/10 dark:ring-white/20"
                            style="background: {{ ($value['hex'] ?? null) ?: '#e5e7eb' }}"></span>
                    @endif

                    {{-- name --}}
                    <x-filament::input.wrapper class="!w-40">
                      <x-filament::input
                          type="text"
                          wire:model.blur="configuredOptions.{{ $optionIndex }}.option_values.{{ $valueIndex }}.value"
                      />
                    </x-filament::input.wrapper>

                    {{-- hex picker --}}
                    <input type="color"
                        wire:model="configuredOptions.{{ $optionIndex }}.option_values.{{ $valueIndex }}.hex"
                        class="h-9 w-12 cursor-pointer rounded border border-gray-300 bg-white p-0.5 dark:border-white/20 dark:bg-gray-800"
                        title="{{ __('admin.variants.pick_color') }}" />

                    {{-- swatch image upload --}}
                    <label class="cursor-pointer text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                      <input type="file" accept="image/*" class="hidden"
                             wire:model="colorImages.{{ $valueIndex }}" />
                      {{ ($value['image_url'] ?? null) || $pendingUpload ? __('admin.variants.change_image') : __('admin.variants.swatch_image') }}
                    </label>

                    {{-- pick from media library --}}
                    {{ ($this->pickSwatchAction)(['optionIndex' => $optionIndex, 'valueIndex' => $valueIndex]) }}

                    <div wire:loading wire:target="colorImages.{{ $valueIndex }}"
                         class="text-xs text-gray-400">…</div>

                    @if(($value['image_url'] ?? null) || $pendingUpload)
                      <button type="button"
                          wire:click="removeSwatchImage({{ $optionIndex }}, {{ $valueIndex }})"
                          class="text-sm font-medium text-danger-600 hover:underline">
                        {{ __('admin.variants.remove_image') }}
                      </button>
                    @endif

                    @if(empty($value['id']))
                      <button type="button"
                          wire:click="removeUnsavedValue({{ $optionIndex }}, {{ $valueIndex }})"
                          class="ms-auto text-sm font-semibold text-danger-600 hover:underline">
                        {{ __('admin.variants.delete') }}
                      </button>
                    @endif
                  </div>
                @endforeach

                <div class="flex items-center gap-1 pt-1">
                  <x-filament::input.wrapper class="!w-40">
                    <x-filament::input
                        type="text"
                        wire:model="newValues.{{ $optionIndex }}"
                        wire:keydown.enter.prevent="addValueToOption({{ $optionIndex }})"
                        placeholder="{{ __('admin.variants.add_color_placeholder') }}"
                    />
                  </x-filament::input.wrapper>
                  <x-filament::button color="gray" size="sm" type="button" wire:click="addValueToOption({{ $optionIndex }})">
                    {{ __('admin.variants.add') }}
                  </x-filament::button>
                </div>
              </div>
            </div>

          @else
            {{-- ============ PLAIN option (Size + any custom): toggle chips ============ --}}
            <div class="p-4 sm:px-6 space-y-3" wire:key="option_{{ $optionIndex }}_plain">
              <div class="flex items-center justify-between gap-2">
                @if($isSeed)
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ $handle === $sizeHandle ? __('admin.variants.size_label') : $option['value'] }}
                  </span>
                @else
                  {{-- custom option: name is editable, and the whole option can be removed --}}
                  <x-filament::input.wrapper class="!w-48">
                    <x-filament::input
                        type="text"
                        wire:model.blur="configuredOptions.{{ $optionIndex }}.value"
                        placeholder="{{ __('admin.variants.option_name_placeholder') }}"
                    />
                  </x-filament::input.wrapper>
                  <button type="button"
                      wire:click="removeOptionFromProduct({{ $optionIndex }})"
                      class="text-sm font-semibold text-danger-600 hover:underline">
                    {{ __('admin.variants.remove_option') }}
                  </button>
                @endif
              </div>

              <div class="flex flex-wrap items-center gap-2">
                @foreach($option['option_values'] as $valueIndex => $value)
                  <span class="inline-flex items-stretch" wire:key="val_{{ $optionIndex }}_{{ $valueIndex }}">
                    <button type="button"
                        wire:click="toggleOptionValue({{ $optionIndex }}, {{ $valueIndex }})"
                        @class([
                          'px-3 py-1.5 text-sm font-medium transition rounded-lg',
                          'rounded-e-none' => empty($value['id']),
                          'bg-primary-600 text-white shadow-sm' => $value['enabled'],
                          'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/20' => ! $value['enabled'],
                        ])>
                      {{ $value['value'] }}
                    </button>
                    @if(empty($value['id']))
                      {{-- unsaved value: can still be discarded --}}
                      <button type="button"
                          wire:click="removeUnsavedValue({{ $optionIndex }}, {{ $valueIndex }})"
                          class="rounded-e-lg bg-gray-200 px-1.5 text-gray-500 hover:bg-danger-100 hover:text-danger-600 dark:bg-white/20 dark:text-gray-300"
                          title="{{ __('admin.variants.delete') }}">
                        &times;
                      </button>
                    @endif
                  </span>
                @endforeach

                <div class="flex items-center gap-1">
                  <x-filament::input.wrapper class="!w-32">
                    <x-filament::input
                        type="text"
                        wire:model="newValues.{{ $optionIndex }}"
                        wire:keydown.enter.prevent="addValueToOption({{ $optionIndex }})"
                        placeholder="{{ $handle === $sizeHandle ? __('admin.variants.add_size_placeholder') : __('admin.variants.add_value_placeholder') }}"
                    />
                  </x-filament::input.wrapper>
                  <x-filament::button color="gray" size="sm" type="button" wire:click="addValueToOption({{ $optionIndex }})">
                    {{ __('admin.variants.add') }}
                  </x-filament::button>
                </div>
              </div>
            </div>
          @endif

        @endforeach

        {{-- ============ Add a new (shared) option ============ --}}
        <div class="p-4 sm:px-6" wire:key="add_option">
          <div class="flex items-center gap-1">
            <x-filament::input.wrapper class="!w-48">
              <x-filament::input
                  type="text"
                  wire:model="newOption"
                  wire:keydown.enter.prevent="addOption"
                  placeholder="{{ __('admin.variants.add_option_placeholder') }}"
              />
            </x-filament::input.wrapper>
            <x-filament::button color="gray" size="sm" type="button" icon="heroicon-m-plus" wire:click="addOption">
              {{ __('admin.variants.add_option') }}
            </x-filament::button>
          </div>
        </div>
      </div>
    </div>

    {{-- ============ Permutation table (same as stock widget) ============ --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
      <div class="fi-ta-header flex flex-col gap-3 p-4 sm:px-6 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
          {{ __('admin.variants.variants_title') }}
        </h3>

        {{-- Bulk-fill: apply one price / stock to every variant row at once. --}}
        @if(count($this->variants) > 1)
          <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1">
              <x-filament::input.wrapper class="!w-32">
                <x-filament::input type="number" step="any" min="0"
                    wire:model="bulkPrice"
                    wire:keydown.enter.prevent="applyBulkPrice"
                    placeholder="{{ __('admin.variants.bulk_price_placeholder') }}" />
              </x-filament::input.wrapper>
              <x-filament::button color="gray" size="sm" type="button" wire:click="applyBulkPrice">
                {{ __('admin.variants.bulk_apply') }}
              </x-filament::button>
            </div>
            <div class="flex items-center gap-1">
              <x-filament::input.wrapper class="!w-32">
                <x-filament::input type="number" step="1" min="0"
                    wire:model="bulkStock"
                    wire:keydown.enter.prevent="applyBulkStock"
                    placeholder="{{ __('admin.variants.bulk_stock_placeholder') }}" />
              </x-filament::input.wrapper>
              <x-filament::button color="gray" size="sm" type="button" wire:click="applyBulkStock">
                {{ __('admin.variants.bulk_apply') }}
              </x-filament::button>
            </div>
          </div>
        @endif
      </div>
      <div class="fi-ta-content divide-y divide-gray-200 overflow-x-auto dark:divide-white/10 dark:border-t-white/10">
        @if(count($this->variants))
          <x-filament-tables::table>
            <thead class="divide-y divide-gray-200 dark:divide-white/5">
              <tr class="bg-gray-50 dark:bg-white/5">
                @if($this->hasNewVariants)
                  <x-filament-tables::header-cell></x-filament-tables::header-cell>
                @endif
                <x-filament-tables::header-cell>
                  <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.variants.variant') }}</span>
                </x-filament-tables::header-cell>
                <x-filament-tables::header-cell>
                  <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.variants.sku') }}</span>
                </x-filament-tables::header-cell>
                <x-filament-tables::header-cell>
                  <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.variants.price') }}</span>
                </x-filament-tables::header-cell>
                <x-filament-tables::header-cell>
                  <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.variants.stock') }}</span>
                </x-filament-tables::header-cell>
                <x-filament-tables::header-cell></x-filament-tables::header-cell>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
              @foreach($this->variants as $permutationIndex => $permutation)
                <x-filament-tables::row wire:key="permutation_{{ $permutation['key'] }}">
                  @if($this->hasNewVariants)
                    <x-filament-tables::cell>
                      <div class="px-3 py-4">
                        @if(!$permutation['variant_id'])
                          <x-filament::badge color="info">{{ __('admin.variants.new') }}</x-filament::badge>
                        @endif
                      </div>
                    </x-filament-tables::cell>
                  @endif
                  <x-filament-tables::cell>
                    <div class="grid w-full gap-y-1 px-3 py-4">
                      <span class="flex flex-col text-sm leading-6 text-gray-950 dark:text-white">
                        @foreach($permutation['values'] as $optionName => $valueName)
                          <small><strong>{{ $optionName }}:</strong> {{ $valueName }}</small>
                        @endforeach
                      </span>
                    </div>
                  </x-filament-tables::cell>
                  <x-filament-tables::cell>
                    <div class="grid w-full gap-y-1 px-3 py-4">
                      <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="variants.{{ $permutationIndex }}.sku" />
                      </x-filament::input.wrapper>
                    </div>
                  </x-filament-tables::cell>
                  <x-filament-tables::cell class="w-32">
                    <div class="grid w-full gap-y-1 px-3 py-4">
                      <x-filament::input.wrapper>
                        <x-filament::input type="number" step="any" min="0" wire:model="variants.{{ $permutationIndex }}.price" />
                      </x-filament::input.wrapper>
                    </div>
                  </x-filament-tables::cell>
                  <x-filament-tables::cell class="w-32">
                    <div class="grid w-full gap-y-1 px-3 py-4">
                      <x-filament::input.wrapper>
                        <x-filament::input type="number" step="1" min="0" wire:model="variants.{{ $permutationIndex }}.stock" />
                      </x-filament::input.wrapper>
                    </div>
                  </x-filament-tables::cell>
                  <x-filament-tables::cell>
                    <div class="flex items-center space-x-2 px-3">
                      @if($permutation['variant_id'])
                        <x-filament::link :href="$this->getVariantLink($permutation['variant_id'])">
                          {{ __('admin.variants.edit') }}
                        </x-filament::link>
                      @endif
                      <button type="button" wire:click="removeVariant('{{ $permutationIndex }}')"
                              class="text-sm font-semibold text-danger-600 hover:underline">
                        {{ __('admin.variants.delete') }}
                      </button>
                    </div>
                  </x-filament-tables::cell>
                </x-filament-tables::row>
              @endforeach
            </tbody>
          </x-filament-tables::table>
        @else
          <x-filament-tables::empty-state
            :heading="__('admin.variants.empty')"
            icon="lucide-shapes"
          ></x-filament-tables::empty-state>
        @endif
      </div>
    </div>

    <div class="flex">
      {{ $this->saveVariantsAction }}
    </div>
  </div>

  <x-filament-actions::modals />
</x-filament-widgets::widget>
