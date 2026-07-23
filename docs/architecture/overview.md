# SME Fashion Ecommerce — Laravel 12 + LunarPHP

> Tài liệu này mô tả **hiện trạng thực tế** của dự án: một storefront fashion cho
> SME (single-store) trên Laravel 12 (PHP 8.4) + LunarPHP 1.0, admin Filament 3
> (native của Lunar), storefront **100% Blade SSR + vanilla JS** (không Vue).
> Chỉ ghi những gì đã có trong code.
>
> Cập nhật lần cuối: **2026-07-13** — 13 module nghiệp vụ, 63 route `api/v1`, 394 test xanh.
>
> **Lunar là composer package `lunarphp/lunar` trong `vendor/`** — bản fork trong repo
> đã được gỡ (2026-07-20). Đừng sửa `vendor/`; xem
> § [Lunar là composer package](#lunar-là-composer-package-không-fork-vào-repo).
>
> **Storefront Next.js: ⏸ TẠM HOÃN (2026-07-13).** Đã từng tồn tại (Next.js 16, App Router,
> TS, Tailwind 4 ở `../storefront`) và tiêu thụ `/api/v1` qua bearer token + `X-Cart-Token`
> — nay **cố ý dừng để tập trung vào Blade SSR**, là storefront chính thức và duy nhất.
> `../storefront` không còn trong workspace.
>
> **Nó đã trả đủ tiền vé, không phải công cốc:** chính client đó làm lộ bug bearer-token ở
> 3 probe công khai (increment #14) — đúng như dự đoán của audit: *có những thứ chỉ lộ ra
> khi có client thật*.
>
> ⚠️ **`/api/v1` KHÔNG phải "API cho headless" — nó là xương sống của chính Blade SSR:**
> **14 file JS** trong `themes/fashion` gọi nó (cart, coupon, search + suggest, notify-me,
> recommend-size, locations, membership, auth). Nên nó **giữ nguyên và phải khoẻ**; gỡ/khoá
> là gãy storefront ngay.
>
> 🧊 **Đóng băng (2026-07-13) là đóng băng BỀ MẶT, không phải code** — không đụng một dòng,
> 394 test nguyên trạng. Luật: **GIỮ, KHÔNG MỞ RỘNG** — thêm endpoint vì Blade SSR cần thì
> cứ làm; thêm "để sẵn cho app sau này" thì **không** (build cho consumer không tồn tại).
> Luật ghi ở `routes/api.php`; danh sách route chưa có consumer Blade + ngưỡng bỏ đóng băng
> ở [todo.md § 11](../roadmap.md) và § Quyết định có chủ đích.

## Bản đồ tài liệu

Đây là **nguồn sự thật duy nhất về hiện trạng**. Các file khác không lặp lại nó:

| File | Trả lời câu hỏi |
|---|---|
| **architecture/overview.md** (file này) | *Hệ thống hiện có những gì, hoạt động ra sao?* |
| [guides/coding-standards.md](../guides/coding-standards.md) | *Viết code ở đây theo quy tắc nào?* |
| [guides/deployment.md](../guides/deployment.md) | *Đưa lên production thế nào, vận hành ra sao?* |
| [guides/commands.md](../guides/commands.md) | *Lệnh artisan nào hay dùng?* |
| [architecture/theme.md](theme.md) | *Theme `fashion` cấu tạo thế nào?* |
| [roadmap.md](../roadmap.md) | *Còn việc gì chưa làm?* |
| [history/2026-07-platform-audit.md](../history/2026-07-platform-audit.md) | *Biên bản lịch sử: đã tìm ra và sửa những bug nào, bằng chứng gì?* |

Điểm vào tổng quan: [docs/README.md](../README.md).

---

# Mục tiêu sản phẩm

Ecommerce fashion cho SME single-store:

- Laravel-native, dùng Lunar làm commerce core (source of truth cho catalog, cart,
  pricing, order, customer).
- **Một API contract dùng chung:** Storefront controller (Blade) và API endpoint
  (`/api/v1/*`) cùng gọi một lớp service + cùng một API Resource — không nhân đôi
  business logic. Nền tảng sẵn sàng cho app/headless dùng lại backend.
- Storefront render **Blade SSR** cho mọi nội dung công khai (SEO), vanilla JS chỉ
  *enhance* markup đã có.
- Admin dùng **Filament 3** của Lunar — kế thừa & mở rộng, không build lại.

## Nguyên tắc kiến trúc cốt lõi

> Chuẩn code chi tiết ở
> [../guides/coding-standards.md](../guides/coding-standards.md).

1. **Không dựng lại tính năng Lunar đã có — chỉ kế thừa và mở rộng.** Cách mở rộng
   theo thứ tự ưu tiên: cấu hình `config/lunar/*` → điểm mở rộng chính chủ của Lunar
   (bind model, pipelines cart/checkout, custom field/attribute, Filament hook,
   events) → wrap bằng service trong module → **cuối cùng** mới là composer patch
   trong `patches/`. Lunar nằm trong `vendor/` nên **không sửa trực tiếp** — mỗi patch
   là một thứ có thể vỡ khi nâng cấp, phải tự bảo trì (xem § dưới).
2. **Lunar là source of truth** cho catalog, cart, pricing, order, customer. Bọc qua
   service/API, không nhân bản dữ liệu/logic.
3. **Một service là nguồn logic duy nhất**, cả Storefront controller lẫn API
   controller đều gọi nó.
4. **Storefront SSR bằng Blade**; vanilla JS enhance các phần tương tác. **Không dùng
   Vue, không Livewire** cho storefront (Filament admin dùng Livewire nội bộ).
5. **Quy mô: single-store SME, tối giản.** Không platform/plugin SDK, không hook
   engine — cross-module gọi service trực tiếp.

---

# Lunar là composer package (không fork vào repo)

LunarPHP là dependency bình thường: `lunarphp/lunar` trong `vendor/`, provider nạp
qua package auto-discovery.

> **Lịch sử:** Lunar từng được fork vào repo (`modules/Lunar` + `modules/LunarAdmin`,
> 2026-07-13) rồi **đưa trở lại vendor (2026-07-20)**. Lý do đảo ngược: toàn bộ bản
> fork 1201 file chỉ có **6 file thực sự bị sửa** (~90 dòng), trong đó 2 file là
> docblock và code chết. Cái giá — mất mọi security fix và bug fix của upstream — quá
> đắt so với thứ nhận lại. Mọi thay đổi nay đi qua điểm mở rộng chính chủ.

| | Cách làm hiện tại |
|---|---|
| Core engine (`Lunar\`) | `vendor/lunarphp/core` — **không sửa** |
| Admin panel (`Lunar\Admin\`) | `vendor/lunarphp/admin` — subclass + `$swaps` trong `ModulesServiceProvider` |
| Thêm quan hệ vào model core | `Model::resolveRelationUsing()` |
| Thay hẳn model core | `ModelManifest::replace()` — ví dụ `ProductOption` (thêm `display_type`) |
| Sửa thứ không có extension point | **composer patch** trong `patches/` — bậc cuối |

**Hệ quả cần nhớ:**

* **`composer update` nâng cấp Lunar bình thường**, security patch tự về.
* **Không sửa `vendor/`.** Muốn đổi hành vi thì leo thang mở rộng dưới đây; hết cách
  mới viết patch vào `patches/` (`cweagans/composer-patches`). Hiện có đúng **1 patch**:
  fix locale fallback trong `HasTranslations` — một trait, nên không swap được bằng
  `ModelManifest`. Patch nào cũng nên kèm PR ngược lên upstream để sớm bỏ được.
* **Patch có thể vỡ khi nâng cấp** — khi đó `composer update` **fail rõ ràng**, không
  im lặng. Kiểm tra upstream đã nhận fix chưa: rồi thì gỡ patch, chưa thì rebase.
* **Config có hai bản:** default trong package (`mergeConfigFrom`) và `config/lunar/*.php`
  (bản đã publish — **bản này thắng**, vì `mergeConfigFrom` chỉ điền khoá còn thiếu).
  Đổi hành vi thì sửa `config/lunar/*` hoặc dùng `LunarConfigOverride`.

## Điểm mở rộng chính chủ của Lunar (ưu tiên trước khi sửa core)

> Dùng theo thứ tự **nhẹ → nặng** dưới đây. Mỗi kỹ thuật kèm trạng thái thực tế
> (✅ đang dùng / ⚪ có sẵn, chưa dùng) và nơi đăng ký (`register()` vs `boot()` của
> service provider).

## Cây quyết định nhanh

```text
Cần ĐỔI hành vi Lunar có sẵn?
├─ Đổi được qua config?                 → (1) Config / pipeline override
├─ Là driver/type mới (payment, discount, shipping, tax…)? → (2) Manager::extend / addType
└─ Đổi luồng xử lý cart/order?          → (1) Pipeline (chèn/đổi/bỏ stage)

Cần THÊM lên model core (Product, Customer, Order…)?
├─ Chỉ thêm quan hệ?                     → (3) Model::resolveRelationUsing()
└─ Thêm method / cast / scope / override? → (4) ModelManifest::replace (subclass)

Cần PHẢN ỨNG khi có sự kiện?             → (5) Event::listen(LunarEvent)
Cần đổi ADMIN (Filament)?                → (6) ResourceExtension / *PageExtension
                                           (hoặc reuse action native của Lunar)

Không cách nào ở trên chạm tới được?     → (7) composer patch trong patches/ — BẬC CUỐI:
                                           kèm PR ngược lên upstream, ghi rõ lý do
```

## (1) Config / pipeline override — nhẹ nhất, không cần code

Lunar đọc hành vi từ `config/lunar/*` (cart, orders, pricing, payments, media, taxes…) —
bản **đã publish** từ default của package, và **bản publish thắng**
(`mergeConfigFrom` chỉ điền khoá còn thiếu). Ghi đè trực tiếp ở `config/lunar/*`,
hoặc dùng **`Modules\Core\Support\LunarConfigOverride`** để re-apply override lên config
đã publish — an toàn trước `php artisan vendor:publish --tag=lunar --force` (chạy trong
`boot()`).

- **Pipelines** (`cart.pipelines.*`, `orders.pipelines.creation`): chèn / đổi / bỏ bước
  xử lý. Stage là class implement pipeline; muốn thêm logic thì viết stage riêng và
  chèn vào mảng.
  - ✅ **Đang dùng:** `Inventory/Config/overrides.php` chèn `DecrementStock` vào cuối
    `orders.pipelines.creation` (giảm tồn khi tạo order).
  - Các stage core có sẵn để tham chiếu/sắp lại: `FillOrderFromCart`, `CreateOrderLines`,
    `CreateOrderAddresses`, `CreateShippingLine`, `CleanUpOrderLines`, `MapDiscountBreakdown`
    (order); `CalculateLines`, `ApplyShipping`, `ApplyDiscounts`, `CalculateTax`,
    `Calculate` (cart).
- **Payment types / media definitions / cart_session**:
  - ✅ **Đang dùng:** `Checkout/Config/payment-overrides.php` (COD/bank/vnpay/momo type),
    `Assets/Config/overrides.php` (FashionMediaDefinitions), cart_session auto_create.

## (2) Manager / Facade `extend()` — thêm driver/type, cực sạch

Lunar expose facade có `extend()` / `add*()` để cắm implementation mới mà không đụng core.

| Facade | Method | Trạng thái |
|---|---|---|
| `Lunar\Facades\Payments` | `Payments::extend('handle', fn($app)=>…)` | ✅ VNPay + MoMo driver (Checkout provider `boot()`) |
| `Lunar\Facades\Discounts` | `Discounts::addType(MyType::class)` | ✅ QuantityPercentageOff, ComboPercentageOff (Promotion `register()`) |
| `Lunar\Base\ShippingModifiers` | `->add(MyModifier::class)` | ✅ FlatRateShippingModifier (Shipping `boot()`) |
| `Lunar\Facades\Pricing` | pipeline `pricing.pipelines` / modifier | ⚪ có sẵn, chưa cần |
| `Lunar\Facades\Taxes` | driver/manifest | ⚪ dùng Lunar mặc định |
| `AttributeManifest` / `FieldTypeManifest` | `->add()` | ⚪ khi cần custom field/attribute type |

> Custom driver/type là **class riêng trong module** (vd `PaymentTypes/VNPayPayment`
> kế thừa `AbstractPayment`; discount type kế thừa `AbstractDiscountType`), đăng ký qua
> facade — **không** copy code Lunar ra.

## (3) `Model::resolveRelationUsing()` — thêm quan hệ vào model core

Laravel-native. Gắn relation vào model Lunar (Product/Customer/Order…) mà không subclass,
đăng ký trong `boot()`.

- ✅ **Đang dùng:** `Product::material` + `Product::sizeChart` (Catalog provider),
  `Customer::measurement` (Customer provider). Model đích (`ProductMaterial`,
  `CustomerMeasurement`…) sống trong module tương ứng.

```php
Customer::resolveRelationUsing(
    'measurement',
    fn (Customer $c) => $c->hasOne(CustomerMeasurement::class, 'customer_id'),
);
```

## (4) Model replace — thay hẳn bằng subclass của mình

Khi cần **override method / thêm cast, scope, accessor** trên chính model core (vượt quá
một relation). Tạo subclass `extends` model Lunar rồi:

```php
// trong register()
app(\Lunar\Base\ModelManifestInterface::class)
    ->replace(\Lunar\Models\Contracts\Product::class, \Modules\Catalog\Models\CustomProduct::class);
```

Subclass kế thừa toàn bộ hành vi Lunar → không phải đụng vào core. ⚪ **Chưa dùng** — hiện
`resolveRelationUsing` + service wrap là đủ; chỉ leo lên mức này khi thực sự cần đổi
method của model core.

## (5) Events — hook không đồng bộ, coupling lỏng

`Event::listen(LunarEvent::class, Listener)` trong `boot()`. Cách tách rời nhất: nhiều
module cùng nghe một event, không biết nhau.

- ✅ **Đang dùng:** `PaymentAttemptEvent` (Order → email xác nhận + `DispatchOrderPaidForOfflineOrder`);
  `MediaHasBeenAddedEvent` (Assets). Domain event của dự án:
  - `Order\Events\OrderPaid` — consumer: email đã-thanh-toán (Order), sync membership (Promotion).
  - `Order\Events\OrderStatusUpdated` — consumer: notification (Notification), **trả tồn kho**
    (Inventory).
- Quy ước: event **domain của dự án** đặt trong module sở hữu (vd `OrderPaid` ở Order),
  không nhét vào Core (Core chỉ hạ tầng, không business).
- **Chỉ thêm event mới khi đã có consumer thứ hai.** Event "phòng xa" là abstraction thừa.
- Listener **queued** cho side-effect (mail, push); **đồng bộ** cho bất biến đúng-sai
  (trả tồn kho): queue chết thì hàng/tiền sai im lặng.

> ⚠️ Event phải có **ngữ nghĩa rõ**. `OrderPaid` = *"được tính là đã thanh toán"* (chi tiêu
> + doanh thu), **không** phải *"đã nhận được tiền"* — COD bắn `OrderPaid` lúc đặt hàng
> nhưng khách trả khi giao. Listener cần "tiền đã về tay" phải tự kiểm `status ===
> 'payment-received'`.

## (6) Filament admin — Extension classes (không fork resource)

Lunar cho phép mở rộng resource/page admin qua `Support/Extending/*` mà không copy resource:
`ResourceExtension`, `EditPageExtension`, `CreatePageExtension`, `ViewPageExtension`,
`ListPageExtension`, `RelationPageExtension`, `RelationManagerExtension`.

- ✅ **Đang dùng:** `ProductSizeExtension extends ResourceExtension` (thêm tab "Size & Fit"
  + swap tab variants vào Lunar `ProductResource`), đăng ký:
  `LunarPanel::extensions([ProductResource::class => ProductSizeExtension::class])`.
- ✅ **Reuse action native:** nút **Refund** dùng thẳng `ManageOrder::getRefundAction()`
  của Lunar (nó gọi `$transaction->refund()` → driver ta viết) — không cần extension.
- Trang/resource **mới** (Lunar không có) thì build trong module + đóng góp qua
  `Modules\Core\Support\AdminPages::add()/addResource()` (không phải extend, là add mới).

## Chốt: nơi đăng ký & thứ tự

| Kỹ thuật | Provider hook | Ghi chú |
|---|---|---|
| Config / pipeline override | `boot()` | qua `LunarConfigOverride::applyFrom()` |
| `Payments::extend`, `ShippingModifiers->add` | `boot()` | facade cần app booted |
| `Discounts::addType` | `register()` | Filament đọc type sớm |
| `resolveRelationUsing` | `boot()` | model đã load |
| `ModelManifest::replace` | `register()` | trước khi model được dùng |
| `Event::listen` | `boot()` | |
| Filament `AdminPages::add*` | `register()` | `ModulesServiceProvider` gom trong register-phase |

> **Core (`Modules\Core`) đăng ký đầu tiên** → `Settings`, `AdminPages`,
> `LunarConfigOverride`, `Queues` sẵn sàng cho mọi module. Core **chỉ hạ tầng**, tuyệt
> đối không chứa business logic hay điểm mở rộng domain-specific.

---

# Tech stack (theo repo hiện tại)

| Layer | Công nghệ |
|---|---|
| Backend | Laravel 12 (PHP 8.4) |
| Kiến trúc | Modular monolith (`modules/`) |
| Commerce core | LunarPHP 1.0 |
| Admin | Filament 3 (qua Lunar) |
| Storefront render | Blade (SSR) |
| Storefront JS | Vanilla JS + jQuery (tiện ích) — **không Vue** |
| Build | Vite 7 + Laravel Vite Plugin |
| HTTP client (JS) | Axios |
| CSS | Tailwind CSS 4 + SCSS (`themes/fashion/css`) |
| API auth | Laravel Sanctum (token PAT + cookie SPA) |
| DB | MySQL 8 |
| Search | Driver `database` (MySQL) sau interface `SearchEngine` |
| Media | Lunar Media (Spatie MediaLibrary) + on-demand conversions |
| Queue | Horizon (`laravel/horizon` đã cài) |

---

# Kiến trúc tổng thể

**Modular monolith**: `app/` là lớp bootstrap mỏng, toàn bộ logic nghiệp vụ nằm trong
`modules/`. Mỗi module tự chứa code + routes + migrations, đăng ký qua service provider
riêng. Theme chỉ render view.

```text
app/
 ├── Providers/ModulesServiceProvider.php   # quét & đăng ký provider của từng module + Lunar panel
 └── Models/User.php                         # auth user (Lunar customer riêng)

routes/{web,api}.php                         # gom routes từ các module
modules/                                     # 13 module (12 feature + Core hạ tầng)
themes/fashion/                              # theme active (view + JS + CSS)
```

## 13 module (12 feature + Core)

Codebase từng có 24 module scaffold; đã **hợp nhất còn 11 feature module** để hợp quy mô
single-store (gộp các sub-domain tương đồng vào một module, bỏ ~13 service provider),
cộng **1 module `Core`** chứa hạ tầng dùng chung.

**Core** — hạ tầng cross-cutting, **không chứa business logic** (đăng ký đầu tiên nên
mọi module khác dùng được): `Support\Settings` (DB settings store key→JSON + fallback
config/env), `Support\Queues` (tên queue tập trung), `Support\AdminPages` (gom Filament
page/resource module đóng góp), `Support\LunarConfigOverride` (re-apply override lên
`config/lunar/*`), migration `app_settings`.

| Module | Gộp từ | Trách nhiệm | Nội dung chính |
|---|---|---|---|
| **Catalog** | Catalog + Product + Pricing + Review + Recommend + Search + Collection | Toàn bộ hiển thị/truy vấn sản phẩm | Services: `ProductService`, `PricingService`, `ReviewService`, `RecommendationService`, `CollectionService`, `SitemapService`, `SizeChartService`, `SizeRecommender`, `FitHistoryService`. Models: `ProductMaterial`, `SizeChart`, `SizeChartRow`, `Review`. Contracts/Drivers: `SearchEngine` + `DatabaseSearchEngine`. Strategies: `Association`, `Collection`. 7 file Filament (SizeChartResource + ProductSizeExtension). Home/sitemap/health + seeders demo. |
| **Content** | CMS + SectionBuilder + Menu | Nội dung storefront admin-managed | Models: `Page`, `Banner`, `Lookbook`(+Image/Item), `Redirect`, `PageSection`, `Menu`(+Item). Services: `ContentService`, `SectionRenderer`, `MenuRenderer`, `MenuTree`. 20 file Filament (6 resource: Page/Banner/Lookbook/Redirect/PageSection/Menu). |
| **Assets** | Media + FileManager | Ảnh/file | Services: `MediaUrl`, `ConversionGenerator`, `MediaRegenerator`, `MediaSettings`, `FileManager`. On-demand conversion + media library. 3 file Filament (MediaImageSizes, MediaLibrary, MediaPicker). |
| **Checkout** | Checkout + Cart + Payment | Luồng cart → checkout → payment | Services: `CartService`, `CheckoutService`, `TokenAwareCartSession`, `RefundService`. Gateway: `VNPayGateway`/`MoMoGateway` + `*PaymentProcessor` kế thừa **`GatewayReconciler`** (nơi duy nhất giữ luật callback: chữ ký → số tiền → đơn đã đóng → khoá chống race). PaymentTypes: `VNPayPayment`, `MoMoPayment`. Config override `cart-overrides.php` + `payment-overrides.php`. |
| **Customer** | Customer + Location | Khách, địa chỉ, auth, wishlist, địa giới VN | Services: `CustomerResolver`, `AuthService`, `TokenIssuer`, `WishlistService`, `RecentlyViewedService`, `CountryService`. Models: `WishlistItem`, `Province`, `Ward`. Auth web + Sanctum (cookie + PAT), address book, order history, VN provinces/wards API + seeder dataset. |
| **Order** | — | Order, trạng thái, email giao dịch, RMA | Services: `OrderService`, `OrderMailer`, `ReturnService`, `InvoiceService`, `OrderTimeline`. Support: `OrderStatus` (**một nguồn** cho status handle, nhãn i18n, `PAID`/`CLOSED`/`RETURNABLE`). Events: `OrderPaid`, `OrderStatusUpdated`. 4 mailable queued + observer/listeners. |
| **Promotion** | — | Discount nâng cao hiển thị storefront | Services: `PromotionService` (facade: queries + coupon + memoization), `PromotionTargetResolver` (targeting/eligibility), `SaleBadgeService` (badge/banner/describe), `MembershipService`. Custom discount types + flash sale + membership. |
| **Inventory** | — | Stock per-variant, reserve **+ release**, notify-me | Services: `InventoryService`, `StockReleaser`, `StockNotificationService`, `BackInStockNotifier`. Pipeline `DecrementStock`. Command `orders:expire-abandoned`. Model `StockNotification`. Filament page Stock Overview. |
| **Shipping** | — | Zone/rate DB-backed | Services: `ShippingService`, `ShippingZoneResolver`. Model `ShippingZone`. Filament resource. |
| **Notification** | — | In-app inbox + push cho mobile | Notification `OrderStatusChanged` (channel `database` + `PushChannel`). Contract `PushSender` + `NullPushSender`. Models `DeviceToken`. Service `DeviceRegistry`. Support `PushSettings` (kill-switch push, admin). Filament page Notifications. API inbox + device registry. **Không** đụng 4 mailable đang chạy. |
| **Analytics** | — | Dashboard bán hàng | `AnalyticsService` + Filament Sales Dashboard. |
| **Theme** | — | Active theme, locale, view namespace | Services: `LocaleService`, `ThemeSettings`. 11 file Filament (Theme settings + resource swaps). Middleware storefront/locale. |

> Cấu hình hệ thống (Channels, Languages, Taxes, Staff) dùng thẳng Settings của Lunar.

## Nguyên tắc Theme

- **Theme = lớp trình bày thuần.** `themes/fashion/` chỉ chứa Blade + JS + CSS, không
  query DB, không gọi model Lunar trực tiếp, không business logic.
- **Data đến từ service layer.** Storefront controller (trong module) gọi service, đổ
  data (qua API Resource shape) vào Blade. View composer inject data trình bày (ảnh,
  giá, menu) để Blade không resolve service (coding standards §7).
- Active theme: `fashion` (theme duy nhất active). Đổi brand = copy `themes/fashion`.

## Cấu trúc một Module

```text
modules/<Name>/
 ├── Http/Controllers/{Storefront,Api/V1}/   # Blade (theme::) và JSON (/api/v1)
 ├── Http/{Requests,Resources}/              # validation + API Resource (JSON contract)
 ├── Services/                               # business logic (web + API gọi chung, ≤ 500 dòng)
 ├── Support/                                # value object, hằng số (vd OrderStatus)
 ├── Data/                                   # DTO / result object, bất biến
 ├── Contracts/                              # CHỈ khi có ≥ 2 implementation (SearchEngine, PushSender)
 ├── Models/ Events/ Listeners/ Jobs/ Observers/
 ├── Pipelines/                              # stage chèn vào pipeline Lunar (DecrementStock)
 ├── DiscountTypes/ | PaymentTypes/ | Modifiers/ | Strategies/ | Drivers/
 ├── Console/                                # artisan command của module
 ├── Filament/  Database/{Migrations,Seeders}  Config/
 ├── Routes/{web,api}.php                    # api tự prefix /api/v1
 └── Providers/<Name>ServiceProvider.php
```

Namespace PSR-4 `Modules\<Name>` → `modules/<Name>`. `ModulesServiceProvider` quét mảng
module, đăng ký provider từng module rồi đăng ký Lunar panel cuối cùng (để gom Filament
page/resource do module đóng góp). Thứ tự trong mảng có ý nghĩa khi module này phụ thuộc
binding của module kia (vd `Notification` sau `Order`).

**Chiều phụ thuộc** (kiểm bằng review):

```text
themes/ ──view──▶ modules/<X>/Http ──▶ modules/<X>/Services ──▶ Lunar
                     │ cross-module CHỈ qua: service/Support công khai của module khác,
                     ▼ domain event, hoặc contract — KHÔNG chạm model nội bộ
routes/ ──gom──▶ modules/*/Routes
app/    ──boot──▶ modules/*/Providers   (Core trước, Lunar panel cuối)
```

---

# API dùng chung (`/api/v1/*`)

Mọi nghiệp vụ storefront expose qua `/api/v1`. Web SSR hydrate từ **chính shape** của
các endpoint này (nhúng `$state` JSON vào DOM) → một contract cho cả SSR và JS.

```text
GET    /api/v1/products                                  (+ ?slugs= giữ thứ tự cho recently-viewed)
GET    /api/v1/products/{slug}                           (+ ?include=size_chart,related)
GET    /api/v1/products/{slug}/size-chart
POST   /api/v1/products/{slug}/recommend-size           (+ fit_history khi đã đăng nhập)
GET    /api/v1/products/{slug}/recommendations
GET    /api/v1/products/{product}/reviews  · POST …/reviews
GET    /api/v1/collections/{slug}
GET    /api/v1/search  ·  /search/suggest
GET,POST,PATCH,DELETE /api/v1/cart …  (lines, coupon, coupons)
GET    /api/v1/cart/recommendations
GET    /api/v1/checkout/shipping-options · POST /checkout/{addresses,shipping} · POST /checkout
POST   /api/v1/auth/{register,login,logout}              (Sanctum SPA cookie)
POST   /api/v1/auth/token · /auth/token/register         (PAT — app/headless)
POST   /api/v1/auth/token/refresh · /auth/token/revoke   (xoay / thu hồi token)
GET    /api/v1/orders · /orders/{id} · /orders/{id}/timeline
GET    /api/v1/notifications  ·  POST /notifications/{id}/read · /notifications/read-all
POST   /api/v1/devices  ·  DELETE /devices               (push token registry)
GET    /api/v1/home-feed                                 (trang chủ dạng JSON cho client headless)
GET,POST,DELETE /api/v1/customer/recently-viewed
GET    /api/v1/customer  ·  wishlist
GET    /api/v1/locations/provinces  ·  /provinces/{province}/wards
POST   /api/v1/inventory/notify-me
GET    /api/v1/promotions  ·  /promotions/membership
GET    /api/v1/health   (probe thật: DB+cache+queue → 503 khi hỏng; không middleware)
```

Đặc điểm API-first đã đạt:
- Storefront và API cùng gọi một service + cùng một API Resource; SSR nhúng đúng shape
  `{data,facets,meta}` (search/collection).
- Error envelope chuẩn cho `api/v1/*`: `{message, errors?}` bất kể `Accept` header
  (`Modules\Core\Support\ApiErrorResponse`, gắn ở `bootstrap/app.php`). Success:
  `{data, meta?}`. Guest gọi route cần auth luôn nhận **401 JSON**, không redirect
  HTML (`redirectGuestsTo` trả `null` cho `api/v1/*`). Khi response đã gửi mà shutdown
  ném tiếp exception → **không nối thêm body** (tránh JSON hỏng).
- **Phân trang một chuẩn:** request `?page=` + `?per_page=` (clamp `[1,100]`), response
  `meta{page,per_page,last_page,total}` — `Modules\Core\Support\ApiPagination`.
- Versioning: mọi route tự prefix `api/v1`.
- **Locale mọi route** `api/v1` (51/52, trừ `health`): `?locale=` → `Accept-Language` →
  default. Ngôn ngữ khách chọn trên storefront (session) thắng `?locale=`.
- Sanctum: SPA cookie (guard `web`) + Personal Access Token (`POST /auth/token`) cho
  app/headless — mọi route `auth:sanctum` nhận cả Bearer token. Token mới có
  `expires_at` (60 ngày, `API_TOKEN_TTL_DAYS`) + ability `customer:*`; xoay qua
  `/auth/token/refresh`. **Không** bật `sanctum.expiration` (nó tính từ `created_at`
  nên sẽ giết token đã phát hành). Ability chỉ ràng buộc **bearer token**
  (`token.ability` middleware), cookie session đi qua nguyên vẹn.
- Storefront controller không chạm model xuyên module (gom về service:
  `WishlistService`, `CountryService`, `OrderService::findByReference`).

---

# Database

Lunar đã có: products, variants, prices, collections, customers, carts, orders, media,
attributes. Các bảng fashion-specific thêm trong module tương ứng:

- **Catalog:** `product_materials`, `size_charts` (+rows, link table `product_size_chart`),
  `product_reviews`.
- **Content:** `pages`, `banners`, `lookbooks` (+images, items có `pos_x/pos_y/image_id`
  cho hotspot shoppable), `redirects`, `page_sections`, `menus`(+items).
- **Assets:** `media_settings`.
- **Checkout:** `lunar_carts.public_token` (nullable, unique) — handle để client headless
  (`X-Cart-Token`) nhận lại giỏ; cart của storefront không có giá trị này.
- **Customer:** `wishlist_items`, `vn_locations` (provinces/wards).
- **Inventory:** `stock_notifications`.
- **Shipping:** `shipping_zones`.
- **Performance indexes** ([add_performance_indexes.php](../../database/migrations/2026_06_29_050039_add_performance_indexes.php)):
  composite index cho `lunar_products(brand_id,status)`, `lunar_urls(slug,element_type,default)`,
  `lunar_prices(priceable_type,price,currency_id)`, variant joins.

Size/màu/fit ưu tiên dùng Lunar attributes & variant options; bảng riêng chỉ cho size
chart phức tạp.

---

# Storefront (theme `fashion`: Blade SSR + vanilla JS)

Storefront **100% Blade SSR + vanilla JS** — không còn Vue (đã gỡ `vue` +
`@vitejs/plugin-vue`, xoá `js/islands/*`; bundle `app.js` ~3KB).

## Mô hình 3 lớp (áp cho mọi trang catalog)

1. **SSR shell** — controller gọi service, Blade render HTML thật (grid, facet, phân
   trang). Crawlable, chạy no-JS. Form/link là `GET` thật.
2. **Hydration payload** — controller serialize cùng shape `/api/v1/*` (API Resource),
   nhúng `<script type="application/json" data-*-state>`. Một contract cho SSR + JS.
3. **Vanilla enhancement** — `enhance/*.js` đọc payload làm state đầu, **không fetch lần
   đầu**, chỉ gọi API khi user tương tác, re-render tại chỗ, đồng bộ URL qua
   `history.replaceState`.

Nội dung SEO công khai (home, product, collection, search, CMS page, breadcrumb,
JSON-LD, meta/OG) **bắt buộc SSR Blade**. Ngoại lệ fetch-on-mount hợp lệ: nội dung
theo session không cần crawl (cart drawer/page, wishlist).

## Các trang & tính năng storefront đã có

- **Home** — promotions-strip, hero-slider, flash-sale, collection-grid,
  product-tabs, promotion-slider, lookbook, iconbox (SectionBuilder render 8
  section từ JSON; section `instagram` đã gỡ 2026-07-09 — migration
  `remove_instagram_page_sections` dọn row cũ; section `testimonial` đã gỡ
  2026-07-11, thay bằng `flash-sale` — band đếm ngược + slider sản phẩm của
  flash sale đang chạy, tự ẩn khi không có flash sale).
- **Product** — gallery Swiper + PhotoSwipe (thumbs-first DOM, responsive `<picture>`
  cho LCP), variant picker (`enhance/product-variant.js`). **Deep-link variant**
  (`?color=red&size=m`): SSR preselect variant + active buttons + **giá đúng variant**
  (no-JS/crawler), JS đồng bộ URL qua `replaceState`. Size chart modal + "find my size".
  Notify-me khi hết hàng. Recently-viewed strip. "You may also like" (Recommend).
  JSON-LD Product + BreadcrumbList.
- **Collection / Search** — SSR-first grid + facet sidebar (`size/color/brand/price`),
  `enhance/_shop.js` fetch khi đổi filter/sort/page, fallback no-JS bằng GET. Search
  autocomplete panel (`/api/v1/search/suggest`). Collection có JSON-LD ItemList +
  BreadcrumbList.
- **Cart** — mini-cart drawer + trang cart (vanilla), qty/remove/coupon/note, free-ship
  progress, applied-discounts label, mini-cart recommendations.
- **Checkout** — Shopify-style 2 cột, order summary sticky, 1 form SSR POST `/checkout`
  (address + shipping + payment cùng lúc, chạy server-side → hết race). Dropdown
  Tỉnh→Phường (`enhance/checkout-address.js` gọi `/api/v1/locations`).
- **Account** — orders + order detail, address book (CRUD + ownership), profile/password.
- **Wishlist / Auth** — vanilla, toggle/count/page; auth form + logout (SPA cookie).
- **Lookbook shoppable** — hotspot pins (dot pulse + popover add-to-cart) + "Shop the set".
- **i18n EN/VI** — lang files `lang/{en,vi}/storefront.php`, `LocaleService` +
  `SetStorefrontLocale` middleware + language switcher; cấu hình ngôn ngữ bật/mặc định
  qua Filament Theme Settings. Single-market: bật 1 ngôn ngữ → khoá locale, ẩn switcher.

## Quy ước JS

- `enhance/*.js`: mỗi module export `default fn(root=document)`, target qua `data-*`,
  bootstrap tự động trong `app.js`. Card động render qua `enhance/_card.js` (khớp
  `product-card.blade.php`).
- Đồng bộ giữa consumer qua DOM event (`cart:updated` → `cart.js` refresh →
  `cart:refreshed`). Không coupling trực tiếp.
- jQuery chỉ cho tiện ích/plugin (slider, lazyload). Axios gọi `/api/v1` (CSRF +
  Sanctum cookie cùng domain). State giỏ là server-side (Lunar cart).

---

# Các domain nghiệp vụ

## Catalog / Product / Search / Recommend
- Product/variant/options map thẳng lên Lunar. `ProductService` là nguồn read duy nhất
  (list qua `SearchEngine`, `findBySlug`, `bySlugs` giữ thứ tự, `related`,
  `resolveSelectedVariant` cho deep-link).
- **Search abstraction:** interface `SearchEngine` + driver `DatabaseSearchEngine`
  (MySQL, `computeFacets` trả size/color/brand/price, `applyFilters`). Đổi engine sau =
  thêm driver, không sửa caller.
- **Recommend:** strategy chain `AssociationStrategy` (Lunar `ProductAssociation`, curate
  tay) → `CollectionStrategy` (wrap `related`). Product page SSR + mini-cart drawer.
- **Review:** model + service + API `products/{product}/reviews`; summary (count+average)
  nhúng vào product payload.
- **Size Intelligence:** size chart + "find my size" (`/recommend-size` → gợi ý, áp vào
  variant picker qua event `size:recommended`). **v2 — fit history**
  (`FitHistoryService`): suy size thật từ order line đã PAID (variant option `size`) +
  `return_requests` có hướng (`too-small`/`too-large`); size đã giữ thắng size đã trả,
  mâu thuẫn → im lặng, trả N chật + N+1 rộng → cảnh báo "giữa hai size". Thứ tự size theo
  `SizeChartRow.sort`. Trả về ở khoá `fit_history` của `/recommend-size` (additive,
  endpoint vẫn public: guest → `null` + 200; resolve user qua guard `sanctum` nên nhận cả
  cookie SPA lẫn Bearer token).
- **Media (Assets):** on-demand conversion (`MediaUrl`/`ConversionGenerator` sinh size
  khi request), sizes cấu hình qua Filament (`MediaSettings`), responsive `<picture>`
  + width-srcset (WebP) ở product card + gallery LCP (`fetchpriority`, dimensions chống
  CLS).

## Cart & Checkout & Payment
- **Headless (2026-07-10):** cart/checkout chạy được **không cần session**.
  `TokenAwareCartSession extends CartSessionManager` (rebind `CartSessionInterface` —
  singleton chính chủ của Lunar, không phải đụng vào core) resolve giỏ theo `X-Cart-Token`
  (cột `lunar_carts.public_token`) rồi tới cart active của user sau Bearer token.
  Guest gọi lần đầu gửi `X-Client: app` để nhận handle. Request có cookie đi **nguyên
  đường Lunar cũ**; `cart_token` **không** lộ vào payload SSR. CSRF miễn trừ request
  stateless (`VerifyCsrfTokenUnlessStateless`) — chúng không mang credential ngầm.
  Cart có `user_id` không claim được chỉ bằng handle.
- Cart = Lunar Cart (server-side) qua `CartService`. Coupon + free-ship threshold +
  applied-discounts.
- Checkout pipeline Lunar (validate→pricing→promotions→shipping→tax→payment→order) qua
  `CheckoutService` + `CustomerResolver` (gắn order vào user đăng nhập).
- **Payment:** driver `offline` (COD/bank) + **VNPay** (`VNPayPayment` driver kế thừa
  `AbstractPayment`, `Payments::extend('vnpay')`; `VNPayGateway` build URL + HMAC-SHA512
  + verify; routes start/return/ipn idempotent, ghi `Transaction`, chuyển order →
  payment-received). VNPay chỉ nhận VND.

## Order & Email
- Order history + order detail + **timeline** (`OrderTimeline` đọc `activity_log` của Lunar,
  **không** tạo bảng riêng; chỉ lấy event `status-update`, vì cùng bảng đó chứa row `updated`
  với full column diff — không được lộ ra).
- **`OrderStatus` là một nguồn duy nhất** cho status handle, nhãn i18n (`label()`), và các
  tập `PAID` / `CLOSED` / `RETURNABLE`. Trước đây mảng "đã thanh toán" bị copy-paste ra
  5 service và đã trôi khỏi nhau (COD tính doanh thu nhưng không lên hạng).
- **4 mailable queued** (`OrderConfirmationMail`, `OrderPaidMail`, `OrderStatusUpdatedMail`,
  `ReturnStatusMail`) + markdown templates + `OrderMailer` (locale-aware).
  Wiring: confirm qua `PaymentAttemptEvent`, paid qua event `OrderPaid` (gateway callback
  **và** COD qua `DispatchOrderPaidForOfflineOrder` — gate theo `OrderStatus::paid()` nên
  bank-transfer/gateway lúc authorize không bắn; `SendOrderPaidEmail` chỉ gửi khi
  `payment-received` vì khách COD chưa trả tiền lúc đặt),
  status-update qua `OrderObserver` (bắn `OrderStatusUpdated` cho **mọi** transition; email
  vẫn giữ skip-list riêng).

## Đổi/trả (RMA)
- `ReturnRequest` + `ReturnRequestLine` (line-level qty), staff approve/reject/refund.
- **Chỉ trả được từ `payment-received` / `dispatched` / `completed`** (`OrderStatus::RETURNABLE`).
  ⚠️ `can_return` trong `OrderResource` chỉ **ẩn nút**; `ReturnService::open()` mới là chỗ
  ép luật — trước 2026-07-10 nó không kiểm gì, mở được RMA trên đơn chưa từng giao rồi
  hoàn tiền. COD ở `payment-offline` không trả được: hàng còn trên đường.
- **Một line chỉ trả được một lần**: `remainingQuantities()` trừ mọi RMA chưa `rejected`,
  validate **trong** transaction sau `lockForUpdate`. Thêm `cappedRefund()` — tổng hoàn không
  vượt order total (COD không có gateway làm trần).
- **Một RMA chỉ hoàn tiền được một lần**: `refund()` **claim** request dưới `lockForUpdate`
  (kiểm `status !== REFUNDED` rồi đánh dấu ngay) **trước** khi gọi gateway. Trước 2026-07-10
  chỉ đường gateway có trần (`RefundService::refundedTotal()`); đơn **COD/bank không có
  capture** nên bỏ qua `RefundService` hoàn toàn → bấm "Refund" hai lần là hoàn tiền đôi và
  gửi email đôi. Lưu ý `cappedRefund()` **không** chặn được ca này vì nó tự loại chính request
  (`whereKeyNot`).
- Gateway fail → **nhả claim** về `approved` + `refund_amount = null` để staff retry, thay vì
  kẹt ở `refunded` mà tiền chưa bao giờ chuyển.
- Refund không có reference từ gateway dùng `refund-{order_id}-{random}`, **không** phải
  `refund-{order_id}` — hai lần hoàn từng phần từng trùng chuỗi, không đối soát nổi với sao kê.
- Email trạng thái gửi **ngoài** transaction (side effect không rollback được); lệnh gọi
  gateway (HTTP) cũng vậy — §4.
- **Branding email:** logo + màu nhấn từ Theme Settings (`ThemeSettings::emailLogo()` —
  URL tuyệt đối vì email render ngoài origin; `emailAccent()` validate hex). Override
  `resources/views/vendor/mail/html/header.blade.php` (logo, fallback site name) và
  `resources/views/mail/default.blade.php` (theme CSS — **phải** ở path này vì chỉ view
  `mail.default` mới được Blade compile, xem `Illuminate\Mail\Markdown::render()`).

## Promotion
- Wrap Lunar Discounts. **Custom discount types** (`QuantityPercentageOff` "mua N giảm
  X%", `ComboPercentageOff` "áo + quần giảm X%") qua `Discounts::addType`. **Flash Sale**
  (AmountOff time-boxed + cờ `data.flash_sale`). **Membership** theo tổng chi tiêu
  (`MembershipService` → Lunar `CustomerGroup` Silver/Gold, sync qua event `OrderPaid`).
- Storefront: promo-bar countdown, "Today's deals" strip, savings ở cart, membership
  card ở account, badge + gạch giá cũ ở product card + trang product (qua
  `PromotionService::saleFor`), applied-discounts ở cart/checkout, section
  `promotion-slider` ở home, trang `/promotions` (index) + `/promotions/{handle}`.
- `PromotionService` là **singleton** + memoize `activeAutomatic()` (eager-load 1 lần)
  → tối ưu N+1 trên product card.

## Inventory
- Stock per-variant. **Reserve** khi tạo order (`DecrementStock` pipeline) + oversell guard
  (conditional UPDATE atomic, tôn trọng `backorder`/`always`).
- **Mặc định `purchasable = in_stock`** (migration đổi default của Lunar, vốn là `always`).
  ⚠️ Trước 2026-07-10 mọi variant đều `always` nên **guard chống oversell chưa từng chạy**
  (đo được: stock=2, đặt 10 → checkout 200, stock **−8**). Admin vẫn chọn backorder/always
  cho từng variant khi muốn bán trước.
- **Release** stock khi đơn `cancelled`/`refunded` (`StockReleaser`, nghe `OrderStatusUpdated`;
  **cố ý đồng bộ** — stock là bất biến đúng-sai, queue chết thì hàng mất im lặng).
  Idempotent qua cột `lunar_orders.stock_released_at`.
- Đơn gateway giữ stock **trước khi** khách trả tiền → command `orders:expire-abandoned`
  (scheduler 10'/lần) huỷ đơn quá hạn và trả stock. Quét **hai** loại:
  (a) đơn gateway bỏ ngang (`status = awaiting-payment` + `meta.payment_type`);
  (b) **đơn mồ côi** `placed_at IS NULL`. Bank-transfer cũng ở `awaiting-payment` nhưng
  thu tay và **có** `placed_at`, nên không bị timer đụng tới.
- **Vì sao có đơn mồ côi:** `Lunar\Actions\Carts\CreateOrder` bọc `DB::transaction` (order
  lines + `DecrementStock` là atomic) và **commit**; driver thanh toán *sau đó* mới
  `update(status, placed_at, meta)` ở câu lệnh riêng. Chết giữa hai bước → order tồn tại,
  kho đã trừ, `meta = null` nên nhánh (a) **mù**. Đo được: sweeper dọn 0 đơn, 2 units kẹt
  vĩnh viễn. Mọi driver đều set `placed_at` ngay khi `authorize()` thành công, nên
  `placed_at IS NULL` là dấu hiệu tin cậy "checkout chưa bao giờ hoàn tất".
  (Double-submit **không** phải nguyên nhân: Lunar chặn bằng `ValidateCartForOrderCreation`
  — cart đã có `completedOrder` thì `createOrder()` ném `CartException`.)
- `InsufficientStockException` trả **422** (không phải 500): người khác lấy mất units cuối
  là chuyện của người mua, không phải lỗi server.
- Notify-me back-in-stock (model + API `/inventory/notify-me` + email queued khi restock).
  Filament page Stock Overview.

## Shipping
- Zone/rate DB-backed (`ShippingZone`: country + states → rate + free-threshold,
  most-specific-wins) qua `ShippingZoneResolver` + `FlatRateShippingModifier`, fallback
  config. Filament Shipping Zones resource.

## Analytics
- `AnalyticsService` (revenue/orders/AOV/monthly/top-products, MySQL-portable, đếm đúng
  paid statuses) + Filament **Sales Dashboard** (KPI + trend 6 tháng + recent orders +
  best-sellers).

---

# Admin (Filament 3 — native Lunar)

Panel Lunar đã có sẵn resource cho Catalog (Products/Brands/Collections/Options/Types/
Variants/Tags/AttributeGroups), Sales (Orders/Discounts), Customers (+Groups), Settings
(Channels/Currencies/Languages/Taxes/Staff/Activities) — **kế thừa, không build lại**.

Resource build mới (Lunar không có), đóng góp qua `AdminPages`/`AdminPages::addResource`:
- **Content:** Pages, Banners, Lookbooks, Redirects, Page Sections, Menus.
- **Catalog:** Size Charts (+ ProductSizeExtension gắn Size Chart/Material vào product
  editor).
- **Assets:** Media Image Sizes, Media Library.
- **Inventory:** Stock Overview. **Shipping:** Shipping Zones. **Analytics:** Sales
  Dashboard. **Theme:** Theme Settings (+ swap một số Lunar resource cho subclass để
  re-group navigation + fix label locale).

`ModulesServiceProvider` reset navigation groups của panel (Catalog/Sales/Content/
Settings) với label dịch được, và gắn resource/page do module đóng góp.

---

# SEO

Canonical, meta, OG/Twitter, robots (noindex trang riêng tư), schema.org
(Product/Offer/BreadcrumbList ở product; ItemList + BreadcrumbList ở collection),
`sitemap.xml` (`SitemapService` gom product/collection/CMS qua Lunar `Url`,
morph-alias-aware, cache 1h) + `robots.txt`. Storefront SSR Blade → crawlable.

---

# Test

**383 test / 1629 assertion, all green (2026-07-10)** — `tests/Feature/`, chạy trên MySQL
`lunar_testing` (app phụ thuộc JSON functions/facets — SQLite không emulate được).
`tests/TestCase` dùng `RefreshDatabase`; trait `CreatesStorefrontData` seed base data +
fixture product/size-chart. Chạy: `vendor/bin/phpunit` (testsuite `Feature`).

Bao phủ: auth (register/login/logout + profile/password), cart (add/update/remove/
coupon), address book CRUD + ownership, checkout→order COD + API + order history/detail
+ cách ly theo customer, search + facets + suggest, size-chart + recommend-size, VNPay
(chữ ký + tamper + callback paid/idempotent/invalid), email (Mail::fake), Location
(provinces/wards), on-demand media conversion, token auth, recommendations, i18n,
product/collection page smoke render, SEO (sitemap + JSON-LD), facet price/brand +
recently-viewed, email branding (logo absolute URL + accent, chặn CSS injection),
fit history (kept/returned → size, between-sizes, cách ly theo customer), cart headless
(X-Cart-Token, không claim được cart của người khác), token policy (expiry/abilities/refresh),
oversell + release tồn kho, RMA (không trả 2 lần, không trả đơn chưa giao), notification.

**Kỷ luật test:**
- **Mutation-check mọi guard**: tắt guard → test phải đỏ. Không đỏ = test không bảo vệ gì.
- Test guard phải chạy trên **dữ liệu như production**. Nếu fixture phải sửa một trường để
  guard hoạt động → hỏi ngay *production có trường đó không?* (bug `purchasable = always`
  lọt qua vì test tự set `in_stock`).
- ⚠️ **Luôn `php artisan optimize:clear` trước khi chạy test.** `config:cache` che các `<env>`
  trong `phpunit.xml` → `DB_DATABASE` trỏ về DB dev và `RefreshDatabase` **xoá sạch nó**.

⬜ **Còn thiếu:** `modules/<Name>/Tests` vẫn trống (toàn bộ 54 file ở `tests/Feature`); chưa
phủ phần thuần-JS (picture/srcset, search-panel, lookbook) — cần browser driver.

---

# Nguyên tắc phạm vi (single-store SME)

Dự án cố ý **không** xây: multi-vendor/marketplace, visual drag-drop editor,
microservices/GraphQL-first, headless SPA tách rời (giữ API sẵn nhưng không tách), AI
recommendations, plugin/platform SDK, hook/workflow engine. Cross-module gọi service
trực tiếp; giữ ít lớp nhất.

## Quyết định có chủ đích — *không phải thiếu sót*

Mỗi mục dưới đây từng được cân nhắc và **cố ý bỏ qua**, kèm **ngưỡng kích hoạt** để lần
sau quyết định bằng dữ kiện chứ không bằng cảm tính.

| Không làm | Vì sao | Ngưỡng để làm |
|---|---|---|
| Class `*Action` riêng | Method nhỏ trong service đã đóng vai action; tách ra chỉ thêm file + indirection, không thêm testability | Một service vượt **500 dòng**, *hoặc* một nghiệp vụ được gọi từ **≥ 2 orchestrator** |
| Repository | Lunar Eloquent **đã là** tầng data; không có nhu cầu đổi store | Cần cache/đổi store thật |
| Interface cho service nội bộ | Contract chỉ đặt ở **ranh giới thay thế được** (`SearchEngine`, payment driver, shipping modifier, `PushSender`); service nội bộ là class cụ thể — container vẫn mock được | Xuất hiện implementation thứ hai |
| Module ERP / CRM / Marketing / Loyalty rỗng | **Loyalty** = membership tiers (đã ở `Promotion`, là biến thể discount). **Marketing** = `Promotion` + `Content`. **ERP/CRM**: chưa có hệ thống ngoài nào để nối | Có hệ thống thật → module `Integrations/<System>` + contract + queued job nghe domain event |
| Cây `app/Domain\|Application\|Infrastructure` | `app/` chỉ ~4 file bootstrap; toàn bộ domain sống trong `modules/` | Xuất hiện logic cross-module không thuộc module nào (`Core` chỉ hạ tầng) |
| ViewModel / Presenter | Blade đã sạch (0 `app()`/`DB::` trong theme); **API Resource chính là** presenter | — |
| BFF | Nay chỉ còn **1 client** (Blade SSR); `/api/v1` là contract chung. Thêm sau **không** phá Domain layer | ≥ 2 client mâu thuẫn nhau về shape/chattiness |
| **Storefront Next.js ⏸** (2026-07-13) | Đã từng chạy, **cố ý dừng để tập trung Blade SSR**. Giữ `/api/v1` + `/home-feed` + token abilities: đang xanh, không nợ, là nền sẵn sàng. **Giữ, KHÔNG mở rộng** — không thêm endpoint/shape cho client chưa tồn tại | Quyết định quay lại headless/mobile app **thật** (có người dùng, không phải "phòng xa") |
| Plugin SDK / hook engine | Đã cố ý gỡ khi gộp 24→13 module | — |

## Increment log

| # | Ngày | Việc | Bằng chứng bảo toàn hành vi |
|---|---|---|---|
| 1 | 2026-07-08 | Tách `PromotionService` (712 dòng) → `PromotionTargetResolver` + `SaleBadgeService`; public API giữ nguyên | 163 test xanh, **không sửa test nào** |
| 2 | 2026-07-09 | Compliance sweep: controller ≤ 100 dòng, Blade ≤ 300, gỡ service-resolve khỏi theme | 170 test xanh, không sửa test |
| 3 | 2026-07-09 | **Phase 1** — sửa nợ P0: một nguồn `paid_statuses`, `OrderPaid` cho COD, rate-limit toàn `api/v1`, health-check thật | 218 test |
| 4 | 2026-07-10 | **Phase 2** — headless: `TokenAwareCartSession` (rebind singleton của Lunar), CSRF cho client stateless, pagination một chuẩn, token expiry + abilities | 279 test |
| 5 | 2026-07-10 | **Phase 3** — mobile: module `Notification` (in-app + push contract), order timeline từ `activity_log`, recently-viewed server-side | 315 test |
| 6 | 2026-07-10 | **Rà soát ecommerce cốt lõi** — tìm + sửa 6 bug tiền/tồn kho (E1–E6), xem [audit](../history/2026-07-platform-audit.md) | 347 test |
| 7 | 2026-07-10 | **Dọn mã nguồn** — `paid_statuses` 5 bản sao → 1; controller về dưới 100 dòng; gỡ N+1 | 349 test |
| 8 | 2026-07-10 | **Siết payment callback** — `GatewayReconciler` chung cho VNPay+MoMo: chặn thiếu tiền, chặn hồi sinh đơn đã đóng, khoá chống race + unique index | 356 test; mutation-check từng guard |
| 9 | 2026-07-10 | **Siết refund** — RMA claim dưới khoá (COD/bank trước đây không có trần nào), nhả claim khi gateway fail, reference refund duy nhất | 360 test; mutation-check từng guard |
| 10 | 2026-07-10 | **Dọn đơn mồ côi** — `orders:expire-abandoned` quét thêm `placed_at IS NULL` (order đã trừ kho nhưng driver chưa kịp ghi `meta`) | 365 test; mutation-check cả 2 nhánh + bank-transfer |
| 11 | 2026-07-10 | **Rà soát tuân thủ standards** — gỡ 2 import model cross-module (§10), Blade thôi resolve service (§7), `DeviceRegistry`/`NotifyMeRequest` đưa logic khỏi controller (§3/§4), 3 Resource mới (§6); xoá endpoint chết `payment/vnpay/start` | 367 test; mutation-check từng guard |
| 12 | 2026-07-10 | **Config → admin** — `inventory.hold_minutes` (giữ hàng đơn chưa trả), `notification.push_enabled` (kill-switch push), `customer.ttl_days` (TTL đăng nhập app). 3 trang Filament + **test Filament đầu tiên** (Livewire) | 383 test; mutation-check cả service lẫn trang admin |
| 13 | 2026-07-12 | **`GET /api/v1/home-feed`** — `SectionRenderer::payload()` song song với `render()` (**cùng provider** → web/JSON không lệch); mỗi section dynamic có **serializer** map model qua Resource sẵn có; section dynamic thiếu serializer bị **bỏ khỏi payload** (không serialise thô) | 390 test; mutation-check guard "thiếu serializer → bỏ" |
| 14 | 2026-07-12 | **Bug thật do client headless lộ ra** — 3 probe công khai (`GET /customer`, `/wishlist`, `/customer/measurements`) nằm ở group `web`, guard mặc định là **session** nên **không thấy bearer token**: client có token hợp lệ vẫn nhận `200 {"data":null}` = "khách vãng lai". Next.js đọc đó là "chưa đăng nhập" → **đá ngược về /login vô hạn**, không lỗi nào hiện ra. Sửa: `$request->user('sanctum')` (guard sanctum đọc **cả** cookie session lẫn bearer, vẫn trả null cho guest thật) | 394 test; mutation-check: trả về `user()` → test đỏ |
| 15 | 2026-07-13 | **Fork Lunar vào repo** — `vendor/lunarphp/{core,admin}` → **`modules/Lunar`** + **`modules/LunarAdmin`**; PSR-4 khai báo tay trong `composer.json`, provider đăng ký tay ở `bootstrap/providers.php` (mất package auto-discovery). Đổi bậc cuối của thang mở rộng: sửa core nay *khả thi* nhưng vẫn là lựa chọn sau cùng — đánh đổi là **tự bảo trì + tự port fix upstream** | Xem § "Lunar là code trong repo" |
| 16 | 2026-07-13 | **Hoãn headless, chốt Blade SSR** — storefront Next.js (`../storefront`) cố ý dừng; Blade SSR là storefront duy nhất. `/api/v1` + `/home-feed` (#13) + token abilities (#4) **giữ nguyên** làm nền, quy tắc **"giữ, KHÔNG mở rộng"**. Hai increment #13/#14 vẫn có giá trị: #14 là bug thật do chính client đó phát hiện | Không đổi code; xanh nguyên trạng |
| 17 | 2026-07-20 | **Đảo ngược #15 — Lunar về lại vendor.** Fork 1201 file nhưng chỉ **6 file thực sự sửa** (~90 dòng), 2 trong số đó là docblock + code chết. Cái giá (mất mọi security/bug fix upstream) quá đắt. `display_type` chuyển sang `ModelManifest::replace` + subclass resource; fix locale `HasTranslations` (trait, không swap được) thành **composer patch** duy nhất trong `patches/` | 423 test xanh, không hồi quy |
| 18 | 2026-07-20 | **nwidart/laravel-modules v13** — package đã cài sẵn nhưng không làm gì; module nạp bằng vòng lặp tay. Chuyển 13 module sang layout v13 (`app/` + `config/database/routes/resources` viết thường, 198 rename giữ history), `module.json` khai báo provider + `priority` thay mảng cứng. `modules_statuses.json` **phải commit** — FileActivator coi module không có trong file là *disabled* | 423 test xanh; panel dựng đúng 30 resource |
| 19 | 2026-07-23 | **Seed đủ tầng SKU** — `lunar_product_skus`, `product_reviews`, `stock_movements` đều **rỗng** dù module đã implement: storefront đọc `skus` nên mọi trang sản phẩm demo không có bộ chọn màu/size, không tồn kho, nút thêm giỏ bị vô hiệu. Thêm 3 seeder (ma trận 3 màu × 4 size, review có hàng đợi duyệt, ledger qua `StockLedger`) | 423 test; ledger invariant 120 SKU, 0 lệch |
| 20 | 2026-07-23 | **Gallery theo màu + sửa N+1** — SKU `images` trả cột JSON thô trong khi gallery cần `{small,large,zoom}`; `swapGallery` bắt theo variant id nên đổi size cũng rebuild. Thêm serialize qua `MediaImageResource`, SSR scope theo variant đang chọn, key theo *tập ảnh*. `chaperone()` trên quan hệ `skus` xoá N+1: endpoint `?slugs=…` từ **297 → 33 statement** | 432 test; mutation-check cả thứ tự ảnh lẫn N+1 |
| 21 | 2026-07-23 | **Sửa lỗi `db:seed` chết giữa chừng** — `menu_items.parent_id` là FK tự tham chiếu `ON DELETE CASCADE`, MySQL từ chối quá 30 lần mở rộng (lỗi 6575). Thêm `Menu::deleteItems()` xoá lá trước. Không chỉ lỗi seed: `MenuTree::save()` (đường lưu menu trong admin) dính cùng lỗi | 431 test; `db:seed` chạy trọn, lặp lại được |

> **Quy tắc cho mọi refactor:** giải thích *why* trước khi viết code · composer patch
> là **bậc cuối** (thử hết extension point trước; nếu patch thì kèm PR upstream)
> và không sửa `vendor/` (package bên thứ ba) · public API (service + shape `/api/v1`) chỉ
> mở rộng tương thích ngược · `vendor/bin/phpunit` xanh + `pint --test` xanh **trên file đã
> sửa** (không phải toàn repo — xem standards §15) trước khi coi là xong · cập nhật tài liệu
> này (ngày tuyệt đối).
