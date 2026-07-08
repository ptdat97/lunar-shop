<?php

namespace Modules\Assets\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Assets\Services\HorizonSettings;
use Modules\Assets\Services\MediaRegenerator;
use Throwable;

/**
 * Admin page to tune Horizon worker scaling (processes / memory / timeout /
 * retries per supervisor) and watch queue health, without editing
 * config/horizon.php. Saved values live in app_settings (via HorizonSettings)
 * and are pushed into the live horizon.* config at boot by AssetsServiceProvider.
 *
 * Changes take effect on the NEXT supervisor launch, so saving prompts the admin
 * to run `php artisan horizon:terminate` (the running daemon restarts and re-reads
 * config). The status panel polls the Horizon repositories so the admin can see
 * whether workers are up and how deep each queue is.
 */
class QueueWorkers extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $slug = 'settings/queue-workers';

    protected static string $view = 'assets::filament.pages.queue-workers';

    public static function getNavigationLabel(): string
    {
        return __('admin.queue_workers.title');
    }

    public function getTitle(): string
    {
        return __('admin.queue_workers.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    /** @var array<string, mixed> */
    public array $data = [];

    /** Whether Horizon's master supervisor is running (drains the queues). */
    public bool $workerAvailable = true;

    /** Whether the laravel/horizon package is installed at all. */
    public bool $horizonInstalled = true;

    /** Live per-queue workload: [['name','length','wait','processes'], …]. */
    public array $queues = [];

    /** Recent + failed job counts from Horizon (null when unavailable). */
    public ?int $recentJobs = null;

    public ?int $failedJobs = null;

    /** Path to the Horizon dashboard (config horizon.path). */
    public string $horizonPath = 'horizon';

    public function mount(): void
    {
        $this->form->fill(app(HorizonSettings::class)->supervisors());
        $this->horizonPath = (string) config('horizon.path', 'horizon');
        $this->refreshStatus();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->supervisorSections())
            ->statePath('data');
    }

    /**
     * One editable section per supervisor (app / media), each with the four
     * scaling knobs bounded to HorizonSettings::FIELDS ranges.
     *
     * @return array<int, Section>
     */
    protected function supervisorSections(): array
    {
        $sections = [];

        foreach (HorizonSettings::SUPERVISORS as $supervisor) {
            $sections[] = Section::make(__("admin.queue_workers.{$supervisor}"))
                ->description(__("admin.queue_workers.{$supervisor}_desc"))
                ->columns(2)
                ->schema($this->supervisorFields($supervisor));
        }

        return $sections;
    }

    /**
     * @return array<int, TextInput>
     */
    protected function supervisorFields(string $supervisor): array
    {
        $fields = [];

        foreach (HorizonSettings::FIELDS as $field => [$min, $max]) {
            $fields[] = TextInput::make("{$supervisor}.{$field}")
                ->label(__("admin.queue_workers.{$field}"))
                ->helperText(__("admin.queue_workers.{$field}_help"))
                ->numeric()
                ->minValue($min)
                ->maxValue($max)
                ->required();
        }

        return $fields;
    }

    public function save(): void
    {
        $this->form->validate();

        app(HorizonSettings::class)->save($this->form->getState());

        Notification::make()
            ->title(__('admin.queue_workers.saved'))
            ->body(__('admin.queue_workers.terminate_note'))
            ->success()
            ->send();

        $this->refreshStatus();
    }

    /**
     * Poll target: refresh the worker/queue status shown in the view. Every
     * Horizon read is guarded so a missing package or unreachable Redis never
     * errors the page — the status just shows as unknown/empty.
     */
    public function refreshStatus(): void
    {
        $this->horizonInstalled = class_exists(\Laravel\Horizon\Horizon::class);
        $this->workerAvailable = app(MediaRegenerator::class)->workerAvailable();

        if (! $this->horizonInstalled) {
            return;
        }

        try {
            $this->queues = app(\Laravel\Horizon\Contracts\WorkloadRepository::class)
                ->get()
                ->map(fn ($q) => [
                    'name' => (string) ($q['name'] ?? ''),
                    'length' => (int) ($q['length'] ?? 0),
                    'wait' => (int) ($q['wait'] ?? 0),
                    'processes' => (int) ($q['processes'] ?? 0),
                ])
                ->values()
                ->all();
        } catch (Throwable $e) {
            $this->queues = [];
        }

        try {
            $jobs = app(\Laravel\Horizon\Contracts\JobRepository::class);
            $this->recentJobs = (int) $jobs->countRecent();
            $this->failedJobs = (int) $jobs->countFailed();
        } catch (Throwable $e) {
            $this->recentJobs = null;
            $this->failedJobs = null;
        }
    }
}
