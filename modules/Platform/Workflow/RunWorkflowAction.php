<?php

namespace Modules\Platform\Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued execution of a single workflow's action. Runs off-request so a slow
 * action (webhook, email) never blocks the triggering operation, and one
 * failing action can't break the others. Context is a plain array (already
 * built by the trigger), so it serialises cleanly for the queue.
 */
class RunWorkflowAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $actionId,
        public array $context,
        public array $config,
    ) {}

    public function handle(WorkflowRegistry $registry): void
    {
        $action = $registry->action($this->actionId);

        if (! $action) {
            Log::warning("Workflow action [{$this->actionId}] is not registered — skipped.");

            return;
        }

        $action->run($this->context, $this->config);
    }
}
