<?php

namespace Modules\Media\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Media\Services\MediaRegenerator;
use Modules\Media\Services\MediaSettings;

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

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $title = 'Image Sizes';

    protected static ?string $slug = 'settings/image-sizes';

    protected static string $view = 'media::filament.pages.media-image-sizes';

    /** Bounds for a configurable dimension (px). */
    protected const MIN_DIMENSION = 16;

    protected const MAX_DIMENSION = 5000;

    /** @var array<string, mixed> */
    public array $data = [];

    /** Live batch progress, refreshed by the page poll. @var array<string,mixed>|null */
    public ?array $batch = null;

    public function mount(): void
    {
        $this->form->fill(app(MediaSettings::class)->sizes());
        $this->refreshBatch();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Conversion sizes')
                    ->description('Width and height (px) for each generated image size. Larger sizes mean sharper images but bigger files — keep them as small as the design allows for fast page loads.')
                    ->schema($this->sizeFields()),
            ])
            ->statePath('data');
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function sizeFields(): array
    {
        $fields = [];

        foreach (MediaSettings::keys() as $key) {
            $fields[] = Section::make(ucfirst($key))
                ->columns(2)
                ->schema([
                    TextInput::make("{$key}.width")
                        ->label('Width (px)')
                        ->numeric()
                        ->minValue(self::MIN_DIMENSION)
                        ->maxValue(self::MAX_DIMENSION)
                        ->required(),
                    TextInput::make("{$key}.height")
                        ->label('Height (px)')
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

        app(MediaSettings::class)->save($this->form->getState());

        Notification::make()
            ->title('Image sizes saved')
            ->body('New sizes apply to newly generated images. Use “Regenerate all” to rebuild existing media with the new sizes.')
            ->success()
            ->send();
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
     * Poll target: refresh the batch progress shown in the view.
     */
    public function refreshBatch(): void
    {
        $this->batch = app(MediaRegenerator::class)->progress();
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
