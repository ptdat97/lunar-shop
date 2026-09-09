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

Rồi (1.5): một trait `SkipsEmptyTranslations` ghi đè `translate()`, gắn lên bốn
model qua `ModelManifest::replace()`.

Nay (2.0, 2026-09-09): [`Modules\Core\Casts\FilledTranslations`](../../modules/Core/app/Casts/FilledTranslations.php),
cài bằng `Model::addCasts()` trong `CatalogServiceProvider`. Phải đổi vì **Lunar
2.0 gỡ hẳn model replacement** — core gọi thẳng `ProductOptionValue::class` và
`ModelManifest` chỉ còn route binding + morph map. `addCasts()`
(`HasExtendableCasts`) là seam duy nhất 2.0 để lại cho việc này.

Bản vá vì thế **hạ xuống một tầng**: thay vì sửa hàm đọc, nó lọc locale rỗng ngay
lúc decode JSON — thế là `translate()` của upstream trở thành đúng như đang viết,
vì "khoá tồn tại nhưng rỗng" không còn xảy ra được. Rộng hơn cách cũ: mọi model
đọc cột đó đều được vá, không chỉ những model ta nhớ mà subclass.

**Bề mặt đổi cả hai chiều ở 2.0:**

- `lunar_attributes.name` và `lunar_attribute_groups.name` nay là cột `string`
  thường — hết chuyện dịch, rời khỏi danh sách.
- Spec 0018 đưa `name` / `description` / `short_description` của product,
  collection, brand **ra khỏi** `attribute_data` thành cột JSON thật, đọc qua
  `translate()` — gia nhập danh sách.

Phủ bởi `tests/Feature/EmptyTranslationFallbackTest.php` (19 test): mỗi cột trong
danh sách, một ca khẳng định container mỗi model khai báo (`Collection` vs
`ArrayObject`) không bị đổi, một ca nói to rằng `attributes.name` nay là string,
và một ca canh **không cho `patches/` quay lại** — nếu cả hai cùng tồn tại thì hai
bản vá chồng nhau và `composer update` lại fail như cũ.

---

## Lỗi 2 — nâng lên 1.5 khoá staff ra khỏi admin

`2025_06_20_100000_rename_two_factor_columns_on_staff_table` đổi tên cột do
`lunarphp/filament3-2fa` để lại nhưng **không đụng tới giá trị**, trong khi hai bên
lưu khác định dạng:

| | Ghi | Đọc |
|---|---|---|
| `lunarphp/filament3-2fa` | `encrypt($secret)` — **có** serialize | `decrypt($v)` |
| Filament v4 / panel 2.0 | cast `'encrypted'` | `decrypt($v, false)` — **không** serialize |

Bên đọc secret cũ ra đúng chuỗi `s:16:"JBSWY3DPEHPK3PXP";`. Mọi staff từng bật
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

**Vẫn đúng nguyên sau khi lên Lunar 2.0** (kiểm 2026-09-09). Panel tự làm 2FA
(`Lunar\Panel\Auth\AppAuthentication`, pragmarx/google2fa) chứ không dùng của
Filament nữa, nhưng đọc **cùng cột** `lunar_staff.app_authentication_*` qua **cùng
cast** `encrypted` / `encrypted:array`. Chỉ accessor đổi (đọc thẳng thuộc tính) và
`verifyCode()` nhận secret trước, mã sau — test đã chuyển sang verifier của panel.

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

- Lỗi 1: gỡ `guardEmptyTranslations()` khỏi `CatalogServiceProvider` và xoá
  `Modules\Core\Casts\FilledTranslations`. Bỏ luôn `EmptyTranslationFallbackTest`.
  (Vẫn còn nguyên ở `lunarphp/core` 2.0.0-alpha.6 — đã kiểm.)
- Lỗi 2: migration của mình thành thừa, nhưng **an toàn khi chạy trùng** (nhận diện
  theo nội dung nên lần hai là no-op). Chỉ bỏ khi dựng lại DB từ đầu.
