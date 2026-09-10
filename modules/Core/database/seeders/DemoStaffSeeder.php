<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Core\Models\Staff;

/**
 * A staff account for local development.
 *
 * Without it `migrate:fresh --seed` leaves a machine that cannot open `/panel`
 * at all — there is nobody to log in as. That gap is how the panel's browser
 * tests came to silently skip themselves after a rebuild.
 *
 * Guarded three ways, because a seeded admin with a known password is exactly
 * the sort of thing that must never reach a real shop:
 *
 *  1. It refuses to run outside `local` / `testing`.
 *  2. The credentials come from the environment; the defaults are local-only
 *     and the password is printed to the console rather than hidden.
 *  3. `firstOrCreate`, so re-seeding never resets a password someone changed.
 */
class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('DemoStaffSeeder bỏ qua: chỉ chạy ở môi trường local/testing.');

            return;
        }

        $email = (string) env('DEMO_STAFF_EMAIL', 'admin@lunar-shop.test');
        $password = (string) env('DEMO_STAFF_PASSWORD', 'password');

        $staff = Staff::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Demo',
                'last_name' => 'Admin',
                'admin' => true,
                'password' => $password,
            ],
        );

        if ($staff->wasRecentlyCreated) {
            $this->command?->info("Tài khoản panel: {$email} / {$password}");

            return;
        }

        $this->command?->info("Tài khoản panel đã có: {$email} (mật khẩu giữ nguyên).");
    }
}
