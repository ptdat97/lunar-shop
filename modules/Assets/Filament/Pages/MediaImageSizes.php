<?php

namespace Modules\Assets\Filament\Pages;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Assets\Services\MediaRegenerator;
use Modules\Assets\Services\MediaSettings;

/**
 * Admin page to configure image conversion sizes (small/medium/large/zoom).
 *
 * Sizes are read by FashionMediaDefinitions, so changes apply to newly generated
 * conversions. Existing media is rebuilt via a QUEUED BATCH (see MediaRegenerator)
 * — production-safe for large libraries: the work runs across many small jobs on
 * the queue with a live progress bar, instead of one synchronous request that
 * would time out. Requires a running queue worker (`php artisan queue:work`).
 */
class MediaImageSizes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    public static function getNavigationLabel(): string
    {
        return __('admin.media.image_sizes');
    }

    public function getTitle(): string
    {
        return __('admin.media.image_sizes');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    protected static ?string $slug = 'settings/image-sizes';

    protected static string $view = 'assets::filament.pages.media-image-sizes';

    /** Bounds for a configurable dimension (px). */
    protected const MIN_DIMENSION = 16;

    protected const MAX_DIMENSION = 5000;

    /** @var array<string, mixed> */
    public array $data = [];

    /** Live batch progress, refreshed by the page poll. @var array<string,mixed>|null */
    public ?array $batch = null;

    /** True after a save that changed a dimension → existing conversions stale. */
    public bool $sizesStale = false;

    /** Whether a queue worker (Horizon) is available to drain the media queue. */
    public bool $workerAvailable = true;

    public function mount(): void
    {
        $this->form->fill(app(MediaSettings::class)->sizes());
        $this->refreshBatch();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('admin.media.conversion_sizes'))
                    ->description(__('admin.media.sizes_desc'))
                    ->schema($this->sizeFields()),
            ])
            ->statePath('data');
    }

    /**
     * @return array<int, Component>
     */
    protected function sizeFields(): array
    {
        $fields = [];

        foreach (MediaSettings::keys() as $key) {
            $fields[] = Section::make(ucfirst($key))
                ->columns(2)
                ->schema([
                    TextInput::make("{$key}.width")
                        ->label(__('admin.media.width'))
                        ->numeric()
                        ->minValue(self::MIN_DIMENSION)
                        ->maxValue(self::MAX_DIMENSION)
                        ->required(),
                    TextInput::make("{$key}.height")
                        ->label(__('admin.media.height'))
                        ->numeric()
                        ->minValue(self::MIN_DIMENSION)
                        ->maxValue(self::MAX_DIMENSION)
                        ->required(),
                ]);
        }

        return $fields;
    }

    public function save(): void
    {
        // Validates against the min/max rules declared on the fields.
        $this->form->validate();

        $settings = app(MediaSettings::class);
        $before = $settings->sizes();
        $after = $this->form->getState();

        $settings->save($after);

        // A size change makes every existing conversion at that size stale. Flag
        // it so the view shows a "rebuild now" call-to-action (Horizon-aware),
        // instead of silently leaving the library at the old size.
        $this->sizesStale = $this->sizesChanged($before, $after);

        $notification = Notification::make()
            ->title(__('admin.media.saved'))
            ->success();

        if ($this->sizesStale) {
            $notification->body(
                app(MediaRegenerator::class)->workerAvailable()
                    ? 'Sizes saved. Existing images are still at the old size — use "Rebuild all" below.'
                    : 'Sizes saved, but no queue worker is running. Start Horizon before rebuilding.'
            );
        } else {
            $notification->body(__('admin.media.saved_body'));
        }

        $notification->send();
    }

    /**
     * @param  array<string, array{width:int, height:int}>  $before
     * @param  array<string, mixed>  $after
     */
    protected function sizesChanged(array $before, array $after): bool
    {
        foreach (MediaSettings::keys() as $key) {
            if ((int) ($before[$key]['width'] ?? 0) !== (int) ($after[$key]['width'] ?? 0)
                || (int) ($before[$key]['height'] ?? 0) !== (int) ($after[$key]['height'] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Queue a batch that regenerates only MISSING conversions (fast, additive).
     */
    public function regenerateMissing(): void
    {
        $this->queueRegenerate(onlyMissing: true);
    }

    /**
     * Queue a batch that rebuilds ALL conversions (use after changing sizes).
     */
    public function regenerateAll(): void
    {
        $this->queueRegenerate(onlyMissing: false);
    }

    public function cancelRegenerate(): void
    {
        app(MediaRegenerator::class)->cancel();
        $this->refreshBatch();

        Notification::make()->title('Regeneration cancelled')->warning()->send();
    }

    /**
     * Poll target: refresh the batch progress + worker status shown in the view.
     */
    public function refreshBatch(): void
    {
        $regenerator = app(MediaRegenerator::class);
        $this->batch = $regenerator->progress();
        $this->workerAvailable = $regenerator->workerAvailable();

        // Once a rebuild is running (or the library is up to date), the stale
        // banner is no longer useful.
        if ($this->batch !== null) {
            $this->sizesStale = false;
        }
    }

    protected function queueRegenerate(bool $onlyMissing): void
    {
        $regenerator = app(MediaRegenerator::class);

        if ($regenerator->isRunning()) {
            Notification::make()
                ->title('Already running')
                ->body('A regeneration batch is already in progress.')
                ->warning()
                ->send();

            return;
        }

        $batchId = $regenerator->dispatch($onlyMissing);

        if ($batchId === null) {
            Notification::make()->title('No media to regenerate')->warning()->send();

            return;
        }

        $this->refreshBatch();

        Notification::make()
            ->title('Regeneration queued')
            ->body('Running in the background. Progress updates below. (Requires a queue worker.)')
            ->success()
            ->send();
    }
}
