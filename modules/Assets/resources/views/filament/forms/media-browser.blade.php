{{--
    MediaBrowser field: the library grid shown inside the MediaPicker modal.
    Search + folder filter, paginated thumbnails, inline upload. All data comes
    from the field object (MediaBrowser) — no service resolved here (§7).
--}}
@php
    $files = $getFiles();
    $folders = $getFolders();
    $selected = $getSelected();
    $isMultiple = $isMultiple();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="space-y-4">
        {{-- Filters --}}
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-48 flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ __('admin.media.search') }}
                </label>
                <input
                    type="search"
                    wire:model.live.debounce.400ms="{{ $statePath }}.search"
                    placeholder="{{ __('admin.media.search_placeholder') }}"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900"
                >
            </div>

            @if (count($folders))
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('admin.media.folder') }}
                    </label>
                    <select
                        wire:model.live="{{ $statePath }}.folder"
                        class="mt-1 block rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900"
                    >
                        <option value="">{{ __('admin.media.all_folders') }}</option>
                        @foreach ($folders as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="ms-auto flex items-center gap-2">
                <span class="text-sm text-gray-500">
                    {{ __('admin.media.selected_count', ['count' => count($selected)]) }}
                </span>
                {{ $getAction('upload') }}
            </div>
        </div>

        {{-- Grid --}}
        @if ($files->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-500 dark:border-white/10">
                {{ __('admin.media.empty') }}
            </div>
        @else
            <div class="grid max-h-[26rem] grid-cols-2 gap-3 overflow-y-auto p-1 sm:grid-cols-4 lg:grid-cols-6">
                @foreach ($files as $asset)
                    @php
                        $preview = $previewFor($asset);
                        $isSelected = in_array($asset->id, $selected, true);
                    @endphp

                    @continue (! $preview)

                    <div
                        wire:key="{{ $statePath }}-browse-{{ $asset->id }}"
                        wire:click="{{ $getToggleHandler($asset->id) }}"
                        role="button"
                        tabindex="0"
                        @class([
                            'group relative cursor-pointer overflow-hidden rounded-xl border bg-white text-left shadow-sm transition dark:bg-gray-900',
                            'border-gray-200 hover:border-gray-300 dark:border-white/10' => ! $isSelected,
                            'border-primary-500 ring-2 ring-primary-500' => $isSelected,
                        ])
                    >
                        <div class="flex aspect-square items-center justify-center bg-gray-50 dark:bg-gray-800">
                            @if ($preview['type'] === 'image')
                                <img
                                    src="{{ $preview['thumb'] }}"
                                    alt="{{ $preview['alt'] }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                >
                            @elseif ($preview['type'] === 'video')
                                <video src="{{ $preview['url'] }}" class="h-full w-full object-cover" muted preload="metadata"></video>
                            @else
                                <x-heroicon-o-document class="h-10 w-10 text-gray-400" />
                            @endif
                        </div>

                        <div class="space-y-0.5 p-2">
                            <p class="truncate text-xs font-medium text-gray-700 dark:text-gray-200" title="{{ $preview['name'] }}">
                                {{ $preview['name'] }}
                            </p>
                            <p class="text-[11px] text-gray-400">
                                {{ ucfirst($preview['type']) }} · {{ $preview['size'] }}
                            </p>
                        </div>

                        @if ($isSelected)
                            <span class="absolute left-1 top-1 rounded-full bg-primary-600 p-1 text-white shadow">
                                <x-heroicon-m-check class="h-3 w-3" />
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if ($files->hasPages())
                <div class="flex items-center justify-between gap-3 border-t border-gray-200 pt-3 dark:border-white/10">
                    <span class="text-sm text-gray-500">
                        {{ __('admin.media.page_of', ['page' => $files->currentPage(), 'last' => $files->lastPage()]) }}
                    </span>

                    <div class="flex gap-2">
                        @unless ($files->onFirstPage())
                            {{ $getAction('page')(['page' => $files->currentPage() - 1])->label(__('admin.media.previous')) }}
                        @endunless

                        @if ($files->hasMorePages())
                            {{ $getAction('page')(['page' => $files->currentPage() + 1])->label(__('admin.media.next')) }}
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-dynamic-component>
