<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Staff;
use Modules\Core\Models\ScheduledRun;
use Tests\TestCase;

/**
 * What the scheduler has been doing, readable without a terminal.
 *
 * The rows were already written on every tick, but `schedule:heartbeat` over
 * SSH was the only way to read them. "Did the cron run last night, and did
 * anything fail?" should not require shell access to answer.
 */
class PanelScheduledRunsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
    }

    private function scheduledRun(string $command, bool $ok, ?string $failure = null): ScheduledRun
    {
        return ScheduledRun::create([
            'command' => $command,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(5)->addSeconds(2),
            'runtime_ms' => 2400,
            'ok' => $ok,
            'failure' => $failure,
        ]);
    }

    /**
     * A green wall of successes is exactly where one red row hides, so failures
     * surface first rather than being buried by recency alone.
     */
    public function test_failures_are_listed_before_successes(): void
    {
        $this->scheduledRun('orders:expire-abandoned', ok: true);
        $failed = $this->scheduledRun('media:regenerate', ok: false, failure: 'Queue worker gone');

        $this->get(route('panel.shop.scheduled-runs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.id', $failed->id)
                ->where('rows.0.command', 'media:regenerate'),
            );
    }

    /** Runtime is shown in units a person reads, not raw milliseconds. */
    public function test_runtime_is_rendered_readably(): void
    {
        $this->scheduledRun('a:slow', ok: true);
        ScheduledRun::latest('id')->first()->update(['runtime_ms' => 250]);

        $this->get(route('panel.shop.scheduled-runs.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.runtime', '250 ms'));

        ScheduledRun::latest('id')->first()->update(['runtime_ms' => 2400]);

        $this->get(route('panel.shop.scheduled-runs.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.runtime', '2.4 s'));
    }

    /** It is a log: nothing writes to it from here. */
    public function test_runs_cannot_be_created_or_edited(): void
    {
        $run = $this->scheduledRun('orders:expire-abandoned', ok: true);

        $this->get(route('panel.shop.scheduled-runs.create'))->assertNotFound();

        $this->put(route('panel.shop.scheduled-runs.update', $run->id), ['command' => 'sửa trộm'])
            ->assertNotFound();

        $this->assertSame('orders:expire-abandoned', $run->fresh()->command);
    }

    /** But pruning stays: the table grows on every tick, forever. */
    public function test_a_run_can_be_deleted(): void
    {
        $run = $this->scheduledRun('orders:expire-abandoned', ok: true);

        $this->delete(route('panel.shop.scheduled-runs.destroy', $run->id))->assertRedirect();

        $this->assertNull($run->fresh());
    }

    public function test_it_requires_the_core_settings_permission(): void
    {
        $staff = Staff::factory()->create(['admin' => false]);

        $this->actingAs($staff, 'staff')
            ->get(route('panel.shop.scheduled-runs.index'))
            ->assertForbidden();
    }
}
