# SME Fashion Ecommerce — Laravel 12 + LunarPHP

> Tài liệu này mô tả **hiện trạng thực tế** của dự án: một storefront fashion cho
> SME (single-store) trên Laravel 12 (PHP 8.4) + LunarPHP 1.0, admin Filament 3
> (native của Lunar), storefront **100% Blade SSR + vanilla JS** (không Vue).
> Chỉ ghi những gì đã có trong code.

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
> [lunarphp_sme_fashion_coding_standards.md](lunarphp_sme_fashion_coding_standards.md).

1. **Không dựng lại tính năng Lunar đã có — chỉ kế thừa và mở rộng.** Cách mở rộng
   theo thứ tự ưu tiên: cấu hình `config/lunar/*` → điểm mở rộng chính chủ của Lunar
   (bind model, pipelines cart/checkout, custom field/attribute, Filament hook,
   events) → wrap bằng service trong module. Không sửa code trong `vendor/`.
2. **Lunar là source of truth** cho catalog, cart, pricing, order, customer. Bọc qua
   service/API, không nhân bản dữ liệu/logic.
3. **Một service là nguồn logic duy nhất**, cả Storefront controller lẫn API
   controller đều gọi nó.
4. **Storefront SSR bằng Blade**; vanilla JS enhance các phần tương tác. **Không dùng
   Vue, không Livewire** cho storefront (Filament admin dùng Livewire nội bộ).
5. **Quy mô: single-store SME, tối giản.** Không platform/plugin SDK, không hook
   engine — cross-module gọi service trực tiếp.

---

# Can thiệp Core Lunar (vendor) mà KHÔNG sửa code

> Nguyên tắc #1: **không bao giờ sửa `vendor/`**. Lunar cung cấp nhiều điểm mở rộng
> chính chủ; dùng chúng theo thứ tự ưu tiên **nhẹ → nặng** dưới đây. Mỗi kỹ thuật kèm
> trạng thái thực tế (✅ đang dùng / ⚪ có sẵn, chưa dùng) và nơi đăng ký
> (`register()` vs `boot()` của service provider).

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
```

## (1) Config / pipeline override — nhẹ nhất, không cần code

Lunar đọc hành vi từ `config/lunar/*` (cart, orders, pricing, payments, media, taxes…).
Ghi đè trực tiếp, hoặc dùng **`Modules\Core\Support\LunarConfigOverride`** để re-apply
override lên config đã publish — an toàn trước `php artisan vendor:publish --tag=lunar
--force` (chạy trong `boot()`).

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

## (3) `Model::resolveRelationUsing()` — thêm quan hệ vào model vendor

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

Subclass kế thừa toàn bộ hành vi Lunar → không sửa vendor. ⚪ **Chưa dùng** — hiện
`resolveRelationUsing` + service wrap là đủ; chỉ leo lên mức này khi thực sự cần đổi
method của model core.

## (5) Events — hook không đồng bộ, coupling lỏng

`Event::listen(LunarEvent::class, Listener)` trong `boot()`. Cách tách rời nhất: nhiều
module cùng nghe một event, không biết nhau.

- ✅ **Đang dùng:** `PaymentAttemptEvent` (Order → email xác nhận, mọi driver);
  domain event của dự án `Modules\Order\Events\OrderPaid` (Promotion nghe để sync
  membership, Checkout dispatch từ callback VNPay/MoMo).
- Quy ước: event **domain của dự án** đặt trong module sở hữu (vd `OrderPaid` ở Order),
  không nhét vào Core (Core chỉ hạ tầng, không business).

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
modules/                                     # 12 module (11 feature + Core hạ tầng)
themes/fashion/                              # theme active (view + JS + CSS)
```

## 12 module (11 feature + Core)

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
| **Catalog** | Catalog + Product + Pricing + Review + Recommend + Search + Collection | Toàn bộ hiển thị/truy vấn sản phẩm | Services: `ProductService`, `PricingService`, `ReviewService`, `RecommendationService`, `CollectionService`, `SitemapService`, `SizeChartService`, `SizeRecommender`. Models: `ProductMaterial`, `SizeChart`, `SizeChartRow`, `Review`. Contracts/Drivers: `SearchEngine` + `DatabaseSearchEngine`. Strategies: `Association`, `Collection`. 7 file Filament (SizeChartResource + ProductSizeExtension). Home/sitemap/health + seeders demo. |
| **Content** | CMS + SectionBuilder + Menu | Nội dung storefront admin-managed | Models: `Page`, `Banner`, `Lookbook`(+Image/Item), `Redirect`, `PageSection`, `Menu`(+Item). Services: `ContentService`, `SectionRenderer`, `MenuRenderer`, `MenuTree`. 20 file Filament (6 resource: Page/Banner/Lookbook/Redirect/PageSection/Menu). |
| **Assets** | Media + FileManager | Ảnh/file | Services: `MediaUrl`, `ConversionGenerator`, `MediaRegenerator`, `MediaSettings`, `FileManager`. On-demand conversion + media library. 3 file Filament (MediaImageSizes, MediaLibrary, MediaPicker). |
| **Checkout** | Checkout + Cart + Payment | Luồng cart → checkout → payment | Services: `CartService`, `CheckoutService`, `VNPayGateway`, `VNPayPaymentProcessor`. PaymentTypes: `VNPayPayment`. VNPay controller + return/IPN. Config override `cart-overrides.php` + `payment-overrides.php`. |
| **Customer** | Customer + Location | Khách, địa chỉ, auth, wishlist, địa giới VN | Services: `CustomerResolver`, `WishlistService`, `CountryService`. Models: `WishlistItem`, `Province`, `Ward`. Auth web + Sanctum (cookie + PAT), address book, order history, VN provinces/wards API + seeder dataset. |
| **Order** | — | Order, trạng thái, email giao dịch | Services: `OrderService`, `OrderMailer`. 3 mailable queued + observer/listeners. |
| **Promotion** | — | Discount nâng cao hiển thị storefront | Services: `PromotionService` (facade: queries + coupon + memoization), `PromotionTargetResolver` (targeting/eligibility), `SaleBadgeService` (badge/banner/describe), `MembershipService`. Custom discount types + flash sale + membership. |
| **Inventory** | — | Stock per-variant, reserve, notify-me | Services: `InventoryService`, `StockNotificationService`, `BackInStockNotifier`. Model `StockNotification`. Filament page Stock Overview. |
| **Shipping** | — | Zone/rate DB-backed | Services: `ShippingService`, `ShippingZoneResolver`. Model `ShippingZone`. Filament resource. |
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
 ├── Services/                               # business logic (web + API gọi chung)
 ├── Models/                                 # model fashion-specific (extend Lunar)
 ├── Filament/                               # admin resources của module
 ├── Database/{Migrations,Seeders}/
 ├── Routes/{web,api}.php                    # api tự prefix /api/v1
 └── Providers/<Name>ServiceProvider.php
```

Namespace PSR-4 `Modules\<Name>` → `modules/<Name>`. `ModulesServiceProvider` quét mảng
module, đăng ký provider từng module rồi đăng ký Lunar panel cuối cùng (để gom Filament
page/resource do module đóng góp).

---

# API dùng chung (`/api/v1/*`)

Mọi nghiệp vụ storefront expose qua `/api/v1`. Web SSR hydrate từ **chính shape** của
các endpoint này (nhúng `$state` JSON vào DOM) → một contract cho cả SSR và JS.

```text
GET    /api/v1/products                                  (+ ?slugs= giữ thứ tự cho recently-viewed)
GET    /api/v1/products/{slug}                           (+ ?include=size_chart,related)
GET    /api/v1/products/{slug}/size-chart
POST   /api/v1/products/{slug}/recommend-size
GET    /api/v1/products/{slug}/recommendations
GET    /api/v1/products/{product}/reviews  · POST …/reviews
GET    /api/v1/collections/{slug}
GET    /api/v1/search  ·  /search/suggest
GET,POST,PATCH,DELETE /api/v1/cart …  (lines, coupon, coupons)
GET    /api/v1/cart/recommendations
GET    /api/v1/checkout/shipping-options · POST /checkout/{addresses,shipping} · POST /checkout
POST   /api/v1/auth/{register,login,logout}              (Sanctum SPA cookie)
POST   /api/v1/auth/token · /auth/token/register         (PAT — app/headless)
GET    /api/v1/customer  ·  wishlist
GET    /api/v1/locations/provinces  ·  /provinces/{province}/wards
POST   /api/v1/inventory/notify-me
GET    /api/v1/promotions  ·  /promotions/membership
GET    /api/v1/health
```

Đặc điểm API-first đã đạt:
- Storefront và API cùng gọi một service + cùng một API Resource; SSR nhúng đúng shape
  `{data,facets,meta}` (search/collection).
- Error envelope chuẩn cho `api/v1/*`: `{message, errors?}` bất kể `Accept` header (map
  401/403/404/status/500 trong `bootstrap/app.php`). Success: `{data, meta?}`.
- Versioning: mọi route tự prefix `api/v1`.
- Sanctum: SPA cookie (guard `web`) + Personal Access Token (`POST /auth/token`) cho
  app/headless — mọi route `auth:sanctum` nhận cả Bearer token.
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
- **Customer:** `wishlist_items`, `vn_locations` (provinces/wards).
- **Inventory:** `stock_notifications`.
- **Shipping:** `shipping_zones`.
- **Performance indexes** ([add_performance_indexes.php](database/migrations/2026_06_29_050039_add_performance_indexes.php)):
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

- **Home** — hero-slider, category-grid, product-tabs, promotion-slider, lookbook,
  testimonial, instagram, iconbox (SectionBuilder render 8 section từ JSON).
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
  variant picker qua event `size:recommended`).
- **Media (Assets):** on-demand conversion (`MediaUrl`/`ConversionGenerator` sinh size
  khi request), sizes cấu hình qua Filament (`MediaSettings`), responsive `<picture>`
  + width-srcset (WebP) ở product card + gallery LCP (`fetchpriority`, dimensions chống
  CLS).

## Cart & Checkout & Payment
- Cart = Lunar Cart (server-side) qua `CartService`. Coupon + free-ship threshold +
  applied-discounts.
- Checkout pipeline Lunar (validate→pricing→promotions→shipping→tax→payment→order) qua
  `CheckoutService` + `CustomerResolver` (gắn order vào user đăng nhập).
- **Payment:** driver `offline` (COD/bank) + **VNPay** (`VNPayPayment` driver kế thừa
  `AbstractPayment`, `Payments::extend('vnpay')`; `VNPayGateway` build URL + HMAC-SHA512
  + verify; routes start/return/ipn idempotent, ghi `Transaction`, chuyển order →
  payment-received). VNPay chỉ nhận VND.

## Order & Email
- Order history + order detail. **3 mailable queued** (`OrderConfirmationMail`,
  `OrderPaidMail`, `OrderStatusUpdatedMail`) + markdown templates + `OrderMailer`.
  Wiring: confirm qua `PaymentAttemptEvent`, paid qua event `OrderPaid` (VNPay callback),
  status-update qua `OrderObserver`.

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
- Stock per-variant. Reserve khi tạo order (`DecrementStock` pipeline) + oversell guard
  (atomic, honor backorder). Notify-me back-in-stock (model + API `/inventory/notify-me`
  + email queued khi restock). Filament page Stock Overview.

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

**163 test / 541 assertion, all green (2026-07-08)** — `tests/Feature/`, chạy trên MySQL
`lunar_testing` (app phụ thuộc JSON functions/facets — SQLite không emulate được).
`tests/TestCase` dùng `RefreshDatabase`; trait `CreatesStorefrontData` seed base data +
fixture product/size-chart. Chạy: `vendor/bin/phpunit` (testsuite `Feature`).

Bao phủ: auth (register/login/logout + profile/password), cart (add/update/remove/
coupon), address book CRUD + ownership, checkout→order COD + API + order history/detail
+ cách ly theo customer, search + facets + suggest, size-chart + recommend-size, VNPay
(chữ ký + tamper + callback paid/idempotent/invalid), email (Mail::fake), Location
(provinces/wards), on-demand media conversion, token auth, recommendations, i18n,
product/collection page smoke render, SEO (sitemap + JSON-LD), facet price/brand +
recently-viewed.

---

# Nguyên tắc phạm vi (single-store SME)

Dự án cố ý **không** xây: multi-vendor/marketplace, visual drag-drop editor,
microservices/GraphQL-first, headless SPA tách rời (giữ API sẵn nhưng không tách), AI
recommendations, plugin/platform SDK, hook/workflow engine. Cross-module gọi service
trực tiếp; giữ ít lớp nhất.
