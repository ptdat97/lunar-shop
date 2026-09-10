<?php

namespace Tests\Feature;

use Lunar\Core\Models\Staff;
use Modules\Core\Database\Seeders\DemoStaffSeeder;
use Tests\TestCase;

/**
 * The staff account `migrate:fresh --seed` leaves behind.
 *
 * Without it a rebuilt machine cannot open `/panel` at all — there is nobody to
 * log in as. That is not hypothetical: it is how the panel's browser tests came
 * to silently skip themselves after a dev database rebuild, which is a worse
 * outcome than failing, because the suite still reported green.
 *
 * A seeded admin with a known password is also exactly the sort of thing that
 * must never reach a real shop, so the guard is tested as carefully as the
 * feature.
 */
class DemoStaffSeederTest extends TestCase
{
    private function seedDemoStaff(): void
    {
        $this->artisan('db:seed', [
            '--class' => DemoStaffSeeder::class,
            '--no-interaction' => true,
            // `--force` so production's own confirmation prompt is out of the
            // way and what gets tested is the seeder's guard, not Laravel's.
            '--force' => true,
        ])->assertSuccessful();
    }

    public function test_it_seeds_an_admin_that_can_reach_the_panel(): void
    {
        $this->seedDemoStaff();

        $staff = Staff::firstWhere('email', 'admin@lunar-shop.test');

        $this->assertNotNull($staff);
        $this->assertTrue((bool) $staff->admin);

        // The point of the account: the panel opens for it.
        $this->actingAs($staff, 'staff')->get(route('panel.dashboard'))->assertOk();
    }

    /** Re-seeding must not reset a password someone changed. */
    public function test_re_seeding_leaves_an_existing_account_alone(): void
    {
        $this->seedDemoStaff();

        $staff = Staff::firstWhere('email', 'admin@lunar-shop.test');
        $staff->update(['password' => 'mat-khau-da-doi', 'first_name' => 'Đã đổi']);

        $hash = $staff->fresh()->password;

        $this->seedDemoStaff();

        $this->assertSame(1, Staff::where('email', 'admin@lunar-shop.test')->count());
        $this->assertSame($hash, $staff->fresh()->password);
        $this->assertSame('Đã đổi', $staff->fresh()->first_name);
    }

    /**
     * The guard that matters. A known-password admin appearing on a real shop
     * would be a backdoor, so the seeder refuses outside local/testing.
     */
    public function test_it_refuses_to_run_outside_local_and_testing(): void
    {
        app()['env'] = 'production';

        try {
            $this->seedDemoStaff();
        } finally {
            app()['env'] = 'testing';
        }

        $this->assertSame(0, Staff::where('email', 'admin@lunar-shop.test')->count());
    }
}
