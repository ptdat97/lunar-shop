# Migration Runbook — Lunar 1.3 → 1.5

> Quy trình từng bước nâng `lunarphp/lunar` **1.3.0 → 1.5.0**. Đọc kèm
> [../architecture/overview.md](../architecture/overview.md) và
> [deployment.md](deployment.md).
> Nguồn: [Lunar upgrade guide](https://docs.lunarphp.com/1.x/getting-started/overview/upgrade-guide#1-5)
> + [Filament v4 upgrade guide](https://filamentphp.com/docs/4.x/upgrade-guide).
> Soạn: **2026-08-26**, đối chiếu code tại commit `a4f619b`.

> **TRẠNG THÁI: ĐÃ ĐÓNG — 2026-08-27.** Lunar 1.5.0 + Filament v4.12.6 chạy trên
> `main`, **532 test xanh** (baseline trước nâng cấp: 506). Nghiệm thu cuối ở
> [§18](#18-nghiệm-thu-cuối). Phần dưới giữ nguyên
> dạng runbook; mục [§13 Nhật ký thực thi](#13-nhật-ký-thực-thi) ghi lại những
> gì lệch so với dự kiến — đọc mục đó trước nếu phải làm lại trên môi trường khác.

---

## 0. Đọc cái này trước

**Đây không phải đợt nâng cấp Lunar — đây là đợt nâng cấp Filament.**

Lunar 1.5 kéo theo Filament v4. Phần thay đổi thuộc về Lunar thì nhẹ; ~90% khối
lượng nằm ở **79 file PHP** dưới `modules/*/app/Filament/` và **17 blade view**
phải viết lại theo API v4.

Ba điều quyết định cách chia việc:

1. **Không thể tách làm hai đợt.** Lunar 1.3 khoá `filament/filament: ^3`, nên
   không nâng Filament trước rồi nâng Lunar sau được. Cả hai đi trong **một**
   `composer update`.
2. **Có một cửa sổ app chết.** Từ lúc `composer update` (Fase 3) tới lúc sửa
   xong code (Fase 5) không có mốc test xanh nào ở giữa. Đừng deploy dở dang.
3. **Migration có sửa cấu trúc DB** (đổi tên cột, bỏ cột, `json` → `jsonb`).
   `git revert` **không** đủ để rollback — bắt buộc phải có dump.

Ước lượng: **4–7 ngày công**, gần như toàn bộ nằm ở Fase 5.

---

## 1. Hiện trạng → đích đến

| Thành phần | Đang có | Sau nâng cấp | Ghi chú |
|---|---|---|---|
| `lunarphp/lunar` | `^1.0` → 1.3.0 | `^1.5` | Pin minor, đừng để `^1.0` |
| `filament/filament` | `^3.3.36` → 3.3.54 | `^4.1` | Khối lượng chính |
| `php` | `^8.2` (runtime 8.4.23) | `^8.3` | Runtime đã đủ |
| `laravel/framework` | `^12.0` | `^12.0` | 1.5 nhận `^12.0\|^13.0`, ta giữ 12 |
| `livewire/livewire` | 3.8.2 | 3.8.2 | v4 cần `^3.6` — đã thoả |
| `kalnoy/nestedset` | `^6.0.5` | *(gỡ)* | Thay bằng `lunarphp/nestedset` qua core |
| `lunarphp/filament3-2fa` | 2.1.0 | *(tự rơi)* | Không có trong `composer.json` gốc |
| `awcodes/shout` | `^2.0.4` | `^3.0` | |
| `awcodes/filament-badgeable-column` | `^2.3.2` | `^3.0` | |
| `leandrocfe/filament-apex-charts` | `^3.2.0` | `^5.0` | |
| `filament/spatie-laravel-media-library-plugin` | `^3.3.4` | `^4.0` | |

Bốn gói Filament cuối bảng **chỉ Lunar dùng** — grep toàn `modules/` + `app/`
không có chỗ nào tham chiếu trực tiếp. Chỉ cần bump version, không phải sửa code.

---

## 2. Sáu fase

| # | Fase | Thời lượng | App chạy được? |
|---|---|---|---|
| 0 | [Chuẩn bị & chốt baseline](#3-fase-0--chuẩn-bị--chốt-baseline) | ~1 giờ | ✅ |
| 1 | [Nới nền tảng PHP](#4-fase-1--nới-nền-tảng-php) | ~15 phút | ✅ |
| 2 | [Chạy codemod Filament v4](#5-fase-2--chạy-codemod-filament-v4) | 1–2 giờ | ⚠️ code v4, deps v3 |
| 3 | [Đổi composer một lần duy nhất](#6-fase-3--đổi-composer-một-lần-duy-nhất) | 30 phút | ❌ |
| 4 | [Migrate DB](#7-fase-4--migrate-db) | 15 phút – vài giờ | ❌ |
| 5 | [Sửa tay code](#8-fase-5--sửa-tay-code) | 3–5 ngày | ❌ → ✅ |
| 6 | [Nghiệm thu & rollout](#9-fase-6--nghiệm-thu--rollout) | 1 ngày | ✅ |

---

## 3. Fase 0 — Chuẩn bị & chốt baseline

```bash
# 1. Ghi lại mốc quay lui (dự án làm thẳng trên main, không dùng feature branch)
git log --oneline -1        # ghi hash này lại — §12 Rollback cần nó

# 2. Backup DB (BẮT BUỘC — đây là thứ thay thế vai trò của branch, xem §12)
mysqldump -h127.0.0.1 -uroot --single-transaction --routines --triggers lunar \
  > ../backup-pre-lunar-1.5.sql
ls -lh ../backup-pre-lunar-1.5.sql            # xác nhận file không rỗng
grep -c 'CREATE TABLE' ../backup-pre-lunar-1.5.sql   # phải ra 104
```

> ⚠️ **Sửa `.env` trước khi làm gì khác.** `DB_DATABASE=lunarshop` nhưng database
> đó **không tồn tại** — DB thật tên là `lunar`. Không sửa thì mọi bước
> `php artisan migrate` bên dưới sẽ fail với `Unknown database 'lunarshop'`.
>
> ```dotenv
> DB_DATABASE=lunar
> ```

```bash
# 3. Chốt baseline để về sau đối chiếu
composer test 2>&1 | tee ../baseline-tests.txt
tail -5 ../baseline-tests.txt
```

> ⚠️ **Nếu test đỏ hàng loạt với `SQLSTATE[HY000] [2002] Connection refused`:**
> đó là mysqld chết giữa chừng (DBngin), **không phải lỗi code**. Kiểm chứng:
> `mysql -h127.0.0.1 -uroot -e "SHOW STATUS LIKE 'Uptime'"` — uptime nhỏ hơn thời
> gian chạy test nghĩa là server vừa restart. Chạy lại, đừng đi sửa test.

**Checklist ra khỏi Fase 0**

- [ ] Tree sạch, đã ghi lại hash commit làm mốc quay lui
- [ ] Dump DB nằm **ngoài** repo, dung lượng hợp lý
- [ ] `.env` trỏ đúng DB `lunar`
- [ ] Baseline test đã lưu file, biết chính xác bao nhiêu test xanh/đỏ
- [ ] Đã đọc hết [Filament v4 upgrade guide](https://filamentphp.com/docs/4.x/upgrade-guide)
      — script tự động **không** thay thế được nó

---

## 4. Fase 1 — Nới nền tảng PHP

Tách riêng để nếu có gì vỡ thì biết chắc không phải do Filament.

```diff
  "require": {
-     "php": "^8.2",
+     "php": "^8.3",
```

```bash
composer update --lock
composer test
```

**Gate:** test vẫn xanh như baseline. Vẫn đang ở Lunar 1.3 + Filament 3.
**Commit riêng** — đây là điểm quay lui an toàn cuối cùng.

---

## 5. Fase 2 — Chạy codemod Filament v4

Chạy **trước** khi đổi version composer, khi code còn ở trạng thái v3 hợp lệ.
Script là bộ rule Rector v2.

```bash
composer require filament/upgrade:"^4.0" -W --dev
vendor/bin/filament-v4
```

> ⚠️ **Cấu trúc module.** Code Filament của dự án nằm ở
> `modules/<Name>/app/Filament/`, **không** phải `app/Filament/`. Script mặc định
> chỉ nhìn `app/` — khi nó hỏi đường dẫn phải trỏ vào cả `app` và `modules`.
> Nếu script không nhận, chạy Rector trực tiếp với ruleset của Filament trên
> `modules/`.
>
> **Không** chạy `php artisan filament:upgrade-directory-structure-to-v4` — nó sẽ
> phá bố cục `nwidart/laravel-modules`.

Số chỗ script dự kiến xử lý (đã đếm trong repo):

| Biến đổi | Số chỗ |
|---|---:|
| `Filament\Tables\Actions\*` → `Filament\Actions\*` | 36 |
| `Filament\Forms\Components\Section` → `Filament\Schemas\Components\Section` | 31 |
| `form(Form $form): Form` → `form(Schema $schema): Schema` | 21 |
| `Filament\Forms\Get` / `Set` → `Filament\Schemas\Components\Utilities\*` | 7 |
| `Forms\Components\Grid` / `Actions` → `Schemas\Components\*` | 6 |

```bash
git diff --stat        # đọc TOÀN BỘ diff trước khi commit
```

Rector sửa nhiều hơn những gì guide liệt kê. Đọc kỹ, rồi commit riêng một
commit "codemod" để về sau tách được đâu là máy sửa, đâu là người sửa.

---

## 6. Fase 3 — Đổi composer một lần duy nhất

```diff
  "require": {
-     "awcodes/filament-badgeable-column": "^2.3.2",
+     "awcodes/filament-badgeable-column": "^3.0",
-     "awcodes/shout": "^2.0.4",
+     "awcodes/shout": "^3.0",
-     "filament/filament": "^3.3.36",
+     "filament/filament": "^4.1",
-     "filament/spatie-laravel-media-library-plugin": "^3.3.4",
+     "filament/spatie-laravel-media-library-plugin": "^4.0",
-     "kalnoy/nestedset": "^6.0.5",
-     "leandrocfe/filament-apex-charts": "^3.2.0",
+     "leandrocfe/filament-apex-charts": "^5.0",
-     "lunarphp/lunar": "^1.0",
+     "lunarphp/lunar": "^1.5",
```

Ba điểm dễ nhầm:

- **`kalnoy/nestedset` bị xoá hẳn**, không thay bằng dòng khác. `lunarphp/nestedset`
  đến qua `lunarphp/core`. Grep toàn dự án: **0 chỗ** dùng namespace `Kalnoy` →
  an toàn.
- **`lunarphp/filament3-2fa` không cần `composer remove`** — nó không nằm trong
  `composer.json` gốc, chỉ là dependency của Lunar 1.3, sẽ tự biến mất.
- **Pin `^1.5`**, đừng để `^1.0` — nếu không, lần `composer update` sau sẽ tự
  nhảy sang minor có breaking change.

```bash
composer update
composer remove filament/upgrade --dev
php artisan optimize:clear
```

> ⚠️ **Nếu `composer update` fail ở bước apply patch:** dự án có một patch
> `cweagans` đè lên `lunarphp/core`
> (`patches/lunar-core-translations-locale-fallback.patch`). Xem [§10 Rủi ro #3](#103-patch-composer-đè-lên-lunarphpcore).

**Gate:** `composer update` chạy trót lọt, `vendor/lunarphp/core` là 1.5.0.
App chưa lên được — **bình thường**, đúng như thiết kế.

---

## 7. Fase 4 — Migrate DB

```bash
php artisan migrate
```

Các migration đáng chú ý của 1.4 + 1.5:

| Migration | Việc nó làm | Rủi ro |
|---|---|---|
| `rename_two_factor_columns_on_staff_table` | `two_factor_secret` → `app_authentication_secret`, `two_factor_recovery_codes` → `app_authentication_recovery_codes`, **bỏ** `two_factor_confirmed_at` | Xem [§10 Rủi ro #4](#104-chuyển-2fa-sang-mfa-của-filament-v4) |
| `make_order_lines_purchasable_morph_nullable` | Shipping line không còn morph giả | Thấp — xem §11 |
| `set_card_type_to_nullable_on_transactions` | `card_type` nullable, bỏ giới hạn độ dài | Thấp |
| `switch_to_jsonb` | `json` → `jsonb` | **Chạy lâu trên DB lớn** |
| `add_missing_indexes_to_tables` | Thêm index | **Chạy lâu trên DB lớn** |
| `add_unique_lunar_product_product_option` | Unique index | Fail nếu DB đã có dòng trùng |

> ⚠️ **Trước khi migrate production:** đo thời gian `switch_to_jsonb` và
> `add_missing_indexes_to_tables` trên **bản copy dữ liệu thật**, rồi mới quyết
> định độ dài maintenance window. Nếu `add_unique_lunar_product_product_option`
> fail, tìm và gộp dòng trùng trước — đừng xoá bừa.

Xác nhận cột đã đổi tên:

```bash
mysql -h127.0.0.1 -uroot -e 'describe lunar_staff;' lunar | grep authentication
```

---

## 8. Fase 5 — Sửa tay code

Phần dài nhất. **Ba nhóm việc khác hẳn nhau** — làm theo thứ tự a → b → c.

### 8a. API mà Rector bỏ sót

```bash
php artisan about                 # lỗi PHP đầu tiên lộ ra ở đây
php artisan serve                 # rồi mở lần lượt từng trang admin
```

Chú ý:

- **14 custom Page** có `protected static string $view` — kiểm tra base class và
  cách khai báo view ở v4.
- **11 file** dùng `Filament\Forms\Concerns` — trait đã đổi vị trí.
- **11 Resource kế thừa Lunar** (`modules/Theme/app/Filament/Resources/*`,
  `modules/Catalog/.../ProductResource.php`) — chữ ký `form()`/`table()` của
  class cha đã đổi sang `Schema`, subclass phải khớp.
- **2 file** kế thừa `Lunar\Admin\Support\Pages\BaseEditRecord` — class này vẫn
  tồn tại ở 1.5, nhưng API bên trong đã theo v4.

### 8b. Thay đổi *hành vi* — không có lỗi PHP, chỉ sai âm thầm

Đây là nhóm nguy hiểm nhất vì test có thể vẫn xanh.

- [x] **Bộ lọc bảng giờ deferred mặc định** — người dùng phải bấm "Apply".
      **Đã quyết: giữ mặc định v4** (2026-08-27), không gọi `->deferFilters(false)`
      ở đâu cả. Xem [§13.6](#136-quyết-định-đã-chốt).
- [ ] **`Section`/`Grid`/`Fieldset` không còn full-width** — thêm
      `->columnSpanFull()`. Nguồn "layout bỗng dưng vỡ" phổ biến nhất, ảnh hưởng
      **31 chỗ** dùng `Section`.
- [ ] **`unique()` mặc định `ignoreRecord: true`** — soát mọi form có validate trùng.
- [ ] **File trên disk không phải local mặc định `private`** — kiểm tra module
      `Assets` (`MediaBrowser`, `MediaPicker`, `MediaPickerField`, `MediaLibrary`)
      và `config/media-library.php`.
- [ ] **Bảng bỏ tuỳ chọn phân trang "all"**.
- [ ] **Tham số URL đổi tên** (`tableFilters` → `filters`, `activeTab` → `tab`,
      `activeRelationManager` → `relation`) — link đã bookmark sẽ hỏng.
- [ ] **Field enum luôn trả về instance enum**, không còn trả giá trị thô.

### 8c. Custom theme Tailwind v4 — bắt buộc

> ⚠️ **Đây là bước dễ bị bỏ quên nhất và hậu quả rất rõ ràng: mất sạch style.**

Filament v4 **không còn nạp sẵn class Tailwind cho blade view của bạn**. Dự án có
17 blade view Filament, trong đó **16 view dùng utility class Tailwind** —
`grid-cols-*`, `dark:*`, `ring-1`, kể cả class nội bộ của Filament như
`fi-wi-stats-overview-stat`. Không tạo custom theme thì các trang đó mất style.

```bash
php artisan make:filament-theme
```

Trong file theme CSS sinh ra, khai báo `@source` trỏ đúng cây module:

```css
@import '../../../../vendor/filament/filament/resources/css/theme.css';

@source '../../../../modules/*/app/Filament/**/*';
@source '../../../../modules/*/resources/views/filament/**/*';
```

> ⚠️ **`package.json` hiện chưa có Tailwind.** Chỉ có `sass` + `bootstrap` cho
> storefront và Vite 7. Phải:
>
> ```bash
> npm i -D tailwindcss@^4 @tailwindcss/vite
> ```
>
> rồi thêm entry theme vào `vite.config.js`. **Giữ pipeline Bootstrap của
> storefront tách bạch** — hai hệ CSS không được giẫm lên nhau. Theme `fashion`
> ([../architecture/theme.md](../architecture/theme.md)) không dùng Tailwind và
> phải giữ nguyên như vậy.

**8 view dùng dày đặc** — nếu `@source` sai thì các trang này vỡ layout thấy ngay,
kiểm tra bằng mắt trước tiên (số trong ngoặc = số thuộc tính `class` chứa utility):

```
modules/Assets/resources/views/filament/pages/media-library.blade.php       (44)
modules/Analytics/resources/views/filament/pages/dashboard.blade.php        (24)
modules/Assets/resources/views/filament/forms/media-browser.blade.php       (22)
modules/Assets/resources/views/filament/pages/queue-workers.blade.php       (20)
modules/Assets/resources/views/filament/pages/media-image-sizes.blade.php   (14)
modules/Inventory/resources/views/filament/pages/stock-overview.blade.php   (13)
modules/Inventory/resources/views/filament/partials/stock-history.blade.php (12)
modules/Assets/resources/views/filament/forms/media-picker.blade.php        (11)
```

8 view còn lại (các trang `*-settings`, `notification-settings`,
`inventory-settings`…) chỉ có 1–2 utility class — chúng **hỏng âm thầm**, không vỡ
hẳn. Đừng dựa vào "nhìn thấy vẫn ổn" để kết luận theme đã đúng.
Riêng `stock-notifications.blade.php` không dùng utility nào.

Ngoài ra các view có dùng component v3: `x-filament::button` (42),
`x-filament-panels::page` (28), `x-filament::section` (10), `x-filament::icon` (3),
`x-filament::modal` (2), `x-filament::badge` (2) — đối chiếu từng cái với v4.

---

## 9. Fase 6 — Nghiệm thu & rollout

Nghiệm thu theo **luồng nghiệp vụ**, không chỉ theo test.

- [ ] Đăng nhập admin + luồng MFA mới. Staff đã bật 2FA phải vào được bằng mã
      TOTP **cũ** sau khi đổi tên cột.
- [ ] **Sidebar admin**: đúng 4 nhóm, đúng thứ tự, đúng nhãn tiếng Việt, không
      có Resource trùng. Đây là thứ dễ vỡ nhất — xem [§10 Rủi ro #1 & #2](#101-reflection-hoán-đổi-resource-trong-panel).
- [ ] Sửa sản phẩm: trang biến thể tự viết (`ManageProductVariants`), trang
      Size & Dáng (`ManageProductSizing`), media picker.
- [ ] Storefront: thêm giỏ → checkout → đặt hàng → mail xác nhận → invoice PDF.
      Đây là nơi bắt `purchasable` null và thứ tự dòng đơn hàng đổi.
- [ ] Khuyến mãi: chạy discount thật trên giỏ thật, đặc biệt `ComboPercentageOff`
      và `QuantityPercentageOff`.
- [ ] `composer test` — so với `../baseline-tests.txt`, **giải thích từng test lệch**.

Rollout: chạy trọn lộ trình trên staging với bản copy dữ liệu thật **một lần
hoàn chỉnh** trước khi đụng production. Deploy theo
[deployment.md §3](deployment.md), nhớ `php artisan filament:cache-components`.

---

## 10. Bốn rủi ro riêng của lunar-shop

Không có trong bất kỳ upgrade guide nào — chúng đến từ code riêng của dự án.

### 10.1 Reflection hoán đổi Resource trong panel

**Mức: cao** · [`app/Providers/ModulesServiceProvider.php:64-101`](../../app/Providers/ModulesServiceProvider.php)

Provider dùng closure bind lại `$this->resources` của `Panel` để thay **11 Resource
của Lunar** bằng subclass:

```php
(function () use ($replacement) {
    $this->resources = $replacement;
})->call($panel);
```

Ở Filament v4, `Panel::resources()` không chỉ ghi vào `$resources` nữa — nó còn
ghi vào `$modelResources`, `$resourceConfigurations` và `$clusteredComponents`
(xem `Panel/Concerns/HasComponents.php`). Reset mỗi `$resources` sẽ để sót:
Resource gốc của Lunar vẫn phân giải được qua model → **trùng menu**, hoặc link
điều hướng trỏ về Resource cũ.

**Cần làm:** reset đủ cả bốn mảng, hoặc — tốt hơn — bỏ hẳn reflection, dựng danh
sách resource cuối cùng rồi truyền một lần qua `->resources()`. Lunar 1.5 còn
thêm `->discoverClusters()` vào panel; kiểm tra việc hoán đổi không làm hỏng
cluster.

### 10.2 Reflection reset nhóm điều hướng

**Mức: cao** · [`app/Providers/ModulesServiceProvider.php:88-101`](../../app/Providers/ModulesServiceProvider.php)

Cùng thủ thuật, áp lên `$this->navigationGroups` để ép 4 nhóm sidebar có nhãn
dịch được. Ở v4, `navigationGroups()` **uỷ quyền sang `$navigationManager`** nếu
manager đã tồn tại:

```php
public function navigationGroups(array | string $groups): static
{
    if (isset($this->navigationManager)) {
        $this->navigationManager->navigationGroups($groups);
        return $this;
    }
    // ...
}
```

Lúc đó gán thẳng vào property sẽ không có tác dụng, và nhóm của Lunar
(`'Catalog'`, `'Sales'`, `NavigationGroup 'Settings'`) quay trở lại.

**Cần làm:** chuyển sang builder chính thức
`->navigation(fn (NavigationBuilder $b) => …)`, hoặc dùng enum navigation group
mà v4 hỗ trợ. Vấn đề gốc — nhãn tiếng Anh hardcode của Lunar không khớp key dịch
`lunarpanel::global.sections.*` — **vẫn còn nguyên ở 1.5**, nên vẫn phải xử lý.

### 10.3 Patch composer đè lên `lunarphp/core`

**Mức: vừa** · `patches/lunar-core-translations-locale-fallback.patch`

Patch sửa `HasTranslations::translate()` để không trả chuỗi rỗng khi locale hiện
tại thiếu bản dịch. Đã đối chiếu: **hàm này ở 1.5.0 giống hệt 1.3.0**, nên patch
dự kiến vẫn apply được — nhưng phải xác nhận ngay sau `composer update`, vì patch
fail thì cả lệnh update fail.

**Nếu trượt:** tạm gỡ khỏi khối `extra.patches` trong `composer.json` để update
xong, rồi rebase patch trên source 1.5.

Nhân tiện: bug này vẫn tồn tại upstream ở 1.5 — cân nhắc gửi PR để bỏ được patch,
theo đúng nguyên tắc "extend, đừng fork" ở [../README.md](../README.md).

### 10.4 Chuyển 2FA sang MFA của Filament v4

**Mức: vừa** · DB `lunar` · bảng `lunar_staff`

Bảng đang có đủ `two_factor_secret`, `two_factor_recovery_codes`,
`two_factor_confirmed_at`. Migration đổi tên hai cột đầu và **bỏ hẳn cột thứ ba**.
Gói `lunarphp/filament3-2fa` biến mất; Lunar 1.5 gọi thẳng MFA của Filament v4:

```php
$panel->multiFactorAuthentication(
    AppAuthentication::make()->recoverable(),
    isRequired: $this->twoFactorAuthForced,
);
```

**Cần làm:** sau migrate, đăng nhập bằng một staff đã bật 2FA và xác nhận mã TOTP
cũ vẫn dùng được. `LunarPanel::forceTwoFactorAuth()` / `disableTwoFactorAuth()`
vẫn còn nhưng đường đi phía sau đã khác — dự án hiện **không** gọi hai hàm này.

---

## 11. Đối chiếu breaking change với code thực tế

Cột cuối là mức ảnh hưởng **lên lunar-shop**, không phải mức Lunar công bố.

| Thay đổi (theo guide) | Thực tế trong dự án | Mức |
|---|---|---|
| Filament v3 → v4 | 79 file PHP + 17 blade view | **Cao** |
| PHP 8.3+ / Laravel 12\|13 | Chỉ nới constraint | Thấp |
| Đổi tên cột 2FA | Bảng `lunar_staff` có đủ 3 cột cũ → xem §10.4 | Vừa |
| `Kalnoy\Nestedset` → `Lunar\Nestedset` | **0 chỗ** dùng namespace `Kalnoy`; chỉ cần gỡ khỏi `composer.json` | Thấp |
| Order line `purchasable` nullable | Mọi chỗ duyệt `$order->lines` (invoice, mail, `OrderResource`, `ReturnService`) chỉ đọc `description`/`quantity`/`sub_total`. Chỗ đọc `purchasable_id` (`DecrementStock`, `StockReleaser`, `StockSettler`, `AnalyticsService`) đều lọc `purchasable_type` trước. `$cart->lines` không ảnh hưởng | Thấp |
| BuyXGetY áp dụng đủ điều kiện | `ComboPercentageOff` + `QuantityPercentageOff` mượn pattern nhưng tự khớp điều kiện. Soát discount đang chạy trong DB | Vừa |
| Thứ tự quan hệ tất định (`Cart::lines`, `Order::lines`, `ProductVariant::values`) | Thứ tự dòng giỏ/đơn có thể đổi — kiểm tra invoice + mail | Thấp |
| Gộp địa chỉ theo type · tax zone scope theo quốc gia · `CartSession::setCurrency()` reset quan hệ | Không có code riêng đè lên — thuần hồi quy, phủ bằng test checkout | Thấp |
| `getDefault()` khai báo nullable | Không đổi hành vi runtime. Vài seeder gọi `Language::getDefault()->id` không null-safe — sửa cho gọn, không chặn | Thấp |
| `DefaultPriceFormatter::$formatterStyle` thành `int` | Không có chỗ nào truyền tay | Thấp |
| Shipping method: bỏ `cutoff`, thêm min/max weight, `ShippingDiscount`, quyền `shipping:manage` (1.4) | **Add-on Table Rate Shipping không được cài** — không có trong `composer.lock`, DB không có bảng `lunar_shipping_methods`. Module `Shipping` là code riêng, không liên quan | **Không áp dụng** |
| Stripe `toStripeAmount()` | Dự án không tích hợp Stripe (chỉ VNPay + MoMo) | **Không áp dụng** |
| Search indexer eager-load hẹp lại | Không có indexer tự viết | **Không áp dụng** |
| `DateTimeInterface` trong `HasCustomerGroups` / `HasChannels` / `CanScheduleAvailability` | Không model nào override các trait này | **Không áp dụng** |

---

## 12. Rollback

Migration của 1.5 **sửa cấu trúc** — đổi tên cột, bỏ cột `two_factor_confirmed_at`,
`json` → `jsonb`. `migrate:rollback` không khôi phục được dữ liệu đã mất, và
`git revert` một mình thì để lại DB ở schema 1.5 mà code 1.3 không đọc được.

Dự án làm thẳng trên `main` nên không có branch để bỏ đi — đường lui là **reset
code về mốc đã ghi ở Fase 0, cộng restore DB**, làm cả hai cùng lúc:

```bash
git reset --hard <hash ghi ở Fase 0>    # mốc trước khi bắt đầu: 4cb1f13
composer install
mysql -h127.0.0.1 -uroot lunar < ../backup-pre-lunar-1.5.sql
php artisan optimize:clear
```

> ⚠️ Nếu đã `git push` các commit của đợt nâng cấp thì `reset --hard` phải kèm
> `git push --force-with-lease`. Vì vậy **commit từng fase một** và chỉ push khi
> fase đó đã qua gate — đừng push code ở giữa cửa sổ app chết.

Vì vậy dump ở Fase 0 là **bắt buộc**, và production chỉ đụng vào sau khi toàn bộ
lộ trình đã chạy trọn một lần trên staging với bản copy dữ liệu thật.

---

## 13. Nhật ký thực thi

Thực hiện **2026-08-27**, từ `4cb1f13` → `ad4d17f`. Kết quả: Lunar 1.5.0 +
Filament v4.12.6, **511 test passed** (baseline trước nâng cấp: 506 — chênh 5 là
`AdminPanelWiringTest` mới thêm).

### 13.1 Những chỗ lệch so với runbook

| # | Dự kiến | Thực tế |
|---|---|---|
| 1 | `vendor/bin/filament-v4` chạy codemod | **Script không chạy được.** Xem §13.2 |
| 2 | Rector chỉ đụng file Filament | Nó đụng thêm 18 file không liên quan do `importNames()` — đã hoàn nguyên để giữ diff đúng phạm vi |
| 3 | Chạy codemod trên `app` + `modules` | **Thiếu `tests/`** — 15 test đỏ vì `Filament\Forms\ComponentContainer` (v4: `Filament\Schemas\Schema`). Phải chạy cả ba |
| 4 | Breaking change đúng như guide liệt kê | Guide **thiếu một mục**: `Lunar\Base\Purchasable` thêm `isPurchasable(): bool`. Xem §13.3 |
| 5 | Section/Grid mất full-width → phải sửa 31 chỗ | **No-op.** Không schema gốc nào gọi `->columns(>1)`, nên Section vẫn chiếm trọn 1 cột |
| 6 | `unique()` đổi mặc định `ignoreRecord` | **No-op.** 3/4 chỗ đã truyền `ignoreRecord: true` sẵn; chỗ thứ tư nằm trong form *create*, không có record để bỏ qua |
| 7 | File trên disk non-local thành private | **Không áp dụng.** `MEDIA_DISK=public`, driver local. *Sẽ* áp dụng nếu production đổi sang S3 |
| 8 | Migration nặng (`switch_to_jsonb`, index) | Cả hai **đã chạy từ trước** ở 1.3. 7 migration còn lại chạy hết trong ~110ms trên DB dev |

### 13.2 `filament/upgrade` không tương thích rector đang cài

`filament/upgrade` v4.12.6 khai `rector/rector: ^2.0`, composer giải ra 2.6.3.
Config của nó dùng `Rector\Transform\Rector\Class_\AddInterfaceByTraitRector`,
rule đã bị rector gỡ và nay **throw** — kết quả `0/425 file` được xử lý, và vì
script gọi rector qua `exec()` nên output bị nuốt, nhìn như nó chạy xong bình thường.

Cách đã dùng: copy `vendor/filament/upgrade/src/rector.php` ra ngoài, xoá đúng
block rule đó, rồi gọi rector trực tiếp để nhìn được diff:

```bash
vendor/bin/rector process app modules tests \
  --config <config-đã-sửa> --clear-cache --dry-run
```

Rule bị bỏ thêm `implements HasActions` cho class dùng `InteractsWithForms` /
`InteractsWithTable` / `InteractsWithInfolists`. Với **Filament Page** thì vô
nghĩa — `Filament\Pages\BasePage` của v4 đã `implements HasActions` sẵn. Nhưng
với **Livewire component thuần** thì vẫn cần: `MediaPickerTestHost` trong
`tests/Feature/MediaPickerTest.php` phải thêm tay.

### 13.3 Breaking change không có trong upgrade guide

`Lunar\Base\Purchasable` ở 1.5 thêm `isPurchasable(): bool`. Mọi model tự
implement contract này (ở đây là `Modules\Catalog\Models\ProductSku`) sẽ chết
ngay ở `package:discover` với *"contains 1 abstract method and must therefore be
declared abstract"*. Lunar mô tả nó là hàng rào cuối cùng lúc tạo đơn: kiểm tra
trạng thái nằm trên chính purchasable (product cha bị xoá mềm / gỡ xuất bản),
**không** kiểm tra channel hay customer group.

### 13.4 Kết cục 4 rủi ro riêng ở §10

| # | Rủi ro | Kết cục |
|---|---|---|
| 1 | Reflection hoán đổi Resource | **Đã vỡ thật** — nhưng qua `clusteredComponents`, không phải `modelResources`. Lunar 1.5 thêm cluster `Taxes`, và cluster đó vẫn giữ 3 resource **bản gốc** của Lunar. Không lỗi nào bắn ra. Đã sửa + bọc `hasCachedComponents()` (xem §13.5) + thêm `AdminPanelWiringTest` làm dây bẫy |
| 2 | Reflection reset nhóm điều hướng | **Vẫn chạy đúng.** `navigationManager` chưa được set lúc closure chạy, nên gán thẳng property vẫn ăn. Cả 4 nhóm ra đúng nhãn tiếng Việt, đúng thứ tự |
| 3 | Patch `cweagans` lên `lunarphp/core` | **Áp sạch.** `HasTranslations::translate()` ở 1.5.0 y hệt 1.3.0. Bug vẫn còn upstream — vẫn nên gửi PR |
| 4 | Chuyển 2FA sang MFA v4 | **Vỡ nặng — đã sửa.** Migration của Lunar chỉ đổi tên cột, còn giá trị thì hai bên lưu khác định dạng, nên mọi staff đã bật 2FA bị khoá ngoài admin. Xem [§15](#15-bug-khoá-staff-ngoài-admin-không-có-trong-upgrade-guide) |

### 13.5 Cái bẫy `hasCachedComponents()`

Bản sửa rủi ro #1 gần như phá production. `Panel::id()` gọi
`restoreCachedComponents()` — tức là khi có `filament:cache-components`
([deployment.md §3](deployment.md) chạy nó mỗi lần deploy), panel **đã** nạp đủ
mảng component từ cache **trước** closure của `ModulesServiceProvider`, và mọi
lệnh đăng ký sau đó cố ý là no-op. Nếu closure vô tư xoá rồi gọi lại
`$panel->resources()`, thì ở mỗi web request production panel sẽ **rỗng**.

Vì `hasCachedComponents()` = `(! runningInConsole()) && file_exists(...)`, lúc
build cache (chạy trong console) nó là `false` → swap chạy → cache ghi ra đã
đúng. Nên bản sửa chỉ cần bọc toàn bộ khối swap trong
`if (! $panel->hasCachedComponents())`. Đã kiểm chứng cả hai nhánh.

**Quy tắc rút ra:** bất cứ thứ gì đụng vào nội bộ `Panel` đều phải hỏi
`hasCachedComponents()` trước.

### 13.6 Quyết định đã chốt

**Bộ lọc bảng: giữ mặc định deferred của v4** (2026-08-27).

Ở v3 mỗi lần đổi filter là một round-trip; v4 gom lại sau nút "Apply". Ảnh hưởng
8 bảng có `->filters()`:

```
modules/Catalog/app/Filament/Resources/SizeChartResource.php
modules/Content/app/Filament/Resources/BannerResource.php
modules/Content/app/Filament/Resources/LookbookResource.php
modules/Content/app/Filament/Resources/PageResource.php
modules/Content/app/Filament/Resources/RedirectResource.php
modules/Inventory/app/Filament/Pages/StockNotificationsPage.php
modules/Inventory/app/Filament/Pages/StockOverview.php
modules/Order/app/Filament/Resources/ReturnRequestResource.php
```

Không có `->deferFilters()` ở bất kỳ đâu trong `modules/`, `app/` hay `config/` —
tức đang chạy đúng mặc định upstream, không phải quên. Nếu về sau muốn đảo lại,
đặt `->deferFilters(false)` trên từng `Table`; đừng sửa vendor.

### 13.7 Còn lại — chưa làm

- **Nghiệm thu tay Fase 6** — phần lớn đã tự động hoá, xem [§16](#16-fase-6-đã-nghiệm-thu-tới-đâu). Còn lại là bấm tay trên staging.
- **Rollout production**: chạy trọn lộ trình trên staging với bản copy dữ liệu
  thật trước. Đo thời gian migration ở đó, đừng lấy con số ~110ms của DB dev.

---

## 14. Đã khai thác được gì từ 1.5

> Đặt kỳ vọng đúng: **1.5 là bản sửa lỗi, không phải bản tính năng.** Changelog
> ~35 PR thì gần như toàn bộ là fix. Phần lớn cái tốt của 1.5 tự đến khi nâng
> phiên bản (memo hoá discount, tax zone scope theo quốc gia, thứ tự quan hệ tất
> định, indexer nhẹ hơn). Danh sách dưới là những thứ **phải chủ động bật**.

### 14.1 Config đã publish bị đóng băng — nguồn bỏ sót lớn nhất

Config publish ra `config/` không tự cập nhật khi nâng package, nên tính năng mới
thêm vào config mặc định sẽ **âm thầm không có**. Cách soát:

```bash
for f in config/lunar/*.php; do
  b=$(basename "$f"); v="vendor/lunarphp/core/config/$b"
  [ -f "$v" ] || v="vendor/lunarphp/lunar/config/$b"
  [ -f "$v" ] && { echo "### $b"; diff "$f" "$v"; }
done
```

Đối chiếu cả 14 config: **chỉ `cart.php` lệch về chức năng**, phần còn lại chỉ
khác kiểu import / typo trong comment. Hai thứ đã bật:

| Thêm vào | Việc nó làm |
|---|---|
| `CartLineAvailability` (validator `add_to_cart` + `update_cart_line`) | Hàng rào cuối ở tầng Lunar. Gọi `isPurchasable()` với purchasable không phải ProductVariant, và kiểm channel + pivot `purchasable` của customer group với ProductVariant |
| `CalculateShippingSubTotal` (pipeline, ngay sau `ApplyShipping`) | Tổng hợp `shippingBreakdown` thành `$cart->shippingSubTotal` |

### 14.2 Lỗ hổng thật đã bịt nhờ `isPurchasable()`

`CartService::guardStatus` trước đây chỉ đọc `status` của **chính SKU**, nên một
SKU có product cha **đã gỡ xuất bản hoặc xoá mềm vẫn vào giỏ được**. Nay nó uỷ
quyền cho `ProductSku::isPurchasable()` — cùng hàm mà `CartLineAvailability` gọi,
nên guard của storefront và validator của Lunar không thể bất đồng.

Vì sao vẫn cần cả hai: `CartService` cho khách 422 có thông điệp; validator của
Lunar ném `CartException` → 500, nhưng nó phủ những đường `CartService` không
đứng chắn (draft order trong admin, code gọi thẳng `$cart->add()`).

### 14.3 Skill agent chính chủ

1.5 bắt đầu ship `vendor/lunarphp/core/resources/boost/skills/lunar/SKILL.md`
(PR 2491) — 83 dòng cô đọng về giá là số nguyên, type hint contract thay vì
model, morph key, `attribute_data`, memo hoá discount… Đã sao nguyên văn vào
`.claude/skills/lunar/`. **Mỗi lần nâng Lunar nhớ copy lại từ vendor.**

Skill cũng chỉ ra MCP docs server, đáng thêm nếu làm Lunar thường xuyên:

```bash
claude mcp add lunar-docs --transport http https://docs.lunarphp.com/mcp
```

### 14.4 Soát theo cảnh báo trong skill

| Cảnh báo | Kết quả trên dự án |
|---|---|
| Scout cần `soft_delete => true` | **Đang `false` → đã sửa.** Chưa lộ vì `SCOUT_DRIVER=collection` truy vấn thẳng Eloquent nên scope soft-delete tự áp; sẽ cắn khi đổi sang Meilisearch/Algolia |
| Gọi `Discounts::resetDiscounts()` sau khi đổi coupon | **Không cần.** PR 2623 của 1.5 đưa `coupon_code` vào cache key của discount, nên đổi coupon tự invalidate memo |
| Chống trôi giỏ bằng `fingerprint()` trước khi capture | **Đã có cách khác.** `CheckoutService::doPlaceOrder` re-đọc giỏ *trong* cache lock rồi `calculate()` lại, nên đơn luôn mang tổng tươi. Xem §14.5 |

### 14.5 Cân nhắc, chưa làm

- **`fingerprint()` / `checkFingerprint()`.** Lock + recalculate hiện tại đảm bảo
  khách bị tính đúng tổng **tại thời điểm đặt**, nhưng không phát hiện được
  trường hợp tổng đã đổi giữa lúc khách *nhìn* trang checkout và lúc bấm đặt
  (giá đổi, khuyến mãi hết hạn). Đây là câu chuyện *đồng thuận của khách*, không
  phải lỗi tính toán — và nó đụng luồng thanh toán, nên để lại thành quyết định
  riêng.
- **`ProductVariantInventoryUpdated`** (PR 2606): không áp dụng. Event này bắn cho
  `Lunar\Models\ProductVariant`, còn tồn kho ở đây nằm trên `ProductSku` của dự án.
- **Digital line trong PDF hoá đơn** (PR 2599): không áp dụng — hoá đơn do
  `Modules\Order\Services\InvoiceService` tự dựng bằng dompdf, không dùng của Lunar.
- **`shipping:manage`, min/max weight, `ShippingDiscount`**: add-on Table Rate
  Shipping không cài.

---

## 15. Bug khoá staff ngoài admin (không có trong upgrade guide)

> **Nếu production có staff đã bật 2FA, đây là thứ phải đọc trước khi deploy.**

Lunar 1.5 bỏ `lunarphp/filament3-2fa`, chuyển sang MFA sẵn có của Filament v4, và
ship migration **chỉ đổi tên cột**. Giá trị giữ nguyên — trong khi hai bên lưu
khác định dạng:

| | Ghi | Đọc |
|---|---|---|
| `lunarphp/filament3-2fa` | `encrypt($secret)` — payload **có** serialize | `decrypt($v)` |
| Filament v4 | cast `'encrypted'` | `decrypt($v, false)` — **không** serialize |

Kết quả: Filament đọc secret cũ ra đúng chuỗi `s:16:"JBSWY3DPEHPK3PXP";` thay vì
`JBSWY3DPEHPK3PXP`. Recovery codes hỏng y hệt — cũ lưu `encrypt(json_encode(...))`
còn cast mới là `'encrypted:array'`, nên `json_decode` rơi vào lớp bọc serialize
và trả `null`.

Tệ hơn "mã sai": chuỗi bọc đó **không phải base32 hợp lệ**, Google2FA ném
`InvalidCharactersException`, và `AppAuthentication::verifyCode()` không bắt —
nên màn hình MFA **500** chứ không báo mã sai.

**Bản sửa:** `database/migrations/2026_08_27_120000_reencrypt_staff_app_authentication_columns.php`
giải mã rồi mã hoá lại đúng định dạng. Nhận diện theo **nội dung** chứ không theo
cờ — chỉ ghi lại giá trị nào giải mã ra một chuỗi PHP-serialized — nên chạy lại
được nhiều lần và an toàn trên bảng lẫn lộn cũ/mới (secret base32 hay mảng JSON
không thể trông giống payload serialize). Giá trị không giải mã nổi bằng `APP_KEY`
hiện tại thì để nguyên.

`StaffTwoFactorReencryptionTest` phủ 7 ca, trong đó có ca sinh mã TOTP thật từ
secret gốc và chạy qua chính verifier của Filament — chứng minh app authenticator
trên máy staff vẫn dùng được sau migration, và **không** dùng được nếu thiếu nó.

> ⚠️ Đây là bug của đường nâng cấp, không phải của dự án. Bất kỳ ai đi từ Lunar
> ≤1.4 (có `lunarphp/filament3-2fa`) lên 1.5 đều dính. Đáng gửi ngược lên upstream.

---

## 16. Fase 6 — đã nghiệm thu tới đâu

Phần lớn checklist ở §9 đã chuyển thành test tự động, vì "mở từng trang bấm thử"
không lặp lại được ở lần nâng Filament sau.

| Hạng mục §9 | Cách nghiệm thu | Kết quả |
|---|---|---|
| Sidebar: 4 nhóm, đúng thứ tự, nhãn tiếng Việt, không trùng | `AdminPanelWiringTest` (5 test) | ✅ |
| Mọi trang admin render | `AdminPagesSmokeTest` — mount 14 page + 29 resource list page | ✅ |
| Sửa sản phẩm: biến thể, Size & Dáng, media picker | `ProductAdminPagesTest`, `MediaPickerTest` | ✅ |
| Storefront: giỏ → checkout → đơn → mail → invoice PDF | `OrderShippingLineTest` (4 test) đặt đơn COD thật qua endpoint thật, rồi render PDF + mail | ✅ |
| Trang storefront công khai | Quét 11 route GET tĩnh qua HTTP | ✅ toàn 200 |
| Khuyến mãi trên giỏ thật | 5 file test sẵn có (`PromotionTest`, `PromotionAdvancedTest`, `CartTest`…) | ✅ |
| Đăng nhập MFA bằng secret cũ | `StaffTwoFactorReencryptionTest` (7 test) | ✅ sau khi có migration §15 |
| `composer test` so baseline | 532 passed (baseline trước nâng cấp: 506) | ✅ |

Hai phát hiện nhờ smoke test, đều **không phải lỗi**, ghi lại để lần sau không mất
công đào lại:

- `ListProducts` fail khi không có currency mặc định — lỗi fixture, không phải
  wiring. `AdminPagesSmokeTest` nay seed dữ liệu nền trước.
- `CollectionResource` index trả 404: `Lunar\Admin\...\ListCollections::mount()`
  **cố ý** `abort(404)` — collection duyệt qua collection group, không có trang
  index phẳng. Đã khai báo tường minh trong test thay vì nuốt mọi 404.

**Còn lại phải bấm tay trên staging** (không tự động hoá được, hoặc không nên):

- Thanh toán thật qua VNPay/MoMo sandbox — test chỉ dùng gateway giả.
- Đo thời gian migration trên bản copy dữ liệu production.

---

## 17. Asset trình duyệt — cái test PHP không bao giờ thấy

> Triệu chứng: mở `/lunar/products`, trang hiện ra nhưng **không bấm được gì**.
> Console đầy `filamentTable is not defined`, `filamentSchema is not defined`,
> `filamentDropdown is not defined`, và 404 cho `actions.js`, `tables.js`,
> `schemas.js`.

Filament copy asset đã biên dịch vào `public/` **lúc cài**, và chúng chỉ được làm
mới khi có ai đó chạy lại. Hook lo việc đó là `@php artisan filament:upgrade`
trong `post-autoload-dump` — **dự án không có nó**. Nên `public/js/` vẫn nguyên
bản v3 từ 2026-07-23, trong khi v4 tách lại gói (`actions/` và `schemas/` là mới,
tên file cũng đổi).

```bash
php artisan filament:assets
```

Và thêm vào `composer.json` để không tái diễn:

```diff
  "post-autoload-dump": [
      "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
-     "@php artisan package:discover --ansi"
+     "@php artisan package:discover --ansi",
+     "@php artisan filament:upgrade"
  ],
```

### Vì sao 528 test vẫn xanh trong lúc admin không dùng được

Đây là bài học đắt nhất của cả đợt nâng cấp. `AdminPagesSmokeTest` mount được mọi
trang vì **phía PHP hoàn toàn lành** — Livewire component render ra HTML đúng.
Cái vỡ nằm ở tầng asset trình duyệt, nơi PHPUnit không bao giờ nhìn tới.

`FilamentAssetsPublishedTest` bịt đúng khoảng mù đó: đối chiếu mọi asset panel
**đăng ký** (`FilamentAsset::getScripts()` + `getStyles()`) với file thật trong
`public/`, chốt riêng 5 gói JS của v4, và canh luôn sự tồn tại của hook composer.
Đã mutation-check bằng cách giấu `tables.js` đi — test đỏ và gọi đúng tên file.

> **Quy tắc rút ra:** nâng Filament major thì đừng tin test PHP một mình. Mở trình
> duyệt, xem tab Console. Hoặc để bài học đó thành test như trên.

### 17.1 Lớp thứ hai: file cũ còn nằm trong cache trình duyệt

Sau khi publish asset, `/lunar/products/18/variants` vẫn nổ — nhưng lỗi khác hẳn:

```
Uncaught TypeError: Expected one of the following types text|select-one|select-multiple
    at new g (select.js?v=4.12.6.0)
Alpine Expression Error: Cannot read properties of null (reading 'destroy')
```

Lần này **server hoàn toàn sạch**: bytes `public/js/filament/forms/components/select.js`
khớp md5 với `vendor/filament/forms/dist/components/select.js`, và file v4 đó
**không hề chứa chữ "Choices"** lẫn chuỗi lỗi trên. Tức code đang chạy trong
trình duyệt không phải file trên đĩa.

Vì sao chỉ mỗi `select.js` dính, còn `actions.js`/`tables.js`/`schemas.js` thì tự
khỏi sau khi publish:

| | v3 có file? | Trước khi publish, request `?v=4.12.6.0` trả về |
|---|---|---|
| `actions.js`, `schemas.js`, `tables.js` | không (mới ở v4) | **404** → không cache được |
| `select.js` | **có** | **200 kèm bytes v3** → trình duyệt cache lại |

Chuỗi `?v=` lấy từ **phiên bản package đã cài**, không phải từ nội dung file. Nên
ngay khi composer nâng lên 4.12.6, URL đã đổi sang `?v=4.12.6.0` trong khi file
trên đĩa vẫn là v3 — cache-buster tự bắn vào chân mình.

**Cách xử lý:** xoá cache trình duyệt cho site đó. Reload thường không đủ, vì
`x-load-src` nạp bằng fetch chứ không phải thẻ `<script>`:

- Chrome/Edge: mở DevTools → tab Network → tick **Disable cache** → reload. Hoặc
  giữ chuột phải vào nút reload → **Empty Cache and Hard Reload**.
- Safari: Develop → Empty Caches.

Chỉ cần làm một lần. Từ nay hook `filament:upgrade` cập nhật file **cùng lúc**
chuỗi `?v=` đổi, nên URL mới luôn đi kèm nội dung mới.

### 17.2 Test canh tính tươi — và khoảng mù trong chính nó

`FilamentAssetsPublishedTest` nay đối chiếu **md5** giữa nguồn trong `vendor/` và
bản đã publish, chứ không chỉ kiểm tồn tại: *file thiếu thì ồn ào, file cũ thì im
lặng.*

Bản đầu của test này bỏ sót nguyên một nhóm. Filament chia asset làm **ba**
collection, và `getScripts()` + `getStyles()` không bao gồm nhóm thứ ba:

```php
FilamentAsset::getScripts(withCore: true)   //  6
FilamentAsset::getStyles()                  //  2
FilamentAsset::getAlpineComponents()        // 22  ← select.js nằm ở đây
```

Nhóm `getAlpineComponents()` chính là các file nạp qua `x-load-src` — nơi chứa
`select.js`. Nghĩa là test có đúng khoảng mù như bug nó định canh: mutation check
(ghi đè `select.js` bằng rác) vẫn xanh. Sau khi gộp đủ ba collection, số asset
được canh đi từ 8 lên **30**, và mutation check sập đúng chỗ.

> **Bài học:** khi viết test cho một sự cố, luôn mutation-check nó. Test xanh
> không chứng minh nó canh được gì.

---

## 18. Nghiệm thu cuối

Chạy lại từ trạng thái cache sạch, 2026-08-27. Phạm vi đợt nâng cấp:
`4cb1f13..HEAD` — **17 commit, 96 file, +5108 / −3182**.

| Kiểm | Kết quả |
|---|---|
| `lunarphp/core` · `lunarphp/lunar` | 1.5.0 |
| `lunarphp/nestedset` | 1.0.0 |
| `filament/filament` | v4.12.6 |
| `livewire/livewire` · `laravel/framework` | v3.8.6 · v12.68.0 |
| `kalnoy/nestedset` · `lunarphp/filament3-2fa` | đã gỡ khỏi lock |
| `composer validate` | valid |
| `composer audit` | **0 advisory** (trước nâng cấp: 14) |
| `migrate:status` | 0 migration đang chờ |
| PHP runtime vs constraint | 8.4.23 vs `^8.3` |
| `npm run build` | tất định — hash không đổi khi build lại |
| `filament:assets` chạy lại | không sinh drift trong `public/js`, `public/css` |
| Pint trên 44 file PHP đã đụng | đạt chuẩn |
| Sót API v3 (`Forms\Form`, `Tables\Actions`, `Forms\Get`, `Forms\Components\Section`, `Kalnoy`, `ComponentContainer`) | 0 file |
| Storefront | 11/11 route → 200 |
| Asset admin v4 qua HTTP | 7/7 → 200; `select.js` phục vụ đúng bản v4 |
| `composer test` | **532 passed** (2165 assertions) |
| Git | tree sạch, `main` đồng bộ `origin/main` |

DB sau migration: `lunar_staff` chỉ còn `app_authentication_*` (không còn
`two_factor_*`), `lunar_order_lines.purchasable_type|_id` đã nullable, cây
nested set của collection nguyên vẹn.

### 29 test đợt nâng cấp để lại

Không phải để đạt con số, mà vì mỗi cái tương ứng một thứ đã thật sự vỡ — hoặc
suýt vỡ — trong đợt này:

| Test | Canh cái gì |
|---|---|
| `CartVariantStatusGuardTest` (7) | Guard `isPurchasable()` + `CartLineAvailability`, gồm ca đi vòng qua `CartService` |
| `StaffTwoFactorReencryptionTest` (7) | Secret 2FA đọc được và mã TOTP thật xác thực được sau migration §15 |
| `AdminPanelWiringTest` (5) | Hoán đổi Resource, cluster `Taxes`, 4 nhóm điều hướng dịch được |
| `FilamentAssetsPublishedTest` (4) | Asset publish **tồn tại và tươi**, cả 30 asset gồm nhóm Alpine component |
| `OrderShippingLineTest` (4) | Morph nullable của dòng shipping, xuyên tới PDF và mail |
| `AdminPagesSmokeTest` (2) | 14 page + 29 resource list page render được |

### Còn lại — không thuộc đợt nâng cấp

- Thanh toán thật qua VNPay/MoMo sandbox (test chỉ dùng gateway giả).
- Đo thời gian migration trên bản copy dữ liệu production trước khi chốt
  maintenance window — con số ~110ms ở đây là DB dev 5MB.
- **Gửi ngược lên upstream — đã chuẩn bị xong, chờ bấm gửi.** Hai patch sẵn sàng
  `git am` ở [`docs/upstream/`](../upstream/README.md): bug khoá staff (§15) và
  fix locale fallback trong `HasTranslations`. Cả hai đã kèm test, chạy sạch trên
  suite của chính Lunar (core 598, admin 229) và áp sạch lên clone `1.x` mới.
