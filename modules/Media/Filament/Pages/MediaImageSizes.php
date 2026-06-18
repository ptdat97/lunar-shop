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
        $this->runRegenerate(['--only-missing' => true, '--force' => true], 'Missing conversions regenerated');
    }

    /**
     * Force regenerate ALL conversions, overwriting existing files. Use after
     * changing sizes so previously generated images are rebuilt.
     */
    public function forceRegenerate(): void
    {
        $this->runRegenerate(['--force' => true], 'All conversions regenerated');
    }

    /**
     * Run media-library:regenerate inline (synchronously) so a button click
     * does the work without needing a separate `php artisan queue:work`
     * terminal. The Spatie command dispatches a PerformConversionsJob per
     * media item, so we force the queue connection to "sync" for this call —
     * those jobs then run in-process and finish before the request returns.
     *
     * @param  array<string, mixed>  $options
     */
    protected function runRegenerate(array $options, string $title): void
    {
        // Regenerating a large library runs many conversions in one request;
        // lift PHP's execution time limit so it isn't killed mid-way. (Has no
        // effect when PHP runs in safe mode or via some FPM configs, but is the
        // standard guard for long-running synchronous work.)
        @set_time_limit(0);

        // Force conversion jobs to run in-process regardless of the app's
        // default queue driver, so nothing is left waiting for a worker.
        $original = config('queue.default');
        config(['queue.default' => 'sync']);

        try {
            \Illuminate\Support\Facades\Artisan::call('media-library:regenerate', $options);
            $output = trim(\Illuminate\Support\Facades\Artisan::output());
        } catch (\Throwable $e) {
            config(['queue.default' => $original]);

            Notification::make()
                ->title('Regeneration failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        config(['queue.default' => $original]);

        Notification::make()
            ->title($title)
            ->body($output !== '' ? $output : 'Done. Existing images have been rebuilt with the current sizes.')
            ->success()
            ->send();
    }
}
