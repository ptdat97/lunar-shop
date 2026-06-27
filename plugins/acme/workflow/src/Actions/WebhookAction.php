<?php

namespace Acme\Workflow\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Workflow\WorkflowAction;

/**
 * POSTs the trigger context as JSON to a configured URL. config: ['url' => '…'].
 * Failures are logged, not thrown (it runs on the queue; one bad endpoint must
 * not poison the worker).
 */
class WebhookAction implements WorkflowAction
{
    public function id(): string
    {
        return 'webhook.post';
    }

    public function label(): string
    {
        return 'Call webhook';
    }

    public function run(array $context, array $config): void
    {
        $url = $config['url'] ?? null;

        if (! $url) {
            return;
        }

        try {
            Http::asJson()->post($url, $context);
        } catch (\Throwable $e) {
            Log::warning("Workflow webhook to [{$url}] failed: {$e->getMessage()}");
        }
    }
}
