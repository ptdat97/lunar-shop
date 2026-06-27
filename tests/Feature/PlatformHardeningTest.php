<?php

namespace Tests\Feature;

use Modules\Platform\Models\Workflow;
use Modules\Platform\Rule\RuleRegistry;
use Modules\Platform\Support\PlatformDoctor;
use Modules\Platform\Workflow\WorkflowContract;
use Modules\Platform\Workflow\WorkflowRegistry;
use Tests\TestCase;

/**
 * Phase 6 — hardening: workflow definition versioning/validation (WorkflowContract)
 * + platform:doctor drift detection between persisted workflows and the live
 * registries.
 */
class PlatformHardeningTest extends TestCase
{
    public function test_workflow_contract_accepts_a_valid_definition(): void
    {
        $errors = WorkflowContract::validate([
            'trigger' => 'order.paid',
            'action' => 'notify.email',
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 'order.total', 'operator' => '>=', 'value' => 1000],
            ]],
        ]);

        $this->assertSame([], $errors);
    }

    public function test_workflow_contract_flags_structural_problems(): void
    {
        $errors = WorkflowContract::validate([
            'trigger' => '',                       // missing
            'action' => 'x',
            'conditions' => ['match' => 'maybe', 'rules' => [   // bad match
                ['field' => '', 'operator' => '><'],            // missing field + bad operator
            ]],
        ]);

        $this->assertContains('Missing trigger.', $errors);
        $this->assertTrue((bool) array_filter($errors, fn ($e) => str_contains($e, 'match')));
        $this->assertTrue((bool) array_filter($errors, fn ($e) => str_contains($e, 'field')));
        $this->assertTrue((bool) array_filter($errors, fn ($e) => str_contains($e, 'operator')));
    }

    public function test_empty_conditions_are_valid(): void
    {
        $this->assertSame([], WorkflowContract::validateConditions(null));
        $this->assertSame([], WorkflowContract::validateConditions([]));
    }

    public function test_doctor_is_clean_when_trigger_action_and_fields_are_registered(): void
    {
        app(WorkflowRegistry::class)->trigger('t.evt', 'E', fn () => []);
        app(WorkflowRegistry::class)->registerAction(new DoctorSpyAction);
        app(RuleRegistry::class)->field('t.field', fn ($c) => 1);

        Workflow::create([
            'name' => 'ok',
            'trigger' => 't.evt',
            'action' => 'doctor.spy',
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 't.field', 'operator' => '==', 'value' => 1],
            ]],
            'enabled' => true,
        ]);

        $this->assertSame([], app(PlatformDoctor::class)->diagnose());
    }

    public function test_doctor_flags_unregistered_trigger_action_and_field(): void
    {
        Workflow::create([
            'name' => 'orphan',
            'trigger' => 'ghost.trigger',
            'action' => 'ghost.action',
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 'ghost.field', 'operator' => '==', 'value' => 1],
            ]],
            'enabled' => true,
        ]);

        $issues = collect(app(PlatformDoctor::class)->diagnose())->pluck('issue');

        $this->assertTrue($issues->contains(fn ($i) => str_contains($i, 'ghost.trigger')));
        $this->assertTrue($issues->contains(fn ($i) => str_contains($i, 'ghost.action')));
        $this->assertTrue($issues->contains(fn ($i) => str_contains($i, 'ghost.field')));
    }

    public function test_platform_doctor_command_exit_codes(): void
    {
        // No workflows → healthy.
        $this->artisan('platform:doctor')->assertExitCode(0);

        // An orphan workflow → failure.
        Workflow::create(['name' => 'x', 'trigger' => 'ghost', 'action' => 'ghost', 'enabled' => true]);
        $this->artisan('platform:doctor')->assertExitCode(1);
    }
}

class DoctorSpyAction implements \Modules\Platform\Workflow\WorkflowAction
{
    public function id(): string
    {
        return 'doctor.spy';
    }

    public function label(): string
    {
        return 'Doctor Spy';
    }

    public function run(array $context, array $config): void {}
}
