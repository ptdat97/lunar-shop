# Plugin SDK — mở rộng hệ thống bằng plugin nội bộ

> **Phạm vi:** SDK này để **dev (team nội bộ hoặc đối tác kỹ thuật)** thêm/đổi tính năng
> cho **một shop** đang vận hành — **KHÔNG** phải nền tảng SaaS / multi-tenant / app store.
> Plugin chạy **cùng quyền với code lõi** (do dev tin cậy viết) nên được kiểm soát bằng
> **allow-list trong config**, không sandbox. Một deploy = một shop.

Plugin là một **extension tự chứa** (routes, migrations, views, Filament page, hooks) cắm
vào app **mà không sửa code lõi**. SDK gồm: một contract (`Plugin`), một runtime
(`PluginManager`), state cài đặt (bảng `plugins`) và 4 lệnh artisan.

---

## 1. Một plugin trông như thế nào

Hai cách cài: **composer package** (khai báo `extra.lunar-sme.plugin`) hoặc **thư mục**
`plugins/<vendor>/<name>/` có `plugin.json`. Ví dụ tham chiếu: `plugins/acme/reviews/`.

```
plugins/acme/reviews/
 ├── plugin.json                 # manifest (discovery + admin liệt kê)
 ├── src/
 │    ├── ReviewsPlugin.php       # class implements Plugin (provider)
 │    ├── ReviewService.php
 │    ├── Models/Review.php
 │    └── Http/ReviewController.php
 ├── routes/api.php
 └── database/migrations/...
```

```json
// plugin.json
{
  "id": "acme/reviews",
  "name": "Product Reviews",
  "version": "1.0.0",
  "provider": "Acme\\Reviews\\ReviewsPlugin",
  "requires": { "core": "^1.0" },
  "hooks": ["product.resource"]
}
```

PSR-4 autoload cho plugin thư mục: thêm vào `composer.json` của app, ví dụ
`"Acme\\Reviews\\": "plugins/acme/reviews/src/"`, rồi `composer dump-autoload`.

---

## 2. Contract `Plugin`

Implement `Modules\Hook\Plugin\Plugin` — hoặc kế thừa `BasePlugin` để chỉ override những
gì cần (`BasePlugin` cho no-op mặc định + `requires() = ['core' => '^1.0']`).

```php
use Modules\Hook\Plugin\BasePlugin;
use Modules\Hook\Services\HookManager;
use Modules\Hook\Support\Hooks;
use Illuminate\Contracts\Foundation\Application;

class ReviewsPlugin extends BasePlugin
{
    public function id(): string { return 'acme/reviews'; }
    public function version(): string { return '1.0.0'; }
    public function requires(): array { return ['core' => '^1.0']; }

    public function register(Application $app): void
    {
        $app->singleton(ReviewService::class);          // bind services
    }

    public function boot(HookManager $hooks): void
    {
        Route::middleware('api')->group(__DIR__.'/../routes/api.php');

        // Enrich product payload — không sửa ProductResource:
        $hooks->addFilter(Hooks::PRODUCT_RESOURCE, function (array $data, $product) {
            $data['reviews'] = app(ReviewService::class)->summaryFor($product->id);
            return $data;
        });
    }

    // Lifecycle — chạy qua artisan, KHÔNG gọi lúc boot:
    public function install(): void   { /* migrate */ }
    public function uninstall(): void { /* rollback */ }
}
```

**Hai pha runtime:**
- `register(Application)` — pha register: bind service vào container.
- `boot(HookManager)` — pha boot: `addAction`/`addFilter` vào `Hooks::*`, load routes/views.

**Lifecycle (idempotent, artisan-driven — KHÔNG chạy lúc boot):**
`install()` (migrate, seed) · `activate()` (publish asset) · `deactivate()` (giữ data) ·
`uninstall()` (rollback, có xác nhận).

---

## 3. Bật & vòng đời

`config/plugins.php` là **nguồn quyết định plugin nào load** (allow-list trong git):

```php
return [
    'core_version' => '1.0.0',                 // khớp requires.core
    'enabled' => ['acme/reviews'],             // chỉ id ở đây mới load
    'paths' => [base_path('plugins')],          // nơi quét plugin.json
];
```

Bảng `plugins` (id, version, active, installed_at) **chỉ lưu state cài đặt** để lifecycle
idempotent — **không** quyết định load. Quy trình điển hình:

```bash
php artisan plugin:install acme/reviews     # migrate + activate (chạy install() lần đầu)
# rồi thêm 'acme/reviews' vào config('plugins.enabled') để nó load khi boot
php artisan plugin:list                      # xem enabled / installed / active / requires
php artisan plugin:disable acme/reviews      # deactivate, GIỮ data
php artisan plugin:uninstall acme/reviews    # rollback (hỏi xác nhận; --force để bỏ qua)
php artisan plugin:doctor                     # kiểm tra requires của plugin đang enabled (read-only)
```

`PluginManager` nạp plugin **sau** module lõi (plugin móc vào app đã wire đủ), **topo-sort
theo `requires`**, **validate version bằng semver**, và **fail-soft**: thiếu dependency /
lệch version / class lỗi → ghi `Log::warning` và **skip**, không làm sập app.

---

## 4. Điểm mở rộng (tái dùng hạ tầng sẵn có)

| Cần | Cơ chế |
|---|---|
| Phản ứng sự kiện nghiệp vụ | `Hook::addAction/addFilter` vào `Hooks::*` (xem §5) |
| Endpoint API/web | `Route::group(...)` trong `boot()` |
| Bảng riêng | migration của plugin + `install()` chạy nó |
| Trang/resource admin | `Modules\Theme\Support\AdminPages::add()` / `addResource()` |
| View/section storefront | namespace `theme::` + SectionBuilder partial |
| Payment/shipping/search driver | `Payments::extend` / `ShippingZoneResolver` / `SearchEngine` |

---

## 5. Danh sách `Hooks::*` (điểm móc domain event)

Tham chiếu hằng số trong `Modules\Hook\Support\Hooks` (đừng dùng chuỗi trần). Docblock của
mỗi hằng ghi rõ Value + Args.

**FILTER (transform giá trị):**
`product.resource`, `cart.resource`, `collection.resource`, `order.resource`,
`search.results`, `product.purchasable`, `menu.items`, `checkout.payment_methods`,
`product.related`.

**ACTION (sự kiện đã xảy ra):**
`order.placed`, `order.paid`, `order.status_changed`, `order.shipped`,
`customer.registered`, `customer.logged_in`,
`cart.line_added`, `cart.line_updated`, `cart.line_removed`, `cart.coupon_applied`,
`cart.emptied`, `checkout.address_set`, `checkout.shipping_selected`,
`product.viewed`, `product.created`, `product.updated`,
`inventory.low_stock`, `inventory.out_of_stock`, `inventory.restocked`,
`search.performed`.

---

## 6. Trang quản lý plugin trong admin (Filament)

Trang **Plugins** (nav group Settings, slug `/lunar/plugins`) gồm:
- **Bảng quản lý** mọi plugin discover được: Version / Enabled / Installed / Active / Requires
  + nút **Install / Reinstall / Disable / Uninstall** (gọi thẳng `PluginManager`).
- **Tab config do chính plugin chèn vào.** Plugin nào implement `PluginConfig` (optional,
  tách khỏi contract `Plugin` để giữ contract tối giản) sẽ có **một tab riêng** trên trang
  này — form Filament native, state nằm dưới namespace theo id plugin nên không đụng nhau.

```php
use Modules\Hook\Plugin\PluginConfig;
use Modules\Hook\Plugin\PluginSettings;
use Filament\Forms\Components\Toggle;

class ReviewsPlugin extends BasePlugin implements PluginConfig
{
    public function configLabel(): string { return 'Reviews'; }

    public function configSchema(): array
    {
        return [ Toggle::make('auto_approve')->label('Auto-approve new reviews')->default(true) ];
    }

    public function configState(): array
    {
        return ['auto_approve' => PluginSettings::for($this->id())->get('auto_approve', true)];
    }

    public function saveConfig(array $data): void
    {
        PluginSettings::for($this->id())->put(['auto_approve' => (bool) ($data['auto_approve'] ?? true)]);
    }
}
```

**`PluginSettings`** là key-value per-plugin do SDK cấp (lưu ở cột `plugins.settings` JSON),
nên plugin chỉ vài tuỳ chọn không cần dựng bảng riêng:
`PluginSettings::for('acme/reviews')->get('auto_approve', true)` / `->put([...])`.

## 7. Payload contract (versioning)

Các payload có hook (`product/cart/order`) là **contract ổn định**: hook được **THÊM** key,
**không** xoá key lõi. `Modules\Hook\Support\PayloadContract` khai báo `REQUIRED_KEYS` cho
mỗi payload + `VERSION`. Test `PayloadContractTest` ghim các key này vào response thật → một
filter/refactor lỡ xoá key lõi sẽ **fail CI** (kể cả do plugin gây ra). `plugin:doctor` in
ra `VERSION` + báo plugin có `requires` không thoả. Khi đổi contract: bump `VERSION` + cập
nhật danh sách **có chủ đích** (được review, không lặng lẽ).

## 8. Plugin tham chiếu (dogfood)

- **`plugins/acme/reviews/`** — review sản phẩm: bảng `product_reviews` riêng, endpoint
  `GET/POST /api/v1/products/{product}/reviews`, khối `reviews` (count + average) nhúng vào
  **mọi** product payload qua FILTER `product.resource`. Test `ReviewsPluginTest`.
- **`plugins/acme/preorder/`** — đặt hàng trước: bảng `product_preorders` riêng; khi bật
  pre-order cho 1 sản phẩm, plugin lật variant sang chế độ `always` (backorder native của
  Lunar) để **mua được khi hết hàng** qua đúng pipeline Lunar + Inventory guard, rồi thêm
  khối `preorder` (enabled + expected_at) vào payload qua FILTER `product.resource`. Test
  `PreorderPluginTest` (mua hết-hàng bị chặn nếu không pre-order; bật pre-order → mua được +
  có badge; sản phẩm thường không bị ảnh hưởng).
- **`plugins/acme/scout-search/`** — **driver-as-plugin**: đăng ký search driver `scout`
  qua `SearchManager::extend('scout', ScoutSearchEngine::class)` trong `boot()`. Bật plugin +
  `SEARCH_DRIVER=scout` → storefront/API search đổi engine, caller (contract `SearchEngine`)
  **không đổi**. ZERO sửa Search module/config. Test `ScoutSearchPluginTest`/`SearchManagerTest`.
- **`plugins/acme/wishlist/`**, **`acme/recommend/`**, **`acme/analytics/`** — feature
  first-party **bóc khỏi module** (Phase 4), enabled mặc định: giữ route/bảng/tên, gỡ coupling
  qua hook (Recommend↔Product dùng `product.related`). Bằng chứng plugin gánh được cả feature
  storefront/admin, không chỉ tiện ích nhỏ.
- **`plugins/acme/workflow/`** — đăng ký **trigger/action/rule-field thật** cho Workflow engine:
  `order.paid` → điều kiện `order.total ≥ N` → action `notify.email`/`webhook.post`. Cho phép
  "khi đơn ≥ N → gửi email" cấu hình qua admin **không code**. Test `WorkflowPluginTest`.

7 plugin tham chiếu dựng **hoàn toàn trên SDK + extension point, ZERO sửa lõi** — phủ mọi kiểu
mở rộng: enrich payload (reviews), can thiệp commerce (preorder), thay driver (scout), bóc
feature (wishlist/recommend/analytics), automation (workflow).
