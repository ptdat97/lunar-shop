<x-filament-panels::page>
    {{-- Upload --}}
    <form wire:submit="uploadFiles" class="space-y-4">
        {{ $this->uploadForm }}

        <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
            Upload
        </x-filament::button>
    </form>

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-4 border-t border-gray-200 pt-4 dark:border-white/10">
        <div>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Type</label>
            <select
                wire:model.live="typeFilter"
                class="mt-1 block rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-white/10"
            >
                <option value="">All types</option>
                <option value="image">Images</option>
                <option value="video">Videos</option>
                <option value="document">Documents</option>
            </select>
        </div>

        @if (count($this->folders))
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Folder</label>
                <select
                    wire:model.live="folderFilter"
                    class="mt-1 block rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-white/10"
                >
                    <option value="">All folders</option>
                    @foreach ($this->folders as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    {{-- Bulk toolbar --}}
    @if (! $this->files->isEmpty())
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button size="sm" color="gray" wire:click="toggleSelectPage">
                Select / deselect page
            </x-filament::button>

            @if (count($selected))
                <span class="text-sm text-gray-500">{{ count($selected) }} selected</span>
                <x-filament::button
                    size="sm"
                    color="danger"
                    icon="heroicon-m-trash"
                    wire:click="deleteSelected"
                    wire:confirm="Delete the {{ count($selected) }} selected file(s)? This cannot be undone."
                >
                    Delete selected
                </x-filament::button>
            @endif
        </div>
    @endif

    {{-- Grid --}}
    @if ($this->files->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-500 dark:border-white/10">
            No files yet. Upload some above to get started.
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            @foreach ($this->files as $file)
                @php
                    $media = $file->file;
                    $type = $media?->getCustomProperty('type') ?? 'document';
                    $url = $media?->getUrl();
                    $thumb = $type === 'image' && $media && in_array('thumb', array_keys($media->generated_conversions ?? []))
                        ? $media->getUrl('thumb')
                        : $url;
                @endphp
                <div
                    wire:key="file-{{ $file->id }}"
                    @class([
                        'group relative overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-gray-900',
                        'border-gray-200 dark:border-white/10' => ! in_array($file->id, $selected),
                        'border-primary-500 ring-2 ring-primary-500' => in_array($file->id, $selected),
                    ])
                >
                    {{-- Selection checkbox --}}
                    <label class="absolute left-1 top-1 z-10 cursor-pointer rounded-md bg-white/90 p-1 shadow dark:bg-gray-800/90">
                        <input
                            type="checkbox"
                            value="{{ $file->id }}"
                            wire:model.live="selected"
                            class="rounded border-gray-300 text-primary-600"
                        >
                    </label>

                    {{-- Preview --}}
                    <div class="flex aspect-square items-center justify-center bg-gray-50 dark:bg-gray-800">
                        @if ($type === 'image')
                            <img src="{{ $thumb }}" alt="{{ $media?->getCustomProperty('alt') }}" class="h-full w-full object-cover" loading="lazy">
                        @elseif ($type === 'video')
                            <video src="{{ $url }}" class="h-full w-full object-cover" muted preload="metadata"></video>
                        @else
                            <x-heroicon-o-document class="h-12 w-12 text-gray-400" />
                        @endif
                    </div>

                    {{-- Meta --}}
                    <div class="space-y-0.5 p-2">
                        <p class="truncate text-xs font-medium text-gray-700 dark:text-gray-200" title="{{ $media?->name }}">
                            {{ $media?->name }}
                        </p>
                        <p class="text-[11px] text-gray-400">{{ ucfirst($type) }} · {{ $media?->humanReadableSize }}</p>
                    </div>

                    {{-- Actions --}}
                    <div class="absolute right-1 top-1 flex gap-1 opacity-0 transition group-hover:opacity-100">
                        <button
                            type="button"
                            wire:click="startEdit({{ $file->id }})"
                            class="rounded-md bg-white/90 p-1 text-gray-700 shadow hover:bg-white dark:bg-gray-800/90 dark:text-gray-200"
                            title="Edit"
                        >
                            <x-heroicon-m-pencil-square class="h-4 w-4" />
                        </button>
                        <a
                            href="{{ $url }}"
                            target="_blank"
                            class="rounded-md bg-white/90 p-1 text-gray-700 shadow hover:bg-white dark:bg-gray-800/90 dark:text-gray-200"
                            title="Open"
                        >
                            <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" />
                        </a>
                        <button
                            type="button"
                            wire:click="deleteFile({{ $file->id }})"
                            wire:confirm="Delete this file? This cannot be undone."
                            class="rounded-md bg-white/90 p-1 text-danger-600 shadow hover:bg-white dark:bg-gray-800/90"
                            title="Delete"
                        >
                            <x-heroicon-m-trash class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div>
            {{ $this->files->links() }}
        </div>
    @endif

    {{-- Edit / update modal --}}
    <x-filament::modal id="edit-file" width="lg">
        <x-slot name="heading">Edit file</x-slot>

        <div class="space-y-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Name</label>
                <input
                    type="text"
                    wire:model="editName"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-white/10"
                >
                @error('editName') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Folder</label>
                <input
                    type="text"
                    wire:model="editFolder"
                    placeholder="e.g. banners, lookbooks"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-white/10"
                >
                <p class="mt-1 text-xs text-gray-400">Leave blank for no folder. Used only to group/filter files.</p>
                @error('editFolder') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Title</label>
                <input
                    type="text"
                    wire:model="editTitle"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-white/10"
                >
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Alt text</label>
                <input
                    type="text"
                    wire:model="editAlt"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-white/10"
                >
                <p class="mt-1 text-xs text-gray-400">Describe the image for accessibility &amp; SEO.</p>
            </div>

            <div class="border-t border-gray-200 pt-4 dark:border-white/10">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Replace file (optional)</label>
                <input
                    type="file"
                    wire:model="replaceUpload"
                    class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm dark:text-gray-300 dark:file:bg-gray-800"
                >
                <p class="mt-1 text-xs text-gray-400">
                    Upload a new file to replace this one. The asset id stays the same, so anywhere it is used updates automatically.
                </p>
                <div wire:loading wire:target="replaceUpload" class="mt-1 text-xs text-primary-600">Uploading…</div>
                @error('replaceUpload') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-slot name="footer">
            <x-filament::button wire:click="saveEdit" wire:loading.attr="disabled" wire:target="saveEdit,replaceUpload">
                Save
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
