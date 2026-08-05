<?php

namespace Modules\Assets\Filament\Forms;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Lunar\Models\Asset;
use Modules\Assets\Services\MediaLibraryService;

/**
 * The library browser shown inside {@see MediaPicker}'s modal: a paginated
 * thumbnail grid with search + folder filter and an inline upload, so an admin
 * can find (or add) an image and pick it without leaving the form.
 *
 * Its state is the modal's working set — `{selected: int[], search, folder,
 * page}` — read back by the picker action when the modal is submitted. Listing
 * and storing are delegated to {@see MediaLibraryService} (coding standards §4);
 * this class only holds the browsing UI state.
 */
class MediaBrowser extends Field
{
    protected string $view = 'assets::filament.forms.media-browser';

    /** Files per grid page inside the modal. */
    protected const PER_PAGE = 24;

    /** Upload cap inside the modal (KB) — same 50MB as the library page. */
    protected const MAX_UPLOAD_KB = 51200;

    /** Restrict the grid to one logical library type (image|video|document). */
    protected ?string $libraryType = null;

    /** Allow picking more than one file. */
    protected bool $isMultiple = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([
            'selected' => [],
            'search' => null,
            'folder' => null,
            'page' => 1,
        ]);

        $this->dehydrated();

        $this->registerActions([
            $this->toggleAction(),
            $this->pageAction(),
            $this->uploadAction(),
        ]);
    }

    /**
     * Pick/unpick a file in the grid.
     */
    protected function toggleAction(): Action
    {
        return Action::make('toggle')
            ->action(function (array $arguments, MediaBrowser $component): void {
                $component->toggle((int) ($arguments['id'] ?? 0));
            });
    }

    /**
     * Move between grid pages.
     */
    protected function pageAction(): Action
    {
        return Action::make('page')
            ->label(__('admin.media.next'))
            ->color('gray')
            ->size('sm')
            ->action(function (array $arguments, MediaBrowser $component): void {
                $component->goToPage((int) ($arguments['page'] ?? 1));
            });
    }

    /**
     * Upload straight from the modal, then pre-select what was uploaded so the
     * admin can confirm without a second trip through the grid.
     */
    protected function uploadAction(): Action
    {
        return Action::make('upload')
            ->label(__('admin.media.upload'))
            ->icon('heroicon-m-arrow-up-tray')
            ->color('gray')
            ->modalHeading(__('admin.media.upload'))
            ->modalSubmitActionLabel(__('admin.media.upload'))
            ->form([
                FileUpload::make('files')
                    ->label(__('admin.media.upload'))
                    ->multiple($this->isMultiple())
                    ->acceptedFileTypes($this->acceptedMimes())
                    ->maxSize(self::MAX_UPLOAD_KB)
                    ->storeFiles(false)
                    ->required(),
                TextInput::make('folder')
                    ->label(__('admin.media.folder'))
                    ->maxLength(255),
            ])
            ->action(function (array $data, MediaBrowser $component): void {
                $count = $component->upload(
                    array_filter((array) ($data['files'] ?? [])),
                    $data['folder'] ?? null,
                );

                Notification::make()
                    ->title(__('admin.media.uploaded', ['count' => $count]))
                    ->success()
                    ->send();
            });
    }

    /**
     * Upload MIME whitelist — narrowed to the field's library type when it is
     * restricted, so an image picker can't take in a PDF.
     *
     * @return array<int, string>
     */
    protected function acceptedMimes(): array
    {
        return match ($this->libraryType) {
            'image' => ['image/*'],
            'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
            'document' => ['application/pdf'],
            default => ['image/*', 'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'application/pdf'],
        };
    }

    public function libraryType(?string $type): static
    {
        $this->libraryType = $type;

        return $this;
    }

    public function getLibraryType(): ?string
    {
        return $this->libraryType;
    }

    public function multiple(bool $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    /**
     * Currently-selected asset ids inside the modal.
     *
     * @return array<int, int>
     */
    public function getSelected(): array
    {
        return MediaPicker::idsOf($this->getStateValue('selected', []), true);
    }

    /**
     * Toggle one asset in the modal's selection. In single mode picking a file
     * replaces the selection (and picking the selected one clears it).
     */
    public function toggle(int $id): void
    {
        $selected = $this->getSelected();

        if (! $this->isMultiple) {
            $this->setStateValue('selected', in_array($id, $selected, true) ? [] : [$id]);

            return;
        }

        $this->setStateValue('selected', in_array($id, $selected, true)
            ? array_values(array_filter($selected, fn (int $existing) => $existing !== $id))
            : [...$selected, $id]);
    }

    /**
     * Apply the search box / folder filter, resetting to the first page so the
     * admin isn't left on a page the narrowed result set no longer has.
     */
    public function filter(?string $search, ?string $folder): void
    {
        $this->setStateValue('search', $search);
        $this->setStateValue('folder', $folder);
        $this->setStateValue('page', 1);
    }

    public function goToPage(int $page): void
    {
        $this->setStateValue('page', max(1, $page));
    }

    /**
     * Livewire click handler that toggles one grid tile. Built from the
     * registered `toggle` action so the whole tile is clickable — an Action
     * button would only make its own small area hit-testable.
     */
    public function getToggleHandler(int $id): string
    {
        return $this->getAction('toggle')(['id' => $id])->getLivewireClickHandler() ?? '';
    }

    /**
     * The grid's current page of library files, already filtered by the field's
     * type restriction plus the admin's search/folder choices.
     */
    public function getFiles(): LengthAwarePaginator
    {
        return $this->library()
            ->browse([
                'type' => $this->libraryType,
                'search' => $this->getStateValue('search'),
                'folder' => $this->getStateValue('folder'),
            ])
            ->paginate(
                perPage: self::PER_PAGE,
                page: max(1, (int) $this->getStateValue('page', 1)),
            );
    }

    /**
     * Distinct folders for the modal's folder filter.
     *
     * @return array<string, string>
     */
    public function getFolders(): array
    {
        return $this->library()->folders();
    }

    /**
     * Presentation payload for one listed asset, so the Blade grid renders the
     * same shape the picker's own thumbnails use.
     *
     * @return array<string, mixed>|null
     */
    public function previewFor(Asset $asset): ?array
    {
        $media = $asset->file;

        return $media ? $this->library()->previewOf($asset->id, $media) : null;
    }

    /**
     * Store files uploaded from inside the modal and pre-select them, so
     * "upload then use it" is one flow instead of a trip to the library page.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function upload(array $files, ?string $folder = null): int
    {
        $library = $this->library();
        $selected = $this->getSelected();
        $count = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $asset = $library->store($file, $folder);
            $count++;

            $selected = $this->isMultiple ? [...$selected, $asset->id] : [$asset->id];
        }

        if ($count) {
            $this->setStateValue('selected', $selected);
            $this->setStateValue('page', 1);
        }

        return $count;
    }

    /**
     * Read one key out of the field's array state.
     */
    protected function getStateValue(string $key, mixed $default = null): mixed
    {
        $state = (array) $this->getState();

        return $state[$key] ?? $default;
    }

    /**
     * Write one key into the field's array state.
     */
    protected function setStateValue(string $key, mixed $value): void
    {
        $state = (array) $this->getState();
        $state[$key] = $value;

        $this->state($state);
    }

    protected function library(): MediaLibraryService
    {
        return app(MediaLibraryService::class);
    }
}
