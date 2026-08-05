{{--
    MediaPicker field: shows the picked library file(s) as thumbnails with
    remove/reorder controls, plus the button that opens the library modal.
    All data comes from the field object (MediaPickerField) — no service is
    resolved here (coding standards §7).
--}}
@php
    $previews = $getPreviews();
    $isMultiple = $isMultiple();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="space-y-3">
        @if (count($previews))
            <div @class([
                'grid gap-3',
                'grid-cols-2 sm:grid-cols-4 lg:grid-cols-6' => $isMultiple,
                'grid-cols-1 sm:max-w-xs' => ! $isMultiple,
            ])>
                @foreach ($previews as $index => $preview)
                    <div
                        wire:key="{{ $statePath }}-{{ $preview['id'] }}"
                        class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
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

                        {{-- Hover controls: reorder (multiple only) + remove. --}}
                        <div class="absolute right-1 top-1 flex gap-1 opacity-0 transition group-hover:opacity-100">
                            @if ($isMultiple)
                                @if ($index > 0)
                                    {{ $getAction('move')(['id' => $preview['id'], 'offset' => -1]) }}
                                @endif

                                @if ($index < count($previews) - 1)
                                    {{ $getAction('move')(['id' => $preview['id'], 'offset' => 1]) }}
                                @endif
                            @endif

                            {{ $getAction('remove')(['id' => $preview['id']]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10">
                {{ $getPlaceholder() ?? __('admin.media.pick') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            {{ $getAction('browseLibrary') }}
        </div>
    </div>
</x-dynamic-component>
