<?php

namespace Modules\Platform\Workflow;

/**
 * Registry of workflow triggers and actions. Core ships none — modules/plugins
 * register:
 *   - triggers: a Hooks::* name + a context builder (maps the hook's args to a
 *     flat context array the rules + actions consume);
 *   - actions: a WorkflowAction by id.
 *
 * Keeps the engine business-free: it only knows how to wire a trigger to its
 * builder and dispatch to an action id.
 */
class WorkflowRegistry
{
    /** @var array<string, array{label:string, context: callable}> trigger => meta */
    protected array $triggers = [];

    /** @var array<string, WorkflowAction> action id => action */
    protected array $actions = [];

    /**
     * Register a trigger: a Hooks::* name + a builder mapping the hook args to a
     * context array.
     *
     * @param  callable(mixed ...$args): array<string, mixed>  $context
     */
    public function trigger(string $hook, string $label, callable $context): void
    {
        $this->triggers[$hook] = ['label' => $label, 'context' => $context];
    }

    public function registerAction(WorkflowAction $action): void
    {
        $this->actions[$action->id()] = $action;
    }

    public function hasTrigger(string $hook): bool
    {
        return isset($this->triggers[$hook]);
    }

    /** @return list<string> registered trigger hook names */
    public function triggers(): array
    {
        return array_keys($this->triggers);
    }

    /** @return array<string, string> trigger => label (for the admin UI) */
    public function triggerLabels(): array
    {
        return array_map(fn ($t) => $t['label'], $this->triggers);
    }

    /** @return array<string, string> action id => label (for the admin UI) */
    public function actionLabels(): array
    {
        return array_map(fn (WorkflowAction $a) => $a->label(), $this->actions);
    }

    /**
     * Build a trigger's context from the hook args.
     *
     * @param  array<int, mixed>  $args
     * @return array<string, mixed>
     */
    public function buildContext(string $hook, array $args): array
    {
        $builder = $this->triggers[$hook]['context'] ?? null;

        return $builder ? (array) $builder(...$args) : [];
    }

    public function action(string $id): ?WorkflowAction
    {
        return $this->actions[$id] ?? null;
    }
}
