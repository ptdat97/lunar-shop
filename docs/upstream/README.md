# Bản vá gửi ngược lên Lunar

> Hai lỗi tìm ra trong đợt nâng Lunar 1.3 → 1.5
> ([../guides/upgrade-lunar-1.5.md](../guides/upgrade-lunar-1.5.md)) là lỗi của
> **upstream**, không phải của dự án. Chừng nào chúng chưa được nhận, mình còn
> phải tự gánh: một `composer patch` và một migration riêng.
> Chuẩn bị: **2026-08-27**, trên `lunarphp/lunar` nhánh `1.x` tại `77bd9c5`.

## Trạng thái

| Patch | Gói | Đã gửi? |
|---|---|---|
| `0001-…-skip-an-empty-translation…` | `lunarphp/core` | ⬜ chưa |
| `0002-…-re-encrypt-carried-over-2FA-secrets…` | `lunarphp/lunar` (admin) | ⬜ chưa |

Cập nhật cột này khi gửi, kèm số PR.

---

## Cách gửi

```bash
git clone https://github.com/<tài-khoản-của-bạn>/lunar.git
cd lunar
git checkout 1.x

git checkout -b fix/empty-translation-fallback
git am /đường/dẫn/docs/upstream/0001-*.patch
git push -u origin fix/empty-translation-fallback

git checkout 1.x
git checkout -b fix/reencrypt-two-factor-secrets
git am /đường/dẫn/docs/upstream/0002-*.patch
git push -u origin fix/reencrypt-two-factor-secrets
```

Rồi mở **hai PR riêng** vào `lunarphp/lunar:1.x` — chúng chạm hai package khác
nhau và không phụ thuộc nhau.

Đã kiểm chứng: cả hai `git am` sạch lên bản clone `1.x` mới tinh.

### Chạy test của họ

```bash
composer install
vendor/bin/pest --testsuite=core     # 598 passed
vendor/bin/pest --testsuite=admin    # 229 passed
vendor/bin/pint --test <file đã sửa> # pass
```

---

## Patch 1 — `translate()` trả về chuỗi rỗng

**Tiêu đề PR:** `fix(core): skip an empty translation instead of returning it`

`HasTranslations::translate()` phân giải locale bằng
`Arr::get($values, $locale, $default)`. Khi key **tồn tại nhưng rỗng**,
`Arr::get()` trả chính giá trị rỗng đó chứ không dùng default — nên nhánh fallback
không bao giờ chạy.

Thực tế: một bản ghi chỉ đặt tên tiếng Anh nhưng mang key `fr` rỗng sẽ dịch ra
`''` dưới locale Pháp, và giao diện hiện nhãn trống.

Điều đáng nói: test `can fallback to existing translation when current is missing`
**đang xanh** mà vẫn không bắt được — vì nó chỉ truyền locale tường minh trong khi
locale ứng dụng vẫn là `en`, nên lượt tra thứ hai tình cờ rơi vào giá trị có nội
dung. Bug chỉ lộ khi locale rỗng **chính là** locale ứng dụng.

Bản vá duyệt các locale ưu tiên theo thứ tự và bỏ qua giá trị rỗng, rồi mới lùi về
giá trị không rỗng đầu tiên. Nhờ vậy `translate()` hành xử **giống
`translateAttribute()`**, vốn đã bỏ qua field rỗng sẵn — đây là lập luận mạnh nhất
cho PR: nó xoá một điểm bất nhất giữa hai hàm chị em.

Nhân tiện: chuyển đổi giá trị dạng object ngay từ đầu. Trước đó chỉ lượt tra đầu
đi qua `get_object_vars()`, để lại fallback gọi `Arr::get()` trên một `stdClass`.

Kèm 2 test hồi quy. Đã mutation-check: bỏ bản vá ra thì test đỏ.

**Ảnh hưởng tới dự án mình:** khi PR này được nhận, gỡ
`patches/lunar-core-translations-locale-fallback.patch` và mục `extra.patches`
tương ứng trong `composer.json`. **Đừng giữ cả hai** — bản vá upstream lùi về
`app()->getLocale()` còn patch của mình dùng `config('app.locale')`; giữ song song
là chồng hai hành vi khác nhau.

---

## Patch 2 — nâng cấp lên 1.5 khoá staff ra khỏi admin

**Tiêu đề PR:** `fix(admin): re-encrypt carried over 2FA secrets so staff are not locked out`

`2025_06_20_100000_rename_two_factor_columns_on_staff_table` đổi tên cột do
`lunarphp/filament3-2fa` để lại nhưng **không đụng tới giá trị**, trong khi hai bên
lưu khác định dạng:

| | Ghi | Đọc |
|---|---|---|
| `lunarphp/filament3-2fa` | `encrypt($secret)` — **có** serialize | `decrypt($v)` |
| Filament v4 | cast `'encrypted'` | `decrypt($v, false)` — **không** serialize |

Filament đọc secret cũ ra đúng chuỗi `s:16:"JBSWY3DPEHPK3PXP";`. Mọi staff từng
bật 2FA mất đường vào panel sau khi nâng lên 1.5.

Và nó không hỏng một cách lịch sự: chuỗi bọc đó không phải base32 hợp lệ, Google2FA
ném `InvalidCharactersException`, mà `AppAuthentication::verifyCode()` không bắt —
màn hình MFA **500** thay vì báo mã sai. Recovery codes hỏng y hệt (cũ lưu
`encrypt(json_encode(...))`, cast mới là `'encrypted:array'` → `json_decode` rơi
vào lớp bọc và trả `null`).

Bản vá thêm một migration nối tiếp, giải mã rồi mã hoá lại đúng định dạng cast cần.
**Nhận diện theo nội dung, không theo cờ**: chỉ ghi lại giá trị nào giải mã ra một
chuỗi PHP-serialized. Nhờ vậy chạy lại được nhiều lần và an toàn trên bảng lẫn lộn
cũ/mới — secret base32 hay mảng JSON không thể trông giống payload serialize. Giá
trị không giải mã nổi bằng `APP_KEY` hiện tại thì để nguyên, không phá thứ mình
không đọc được.

Kèm 7 test, trong đó có ca sinh mã TOTP thật từ secret gốc rồi chạy qua chính
verifier của Filament — chứng minh app authenticator trên máy staff vẫn dùng được;
và một ca ghi nhận hành vi khi **thiếu** migration (ném exception, không phải "mã
sai").

**Ảnh hưởng tới dự án mình:** khi PR này được nhận, migration riêng
`database/migrations/2026_08_27_120000_reencrypt_staff_app_authentication_columns.php`
trở nên thừa. Nó **an toàn khi chạy trùng** (nhận diện theo nội dung nên lần hai là
no-op), vậy nên đừng vội xoá ở môi trường đã chạy — chỉ bỏ khi dựng lại từ đầu.

---

## Vì sao đáng gửi

Cả hai đều không phải chuyện riêng của shop này:

- Patch 1 đụng mọi cửa hàng đa ngôn ngữ có locale nào đó để trống — càng dễ gặp ở
  cửa hàng không dùng tiếng Anh làm ngôn ngữ chính.
- Patch 2 đụng **mọi** người đi từ Lunar ≤ 1.4 lên 1.5 mà có staff bật 2FA. Không
  có dòng nào trong upgrade guide nhắc tới, và triệu chứng (500 ở màn hình MFA)
  không hề gợi ý nguyên nhân là định dạng mã hoá.
