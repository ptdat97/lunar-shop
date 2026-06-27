<?php

namespace Acme\Workflow;

use Acme\Workflow\Actions\EmailAction;
use Acme\Workflow\Actions\WebhookAction;
use Illuminate\Contracts\Foundation\Application;
use Lunar\Models\Order;
use Modules\Platform\Plugin\BasePlugin;
use Modules\Platform\Rule\RuleRegistry;
use Modules\Platform\Services\HookManager;
use Modules\Platform\Support\Hooks;
use Modules\Platform\Workflow\WorkflowRegistry;

/**
 * Wires REAL workflow triggers/actions/rule-fields onto the generic Platform
 * Workflow engine — so "when order.paid AND total >= 2,000,000 → email VIP" can
 * be configured in the admin with no code. This business knowledge (what an
 * Order's total is, how to email) lives here in a plugin, keeping Core generic.
 *
 * Enabled by default in config/plugins.php.
 */
class WorkflowPlugin extends BasePlugin
{
    public function id(): string
    {
        return 'acme/workflow';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function boot(HookManager $hooks): void
    {
        $workflows = app(WorkflowRegistry::class);
        $rules = app(RuleRegistry::class);

        // Trigger: order.paid → a flat, queue-safe context (no models).
        $workflows->trigger(Hooks::ORDER_PAID, 'Order paid', function (Order $order): array {
            $order->loadMissing(['shippingAddress', 'billingAddress', 'user']);

            return [
                'order_id' => $order->id,
                'order_reference' => $order->reference,
                'order_total' => (int) $order->total->value,   // minor units
                'customer_id' => $order->customer_id,
                'customer_email' => $order->shippingAddress?->contact_email
                    ?: $order->billingAddress?->contact_email
                    ?: $order->user?->email,
            ];
        });

        // Rule fields usable in workflow conditions (resolve from the context).
        $rules->field('order.total', fn (array $c) => $c['order_total'] ?? 0);
        $rules->field('order.reference', fn (array $c) => $c['order_reference'] ?? null);
        $rules->field('customer.id', fn (array $c) => $c['customer_id'] ?? null);

        // Actions.
        $workflows->registerAction(new EmailAction);
        $workflows->registerAction(new WebhookAction);
    }
}
