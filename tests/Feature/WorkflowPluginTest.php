<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Modules\Platform\Models\Workflow;
use Modules\Platform\Plugin\PluginManager;
use Modules\Platform\Rule\RuleRegistry;
use Modules\Platform\Services\HookManager;
use Modules\Platform\Support\Hooks;
use Modules\Platform\Workflow\RunWorkflowAction;
use Modules\Platform\Workflow\WorkflowEngine;
use Modules\Platform\Workflow\WorkflowRegistry;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * W.3 dogfood — the acme/workflow plugin registers REAL triggers/actions/fields
 * on the generic engine: "when order.paid AND order.total >= N → run an action".
 * Proves the whole chain (Phase 5) works end-to-end with a real Hooks::* event.
 */
class WorkflowPluginTest extends TestCase
{
    use CreatesStorefrontData;

    protected function bootWorkflowPlugin(): void
    {
        config(['plugins.enabled' => ['acme/workflow']]);

        $manager = new PluginManager($this->app);
        $manager->load();
        $manager->boot();

        // Subscribe the engine to the now-registered triggers.
        app(WorkflowEngine::class)->listen();
    }

    public function test_plugin_registers_the_order_paid_trigger_and_actions(): void
    {
        $this->bootWorkflowPlugin();

        $this->assertTrue(app(WorkflowRegistry::class)->hasTrigger(Hooks::ORDER_PAID));
        $this->assertArrayHasKey('notify.email', app(WorkflowRegistry::class)->actionLabels());
        $this->assertArrayHasKey('webhook.post', app(WorkflowRegistry::class)->actionLabels());
        $this->assertTrue(app(RuleRegistry::class)->has('order.total'));
    }

    public function test_high_value_order_triggers_the_action(): void
    {
        $this->bootWorkflowPlugin();
        Queue::fake();

        Workflow::create([
            'name' => 'VIP email',
            'trigger' => Hooks::ORDER_PAID,
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 'order.total', 'operator' => '>=', 'value' => 200000],
            ]],
            'action' => 'notify.email',
            'action_config' => ['to' => 'vip@example.test', 'subject' => 'Thanks {order_reference}'],
            'enabled' => true,
        ]);

        $order = \Lunar\Models\Order::factory()->create([
            'channel_id' => \Lunar\Models\Channel::getDefault()->id,
            'total' => 250000,
            'reference' => 'ORD-VIP-1',
        ]);

        app(HookManager::class)->doAction(Hooks::ORDER_PAID, [$order]);

        Queue::assertPushed(RunWorkflowAction::class, function (RunWorkflowAction $job) {
            return $job->actionId === 'notify.email'
                && $job->context['order_total'] === 250000
                && $job->context['order_reference'] === 'ORD-VIP-1';
        });
    }

    public function test_low_value_order_does_not_trigger(): void
    {
        $this->bootWorkflowPlugin();
        Queue::fake();

        Workflow::create([
            'name' => 'VIP email',
            'trigger' => Hooks::ORDER_PAID,
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 'order.total', 'operator' => '>=', 'value' => 200000],
            ]],
            'action' => 'notify.email',
            'enabled' => true,
        ]);

        $order = \Lunar\Models\Order::factory()->create([
            'channel_id' => \Lunar\Models\Channel::getDefault()->id,
            'total' => 5000,
        ]);

        app(HookManager::class)->doAction(Hooks::ORDER_PAID, [$order]);

        Queue::assertNothingPushed();
    }
}
