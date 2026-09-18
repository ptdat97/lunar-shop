<?php

namespace Modules\Core\Support;

/**
 * Những `APP_KEY` đã bị lộ và không được phép dùng lại.
 *
 * ## Vì sao là DIGEST chứ không phải giá trị
 *
 * Danh sách nằm trong repo, và repo là nơi khoá bị lộ ngay từ đầu. Lưu giá trị
 * thật ở đây là chép lại đúng sai lầm cũ vào một file mới — lần này còn cố ý.
 * SHA-256 trả lời được đúng câu hỏi cần trả lời (*"khoá đang chạy có phải khoá
 * đã cháy không?"*) mà không mang theo khoá.
 *
 * ## Vì sao cần tồn tại
 *
 * Rotate khoá là việc làm TAY, và việc làm tay thì quên được. Không có chốt này
 * thì cách duy nhất phát hiện production vẫn chạy khoá cũ là có người nhớ ra —
 * mà "có người nhớ ra" chính là thứ đã để khoá nằm trong git history suốt từ
 * đầu. `shop:preflight` biến nó thành một cổng: còn khoá cháy thì không deploy.
 *
 * ## Vì sao đọc từ config
 *
 * Người vận hành thêm được khoá cháy của riêng họ (một lần lỡ dán vào Slack, một
 * file backup rò ra) mà không phải sửa code — và nhờ đó chốt này cũng test được
 * mà không cần nhét một khoá thật vào test.
 */
class CompromisedKeys
{
    /**
     * Khoá này có nằm trong danh sách cháy không.
     */
    public static function includes(?string $key): bool
    {
        if (blank($key)) {
            return false;
        }

        return in_array(self::digest($key), self::digests(), true);
    }

    /** @return list<string> */
    public static function digests(): array
    {
        return array_values(array_filter((array) config('security.compromised_app_keys', [])));
    }

    /**
     * Digest dùng để đối chiếu — cũng là hàm dùng khi thêm một khoá mới vào danh sách.
     *
     * Băm **byte khoá sau khi giải base64**, không băm chuỗi env thô. Cùng một
     * khoá xuất hiện dưới hai hình dạng — `base64:AbC…` trong `.env`, nhưng là
     * byte thuần khi ai đó dán thẳng — và một chốt bỏ sót chỉ vì lệch đúng bảy
     * ký tự tiền tố là một chốt không có tác dụng gì.
     */
    public static function digest(string $key): string
    {
        return hash('sha256', self::normalise($key));
    }

    private static function normalise(string $key): string
    {
        $key = trim($key);

        if (str_starts_with($key, 'base64:')) {
            // `strict: false`: chuỗi hỏng thì trả về những gì giải được thay vì
            // `false`. Đây là phép so sánh, không phải đường giải mã — một khoá
            // méo vẫn cần băm ra được cái gì đó để so, và nó sẽ không khớp.
            $key = (string) base64_decode(substr($key, 7), false);
        }

        return $key;
    }
}
