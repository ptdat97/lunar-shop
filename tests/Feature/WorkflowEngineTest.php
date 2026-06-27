<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Modules\Platform\Models\Workflow;
use Modules\Platform\Rule\RuleRegistry;
use Modules\Platform\Services\HookManager;
use Modules\Platform\Workflow\RunWorkflowAction;
use Modules\Platform\Workflow\WorkflowAction;
use Modules\Platform\Workflow\WorkflowEngine;
use Modules\Platform\Workflow\WorkflowRegistry;
use Tests\TestCase;

/**
 * W.2 — the Workflow engine: Trigger (Hooks::* event) → Conditions (RuleSet) →
 * Action (queued). Business-free: this test registers its own trigger, field and
 * action, then drives the engine end-to-end.
 */
class WorkflowEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register a trigger (hook + context builder), a rule field, and an action.
        app(WorkflowRegistry::class)->trigger(
            'test.order_paid',
            'Test order paid',
            fn ($total, $group = null) => ['total' => $total, 'group' => $group],
        );
        app(RuleRegistry::class)->field('total', fn (array $c) => $c['total'] ?? 0);
        app(RuleRegistry::class)->field('group', fn (array $c) => $c['group'] ?? null);
        app(WorkflowRegistry::class)->registerAction(new SpyAction);

        // Subscribe the engine to the (now-registered) trigger.
        app(WorkflowEngine::class)->listen();

        SpyAction::$ran = [];
    }

    public function test_a_matching_workflow_dispatches_its_action(): void
    {
        Queue::fake();

        Workflow::create([
            'name' => 'VIP thankyou',
            'trigger' => 'test.order_paid',
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 'total', 'operator' => '>=', 'value' => 2000],
            ]],
            'action' => 'test.spy',
            'action_config' => ['note' => 'hi'],
            'enabled' => true,
        ]);

        app(HookManager::class)->doAction('test.order_paid', [5000, 'gold']);

        Queue::assertPushed(RunWorkflowAction::class, function (RunWorkflowAction $job) {
            return $job->actionId === 'test.spy'
                && $job->context['total'] === 5000
                && $job->config['note'] === 'hi';
        });
    }

    public function test_conditions_that_fail_do_not_dispatch(): void
    {
        Queue::fake();

        Workflow::create([
            'name' => 'Big spenders only',
            'trigger' => 'test.order_paid',
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 'total', 'operator' => '>=', 'value' => 100000],
            ]],
            'action' => 'test.spy',
            'enabled' => true,
        ]);

        app(HookManager::class)->doAction('test.order_paid', [5000, 'gold']);

        Queue::assertNothingPushed();
    }

    public function test_disabled_workflows_are_skipped(): void
    {
        Queue::fake();

        Workflow::create([
            'name' => 'Off',
            'trigger' => 'test.order_paid',
            'action' => 'test.spy',
            'enabled' => false,
        ]);

        app(HookManager::class)->doAction('test.order_paid', [5000]);

        Queue::assertNothingPushed();
    }

    public function test_empty_conditions_always_pass(): void
    {
        Queue::fake();

        Workflow::create([
            'name' => 'Always',
            'trigger' => 'test.order_paid',
            'conditions' => null,
            'action' => 'test.spy',
            'enabled' => true,
        ]);

        app(HookManager::class)->doAction('test.order_paid', [1]);

        Queue::assertPushed(RunWorkflowAction::class);
    }

    public function test_the_queued_job_runs_the_registered_action(): void
    {
        $job = new RunWorkflowAction('test.spy', ['total' => 9], ['note' => 'run']);
        $job->handle(app(WorkflowRegistry::class));

        $this->assertSame([['total' => 9], ['note' => 'run']], SpyAction::$ran[0]);
    }

    public function test_listen_is_idempotent_no_double_dispatch(): void
    {
        Queue::fake();

        // listen() already ran in setUp + app booted; call again — must not
        // double-subscribe the trigger.
        app(WorkflowEngine::class)->listen();
        app(WorkflowEngine::class)->listen();

        Workflow::create([
            'name' => 'Once',
            'trigger' => 'test.order_paid',
            'action' => 'test.spy',
            'enabled' => true,
        ]);

        app(HookManager::class)->doAction('test.order_paid', [1]);

        Queue::assertPushed(RunWorkflowAction::class, 1);
    }
}

class SpyAction implements WorkflowAction
{
    /** @var list<array{0:array,1:array}> */
    public static array $ran = [];

    public function id(): string
    {
        return 'test.spy';
    }

    public function label(): string
    {
        return 'Spy';
    }

    public function run(array $context, array $config): void
    {
        self::$ran[] = [$context, $config];
    }
}
