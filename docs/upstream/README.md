# Hai lỗi của Lunar và cách dự án sống chung

> Tìm ra trong đợt nâng Lunar 1.3 → 1.5
> ([../guides/upgrade-lunar-1.5.md](../guides/upgrade-lunar-1.5.md)). Cả hai là lỗi
> của **upstream**, không phải của dự án.
> Cập nhật: **2026-08-27**, đối chiếu `lunarphp/lunar` nhánh `1.x` tại `77bd9c5`.

**Quyết định: không fork Lunar.** Cả hai lỗi được xử lý ngay trong repo này, bằng
điểm mở rộng chính chủ, tương thích nguyên vẹn với `"lunarphp/lunar": "^1.5"`.
Hai file `.patch` kèm đây giữ lại làm **bằng chứng kỹ thuật** — đủ để mở issue
upstream mà không cần fork, và để đối chiếu nếu bản vá chính thức xuất hiện.

---

## Lỗi 1 — `translate()` trả về chuỗi rỗng

`HasTranslations::translate()` phân giải locale bằng
`Arr::get($values, $locale, $default)`. Khi key **tồn tại nhưng rỗng**,
`Arr::get()` trả chính giá trị rỗng đó chứ không dùng default — nên nhánh fallback
không bao giờ chạy. Shop chạy `vi` mà option chỉ đặt tên tiếng Anh sẽ hiện nhãn
trống, cả ở storefront lẫn variant builder trong admin.

Test `can fallback to existing translation when current is missing` của Lunar
**đang xanh** mà vẫn không bắt được: nó chỉ truyền locale tường minh trong khi app
locale vẫn là `en`, nên lượt tra thứ hai tình cờ rơi vào giá trị có nội dung. Bug
chỉ lộ khi locale rỗng **chính là** locale ứng dụng.

Đáng chú ý: `translateAttribute()` của chính Lunar **đã** bỏ qua field rỗng. Đây là
điểm bất nhất giữa hai hàm chị em, không phải hành vi cố ý.

### Dự án xử lý thế nào

Trước đây: `composer patch` lên `lunarphp/core` — **nấc cuối** của thang mở rộng
([../README.md](../README.md) §1), và làm `composer update` fail cứng mỗi khi
upstream đụng vào method đó.

Nay: [`Modules\Core\Support\Concerns\SkipsEmptyTranslations`](../../modules/Core/app/Support/Concerns/SkipsEmptyTranslations.php),
gắn qua `ModelManifest::replace()` trong `CatalogServiceProvider`. Không đụng
`vendor/`, không ràng buộc lúc cài đặt.

**Phạm vi là bốn model, và đó là toàn bộ bề mặt.** Chỉ bốn bảng có cột `name` kiểu
JSON — `lunar_product_options`, `lunar_product_option_values`, `lunar_attributes`,
`lunar_attribute_groups`. Mọi model khác giữ tên trong `attribute_data` và đọc qua
`translateAttribute()`, vốn đã bỏ qua giá trị rỗng ở upstream.

Phủ bởi `tests/Feature/EmptyTranslationFallbackTest.php` (12 test), trong đó có một
ca canh **không cho `patches/` quay lại** — nếu cả hai cùng tồn tại thì hai bản vá
chồng nhau và `composer update` lại fail như cũ.

---

## Lỗi 2 — nâng lên 1.5 khoá staff ra khỏi admin

`2025_06_20_100000_rename_two_factor_columns_on_staff_table` đổi tên cột do
`lunarphp/filament3-2fa` để lại nhưng **không đụng tới giá trị**, trong khi hai bên
lưu khác định dạng:

| | Ghi | Đọc |
|---|---|---|
| `lunarphp/filament3-2fa` | `encrypt($secret)` — **có** serialize | `decrypt($v)` |
| Filament v4 | cast `'encrypted'` | `decrypt($v, false)` — **không** serialize |

Filament đọc secret cũ ra đúng chuỗi `s:16:"JBSWY3DPEHPK3PXP";`. Mọi staff từng bật
2FA mất đường vào panel. Và không hỏng lịch sự: chuỗi bọc đó không phải base32 hợp
lệ, Google2FA ném `InvalidCharactersException`, `AppAuthentication::verifyCode()`
không bắt — màn hình MFA **500** thay vì báo mã sai.

### Dự án xử lý thế nào

Migration riêng:
[`database/migrations/2026_08_27_120000_reencrypt_staff_app_authentication_columns.php`](../../database/migrations/2026_08_27_120000_reencrypt_staff_app_authentication_columns.php).
Không đụng `vendor/` — đây là migration của ứng dụng, chạy sau migration đổi tên của
Lunar.

Nhận diện **theo nội dung chứ không theo cờ**: chỉ ghi lại giá trị nào giải mã ra
một chuỗi PHP-serialized. Nhờ vậy chạy lại được nhiều lần và an toàn trên bảng lẫn
lộn cũ/mới. Phủ bởi `tests/Feature/StaffTwoFactorReencryptionTest.php` (7 test).

---

## Nếu muốn báo lên upstream

Không cần fork để **mở issue**. Hai file `.patch` ở thư mục này là bản vá hoàn
chỉnh, đã kiểm chứng trên chính suite của Lunar:

| Patch | Gói | Test của Lunar |
|---|---|---|
| `0001-…-skip-an-empty-translation…` | `lunarphp/core` | core 598 passed |
| `0002-…-re-encrypt-carried-over-2FA-secrets…` | `lunarphp/lunar` | admin 229 passed |

Cả hai `git am` sạch lên clone `1.x` mới tinh, và Pint theo chuẩn của họ đều đạt.
Đính kèm vào issue là đủ để maintainer tự áp.

**Vì sao đáng báo:** lỗi 1 đụng mọi cửa hàng đa ngôn ngữ có locale để trống — càng
dễ gặp ở shop không lấy tiếng Anh làm ngôn ngữ chính. Lỗi 2 đụng **mọi** người đi
từ Lunar ≤ 1.4 lên 1.5 mà có staff bật 2FA; upgrade guide không có một dòng nào
nhắc tới, và triệu chứng (500 ở màn hình MFA) không hề gợi ý nguyên nhân là định
dạng mã hoá.

### Khi bản vá chính thức xuất hiện

- Lỗi 1: gỡ `SkipsEmptyTranslations` khỏi bốn model, và gỡ ba model chỉ tồn tại để
  mang trait (`ProductOptionValue`, `Attribute`, `AttributeGroup`) cùng ba dòng
  `ModelManifest::replace()` tương ứng. Giữ `ProductOption` — nó còn mang
  `display_type`. Bỏ luôn `EmptyTranslationFallbackTest`.
- Lỗi 2: migration của mình thành thừa, nhưng **an toàn khi chạy trùng** (nhận diện
  theo nội dung nên lần hai là no-op). Chỉ bỏ khi dựng lại DB từ đầu.
