<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Models\Staff;
use Lunar\Panel\Auth\AppAuthentication;
use PragmaRX\Google2FA\Exceptions\InvalidCharactersException;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Covers database/migrations/..._reencrypt_staff_app_authentication_columns.php.
 *
 * lunarphp/filament3-2fa wrote the TOTP secret with encrypt(), which serializes;
 * the replacement reads it through the 'encrypted' cast, which does not. Lunar's
 * own migration only renames the columns, so without this fix every staff member
 * who had 2FA enabled is locked out after the upgrade.
 *
 * The Lunar 2.0 panel does its own 2FA (`Lunar\Panel\Auth\AppAuthentication`,
 * pragmarx/google2fa) rather than Filament's, and this is what confirms the fix
 * carried across: it reads the SAME `lunar_staff.app_authentication_*` columns
 * through the SAME `encrypted` / `encrypted:array` casts, so a secret repaired
 * for Filament v4 is exactly what the panel expects. Only the accessors changed
 * — the columns are read directly now, and `verifyCode()` takes the secret
 * first.
 */
class StaffTwoFactorReencryptionTest extends TestCase
{
    private const SECRET = 'JBSWY3DPEHPK3PXP';

    /** @var array<int, string> */
    private const RECOVERY = ['aaaa-bbbb', 'cccc-dddd'];

    private function table(): string
    {
        return config('lunar.database.table_prefix').'staff';
    }

    /** Write the columns exactly the way the retired package did. */
    private function staffWithLegacySecret(): Staff
    {
        $staff = Staff::factory()->create(['admin' => true]);

        DB::table($this->table())->where('id', $staff->id)->update([
            'app_authentication_secret' => encrypt(self::SECRET),
            'app_authentication_recovery_codes' => encrypt(json_encode(self::RECOVERY)),
        ]);

        return $staff->fresh();
    }

    private function runMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_08_27_120000_reencrypt_staff_app_authentication_columns.php'
        );

        $migration->up();
    }

    public function test_a_legacy_secret_is_unreadable_before_the_migration(): void
    {
        $staff = $this->staffWithLegacySecret();

        // This is the bug: the serialized wrapper comes back as the "secret".
        $this->assertSame(
            's:16:"'.self::SECRET.'";',
            $staff->app_authentication_secret,
        );
        $this->assertNull($staff->app_authentication_recovery_codes);
    }

    public function test_the_migration_makes_the_secret_readable(): void
    {
        $staff = $this->staffWithLegacySecret();

        $this->runMigration();

        $staff = $staff->fresh();

        $this->assertSame(self::SECRET, $staff->app_authentication_secret);
        $this->assertSame(self::RECOVERY, $staff->app_authentication_recovery_codes);
    }

    public function test_it_leaves_an_already_converted_row_alone(): void
    {
        $staff = $this->staffWithLegacySecret();

        $this->runMigration();
        $afterFirst = DB::table($this->table())->where('id', $staff->id)->first();

        // Running it twice must not wrap the value again.
        $this->runMigration();

        $this->assertSame(self::SECRET, $staff->fresh()->app_authentication_secret);

        // The ciphertext differs (random IV) but the plaintext must not.
        $afterSecond = DB::table($this->table())->where('id', $staff->id)->first();
        $this->assertSame(
            Crypt::decryptString($afterFirst->app_authentication_secret),
            Crypt::decryptString($afterSecond->app_authentication_secret),
        );
    }

    public function test_staff_without_two_factor_are_untouched(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);

        $this->runMigration();

        $row = DB::table($this->table())->where('id', $staff->id)->first();

        $this->assertNull($row->app_authentication_secret);
        $this->assertNull($row->app_authentication_recovery_codes);
    }

    /** A value encrypted under a different APP_KEY must not be destroyed. */
    public function test_an_undecryptable_value_is_left_in_place(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);
        $garbage = base64_encode('not-a-laravel-payload');

        DB::table($this->table())->where('id', $staff->id)
            ->update(['app_authentication_secret' => $garbage]);

        $this->runMigration();

        $this->assertSame(
            $garbage,
            DB::table($this->table())->where('id', $staff->id)->value('app_authentication_secret'),
        );
    }

    /**
     * The decisive one: reading the right string back is not the same as being
     * able to log in. Drive the panel's own verifier with a code generated from
     * the secret the staff member's authenticator app still holds.
     */
    public function test_a_code_from_the_original_secret_validates_after_the_migration(): void
    {
        $staff = $this->staffWithLegacySecret();

        $this->runMigration();

        // The code the authenticator app on the staff member's phone shows.
        $code = app(Google2FA::class)->getCurrentOtp(self::SECRET);

        $this->assertTrue(
            app(AppAuthentication::class)->verifyCode($staff->fresh()->app_authentication_secret, $code),
            'After the migration the existing authenticator app must work again.',
        );
    }

    /**
     * And what the staff member hits without the migration: the serialized
     * wrapper is not valid base32, so Google2FA throws instead of returning
     * false. AppAuthentication::verifyCode() does not catch it — so the MFA
     * challenge 500s rather than politely saying the code is wrong. The panel's
     * verifier behaves the same way, which is why the migration still matters.
     */
    public function test_without_the_migration_the_mfa_challenge_errors_out(): void
    {
        $staff = $this->staffWithLegacySecret();

        $code = app(Google2FA::class)->getCurrentOtp(self::SECRET);

        $this->expectException(InvalidCharactersException::class);

        app(AppAuthentication::class)->verifyCode($staff->app_authentication_secret, $code);
    }
}
