# Migration Runbook — Lunar 1.5 → 2.0 + đổi admin sang `lunarphp/panel`

> Kế hoạch chuyển từ **Lunar 1.5.0 + Filament v4** sang **Lunar 2.0.0-alpha.6 +
> panel Inertia/Vue chính chủ**, bỏ hẳn Filament admin.
> Khảo sát trên tag [`2.0.0-alpha.6`](https://github.com/lunarphp/lunar/tree/2.0.0-alpha.6)
> (phát hành 2026-09-01). Soạn: **2026-09-08**.
> Đọc kèm [upgrade-lunar-1.5.md](upgrade-lunar-1.5.md) — cùng một dự án, cùng
> những cái bẫy đã trả giá.

---

## 0. Đọc cái này trước

**Đây không phải nâng cấp phiên bản. Đây là thay toàn bộ tầng admin.**

Lunar 2.0 giữ cả hai đường: `lunarphp/lunar` (Filament, nay là **v5**) và
`lunarphp/panel` (Inertia v3 + Vue 3). **Đã chọn: đi panel.** Nghĩa là 64 file
PHP Filament + 17 blade view của dự án không *migrate* mà **viết lại**.

Ba điều quyết định cách chia việc:

1. **Phần code ngoài admin nhẹ hơn nhiều so với cảm giác ban đầu.** Toàn bộ
   `Lunar\*` dời sang `Lunar\Core\*` — 513 class đổi tên — nhưng Lunar ship
   **rector set chính chủ**. Đo trên repo: dự án dùng 136 class Lunar, **113 cái
   rector tự đổi**, 22 cái thuộc `Lunar\Admin` (sẽ bỏ cùng Filament), còn **đúng
   1 cái** phải xem tay.
2. **Phần admin nặng, nhưng không nặng như 64 file.** 10/20 resource riêng chỉ là
   *swap của Lunar để đổi nhóm điều hướng* — panel mới đã có sẵn hết, nên chúng
   **bị xoá, không phải viết lại**. Xem bảng §4.
3. **Đây là bản alpha.** alpha.6 ra 2026-09-01, alpha.5 ra trước đó **ba ngày** —
   và chính alpha.5 là bản đổ bộ toàn bộ panel (Customers, Collections, Products,
   Dashboard, nâng Inertia v3). API đang đổi theo tuần.

### Ngưỡng kích hoạt — đừng bắt đầu trước khi có đủ

- [ ] **P0 trong [roadmap](../roadmap.md) đã xong**: rotate secrets, error
      tracker, HĐĐT. Đang thiếu lưới an toàn mà nhảy lên alpha là cộng dồn rủi ro.
- [ ] Lunar ra **2.0.0-beta** trở lên, hoặc bạn chấp nhận đi theo alpha và
      `composer update` lại mỗi tuần.
- [ ] Có **môi trường staging** với bản copy dữ liệu thật.

Ước lượng: **3–5 tuần công**, gần như toàn bộ nằm ở việc viết lại admin bằng Vue.

---

## 1. Quyết định phải ghi nhận: stack frontend thứ ba

Panel chạy **Vue 3 + Inertia v3 + Tailwind 4 + reka-ui + TipTap + TypeScript +
Vitest**. Storefront chạy **Blade SSR + Bootstrap 5 + vanilla JS**.

Điều này **va vào nguyên tắc đã ghi** trong [../README.md](../README.md) §2 và
mục "Nguyên tắc phạm vi" của roadmap, vốn loại bỏ *headless SPA tách rời*.

Lập luận để vẫn làm — cần ghi lại để sau này không ai tưởng là sơ suất:

- Nguyên tắc SSR-first là **cho nội dung cần SEO**. Admin không cần crawl, không
  có khách vào, nên đánh đổi không giống nhau.
- Đi ngược lại (ở lại Filament) cũng **không miễn phí**: 2.0 yêu cầu Filament
  **v5**, tức lại một lần migrate major toàn bộ 64 file + 17 blade, ngay sau khi
  vừa xong v3→v4 (xem [nhật ký 1.5](upgrade-lunar-1.5.md)). Chi phí một lần của
  panel đổi lấy việc thoát khỏi vòng lặp đó.
- Nhưng phải trả giá thật: **hai stack frontend cần bảo trì**, và mỗi lập trình
  viên chạm admin từ nay phải biết Vue.

> Nếu không chấp nhận điểm cuối, lựa chọn đúng là **ở lại Filament v5**, và
> runbook này không dùng tới.

---

## 2. Hiện trạng → đích đến

| Thành phần | Đang có | Sau nâng cấp |
|---|---|---|
| `lunarphp/lunar` | 1.5.0 (Filament admin) | *gỡ* |
| `lunarphp/core` | 1.5.0 | `2.0.0-alpha.6` |
| `lunarphp/panel` | — | `2.0.0-alpha.6` (Inertia + Vue) |
| `filament/*` | v4.12.6 | *gỡ toàn bộ* |
| `livewire/livewire` | v3.8.6 | *gỡ* (chỉ Filament dùng) |
| `php` | `^8.3` | `^8.4` (runtime đã 8.4.23) |
| `laravel/framework` | `^12.0` | `^12.0` (2.0 nhận `^12\|^13`) |
| `technikermathe/blade-lucide-icons` | v3 | `mallardduck/blade-lucide-icons ^1.26` |
| Phụ thuộc core **mới** | — | `spatie/laravel-model-states ^2.11`, `spatie/laravel-permission` (dời từ admin xuống core) |

`spatie/laravel-model-states` là dấu hiệu **trạng thái đơn hàng chuyển sang state
machine**. Dự án có `Modules\Order\Support\OrderStatus` riêng với trạng thái
`dispatched` — đây là chỗ phải soi kỹ nhất ở tầng dữ liệu (§6).

---

## 3. Công cụ chính chủ

Lunar 2.0 ship gói `lunarphp/upgrade`:

```bash
php artisan lunar:upgrade
```

Bốn bước nó chạy (`packages/upgrade/src/Steps`):

| Step | Việc |
|---|---|
| `ComposerRequireRewriteStep` | Viết lại khối require trong `composer.json` |
| `RectorStep` | Áp 513 rename `Lunar\*` → `Lunar\Core\*` |
| `DataMigrationStep` | Chuyển dữ liệu v1 → v2 |
| `LedgerRewriteStep` | Viết lại sổ (ledger) |

Đây là thứ gánh phần lớn công việc ở tầng PHP ngoài admin. **Đọc diff của rector
trước khi commit** — bài học từ đợt 1.5: nó sửa nhiều hơn tài liệu liệt kê.

---

## 4. Bản đồ admin: cái gì xoá, cái gì viết lại

Đây là bảng quan trọng nhất của cả runbook.

### 4.1 Xoá được — panel đã có sẵn (10 file)

`modules/Theme/app/Filament/Resources/` chỉ chứa **subclass của Lunar để đổi nhóm
điều hướng**. Panel mới tự lo điều hướng, nên chúng biến mất:

`AttributeGroupResource` · `CollectionResource` · `CustomerGroupResource` ·
`ProductOptionResource` · `ProductTypeResource` · `ProductVariantResource` ·
`TagResource` · `TaxClassResource` · `TaxRateResource` · `TaxZoneResource`

Panel ship sẵn 17 Settings section (Attributes, AttributeGroups, Channels,
Countries, Currencies, CustomerGroups, Languages, Locations, ProductOptions,
Regions, Roles, Staff, Tags, TaxClasses, TaxZones, ActivityLog) + Catalog
(Products, Brands, Collections, ProductTypes, ProductVariants) + Sales (Orders,
Customers, Discounts).

⚠️ **Ngoại lệ:** `ProductOption` của dự án mang thêm `display_type` (lưu trong
`meta`) qua `ModelManifest::replace()`. Model swap đó **giữ nguyên** — chỉ resource
Filament bị xoá. Phần UI chọn display_type phải làm lại thành slot trên trang
Product Options của panel.

### 4.2 Phải viết lại bằng Vue (9 resource + 16 page + 3 form component)

| Nhóm | Của dự án | Cách làm trên panel |
|---|---|---|
| **Nội dung** (6) | Banner · Lookbook · Menu · Page · PageSection · Redirect | Một `Section` riêng (`Content`) + trang Vue. Không có tương đương first-party |
| **Catalog** (2) | SizeChart · ProductResource (swap) | SizeChart → Section riêng. ProductResource swap → **slot** trên trang Product |
| **Đơn hàng** (1) | ReturnRequest (RMA) | `SectionExtension` mở rộng `sales` |
| **Vận chuyển** (1) | ShippingZone | Settings section riêng |
| **Trang cấu hình** (9) | Catalog · Payment · Customer · Inventory · Notification · Membership · Shipping · Theme · MediaImageSizes | `settingsNavigation()` + trang `SettingsShell`. Toàn bộ đọc/ghi qua `Modules\Core\Support\Settings` nên **backend giữ nguyên**, chỉ đổi lớp UI |
| **Kho** (2) | StockOverview · StockNotifications | Section riêng, hoặc widget dashboard |
| **Vận hành** (2) | MediaLibrary · QueueWorkers | Section riêng |
| **Phân tích** (1) | AnalyticsDashboard | Panel có dashboard + `widgets()` đăng ký được → chuyển thành widget |
| **Biến thể** (2) | ManageProductVariants · ManageProductSizing | Slot/trang gắn vào Product edit. **Đây là phần khó nhất** — SKU builder tự viết |
| **Form component** (3) | MediaPicker · MediaBrowser · MediaPickerField | Viết lại thành Vue component. **14 call site.** Không có tương đương first-party |

**Nặng nhất, theo thứ tự:** MediaPicker (14 call site, vừa sửa 2 bug tuần trước —
xem [e2e-testing.md](e2e-testing.md)) → ManageProductVariants (SKU builder) → 6
resource Nội dung.

---

## 5. API mở rộng của panel — cần biết trước khi ước lượng

Addon đăng ký qua service provider:

```php
Panel::section(new ContentSection);            // section mới
Panel::extendSection(new SalesExtension);      // mở rộng section có sẵn

$this->app->make(PanelManager::class)->vite('lunar-shop', [
    'input' => 'resources/js/panel.ts',
    'buildDirectory' => 'vendor/lunar-panel/lunar-shop',
]);
```

`Section` có các hook: `navigation()` · `settingsNavigation()` · `routes()` ·
`slots()` · `tableExtensions()` · `pageActions()` · `widgets()` ·
`langNamespaces()`.

Phía Vue, **đăng ký phải xong TRƯỚC lần render đầu**:

```ts
window.LunarPanel.registerPages({ 'lunar-shop::Content/Banners': BannersPage });
window.LunarPanel.registerComponents('lunar-shop', { MediaPicker });
```

Build bằng `@lunarphp/panel-vite-plugin` (ép IIFE, externalize `vue`,
`@inertiajs/vue3`, `@lunarphp/panel` sang global do panel publish).

### Năm cái bẫy tài liệu Lunar tự cảnh báo

1. **Không đăng ký được SVG riêng** — chỉ dùng được tên icon có sẵn của panel.
   Ảnh hưởng trực tiếp: 16 page hiện có đều đang đặt `navigationIcon` riêng.
2. **Đăng ký component trong `booting()` là quá muộn** — Inertia resolve xong rồi.
3. **Zone của slot phải khớp *tên route thật*** (`customers.edit`, không phải
   `customers.show`). Sai thì slot **im lặng không hiện**, không báo lỗi.
4. **`tableExtensions()` gõ sai key cũng không báo lỗi** — chỉ là cột không xuất hiện.
5. **Bundle panel cũ hơn vite plugin lúc build addon** → global `InertiaVue3`
   undefined, đổ thành "Panel page not found".

> Bẫy 3 và 4 cùng một dạng với những lỗi đã trả giá ở đợt 1.5: **sai thì im lặng**.
> Mọi slot/extension viết ra phải có một test khẳng định nó thực sự xuất hiện.

---

## 6. Lộ trình

| # | Fase | Thời lượng | App chạy được? |
|---|---|---|---|
| 0 | Chuẩn bị & chốt baseline | ~1 giờ | ✅ |
| 1 | Nới `php` lên `^8.4` | ~15 phút | ✅ |
| 2 | Chạy `lunar:upgrade` (rector + data) | 1–2 ngày | ❌ |
| 3 | Dựng panel trống, gỡ Filament | 2–3 ngày | ⚠️ admin trống |
| 4 | Viết lại admin bằng Vue | **2–4 tuần** | ⚠️ dần đầy |
| 5 | Nghiệm thu & rollout | 2–3 ngày | ✅ |

### Fase 0 — chuẩn bị

Giống hệt [1.5 §3](upgrade-lunar-1.5.md): ghi hash mốc quay lui, dump DB
(`mysqldump --single-transaction`), chốt baseline `composer test`.

Thêm hai việc riêng của đợt này:

- [ ] Ghi lại **ảnh chụp màn hình** từng trang admin đang dùng. Viết lại UI mà
      không có bản gốc để đối chiếu là tự chuốc khổ.
- [ ] Liệt kê **14 call site của MediaPicker** — chúng là hợp đồng phải giữ.

### Fase 1 — `php: ^8.3` → `^8.4`

Tách riêng, commit riêng, `composer test` phải xanh. Runtime đã là 8.4.23.

### Fase 2 — `lunar:upgrade`

```bash
composer require lunarphp/upgrade:"^2.0" -W --dev
php artisan lunar:upgrade
```

- Đọc **toàn bộ diff rector** trước khi commit.
- `Lunar\DiscountTypes\AmountOff` **không có trong set** → sửa tay. Kiểm cả
  `Modules\Promotion\DiscountTypes\{ComboPercentageOff,QuantityPercentageOff}`
  vì chúng kế thừa/mượn pattern của Lunar.
- Chạy `php artisan migrate` rồi soi `spatie/laravel-model-states`: đối chiếu
  `Modules\Order\Support\OrderStatus` (nhất là `dispatched` và
  `stock_released_at`) với state machine mới. **Đây là chỗ dữ liệu có thể lệch.**

### Fase 3 — dựng panel, gỡ Filament

```bash
composer require lunarphp/panel:"2.0.0-alpha.6"
composer remove lunarphp/lunar   # kéo theo filament/*
```

- Xoá 10 file ở §4.1 và `modules/*/app/Filament/` không còn dùng.
- Gỡ hook `filament:upgrade` khỏi `post-autoload-dump` trong `composer.json`;
  gỡ `FilamentAssetsPublishedTest`, `AdminPagesSmokeTest`,
  `VariantRepeaterKeysTest`, `tests/Browser/VariantMediaPickerTest.php` — chúng
  canh Filament, không còn đối tượng.
- `app/Providers/ModulesServiceProvider.php`: bỏ toàn bộ phần reflection hoán đổi
  `Panel::$resources` / `$navigationGroups` (xem [1.5 §13.1–13.2](upgrade-lunar-1.5.md))
  — panel mới có `Panel::section()` chính thức, **không cần reflection nữa**. Đây
  là món nợ kỹ thuật được trả tự động.
- Dựng khung addon theo `packages/panel-addon-example`: một `Section` cho mỗi
  module có admin.

### Fase 4 — viết lại admin — ✅ xong

Thứ tự **thực tế đã làm** khác kế hoạch, vì việc hợp nhất SKU → variant đã xoá
hai mục nặng nhất khỏi danh sách:

1. **Engine resource khai báo + 3 màn hình Nội dung** — dựng nền trước, để một
   màn hình admin tốn một class schema chứ không tốn một trang Vue.
2. **Trang chủ (section) + Lookbook** — hai màn hình Nội dung khó nhất.
3. **Menu** — form sâu nhất (cây 3 tầng).
4. **RMA, vùng ship, bảng size, báo hàng về**.
5. **8 trang cài đặt → một màn hình**.
6. **AnalyticsDashboard → một widget** (phần còn lại panel đã có sẵn).
7. **Size & Fit (slot) + bộ chọn thư viện ảnh**.

`ManageProductVariants` biến mất khỏi danh sách: sau hợp nhất SKU → variant thì
màn hình biến thể chính chủ dùng được. MediaPicker làm **cuối** chứ không phải
đầu — hoá ra nó là chuyện đúng/sai (cột lưu id Asset) chứ không phải nền móng.

Nhật ký ở §9.8; kiến trúc ở
[architecture/panel-addon.md](../architecture/panel-addon.md).

### Fase 5 — nghiệm thu — ✅ xong

- Dựng lại `AdminPagesSmokeTest` cho panel mới: mọi route panel trả 200.
- Test cho **từng slot/extension** khẳng định nó xuất hiện (bẫy 3 & 4 ở §5).
- E2E Dusk vẫn dùng được — panel là ứng dụng web bình thường. Giữ nguyên cách
  làm ở [e2e-testing.md](e2e-testing.md), riêng selector phải viết lại (không còn
  `wire:`, chuyển sang `data-*` của Vue).
- Storefront **không đổi gì** ở đợt này — nếu có test storefront đỏ thì đó là
  regression của Fase 2, không phải của panel.

---

## 7. Rollback

Migration của 2.0 sửa cấu trúc và chuyển dữ liệu (`DataMigrationStep`,
`LedgerRewriteStep`), nên `git reset` một mình **không đủ**:

```bash
git reset --hard <hash ghi ở Fase 0>
composer install
mysql -h127.0.0.1 -uroot lunar < ../backup-pre-lunar-2.0.sql
php artisan optimize:clear
```

Vì đây là alpha, khả năng phải rollback **cao hơn hẳn** đợt 1.5. Đừng chạy trên
production trước khi đi trọn lộ trình một lần trên staging với dữ liệu thật.

---

## 8. Năm câu hỏi mở — đã trả lời khi bắt tay

- [x] **`lunarphp/admin` không có trên packagist.** Không cần nữa: dự án bỏ
      Filament, cài `lunarphp/core` + `lunarphp/panel` (cả hai `2.0.0-alpha.6`).
- [x] **Phân quyền của panel khớp sẵn** — câu hỏi tự tiêu khi kiểm tra:
      `Lunar\Core\Models\Staff` dùng đúng `Spatie\Permission\Traits\HasRoles`
      mà dự án đang dùng, `NavigationRegistry` chỉ gọi `$user->can($permission)`,
      và bảng `permissions` đã có sẵn các handle với `guard_name = staff`.
      Điều **không** hiển nhiên: `Gate::after` của panel chỉ cấp một ability khi
      manifest access-control biết đến nó, mà manifest dựng từ bảng
      `permissions`. Quyền mới vì thế phải có hàng trong bảng — thiếu nó thì
      `can:` chặn tất cả, kể cả admin. Xem
      `..._add_content_manage_permission.php`.
- [x] **2FA đọc được.** Panel tự làm 2FA (`Lunar\Panel\Auth\AppAuthentication`,
      pragmarx/google2fa) nhưng đọc **cùng cột** `lunar_staff.app_authentication_*`
      qua **cùng cast** `encrypted` / `encrypted:array`. Bản vá ở
      [1.5 §15](upgrade-lunar-1.5.md) vì thế còn nguyên giá trị; chỉ khác accessor
      (đọc thẳng thuộc tính) và thứ tự tham số `verifyCode($secret, $code)`.
      `StaffTwoFactorReencryptionTest` đã chuyển sang verifier của panel.
- [x] **Drafts không xung đột với model replacement** — vì 2.0 **xoá hẳn** cơ chế
      model replacement (xem §9.1). Câu hỏi tự tiêu.
- [x] **`SkipsEmptyTranslations` vẫn cần.** `HasTranslations::translate()` ở
      alpha.6 y nguyên lỗi cũ. Nhưng cách vá phải đổi (§9.1), và bề mặt đổi cả hai
      chiều: `attributes.name` / `attribute_groups.name` nay là cột string thường
      (hết việc), còn `name`/`description`/`short_description` của product,
      collection, brand ra khỏi `attribute_data` thành cột JSON thật (thêm việc).

---

## 9. Nhật ký thực thi (Fase 0 → 3)

Hoàn tất 2026-09-09. **560/560 test xanh**, panel phục vụ 370 route, `/panel/login`
trả 200. Sáu commit: `2abb2eb` (Fase 1) → `76c8e29`.

Mục này ghi những gì **kế hoạch không lường được**. Phần nào runbook ở trên đã
nói đúng thì không nhắc lại.

### 9.1 `ModelManifest::replace()` biến mất — không có cái thay thế

Kế hoạch xếp nó là bậc thứ tư trên thang mở rộng (docs/README.md §1). 2.0 **gỡ
hẳn**: core gọi thẳng `ProductOptionValue::class`, `ModelManifest` chỉ còn route
binding + morph map. Bốn lần `replace()` trong `CatalogServiceProvider` phải đi
đường khác:

| Việc cũ | Đường mới |
|---|---|
| `SkipsEmptyTranslations` trên 4 model | Cast `Modules\Core\Casts\FilledTranslations` cài bằng `Model::addCasts()` |
| `display_type` của ProductOption | Cột `type` chính chủ + enum `ProductOptionType` |

`Base::addCasts()` (`HasExtendableCasts`) là seam duy nhất 2.0 để lại cho việc
này. Bản vá **hạ xuống một tầng**: thay vì sửa `translate()`, lọc locale rỗng
ngay lúc decode JSON — thế là `translate()` của upstream trở thành đúng, vì
"khoá tồn tại nhưng rỗng" không còn xảy ra được. Rộng hơn bản cũ: mọi model đọc
cột đó đều được, không chỉ những model ta nhớ mà subclass.

`display_type` thì **2.0 đã làm hộ**: cột `type` có index, enum
`ProductOptionType` (text/colour/swatch), `UpdateProductOption` tự dọn payload
khi đổi type. Extension của dự án biến mất, dữ liệu chuyển bằng migration
(`color` → `colour`, `image` → `swatch`).

### 9.2 `lunarphp/upgrade` alpha.6 để lại ba lỗ

Bước cuối của `lunar:upgrade` ghi lại ledger, đánh dấu **toàn bộ** baseline v2 là
đã chạy. Nghĩa là: cột nào 19 data migration bỏ sót thì **không bao giờ** được
tạo nữa. Phát hiện bằng cách dựng baseline v2 vào một DB nháp rồi diff
`information_schema` — nên làm ở mọi lần nâng cấp alpha:

```bash
mysql -e "create database lunar_v2_ref"
DB_DATABASE=lunar_v2_ref php artisan migrate
# rồi so sánh cột giữa hai schema
```

Ba cột thiếu, cả ba đều có code 2.0 đang đọc: `customers.admin_notes` (panel),
`order_lines.refunded_quantity` (RefundOrder), `product_options.type`
(ProductOptionType). Vá bằng
`2026_09_08_080000_close_lunar_2_0_upgrade_schema_gaps`.

Hai lỗ còn lại:

- `v2_baseline` trong config thiếu bốn file (`000066/70/71/72`) mà chính gói đó
  ship. `000071` thêm FK đã tồn tại → phải đánh dấu đã chạy bằng tay.
- `RectorStep` là **stub**, và config của nó hardcode
  `withPaths([app, config, database])` — dự án này để gần như toàn bộ code trong
  `modules/`, `themes/`, `tests/` nên **không rule nào chạm tới**. Phải tự viết
  config trỏ đúng đường và thêm `withImportNames`, nếu không 186 file còn lại
  import chết.

> **Bẫy production, chưa nổ ở đây chỉ vì DB dev rỗng đơn hàng:**
> `lunar.upgrade.orders.{fulfilled,closed,cancelled}_statuses` mặc định là
> `['dispatched','complete']` / `['complete','cancelled','refunded']`. Shop này
> dùng `completed`, `payment-offline`, `payment-received` — **không khớp**. Chạy
> trên dữ liệu thật mà không publish và sửa config đó trước thì mọi đơn đã hoàn
> tất bị map sai, lặng lẽ.

### 9.3 Ba migration của dự án giả định schema v1

Chỉ hỏng khi **cài mới** — tức là CI, không phải máy dev đã nâng cấp. Đây là
loại lỗi runbook không thấy vì nó chỉ nhìn đường nâng cấp:

- `default_variants_to_in_stock` — cột `purchasable` đổi tên thành
  `selling_policy`.
- `add_seo_attributes_to_products` — `attribute_groups.attributable_type` và
  `attributes.attribute_type` biến mất (quan hệ chuyển sang pivot
  `attribute_models`), `name` thành cột string, và `type` là **chuỗi
  `FieldTypeEnum`** chứ không phải class name.
- `add_builder_columns_to_product_variants` — `after('purchasable')`. 2.0 còn tự
  có sẵn `model` + `cost_price`.

Bài học chung: **`after()` trong migration là một liên kết ngầm tới layout của
vendor.** Thứ tự cột là thẩm mỹ; đừng trả giá bằng một lần hỏng khi vendor đổi
tên hàng xóm.

### 9.4 API tiền — rector không đụng tới

Spec 0012 gỡ cast tiền khỏi `Order`/`OrderLine`/`Transaction`: cột là `int`
thường, format qua `$model->format('cột')`. Riêng `Price` của catalogue có
`unitFormat('price')` / `unitDecimal('price')` — chia theo `unit_quantity`, đúng
bằng việc cast cũ làm. Còn `Cart`/`CartLine`/`ShippingOption` **vẫn** là
`PriceValue` với `->format()`. Ba nhóm, ba cách gọi.

`Pricing::for($sku)->get()->matched` nay là **model** `Price`, không phải object
tiền: `->price` là int.

Rule rector cho việc này chỉ khớp property fetch **có kiểu**, mà code dự án đi
qua quan hệ nên nó bỏ qua sạch — 47 chỗ phải sửa tay.

### 9.5 Catalogue `name`/`description` ra khỏi `attribute_data`

Spec 0018 đưa chúng thành cột JSON thật; spec 0019 đổi luôn hình dạng
`attribute_data` từ envelope theo handle (mang sẵn `field_type`) sang **map thô
theo ID attribute**. Hệ quả cho code đọc:

- `translateAttribute('name')` → `translate('name')` (6 file).
- SQL sort/search phải đổi cột. Đây cũng là **một bản sửa lỗi**: biểu thức cũ
  `JSON_EXTRACT(attribute_data, '$.name.value')` trả cả object JSON với
  `TranslatedText`, nên sort A–Z thực chất sort dấu ngoặc kép và tìm kiếm không
  bao giờ khớp tên đã dịch. Nay đi qua `TranslatedColumn::sql()`.
- Bất cứ chỗ nào đọc `attribute_data` phải tra loại field từ bảng `attributes`,
  vì hàng dữ liệu không còn tự khai nữa.

### 9.6 Vòng đời đơn hàng: từ cột sang phái sinh

Thay đổi lớn nhất, và là **quyết định**, không phải phép đổi tên. 2.0 xoá
`lunar_orders.status` và cố ý không mô hình hoá vòng đời do người vận hành tự
bấm — thay bằng hai rollup dẫn xuất (`payment_status` từ sổ giao dịch,
`fulfilment_status` từ fulfilment) cộng `closed_at`/`cancelled_at`.

Dự án **chuyển hẳn** sang mô hình đó. Bảy handle vẫn còn (khách hàng, email,
SMS, file ngôn ngữ đều nói thứ tiếng đó) nhưng là **khung nhìn** trên bốn sự
thật kia — `OrderStatus::of()` — không phải cột ai đó ghi. Không nơi nào set
status nữa: ghi lại sự thật, status tự theo.

Bốn điều chỉ lộ ra khi làm:

1. **Một phân biệt bốn sự thật không làm được:** `payment-offline` (COD, đã bán,
   thu tiền khi giao) và `awaiting-payment` (cổng thanh toán bỏ dở) đều là
   payment pending. Cái tách chúng là *phương thức thanh toán*. Nên checkout ghi
   `meta.payment_type` cho **mọi** đơn, danh sách "trả khi nhận" đọc từ
   `lunar.payments.types.*.authorized` (config đã khai báo sẵn — đừng chép lại
   thành hằng số thứ hai), và một migration backfill `cod` cho đơn cũ. Không có
   nó, mọi đơn COD lịch sử rơi khỏi doanh thu.
2. **Đơn không có gì để giao rollup thành `fulfilled`** ("settled by
   definition"). Nếu đọc rollup trần thì đơn rỗng đọc thành `dispatched` ngay
   khi tạo. Phải thêm điều kiện: thực sự có dòng hàng cần giao.
3. **`RecomputeOrderStatus` ghi bằng `saveQuietly()`** — cố ý, để rollup không
   vòng ngược qua observer. Nghĩa là **observer không bao giờ thấy** hai chuyển
   trạng thái quan trọng nhất. Domain event phải dựng từ chính năm sự kiện của
   Lunar (payment/fulfilment status, cancelled, closed, reopened). Email đổi
   trạng thái và lịch sử timeline cũng phải chuyển thành listener của event đó —
   1.x ghi entry `status-update` vào activity log từ observer canh cột; 2.0 không
   còn cột nên không còn entry.
4. **Lunar tạo sẵn fulfilment lúc đặt hàng** (`EnsureInitialFulfilment`). "Giao
   hàng" là đẩy cái có sẵn sang trạng thái done, **không** phải tạo mới — tạo
   thêm bị từ chối vì dòng hàng đã được phủ.

### 9.7 Asset của panel

`public/vendor/lunar-panel` là bản biên dịch sẵn của vendor: gitignore, và
`lunar:panel:install` vào `post-autoload-dump` — đúng vai trò `filament:upgrade`
từng giữ. Thiếu nó mọi trang panel trả 500 với
`ViteManifestNotFoundException`. Bài học y hệt `public/build` ở
[1.5](upgrade-lunar-1.5.md): **thư mục gitignore chứa asset là bước build bắt
buộc, không phải tuỳ chọn.**

### 9.8 Fase 4 — đã xong

Bảy commit (`c16c8e3` → `3bec7ad`), **618 test xanh**.

Điểm chốt: 25 trong 64 file admin đã xoá là **panel lo sẵn** (sản phẩm, biến thể,
collection, product type, product option, attribute group, customer group, tag,
thuế) — không viết lại gì. Phần còn lại đi qua điểm mở rộng chính thức, không
fork gì cả:

| Cách | Dùng cho |
| --- | --- |
| Engine resource khai báo | 10 màn hình CRUD (Nội dung, RMA, vùng ship, bảng size, báo hàng về) |
| `SettingsGroup` | 8 trang cài đặt cũ → một màn hình, 8 tab |
| `Slot` | Size & Fit chèn vào trang sửa sản phẩm chính chủ |
| `widgets()` | một thẻ dashboard (phần còn lại panel đã có) |
| Không làm | QueueWorkers → Horizon + `queue:monitor`; MediaImageSizes → `media-library:regenerate` |

Chi tiết kiến trúc và các hợp đồng của panel phải dò ra bằng cách đọc nguồn:
[architecture/panel-addon.md](../architecture/panel-addon.md).

**Bốn lỗi tự gây rồi tự bắt được** — đáng ghi vì đều thuộc loại hỏng im lặng:

1. `syncRelations` tái dùng cùng một query builder của quan hệ. `find()` để lại
   `where id = ?` trên chính builder đó nên `delete()` sau đó không xoá hàng nào.
2. Mọi default sort đều trên cột lặp (`created_at`, `sort`, `priority`). Phân
   trang với thứ tự không xác định có thể hiện một dòng ở hai trang và giấu hẳn
   một dòng khác. Nay luôn tie-break theo khoá chính.
3. `tags` chỉ được chuẩn hoá ở `ResourceController`, `SettingsController` không
   biết — hai controller cùng phải tự biết về kiểu trường là công thức sinh lỗi.
   Gộp về `InputNormaliser`.
4. `Field::image()` ban đầu là ô text. Các cột ảnh lưu **id Asset**, nên đó là
   gõ id trong vô định và ảnh xem trước không bao giờ resolve.

### 9.9 Fase 5 — đã xong

Hai commit (`20d27e0`, `75cfbdc`). **639 test PHPUnit + 8 test Dusk**, tất cả xanh.

`PanelRoutesSmokeTest` quét **bảng route** chứ không phải danh sách viết tay, nên
màn hình thêm ngày mai được phủ mà không cần ai nhớ sửa nó. Hiện 60 màn hình,
cộng: từng resource khớp đúng cái nó khai (`canCreate` sai thì màn hình thêm
phải 404, không phải mở), từng tab cài đặt, và mọi mục điều hướng phải trỏ tới
route có thật — mục nào route đổi tên sẽ resolve ra null thành link chết mà
không ai chạy test thấy được. Chuyển hướng thì đi theo chứ không bỏ qua: một cú
302 vào màn hình hỏng vẫn trông như pass.

Về Dusk: suite cũ chỉ phủ storefront nên **không có selector `wire:` nào để
đổi** — thứ lỗi thời là tài liệu, đã viết lại (§3.2, §3.3 của
[e2e-testing.md](e2e-testing.md)). `PanelFormsTest` thêm vào để phủ đúng nửa mà
test feature không chạm tới được: nhánh điều kiện hiện/ẩn, thêm/xoá dòng
repeater, slug bám tiêu đề, bộ chọn ảnh mở ra. Chỉ đọc, không bao giờ lưu.

Hai lỗi bắt được nhờ chính việc viết test:

- `hold_minutes` để `min:1` trong khi `InventoryService::holdMinutes()` clamp về
  `MIN_HOLD_MINUTES = 10` — admin gõ 1, thấy báo đã lưu, hệ thống dùng 10.
- Select bắt buộc chưa chọn thì Vue đặt `selectedIndex = -1`, ô hiện trống, đọc
  như "rỗng" chứ không phải "hãy chọn".

### 9.10 Việc duy nhất còn lại — và tại sao chưa làm

`lunar_product_skus` (648 dòng) và `sku_variant_map` (648 dòng) **vẫn còn trong
DB**. Đã kiểm chứng lại 2026-09-09:

- Ngoài migration, **không còn dòng code nào** đọc hai bảng này.
- Ánh xạ còn nguyên vẹn: 648 SKU ↔ 648 map ↔ 648 variant, 0 mồ côi hai chiều.

Chưa drop là **có chủ ý**, đúng điều kiện đặt ra ở
[migrate-skus-to-variants.md §7](migrate-skus-to-variants.md): drop khi *đã chạy
production một thời gian và không còn ai cần tra ngược*. Điều kiện đó chưa đạt —
panel vừa viết xong tuần này. Đây là bước duy nhất không hoàn tác được từ dữ
liệu còn lại, và bỏ nó đi ngay lúc rủi ro lộ lỗi muộn còn cao nhất là đổi một
lưới an toàn không tốn gì lấy một khoảng trống không lấp lại được.

Khi đủ điều kiện: một migration nhỏ drop hai bảng, không gì khác.
