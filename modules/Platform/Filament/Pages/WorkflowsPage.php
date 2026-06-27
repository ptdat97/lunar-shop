<?php

namespace Modules\Platform\Filament\Pages;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Platform\Models\Workflow;
use Modules\Platform\Rule\Operator;
use Modules\Platform\Rule\RuleRegistry;
use Modules\Platform\Workflow\WorkflowRegistry;

/**
 * Admin page to author workflows (Trigger → Conditions → Action) as forms — NOT
 * a drag-drop canvas. Trigger/action options come from the WorkflowRegistry,
 * condition fields from the RuleRegistry; definitions persist as JSON on the
 * `workflows` table and the engine runs them. Listed in the Settings group.
 */
class WorkflowsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $slug = 'workflows';

    protected static string $view = 'platform::filament.workflows';

    public static function getNavigationLabel(): string
    {
        return 'Workflows';
    }

    public function getTitle(): string
    {
        return 'Workflows';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    /** @var array<string, mixed> the create/edit form state */
    public array $data = [];

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        $registry = app(WorkflowRegistry::class);

        return $form->statePath('data')->schema([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->required(),
                Toggle::make('enabled')->default(true),
                Select::make('trigger')
                    ->options($registry->triggerLabels())
                    ->required()
                    ->helperText('The domain event that starts this workflow.'),
                Select::make('action')
                    ->options($registry->actionLabels())
                    ->required(),
            ]),

            Section::make('Conditions')->schema([
                Select::make('match')
                    ->options(['all' => 'Match ALL', 'any' => 'Match ANY'])
                    ->default('all'),
                Repeater::make('rules')->schema([
                    Select::make('field')
                        ->options(fn () => array_combine(
                            app(RuleRegistry::class)->keys(),
                            app(RuleRegistry::class)->keys(),
                        ))
                        ->required(),
                    Select::make('operator')
                        ->options(collect(Operator::cases())->mapWithKeys(
                            fn (Operator $o) => [$o->value => $o->value]
                        )->all())
                        ->required(),
                    TextInput::make('value'),
                ])->columns(3)->default([]),
            ]),

            Section::make('Action config')->schema([
                Repeater::make('action_config')->schema([
                    TextInput::make('key')->required(),
                    TextInput::make('value'),
                ])->columns(2)->default([])
                    ->helperText('e.g. to / subject / body for email; url for webhook.'),
            ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $conditions = [
            'match' => $state['match'] ?? 'all',
            'rules' => array_values($state['rules'] ?? []),
        ];

        // Guard the stored definition against the workflow contract before it
        // persists (valid match/operator/required fields).
        $errors = \Modules\Platform\Workflow\WorkflowContract::validate([
            'trigger' => $state['trigger'] ?? null,
            'action' => $state['action'] ?? null,
            'conditions' => $conditions,
        ]);

        if ($errors) {
            Notification::make()->title('Invalid workflow')->body(implode(' ', $errors))->danger()->send();

            return;
        }

        Workflow::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $state['name'],
                'trigger' => $state['trigger'],
                'action' => $state['action'],
                'enabled' => (bool) ($state['enabled'] ?? true),
                'conditions' => $conditions,
                'action_config' => collect($state['action_config'] ?? [])
                    ->pluck('value', 'key')->filter(fn ($v, $k) => $k !== '')->all(),
            ],
        );

        $this->editingId = null;
        $this->form->fill();

        Notification::make()->title('Workflow saved.')->success()->send();
    }

    public function edit(int $id): void
    {
        $workflow = Workflow::findOrFail($id);
        $this->editingId = $id;

        $this->form->fill([
            'name' => $workflow->name,
            'trigger' => $workflow->trigger,
            'action' => $workflow->action,
            'enabled' => $workflow->enabled,
            'match' => $workflow->conditions['match'] ?? 'all',
            'rules' => $workflow->conditions['rules'] ?? [],
            'action_config' => collect($workflow->action_config ?? [])
                ->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all(),
        ]);
    }

    public function deleteWorkflow(int $id): void
    {
        Workflow::whereKey($id)->delete();
        Notification::make()->title('Workflow deleted.')->success()->send();
    }

    public function workflowRows(): array
    {
        return Workflow::orderBy('trigger')->get()
            ->map(fn (Workflow $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'trigger' => $w->trigger,
                'action' => $w->action,
                'enabled' => $w->enabled,
            ])->all();
    }
}
