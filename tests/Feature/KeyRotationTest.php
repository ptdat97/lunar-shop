<?php

namespace Tests\Feature;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lunar\Core\Models\Staff;
use Modules\Core\Support\CompromisedKeys;
use Modules\Core\Support\EncryptedColumns;
use Tests\TestCase;

/**
 * Rotate `APP_KEY` (roadmap P0 §1).
 *
 * Khoá cũ nằm trong git history, nên phải đổi. Đổi khoá thì phần dễ là sinh khoá
 * mới; phần giết người là **dữ liệu đã mã hoá trong DB không giải được nữa** —
 * với shop này là 2FA của staff, và nó không hỏng lịch sự: secret giải ra rác
 * không phải base32 nên Google2FA ném `InvalidCharactersException` và màn hình
 * 2FA **500** chứ không báo "mã sai".
 *
 * Hai chốt được kiểm ở đây:
 *
 * - `shop:reencrypt` chuyển được dữ liệu sang khoá mới, chạy lại được, và
 *   **không bao giờ phá** giá trị nó không đọc được;
 * - `shop:preflight` chặn deploy production khi khoá đang chạy là khoá đã cháy.
 */
class KeyRotationTest extends TestCase
{
    private const SECRET = 'JBSWY3DPEHPK3PXP';

    /** @var list<string> */
    private const RECOVERY = ['aaaa-bbbb', 'cccc-dddd'];

    private function staffTable(): string
    {
        return config('lunar.database.table_prefix').'staff';
    }

    private function newKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
    }

    private function encrypterFor(string $key): Encrypter
    {
        return new Encrypter(base64_decode(substr($key, 7)), config('app.cipher'));
    }

    /**
     * Một staff có 2FA, các cột được ghi bằng $key.
     */
    private function staffWith2FA(string $key): Staff
    {
        $staff = Staff::factory()->create(['admin' => true]);
        $crypt = $this->encrypterFor($key);

        DB::table($this->staffTable())->where('id', $staff->id)->update([
            'app_authentication_secret' => $crypt->encrypt(self::SECRET, false),
            'app_authentication_recovery_codes' => $crypt->encrypt(json_encode(self::RECOVERY), false),
        ]);

        return $staff;
    }

    /** Đổi khoá đang chạy, giữ khoá cũ ở previous_keys như quy trình rotate. */
    private function rotateTo(string $new, ?string $previous = null): void
    {
        config([
            'app.key' => $new,
            'app.previous_keys' => $previous ? [$previous] : [],
        ]);

        // Encrypter là singleton đã bind với khoá cũ — không quên cái này thì
        // test đang đo một Crypt vẫn đang dùng khoá cũ.
        $this->app->forgetInstance('encrypter');
    }

    private function rawSecret(Staff $staff): ?string
    {
        return DB::table($this->staffTable())->where('id', $staff->id)->value('app_authentication_secret');
    }

    // ------------------------------------------------------------- reencrypt

    public function test_rotating_the_key_without_reencrypting_breaks_two_factor(): void
    {
        $old = $this->newKey();
        $staff = $this->staffWith2FA($old);

        // Khoá mới, KHÔNG khai báo khoá cũ — đúng cái xảy ra khi ai đó chỉ chạy
        // `key:generate` rồi deploy.
        $this->rotateTo($this->newKey());

        // NÉM, không phải trả null. Đây chính là lý do màn hình 2FA trả 500 chứ
        // không báo "mã sai": không có gì bắt exception này trên đường đăng nhập.
        $this->expectException(DecryptException::class);

        $staff->fresh()->app_authentication_secret;
    }

    public function test_reencrypt_moves_the_data_onto_the_new_key(): void
    {
        $old = $this->newKey();
        $staff = $this->staffWith2FA($old);
        $new = $this->newKey();

        $this->rotateTo($new, $old);
        $this->artisan('shop:reencrypt')
            ->expectsOutputToContain('Đã mã hoá lại: 2')
            ->assertSuccessful();

        // Bỏ khoá cũ đi — đây là bước 4 của quy trình, và là lúc một lần
        // reencrypt bị bỏ sót sẽ nổ.
        $this->rotateTo($new);

        $fresh = $staff->fresh();
        $this->assertSame(self::SECRET, $fresh->app_authentication_secret);
        $this->assertSame(self::RECOVERY, $fresh->app_authentication_recovery_codes);
    }

    public function test_reencrypt_can_be_run_twice(): void
    {
        $old = $this->newKey();
        $staff = $this->staffWith2FA($old);
        $new = $this->newKey();

        $this->rotateTo($new, $old);
        $this->artisan('shop:reencrypt')->assertSuccessful();

        $after = $this->rawSecret($staff);

        // Lần hai không được ghi lại gì: giá trị đã ở khoá hiện tại.
        $this->artisan('shop:reencrypt')->assertSuccessful();

        $this->assertSame($after, $this->rawSecret($staff));
        $this->rotateTo($new);
        $this->assertSame(self::SECRET, $staff->fresh()->app_authentication_secret);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $old = $this->newKey();
        $staff = $this->staffWith2FA($old);
        $before = $this->rawSecret($staff);

        $this->rotateTo($this->newKey(), $old);
        $this->artisan('shop:reencrypt --dry-run')->assertSuccessful();

        $this->assertSame($before, $this->rawSecret($staff));
    }

    public function test_a_value_no_key_can_read_is_kept_not_destroyed(): void
    {
        $staff = $this->staffWith2FA($this->newKey());
        $before = $this->rawSecret($staff);

        // Khoá cũ THẤT LẠC: chỉ có khoá mới. Ghi đè bằng rác ở đây là phá dữ
        // liệu; thà để nguyên và bắt staff bật lại 2FA.
        $this->rotateTo($this->newKey());

        $this->artisan('shop:reencrypt')->assertFailed();

        $this->assertSame($before, $this->rawSecret($staff));
    }

    public function test_reencrypt_leaves_staff_without_two_factor_alone(): void
    {
        $old = $this->newKey();
        Staff::factory()->create(['admin' => true]);

        $this->rotateTo($this->newKey(), $old);

        $this->artisan('shop:reencrypt')->assertSuccessful();

        $this->assertNull($this->rawSecret(Staff::firstOrFail()));
    }

    // -------------------------------------------------------------- preflight

    public function test_preflight_blocks_production_on_a_compromised_key(): void
    {
        $burned = $this->newKey();

        config([
            'app.key' => $burned,
            'security.compromised_app_keys' => [CompromisedKeys::digest($burned)],
        ]);
        $this->app->detectEnvironment(fn () => 'production');

        // Exit code KHÔNG đủ để cô lập chốt này: preflight ở production còn
        // trượt vì https/debug, nên `assertFailed()` xanh kể cả khi chốt khoá
        // cháy bị gỡ hẳn. Phải khẳng định đúng dòng của nó được in ra.
        $this->artisan('shop:preflight --env-only')
            ->expectsOutputToContain('nằm trong git history')
            ->assertFailed();
    }

    public function test_preflight_passes_once_the_key_is_rotated(): void
    {
        $burned = $this->newKey();

        config([
            'app.key' => $this->newKey(),
            'security.compromised_app_keys' => [CompromisedKeys::digest($burned)],
        ]);
        $this->app->detectEnvironment(fn () => 'production');

        // Vẫn có thể trượt vì lý do KHÁC (https, debug…), nên khẳng định đúng
        // điều đang kiểm: phần GIẢI THÍCH của chốt này không còn được in ra.
        // `report()` chỉ in detail cho dòng trượt; dòng đạt chỉ in tên chốt.
        $this->artisan('shop:preflight --env-only')
            ->doesntExpectOutputToContain('nằm trong git history');
    }

    public function test_a_compromised_key_is_recognised_in_either_form(): void
    {
        $key = $this->newKey();
        $raw = base64_decode(substr($key, 7));

        config(['security.compromised_app_keys' => [CompromisedKeys::digest($key)]]);

        // `base64:…` và byte thuần phải cho cùng một digest, nếu không chốt sẽ
        // trượt đúng cái khoá nó sinh ra để bắt.
        $this->assertTrue(CompromisedKeys::includes($key));
        $this->assertTrue(CompromisedKeys::includes($raw));
        $this->assertFalse(CompromisedKeys::includes($this->newKey()));
        $this->assertFalse(CompromisedKeys::includes(null));
    }

    public function test_the_shipped_list_flags_the_key_that_leaked(): void
    {
        // Danh sách mặc định phải có đúng một dòng — khoá trong .env đã commit.
        // Dòng này rụng đi (ai đó "dọn" config) là chốt biến mất mà không ai hay.
        $this->assertNotEmpty(
            config('security.compromised_app_keys'),
            'danh sách khoá cháy không được rỗng: khoá cũ vẫn nằm trong git history',
        );
    }

    /**
     * Không file nào ĐANG được commit được phép mang một khoá đã cháy.
     *
     * Phép kiểm này sinh ra từ một lần bỏ sót thật: đợt rà soát đầu tiên chỉ
     * quét `.env` và kết luận khoá "chỉ nằm trong git history" — trong khi
     * `.env.example`, một file **đang tracked ở HEAD**, mang đúng khoá đó. Ai
     * clone repo cũng có nó, không cần đào history.
     *
     * Quét mọi chuỗi hình dạng khoá Laravel trong file đã tracked, băm, đối
     * chiếu với danh sách cháy. Rẻ, và bắt đúng lớp lỗi mà mắt người vừa trượt.
     */
    public function test_no_tracked_file_carries_a_compromised_key(): void
    {
        $tracked = trim((string) shell_exec('cd '.escapeshellarg(base_path()).' && git ls-files 2>/dev/null'));

        if ($tracked === '') {
            $this->markTestSkipped('Không đọc được danh sách file tracked (không phải git repo?).');
        }

        $offenders = [];

        foreach (explode("\n", $tracked) as $relative) {
            $path = base_path(trim($relative));

            if (! is_file($path) || filesize($path) > 512 * 1024) {
                continue;
            }

            $contents = (string) file_get_contents($path);

            // Khoá Laravel: `base64:` + 44 ký tự base64 (32 byte).
            if (! preg_match_all('/base64:[A-Za-z0-9+\/]{42,45}=*/', $contents, $matches)) {
                continue;
            }

            foreach ($matches[0] as $candidate) {
                if (CompromisedKeys::includes($candidate)) {
                    $offenders[] = $relative;
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            'File đang commit mang khoá đã cháy — thay bằng chỗ trống, `key:generate` sẽ điền.',
        );
    }

    // ------------------------------------------------- danh sách cột không lệch

    /**
     * `EncryptedColumns` khai báo tay, nên phải có thứ bắt nó lệch.
     *
     * Quét source tìm mọi cast `encrypted` và đối chiếu với những gì registry
     * khẳng định là đã phủ. Một model mới mang cột mã hoá mà quên khai báo thì
     * `shop:reencrypt` bỏ sót nó — và lần đầu biết là lúc có người không đăng
     * nhập được, nhiều tháng sau lần rotate.
     */
    public function test_every_encrypted_cast_is_covered_by_the_registry(): void
    {
        $roots = [base_path('modules'), base_path('app'), base_path('vendor/lunarphp')];
        $found = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $source = file_get_contents($file->getPathname());

                if (! str_contains($source, "=> 'encrypted")) {
                    continue;
                }

                preg_match_all("/'([a-z0-9_]+)'\s*=>\s*'encrypted(?::\w+)?'/i", $source, $matches);

                foreach ($matches[1] as $attribute) {
                    $found[$file->getPathname()][] = $attribute;
                }
            }
        }

        $this->assertNotEmpty($found, 'không quét thấy cast encrypted nào — phép kiểm này đang không kiểm gì');

        $covered = collect(EncryptedColumns::covered())->flatten()->all();

        foreach ($found as $path => $attributes) {
            foreach ($attributes as $attribute) {
                $this->assertContains(
                    $attribute,
                    $covered,
                    "Cột mã hoá [{$attribute}] trong {$path} chưa được EncryptedColumns khai báo — ".
                    'shop:reencrypt sẽ bỏ sót nó khi rotate APP_KEY.',
                );
            }
        }
    }

    public function test_the_registry_points_at_columns_that_exist(): void
    {
        foreach (EncryptedColumns::all() as $spec) {
            $this->assertTrue(
                Schema::hasTable($spec['table']),
                "bảng [{$spec['table']}] không tồn tại",
            );

            foreach ($spec['columns'] as $column) {
                $this->assertTrue(
                    Schema::hasColumn($spec['table'], $column),
                    "cột [{$spec['table']}.{$column}] không tồn tại",
                );
            }
        }
    }
}
