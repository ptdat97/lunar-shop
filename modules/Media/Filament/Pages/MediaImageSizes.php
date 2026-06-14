<?php

namespace Modules\Media\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Media\Services\MediaSettings;

/**
 * Admin page to configure image conversion sizes (small/medium/large/zoom).
 * Sizes are read by FashionMediaDefinitions, so changes apply to newly
 * generated conversions; existing media can be regenerated from here.
 */
class MediaImageSizes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $title = 'Image Sizes';

    protected static ?string $slug = 'settings/image-sizes';

    protected static string $view = 'media::filament.pages.media-image-sizes';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(app(MediaSettings::class)->sizes());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Conversion sizes')
                    ->description('Width and height (px) for each generated image size. "Fit fill" keeps the given aspect ratio.')
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
                        ->numeric()->minValue(1)->required(),
                    TextInput::make("{$key}.height")
                        ->label('Height (px)')
                        ->numeric()->minValue(1)->required(),
                ]);
        }

        return $fields;
    }

    public function save(): void
    {
        app(MediaSettings::class)->save($this->form->getState());

        Notification::make()
            ->title('Image sizes saved')
            ->body('New sizes apply to newly generated images. Use “Regenerate all” to update existing media.')
            ->success()
            ->send();
    }

    /**
     * Regenerate only the conversions that are missing (fast, non-destructive).
     */
    public function regenerate(): void
    {
        \Illuminate\Support\Facades\Artisan::queue('media-library:regenerate', [
            '--only-missing' => true,
            '--force' => true,
        ]);

        $this->queuedNotification('Regeneration queued', 'Missing conversions');
    }

    /**
     * Force regenerate ALL conversions, overwriting existing files. Use after
     * changing sizes so previously generated images are rebuilt.
     */
    public function forceRegenerate(): void
    {
        \Illuminate\Support\Facades\Artisan::queue('media-library:regenerate', [
            '--force' => true,
        ]);

        $this->queuedNotification('Force regeneration queued', 'All conversions');
    }

    /**
     * Notify that work was queued. Because regeneration runs on the queue
     * (and each image is a further PerformConversionsJob), a running queue
     * worker is required — warn if there is no driver to process it.
     */
    protected function queuedNotification(string $title, string $what): void
    {
        $needsWorker = config('queue.default') !== 'sync';

        $notification = Notification::make()
            ->title($title)
            ->success();

        if ($needsWorker) {
            $notification->body("{$what} are queued and will be processed by a background queue worker. Make sure a worker is running (e.g. `php artisan queue:work` or Horizon).");
        } else {
            $notification->body("{$what} are being regenerated now.");
        }

        $notification->send();
    }
}
