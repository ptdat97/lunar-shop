<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Re-encrypt staff 2FA secrets carried over from lunarphp/filament3-2fa.
 *
 * Lunar 1.5 replaced that package with Filament v4's built-in MFA and shipped a
 * migration that RENAMES the columns (two_factor_secret →
 * app_authentication_secret, two_factor_recovery_codes →
 * app_authentication_recovery_codes). It does not touch the values — and the two
 * implementations do not store them the same way:
 *
 *   old  encrypt($secret)                      → Laravel payload WITH serialize
 *   new  cast 'encrypted'  → decrypt($v, false) → Laravel payload WITHOUT serialize
 *
 * So Filament reads a legacy secret back as the literal string
 * `s:16:"JBSWY3DPEHPK3PXP";` instead of `JBSWY3DPEHPK3PXP`, and every staff
 * member who had 2FA enabled is locked out of the admin panel. It does not even
 * fail politely: that wrapper is not valid base32, Google2FA throws
 * InvalidCharactersException, and AppAuthentication::verifyCode() does not catch
 * it — so the MFA challenge 500s instead of saying the code is wrong. Recovery codes break the same way: the old package stored
 * encrypt(json_encode([...])) while the new cast is 'encrypted:array', so
 * json_decode() lands on the serialized wrapper and yields null.
 *
 * This is not mentioned in Lunar's upgrade guide.
 *
 * Detection is by content, not by a flag, so the migration is safe to re-run and
 * safe on a mixed table: only a value that decrypts to a PHP-serialized string
 * is rewritten. A base32 secret and a JSON array can never look like one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('lunar.database.table_prefix');
        $table = "{$prefix}staff";

        DB::table($table)
            ->where(function ($query) {
                $query->whereNotNull('app_authentication_secret')
                    ->orWhereNotNull('app_authentication_recovery_codes');
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $update = array_filter([
                        'app_authentication_secret' => $this->convert($row->app_authentication_secret),
                        'app_authentication_recovery_codes' => $this->convert($row->app_authentication_recovery_codes),
                    ], fn ($value) => $value !== null);

                    if ($update) {
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                }
            });
    }

    public function down(): void
    {
        // Deliberately irreversible. Rolling the values back to the serialized
        // form would re-break the panel, and the plaintext is unchanged either
        // way — nothing is lost by leaving them in the format Filament reads.
    }

    /**
     * Return the re-encrypted value, or null when the row needs no change.
     */
    private function convert(?string $stored): ?string
    {
        if (blank($stored)) {
            return null;
        }

        try {
            $plain = Crypt::decryptString($stored);
        } catch (Throwable) {
            // Not decryptable with the current APP_KEY — leave it alone rather
            // than destroying a value we cannot read. The staff member will have
            // to re-enrol either way.
            return null;
        }

        $unserialized = @unserialize($plain);

        if (! is_string($unserialized)) {
            return null; // already in the format Filament v4 expects
        }

        return Crypt::encryptString($unserialized);
    }
};
