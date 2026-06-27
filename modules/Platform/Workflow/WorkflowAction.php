<?php

namespace Modules\Platform\Workflow;

/**
 * An action a workflow can run when its trigger fires and conditions pass.
 * Implementations are registered by id (WorkflowRegistry::action) and run with
 * the trigger context + the workflow's per-action config. Side effects live
 * here (send mail, tag customer, call webhook) — the engine only orchestrates.
 */
interface WorkflowAction
{
    /** Stable id, e.g. "notify.email" or "customer.add_tag". */
    public function id(): string;

    /** Human label for the admin UI. */
    public function label(): string;

    /**
     * Run the action.
     *
     * @param  array<string, mixed>  $context  the trigger context
     * @param  array<string, mixed>  $config   the workflow's action_config
     */
    public function run(array $context, array $config): void;
}
