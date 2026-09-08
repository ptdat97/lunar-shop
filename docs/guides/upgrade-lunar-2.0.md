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

### Fase 4 — viết lại admin

Thứ tự đề nghị, **rủi ro cao trước**:

1. **MediaPicker/MediaBrowser** — 14 call site phụ thuộc. Làm xong mới có nền cho
   phần còn lại.
2. **ManageProductVariants** (SKU builder) — phức tạp nhất, và là nơi đã có 2 bug
   thật.
3. **9 trang cấu hình** — dễ nhất, backend `Settings` giữ nguyên. Làm sớm để quen
   `SettingsShell`.
4. **6 resource Nội dung** — nhiều nhưng lặp lại.
5. **RMA, ShippingZone, SizeChart, Kho, MediaLibrary, QueueWorkers**.
6. **AnalyticsDashboard → widget**.

### Fase 5 — nghiệm thu

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

## 8. Việc chưa trả lời được, phải kiểm lúc bắt tay

Ghi ra để lúc làm không tưởng là đã khảo sát rồi:

- [ ] **`lunarphp/admin` không có trên packagist** (404) trong khi tồn tại trong
      monorepo. Nếu chọn ở lại Filament thì phải xác minh gói nào thực sự cài được.
- [ ] Panel có cơ chế **phân quyền** riêng (`permission: 'sales:manage-customers'`).
      Phải soi nó khớp thế nào với `spatie/laravel-permission` mà dự án đang dùng.
- [ ] **2FA**: panel tự làm (`pragmarx/google2fa` + `bacon/bacon-qr-code`), khác
      đường của Filament v4. Dữ liệu `lunar_staff.app_authentication_*` — vốn đã
      phải chuyển mã một lần ở [1.5 §15](upgrade-lunar-1.5.md) — có đọc được không?
- [ ] Panel dùng **Drafts** (autosave + phát hiện xung đột). Chưa rõ nó tương tác
      thế nào với `ModelManifest::replace()` của dự án (ProductOption, ProductSku).
- [ ] Kiểm `SkipsEmptyTranslations` (xem [upstream/README.md](../upstream/README.md))
      còn cần không — 2.0 có thể đã sửa `translate()` ở upstream.
