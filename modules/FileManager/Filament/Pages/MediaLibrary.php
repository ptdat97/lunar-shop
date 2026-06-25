<?php

namespace Modules\FileManager\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Livewire\WithFileUploads;
use Lunar\Models\Asset;
use Modules\FileManager\Services\FileManager;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Gallery-style media library built on Lunar Assets + Spatie MediaLibrary.
 * Upload images/videos/documents, browse them as a thumbnail grid, edit
 * metadata (name/alt/title/folder), replace files, filter and bulk-delete.
 * Storage logic is delegated to the FileManager service.
 */
class MediaLibrary extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    public static function getNavigationLabel(): string
    {
        return __('admin.media.library');
    }

    public function getTitle(): string
    {
        return __('admin.media.library');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.content');
    }

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'media-library';

    protected static string $view = 'filemanager::filament.pages.media-library';

    /** @var array<string, mixed> */
    public array $uploadData = [];

    /** Active filters bound to the toolbar. */
    public ?string $typeFilter = null;

    public ?string $folderFilter = null;

    /** Edit-modal state (keyed by Asset id). */
    public ?int $editingId = null;

    public ?string $editName = null;

    public ?string $editAlt = null;

    public ?string $editTitle = null;

    public ?string $editFolder = null;

    /** Optional replacement upload (Livewire temporary file). */
    public $replaceUpload = null;

    /** Selected Asset IDs for bulk actions. @var array<int, int> */
    public array $selected = [];

    public function mount(): void
    {
        $this->uploadForm->fill();
    }

    /**
     * Register the page's forms by name so Filament resolves `uploadForm`
     * as a Form (and the infolist discovery doesn't probe it as an infolist).
     *
     * @return array<int, string>
     */
    protected function getForms(): array
    {
        return ['uploadForm'];
    }

    /**
     * Upload form shown at the top of the page.
     */
    public function uploadForm(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('files')
                    ->label(__('admin.media.upload'))
                    ->multiple()
                    ->acceptedFileTypes([
                        'image/*',
                        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
                        'application/pdf',
                    ])
                    ->maxSize(51200) // 50 MB
                    ->storeFiles(false)
                    ->helperText('Images, video (mp4/webm) and PDF. Max 50MB each.'),
                TextInput::make('folder')
                    ->label(__('admin.media.folder'))
                    ->placeholder('e.g. banners, lookbooks')
                    ->maxLength(255),
            ])
            ->statePath('uploadData');
    }

    /**
     * Handle the upload form submission: store each file via the service.
     *
     * Note: named `uploadFiles` (not `upload`) to avoid shadowing Livewire's
     * reserved internal `upload()` file-transfer method.
     */
    public function uploadFiles(): void
    {
        $state = $this->uploadForm->getState();

        /** @var array<int, UploadedFile> $files */
        $files = array_filter(
            $state['files'] ?? [],
            fn ($f) => $f instanceof UploadedFile,
        );

        if (empty($files)) {
            Notification::make()->title(__('admin.media.no_files'))->warning()->send();

            return;
        }

        $manager = $this->manager();
        $folder = $state['folder'] ?? null;
        $count = 0;

        foreach ($files as $file) {
            $manager->store($file, $folder);
            $count++;
        }

        $this->uploadForm->fill();

        Notification::make()
            ->title(__('admin.media.uploaded', ['count' => $count]))
            ->success()
            ->send();
    }

    /**
     * Open the edit modal for an asset, preloading its metadata.
     */
    public function startEdit(int $id): void
    {
        $asset = Asset::with('file')->findOrFail($id);
        $media = $asset->file;

        $this->editingId = $asset->id;
        $this->editName = $media?->name;
        $this->editAlt = $media?->getCustomProperty('alt');
        $this->editTitle = $media?->getCustomProperty('title');
        $this->editFolder = $media?->getCustomProperty('folder');
        $this->replaceUpload = null;
        $this->dispatch('open-modal', id: 'edit-file');
    }

    /**
     * Persist changes from the edit modal: metadata, rename, folder and an
     * optional file replacement.
     */
    public function saveEdit(): void
    {
        if (! $this->editingId) {
            return;
        }

        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editFolder' => ['nullable', 'string', 'max:255'],
            'replaceUpload' => ['nullable', 'file', 'max:51200'],
        ]);

        $asset = Asset::findOrFail($this->editingId);
        $manager = $this->manager();

        // Replace the binary first (keeps the same Asset id / references).
        if ($this->replaceUpload instanceof UploadedFile) {
            $asset = $manager->replace($asset, $this->replaceUpload);
        }

        $manager->updateMeta($asset, [
            'name' => $this->editName,
            'alt' => $this->editAlt,
            'title' => $this->editTitle,
            'folder' => $this->editFolder,
        ]);

        $this->reset(['editingId', 'editName', 'editAlt', 'editTitle', 'editFolder', 'replaceUpload']);
        $this->dispatch('close-modal', id: 'edit-file');

        Notification::make()->title(__('admin.media.file_updated'))->success()->send();
    }

    /**
     * Delete a single asset.
     */
    public function deleteFile(int $id): void
    {
        $asset = Asset::find($id);

        if ($asset) {
            $this->manager()->delete($asset);
            $this->selected = array_values(array_diff($this->selected, [$id]));
            Notification::make()->title(__('admin.media.file_deleted'))->success()->send();
        }
    }

    /**
     * Delete every selected asset (bulk).
     */
    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            Notification::make()->title(__('admin.media.no_files'))->warning()->send();

            return;
        }

        $manager = $this->manager();
        $assets = Asset::whereIn('id', $this->selected)->get();

        foreach ($assets as $asset) {
            $manager->delete($asset);
        }

        $count = $assets->count();
        $this->selected = [];

        Notification::make()->title($count.' file(s) deleted')->success()->send();
    }

    /**
     * Select/deselect every file on the current page.
     */
    public function toggleSelectPage(): void
    {
        $ids = $this->files->pluck('id')->all();
        $allSelected = empty(array_diff($ids, $this->selected));

        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $ids))   // deselect page
            : array_values(array_unique([...$this->selected, ...$ids])); // select page
    }

    /**
     * Assets (with their media) for the grid, filtered + paginated.
     */
    public function getFilesProperty(): LengthAwarePaginator
    {
        return Asset::query()
            ->whereHas('file')
            ->with('file')
            ->when($this->typeFilter, fn ($q) => $q->whereHas(
                'file',
                fn ($m) => $m->where('custom_properties->type', $this->typeFilter),
            ))
            ->when($this->folderFilter, fn ($q) => $q->whereHas(
                'file',
                fn ($m) => $m->where('custom_properties->folder', $this->folderFilter),
            ))
            ->latest('id')
            ->paginate(24);
    }

    /**
     * Distinct folders for the filter dropdown.
     *
     * @return array<string, string>
     */
    public function getFoldersProperty(): array
    {
        return Media::query()
            ->whereNotNull('custom_properties->folder')
            ->pluck('custom_properties')
            ->map(fn ($p) => $p['folder'] ?? null)
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn ($f) => [$f => $f])
            ->all();
    }

    protected function manager(): FileManager
    {
        return app(FileManager::class);
    }
}
