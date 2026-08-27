# Migration Runbook — Lunar 1.3 → 1.5

> Quy trình từng bước nâng `lunarphp/lunar` **1.3.0 → 1.5.0**. Đọc kèm
> [../architecture/overview.md](../architecture/overview.md) và
> [deployment.md](deployment.md).
> Nguồn: [Lunar upgrade guide](https://docs.lunarphp.com/1.x/getting-started/overview/upgrade-guide#1-5)
> + [Filament v4 upgrade guide](https://filamentphp.com/docs/4.x/upgrade-guide).
> Soạn: **2026-08-26**, đối chiếu code tại commit `a4f619b`.

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

- [ ] **Bộ lọc bảng giờ deferred mặc định** — người dùng phải bấm "Apply". Muốn
      giữ như cũ: `->deferFilters(false)`.
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
