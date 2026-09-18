<?php

namespace Modules\Core\Support;

use Lunar\Core\Models\Staff;

/**
 * Mọi cột trong DB đang giữ giá trị mã hoá bằng `APP_KEY`.
 *
 * ## Vì sao danh sách này tồn tại
 *
 * Đổi `APP_KEY` làm hai việc, và chỉ một việc là vô hại. Session cookie hỏng thì
 * khách bị đăng xuất — phiền, hết. Nhưng **giá trị đã mã hoá nằm trong DB thì
 * không giải được nữa**, và đó không phải phiền mà là mất dữ liệu.
 *
 * Với shop này, cột nguy hiểm là 2FA của staff: đổi khoá mà không mã hoá lại thì
 * **mọi staff đang bật 2FA bị khoá vĩnh viễn ra khỏi panel**, và khoá theo kiểu
 * tệ nhất — secret giải ra rác thì không phải base32, Google2FA ném
 * `InvalidCharactersException`, nên màn hình 2FA **500** chứ không nói "mã sai".
 * Dự án đã trả giá đúng lớp lỗi này một lần rồi, ở đợt nâng Lunar 1.5
 * ([docs/upstream/README.md](../../../../docs/upstream/README.md)).
 *
 * ## Vì sao khai báo TAY chứ không dò tự động
 *
 * Dò `$casts` của mọi model lúc chạy nghe hay hơn, nhưng nó im lặng khi dò hụt —
 * mà dò hụt ở đây nghĩa là một cột không được mã hoá lại và không ai biết cho tới
 * lúc có người đăng nhập. Danh sách tay thì lệch được, nên **`EncryptedColumnsTest`
 * quét source tìm mọi cast `encrypted` và bắt danh sách này phải phủ hết**. Lệch
 * là suite đỏ, không phải là một sự cố production.
 */
class EncryptedColumns
{
    /**
     * @return list<array{table: string, key: string, columns: list<string>}>
     */
    public static function all(): array
    {
        $prefix = config('lunar.database.table_prefix', 'lunar_');

        return [
            [
                // Lunar\Core\Models\Staff: 'app_authentication_secret' => 'encrypted',
                // 'app_authentication_recovery_codes' => 'encrypted:array'.
                'table' => $prefix.'staff',
                'key' => 'id',
                'columns' => ['app_authentication_secret', 'app_authentication_recovery_codes'],
            ],
        ];
    }

    /**
     * Cặp (class model, thuộc tính) mà danh sách trên khẳng định là đã phủ.
     *
     * `EncryptedColumnsTest` đối chiếu đúng mảng này với những gì nó quét được
     * trong source — nên thêm một cột vào `all()` mà quên ở đây (hoặc ngược lại)
     * là suite đỏ.
     *
     * @return array<string, list<string>>
     */
    public static function covered(): array
    {
        return [
            Staff::class => [
                'app_authentication_secret',
                'app_authentication_recovery_codes',
            ],
        ];
    }
}
