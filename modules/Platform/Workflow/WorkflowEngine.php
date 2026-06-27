<?php

namespace Modules\Platform\Workflow;

use Modules\Platform\Models\Workflow;
use Modules\Platform\Rule\RuleRegistry;
use Modules\Platform\Services\HookManager;

/**
 * Orchestrates workflows: Trigger (Hooks::* event) → Conditions (RuleSet) →
 * Action (queued). At boot it subscribes to every registered trigger hook; when
 * one fires it builds the context, finds enabled workflows for that trigger,
 * evaluates their conditions, and dispatches passing actions onto the queue.
 *
 * Business-free: triggers/actions/rule-fields are all contributed by
 * modules/plugins via the registries; the engine only wires + dispatches.
 */
class WorkflowEngine
{
    /** Hooks already subscribed, so listen() never double-subscribes a trigger. */
    protected array $subscribed = [];

    public function __construct(
        protected WorkflowRegistry $registry,
        protected RuleRegistry $rules,
        protected HookManager $hooks,
    ) {}

    /**
     * Subscribe to all registered trigger hooks. Idempotent — safe to call more
     * than once (e.g. boot + a late trigger registration); each hook is only
     * subscribed once.
     */
    public function listen(): void
    {
        foreach ($this->registry->triggers() as $hook) {
            if (isset($this->subscribed[$hook])) {
                continue;
            }

            $this->subscribed[$hook] = true;

            $this->hooks->addAction($hook, function (...$args) use ($hook): void {
                $this->dispatch($hook, $args);
            });
        }
    }

    /**
     * Run the workflows for a fired trigger: build context, evaluate each
     * enabled workflow's conditions, dispatch passing actions to the queue.
     *
     * @param  array<int, mixed>  $args  the hook args
     */
    public function dispatch(string $hook, array $args): void
    {
        $workflows = Workflow::query()
            ->where('trigger', $hook)
            ->where('enabled', true)
            ->get();

        if ($workflows->isEmpty()) {
            return;
        }

        $context = $this->registry->buildContext($hook, $args);

        foreach ($workflows as $workflow) {
            if ($workflow->ruleSet()->passes($context, $this->rules)) {
                RunWorkflowAction::dispatch(
                    $workflow->action,
                    $context,
                    $workflow->action_config ?? [],
                );
            }
        }
    }
}
