<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Support\EncryptedColumns;

/**
 * Mã hoá lại mọi giá trị trong DB sang `APP_KEY` hiện tại.
 *
 * Đây là bước GIỮA của việc rotate khoá, và là bước duy nhất không ai nghĩ tới:
 *
 *   1. sinh khoá mới, đặt khoá cũ vào `APP_PREVIOUS_KEYS`
 *   2. **chạy lệnh này**  ← không có bước này thì dữ liệu vẫn nằm dưới khoá cũ
 *   3. bỏ `APP_PREVIOUS_KEYS` đi
 *
 * Bỏ bước 2 thì mọi thứ *trông như* chạy được — `APP_PREVIOUS_KEYS` khiến
 * Laravel vẫn giải mã được, nên không có triệu chứng nào. Rồi tới lúc ai đó dọn
 * biến môi trường thừa, 2FA của toàn bộ staff chết cùng một lúc, nhiều tháng sau
 * lần rotate, không còn ai nối được hai sự kiện với nhau. Xem
 * {@see EncryptedColumns} cho danh sách cột và vì sao nó khai báo tay.
 *
 * **Ba tính chất phải giữ:**
 *
 * - *Chạy lại được.* Giá trị nào đã ở khoá hiện tại thì bỏ qua, không ghi lại.
 *   Nhờ đó chạy nửa chừng bị ngắt thì chỉ cần chạy lại.
 * - *Không bao giờ phá dữ liệu.* Giá trị không giải được bằng BẤT KỲ khoá nào
 *   (hiện tại lẫn cũ) thì **để nguyên** và báo ra, chứ không ghi đè bằng rác.
 *   Cùng nguyên tắc với bản vá 2FA ở đợt nâng 1.5.
 * - *Từng bản ghi một transaction.* Một hàng hỏng không được kéo theo hàng khác.
 */
class ReencryptSecrets extends Command
{
    protected $signature = 'shop:reencrypt
                            {--dry-run : Chỉ đếm và báo, không ghi gì}';

    protected $description = 'Mã hoá lại dữ liệu trong DB sang APP_KEY hiện tại (bước 2 của rotate khoá)';

    private int $rewritten = 0;

    private int $skipped = 0;

    private int $unreadable = 0;

    public function handle(): int
    {
        $current = $this->currentKeyEncrypter();

        if (! $current) {
            $this->error('APP_KEY chưa có — không có gì để mã hoá lại.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line('<comment>--dry-run: không ghi gì.</comment>');
        }

        foreach (EncryptedColumns::all() as $spec) {
            $this->process($spec, $current);
        }

        $this->newLine();
        $this->line("Đã mã hoá lại: <info>{$this->rewritten}</info> · đã ở khoá hiện tại: {$this->skipped}");

        if ($this->unreadable > 0) {
            // Không phải cảnh báo suông: đây là dữ liệu sẽ chết lặng lẽ khi bỏ
            // APP_PREVIOUS_KEYS, nên nó phải chặn quy trình lại.
            $this->error(
                "{$this->unreadable} giá trị KHÔNG giải được bằng khoá nào — đã để nguyên. ".
                'Đừng bỏ APP_PREVIOUS_KEYS cho tới khi xử lý xong (thường là bắt staff bật lại 2FA).'
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{table: string, key: string, columns: list<string>}  $spec
     */
    private function process(array $spec, Encrypter $current): void
    {
        if (! Schema::hasTable($spec['table'])) {
            $this->line("  <fg=gray>·</> {$spec['table']} — chưa có bảng, bỏ qua.");

            return;
        }

        $columns = array_values(array_filter(
            $spec['columns'],
            fn (string $c) => Schema::hasColumn($spec['table'], $c),
        ));

        if ($columns === []) {
            return;
        }

        DB::table($spec['table'])
            ->select([$spec['key'], ...$columns])
            ->orderBy($spec['key'])
            ->chunk(200, function ($rows) use ($spec, $columns, $current): void {
                foreach ($rows as $row) {
                    $this->processRow($spec, $columns, $current, $row);
                }
            });
    }

    /**
     * @param  array{table: string, key: string, columns: list<string>}  $spec
     * @param  list<string>  $columns
     */
    private function processRow(array $spec, array $columns, Encrypter $current, object $row): void
    {
        $updates = [];

        foreach ($columns as $column) {
            $value = $row->{$column} ?? null;

            if (blank($value)) {
                continue;
            }

            // Đã đọc được bằng RIÊNG khoá hiện tại → không phải làm gì. Đây là
            // thứ khiến lệnh chạy lại được.
            if ($this->decryptable($current, $value)) {
                $this->skipped++;

                continue;
            }

            // Crypt facade mang theo cả APP_PREVIOUS_KEYS, nên đây là lần thử
            // cuối cùng: đọc bằng khoá cũ rồi viết lại bằng khoá mới.
            try {
                $plain = Crypt::decrypt($value, false);
            } catch (DecryptException) {
                $this->unreadable++;
                $this->line("  <fg=red>✗</> {$spec['table']}#{$row->{$spec['key']}}.{$column} — không giải được, giữ nguyên.");

                continue;
            }

            $updates[$column] = $current->encrypt($plain, false);
        }

        if ($updates === []) {
            return;
        }

        $this->rewritten += count($updates);

        if ($this->option('dry-run')) {
            return;
        }

        DB::transaction(fn () => DB::table($spec['table'])
            ->where($spec['key'], $row->{$spec['key']})
            ->update($updates));
    }

    /**
     * Một Encrypter chỉ biết khoá HIỆN TẠI.
     *
     * Không dùng `Crypt` được cho phép thử này: facade nạp cả
     * `APP_PREVIOUS_KEYS`, nên nó giải được cả giá trị khoá cũ và câu hỏi "đã ở
     * khoá mới chưa" luôn trả lời CÓ — lệnh sẽ không làm gì và báo thành công.
     */
    private function currentKeyEncrypter(): ?Encrypter
    {
        $key = (string) config('app.key');

        if ($key === '') {
            return null;
        }

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return new Encrypter($key, config('app.cipher'));
    }

    private function decryptable(Encrypter $encrypter, string $value): bool
    {
        try {
            $encrypter->decrypt($value, false);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
