# SME Fashion Ecommerce — Laravel 12 + LunarPHP

> Trạng thái hiện tại của repo: Laravel 12 + Lunar 1.0. Lunar đã kéo theo
> **Filament 3** làm admin panel, và frontend đã có **Vite 7 + Tailwind 4 + Axios**.
> Kế hoạch dưới đây bám sát đúng những gì đã cài, không thêm stack chưa cần.
>
> **Cập nhật rà soát codebase — 2026-06-21:** nền tảng đã đi xa hơn nhiều so với
> "skeleton". **23 module** đã scaffold (thêm **Location** — VN tỉnh/phường — so với
> rà soát trước) và phần lớn có service + controller + routes (web + `/api/v1`)
> hoạt động. Theme `fashion` là theme đang active với storefront hoàn chỉnh (home,
> product, collection, cart, checkout, search, account, wishlist) bằng Blade SSR +
> Vue islands. Tóm tắt nhanh:
>
> - ✅ **Đã chạy được:** Catalog/Product/Collection, Cart + Checkout (Lunar, COD/bank
>   **+ VNPay**), Customer auth (web + Sanctum), Wishlist, Order history + **email giao
>   dịch (queued)**, Search (interface + driver `database` + `scout`), CMS
>   (Pages/Banners/Lookbooks/Redirects + Filament), Menu, SectionBuilder, Theme switch,
>   **Fashion Size Intelligence** (size chart + "find my size" qua `/recommend-size`),
>   **Location VN** (provinces/wards API + dropdown checkout/account), **Media
>   on-demand conversions** (sinh size khi request, sizes cấu hình qua Filament).
> - ⚠️ **Mới khung, chưa có ruột:** **Payment MoMo** (mới có VNPay; MoMo chưa),
>   **Analytics** (mới vài hàm tổng hợp, chưa có dashboard), **Promotion** (mới wrap
>   Discount đọc, chưa có UI áp dụng nâng cao), **Inventory** (đọc stock/low/out;
>   chưa reserve/oversell/notify-me).
> - ❌ **Chưa có:** ảnh responsive `<picture>`/`srcset` ở storefront (hạ tầng
>   conversion đã xong, theme vẫn render `<img>` 1 size), sitemap.xml + JSON-LD
>   collection/CMS, invoice PDF, RMA/đổi-trả.
>
> **Test:** từ 0 → **hiện 60 test / 251 assertion** (auth, cart, address, checkout
> API + **checkout SSR**, search/size, VNPay, Location, on-demand conversion, token
> auth, recommendations) chạy trên MySQL `lunar_testing`.
>
> ⭐ **Cập nhật kiến trúc 2026-06-21 — BỎ HOÀN TOÀN VUE, storefront 100% Blade SSR +
> vanilla JS.** Lý do: luồng checkout multi-step bằng Vue island gây lỗi "Enter your
> address first" / state lệch. Đã viết lại:
> - **Checkout**: 1 form Blade SSR POST `/checkout` (address + shipping + payment cùng
>   lúc) → `CheckoutController@place` chạy cả flow server-side → hết race multi-step.
>   Dropdown Tỉnh→Phường bằng `enhance/checkout-address.js` (vanilla, vẫn dùng
>   `/api/v1/locations`).
> - **Product**: variant picker + gallery (PhotoSwipe) chuyển sang `enhance/product-variant.js`
>   + `enhance/_gallery.js` (đọc `ProductResource` nhúng SSR; pre-select variant đầu → chạy no-JS).
> - Gỡ `vue` + `@vitejs/plugin-vue` khỏi `package.json`/`vite.config.js`; xoá toàn bộ
>   `themes/fashion/js/islands/*`. Bundle `app.js` từ ~126KB (có Vue) → ~3KB.
> - **Các mục/bảng nhắc "Vue island" bên dưới là LỊCH SỬ** — storefront giờ không còn Vue.
>
> Bảng trạng thái chi tiết ở mục "Trạng thái triển khai (rà soát thực tế)" bên dưới.

---

# Mục tiêu sản phẩm

Xây ecommerce fashion cho SME theo hướng:

- Laravel-native, dùng Lunar làm commerce core
- API-first: mọi nghiệp vụ storefront đi qua một lớp API dùng chung
- Storefront render bằng **Blade + Vue 3 + jQuery** (chưa làm headless ngay, nhưng nền tảng API sẵn sàng cho app/headless sau)
- Admin dùng **Filament 3** (panel native của Lunar) — không tự build lại admin
- Performance-first, SEO tốt, mobile UX tốt
- Dễ customize cho nhiều brand, không vendor lock

## Nguyên tắc kiến trúc cốt lõi

> Chuẩn code chi tiết (Controller/Service/Blade/JS/Hook/Test rules) ở
> [lunarphp_sme_fashion_coding_standards.md](lunarphp_sme_fashion_coding_standards.md) —
> xem **§0 Nguyên tắc cốt lõi** và **§5 Lunar — kiểm tra trước khi build**.

1. **KHÔNG dựng lại tính năng `vendor/lunarphp` đã có — chỉ kế thừa và mở rộng.**
   Nếu Lunar đã làm (product, variant, cart, pricing, discount, order, customer,
   shipping, payment, media…), ta **dùng lại** và chỉ thêm phần thiếu. Cách mở rộng,
   theo thứ tự ưu tiên:
   - **Cấu hình** trong `config/lunar/*` trước (đã publish sẵn trong repo).
   - **Extend / customize** qua điểm mở rộng chính chủ của Lunar: bind lại model
     (`Lunar\Facades\ModelManager` / cấu hình model), pipelines (cart/checkout),
     custom field & attribute, Filament resource hooks, events.
   - **Wrap** bằng service trong module để storefront/API gọi — không sửa code trong `vendor/`.
   - Chỉ viết **mới** khi Lunar thực sự không có (CMS, Theme, SectionBuilder, Search nâng cao…).

   **⚠️ BẮT BUỘC — Quy trình trước khi build BẤT KỲ tính năng mới nào:**

   > Trước khi viết một dòng code cho tính năng mới, **phải kiểm tra `vendor/lunarphp`
   > xem đã có chưa**. Chỉ khi xác nhận Lunar **không** có mới được build mới; nếu có,
   > **kế thừa và phát triển** trên cái đã có.

   Checklist 4 bước (làm theo thứ tự, dừng ngay khi tìm thấy):
   1. **Model / nghiệp vụ?** → grep `vendor/lunarphp` tìm model, table, migration, facade.
      ```bash
      grep -ri "<feature>" vendor/lunarphp/*/src --include=*.php -l
      ```
   2. **Admin (Filament resource)?** → tìm resource sẵn có.
      ```bash
      find vendor/lunarphp -path "*Resources*Resource.php" | grep -i "<feature>"
      ```
   3. **Config / điểm mở rộng?** → xem `config/lunar/*` và pipelines/events Lunar expose.
   4. **Kết luận:**
      - **Có** → kế thừa: config → extend (model bind / pipeline / custom field / Filament hook) → wrap service. **Không** copy ra module viết lại.
      - **Không** → mới được build mới trong module tương ứng; ghi rõ "Lunar không có" trong PR/commit.

2. **Lunar là source of truth** cho catalog, cart, pricing, order, customer.
   Bọc (wrap) qua service/API, không nhân bản dữ liệu/logic.
3. **Một API contract dùng chung.** Blade controller và API endpoint cùng gọi
   một lớp service/action, không nhân đôi business logic. Khi làm app/headless
   sau này chỉ cần thêm consumer, không viết lại backend.
4. **Storefront SSR bằng Blade**, dùng Vue cho các "đảo" tương tác (cart drawer,
   variant picker, quick view, filter), jQuery cho các tiện ích nhỏ / plugin có sẵn.

---

# Tech stack (đúng theo repo hiện tại)

| Layer | Công nghệ | Trạng thái |
|---|---|---|
| Backend | Laravel 12 (PHP 8.4) | ✅ đã có |
| Kiến trúc | Modular monolith (`modules/`) | cần scaffold |
| Commerce core | LunarPHP 1.0 | ✅ đã có |
| Admin | Filament 3 (qua Lunar) | ✅ đã có, cần publish panel |
| Storefront render | Blade (SSR) | cần build |
| Storefront JS | **Vanilla JS (no Vue)** + jQuery (tiện ích) | ✅ |
| Build | Vite 7 + Laravel Vite Plugin | ✅ đã có |
| HTTP client (JS) | Axios | ✅ đã có |
| CSS | Tailwind CSS 4 | ✅ đã có |
| API auth | Laravel Sanctum (token + cookie) | cần thêm |
| DB | MySQL 8 (prod) / SQLite (dev) | cấu hình theo `.env` |
| Cache / Session | Redis (prod) | cần cấu hình prod |
| Queue | Horizon (prod) | cần thêm khi cần |
| Search | MySQL full-text (P1) → Scout sau | DB-first |
| Media | Lunar Media (Spatie MediaLibrary) | ✅ đi kèm Lunar |
| Images | WebP/AVIF qua conversions | cấu hình sau |
| CDN | Cloudflare | prod |

> Lưu ý: **không** dùng Livewire cho storefront (chỉ Filament admin dùng nội bộ).
> Storefront là Blade + Vue + jQuery theo yêu cầu.

---

# Kiến trúc tổng thể

Áp dụng **modular monolith**: `app/` chỉ là lớp bootstrap mỏng, **toàn bộ
logic nghiệp vụ nằm trong `modules/`**. Mỗi module tự chứa code + routes +
migrations + tests, đăng ký qua service provider riêng. Theme chỉ render view.

```text
app/                             # mỏng: bootstrap, không chứa business logic
 ├── Providers/                  # AppServiceProvider, ThemeServiceProvider, ModulesServiceProvider
 └── Models/User.php             # auth user (Lunar customer riêng)

routes/
 ├── web.php                     # gom (require) web routes từ các module
 └── api.php                     # gom /api/v1/* từ các module (Sanctum)

modules/                         # TOÀN BỘ logic ở đây (23 module — đã scaffold)
 ├── Catalog
 ├── Product
 ├── Collection
 ├── Inventory
 ├── Pricing
 ├── Cart
 ├── Checkout
 ├── Customer                     # + auth Sanctum, wishlist
 ├── Order
 ├── CMS
 ├── Menu                         # (ngoài plan gốc) menu điều hướng
 ├── Theme
 ├── SectionBuilder
 ├── Media
 ├── FileManager                  # (ngoài plan gốc) quản lý file admin
 ├── Search
 ├── Recommend                    # gợi ý sản phẩm (P1: Association + Collection)
 ├── Promotion
 ├── Shipping
 ├── Payment
 ├── Location                     # (ngoài plan gốc) địa giới VN: tỉnh/phường cho địa chỉ
 ├── Hook
 └── Analytics

themes/                          # theme CHỈ chứa view, KHÔNG có business logic
 └── fashion/
      ├── theme.json             # manifest: name, version, author
      ├── views/                 # Blade (SSR) — render từ data API/service đổ vào
      │    ├── layouts/
      │    ├── pages/            # home, product, collection, cart, checkout…
      │    ├── sections/         # hero, product-grid, banner, slider… (JSON → partial)
      │    └── components/       # Blade components UI
      ├── js/
      │    ├── app.js            # bootstrap Vue + jQuery + axios
      │    └── components/       # Vue islands (cart, filters, variant…) → gọi /api/v1
      ├── css/
      │    └── app.css
      └── assets/                # ảnh tĩnh, fonts của theme
```

## Nguyên tắc Theme

- **Theme = lớp trình bày thuần.** `themes/fashion/` chỉ chứa Blade + JS + CSS,
  **không** chứa query DB, không gọi model Lunar trực tiếp, không business logic.
- **Data luôn đến từ API / service layer.** Storefront controller (trong module, ví dụ
  `modules/Product/Http/Controllers/Storefront`) gọi service/action của module, đổ data
  (đã qua API Resource shape) vào Blade. Vue islands trong theme gọi `/api/v1/*` cho phần động.
- **Đổi/nhân bản theme cho brand khác** = copy `themes/fashion` → `themes/<brand>`,
  không đụng tới `app/`. Active theme chọn qua config/DB.

## Đăng ký theme (cấu hình cần thêm ở Phase 0)

- **View namespace:** trỏ Blade tới theme đang active.
  ```php
  // AppServiceProvider / ThemeServiceProvider
  $theme = config('theme.active', 'fashion');
  View::addNamespace('theme', base_path("themes/{$theme}/views"));
  // dùng: return view('theme::pages.product', $data);
  ```
- **Vite input:** build JS/CSS của theme đang active.
  ```js
  // vite.config.js
  input: [
    `themes/${process.env.THEME ?? 'fashion'}/css/app.css`,
    `themes/${process.env.THEME ?? 'fashion'}/js/app.js`,
  ]
  ```
- `theme.json` (manifest) giữ name/version/author để quản lý nhiều theme.

## Cấu trúc một Module

Mỗi module tự chứa, đặt namespace `Modules\<Name>`:

```text
modules/Product/
 ├── Http/
 │    ├── Controllers/
 │    │    ├── Storefront/        # trả Blade (theme::…) cho web
 │    │    └── Api/V1/            # trả JSON cho Vue/app/headless
 │    ├── Requests/
 │    └── Resources/             # API Resource — JSON contract ổn định
 ├── Services/ | Actions/         # business logic (web + API gọi chung)
 ├── Models/                     # model fashion-specific (wrap/extend Lunar)
 ├── Filament/                   # admin resources của module
 ├── Database/Migrations/
 ├── Routes/
 │    ├── web.php
 │    └── api.php                # /api/v1/...
 ├── Tests/
 └── ProductServiceProvider.php  # đăng ký routes, migrations, views, bindings
```

**Quy ước chung mọi module:**
- Một lớp service/action là nguồn logic duy nhất; **cả Storefront controller lẫn
  API controller đều gọi nó** — không nhân đôi nghiệp vụ.
- Module **không gọi thẳng model của module khác**; giao tiếp qua service công khai
  hoặc event (xem module `Hook`) để giữ ranh giới rõ ràng.
- `composer.json` autoload PSR-4 `Modules\\` → `modules/`. Một
  `ModulesServiceProvider` quét và đăng ký service provider của từng module.

## Trách nhiệm từng module

| Module | Trách nhiệm | Resource/feature Lunar đã có (kế thừa) | Trạng thái thực tế |
|---|---|---|---|
| **Catalog** | Điều phối product/collection, listing, facet, seeders demo | Products, Brands, Tags, Attribute Groups, Product Types | ✅ chạy (home + storefront controllers, seeders) |
| **Product** | Product, variant, options (size/color), material, size chart, related | Products, Product Variants, Product Options | ✅ chạy (Service + API Resource + size chart) |
| **Collection** | Collection, collection groups, gán product | Collections, Collection Groups | ✅ chạy (storefront + API) |
| **Inventory** | Stock per-variant, reserve, low-stock, oversell | stock trên Product Variant | ✅ reserve stock khi tạo order (`DecrementStock` pipeline) + oversell guard (atomic, honor backorder/always), notify-me back-in-stock (model + API `/inventory/notify-me` + email queued khi restock), Filament page **Stock Levels** |
| **Pricing** | Giá theo variant/customer-group, tax-inclusive | Prices, Currencies, Customer Groups | ✅ wrap Lunar Pricing |
| **Cart** | Lunar Cart wrap, line, coupon, free-ship threshold | Lunar Cart | ✅ chạy (drawer/page/count vanilla + API + coupon) |
| **Checkout** | Pipeline validate→pricing→ship→tax→pay→order | Lunar checkout pipeline | ✅ chạy (addresses/shipping/placeOrder, driver `offline`) |
| **Customer** | Khách, địa chỉ, auth (web + Sanctum), order history, wishlist | Customers, Customer Groups | ✅ chạy (auth web+API, wishlist, orders) |
| **Order** | Order, trạng thái, fulfilment, invoice | Sales / Orders | ✅ order history + **email giao dịch (3 mailable queued)**; ⚠️ chưa invoice PDF/fulfilment UI |
| **CMS** | Pages, banners, lookbooks, redirects | — | ✅ chạy (4 Filament resource + storefront) |
| **Menu** | Menu điều hướng storefront (header/footer) | — | ✅ chạy (model + Filament + service) |
| **Theme** | Active theme, view namespace, Vite, manifest | — | ✅ chạy (2 theme, switch qua config) |
| **SectionBuilder** | page_sections (JSON) → Blade partial trong theme | — | ✅ chạy (model + Filament + render) |
| **Media** | Conversions, definitions fashion (hover/gallery/zoom) | Lunar Media (Spatie) | ✅ **on-demand conversion** (`MediaUrl`+`ConversionGenerator` tự sinh size khi request, dùng trong API Resource) + sizes cấu hình qua Filament (`MediaSettings`); ⚠️ theme vẫn `<img>` 1 size, chưa `<picture>`/`srcset` |
| **FileManager** | Quản lý file/asset trong admin | — | ✅ Filament page (tiện ích nội bộ) |
| **Search** | Interface + driver `database`/`scout`; suggest + filters | — (xem Search abstraction) | ✅ chạy (interface + 2 driver + DTO) |
| **Recommend** | Gợi ý sản phẩm (product page + mini-cart), strategy chain | Lunar `ProductAssociation` (curate) | ✅ chạy P1 (Association + Collection strategy + API) |
| **Promotion** | %, BXGY, free-ship, cart/coupon rules, **flash sale / combo / membership** | Discounts | ✅ apply/remove coupon + **validate coupon không-apply** (`/cart/coupon/validate`, live feedback) + mô tả discount human-readable (`describe`); admin discount dùng Lunar `DiscountResource`. **Nâng cấp 2026-06-24:** 2 custom discount type tự động (`QuantityPercentageOff` = "mua N giảm X%", `ComboPercentageOff` = "áo + quần giảm X%") đăng ký qua `Discounts::addType`; **Flash Sale** (AmountOff time-boxed + cờ `data.flash_sale`) với `PromotionService::currentFlashSale/activeAutomatic`; **Membership theo tổng chi tiêu** (`MembershipService` → Lunar `CustomerGroup` Silver/Gold, sync qua hook `order.paid`, discount scope theo group); API `GET /api/v1/promotions` + `/promotions/membership`; theme: promo-bar countdown (SSR + `enhance/flash-sale.js`), "Today's deals" strip ở home, savings row ở cart drawer, membership card ở account (`enhance/membership.js`), **+ label khuyến mãi + gạch giá cũ/chèn giá mới ở product card VÀ trang single product** (`PromotionService::saleFor()` → badge + price-break; nhúng vào product payload qua hook `product.resource` (`PromotionHooks`) nên SSR `product-card`/`price`, trang product, và grid JS `_card.js` khớp nhau; `product-variant.js` render giá có promotion khi đổi variant — giá/tiền lấy qua `PricingService`, không tính trong Blade); membership loại khỏi badge công khai (chỉ ở account); **+ label khuyến mãi đã áp dụng ở minicart/cart/checkout** (`PromotionService::appliedTo($cart)` đọc `discountBreakdown` Lunar → list "Flash Sale −$X" mỗi promotion; CartResource trả `applied_discounts`, `enhance/cart.js::appliedDiscountsHtml` dùng chung cho drawer + cart-page + checkout-coupon, checkout render SSR); seeder `DemoPromotionSeeder` + **`PromotionShowcaseSeeder`** (gắn promotion vào sản phẩm thật: 6 sp flash sale -25%, 6 sp buy-2 -10%, tops/bottoms combo -15% → badge hiện rõ trên storefront) + 11 test |
| **Shipping** | Shipping methods/zones/rates | shipping của Lunar | ✅ **zone/rate DB-backed** (`ShippingZone` model: country + states → rate + free-threshold, most-specific-wins) qua `ShippingZoneResolver` + `FlatRateShippingModifier`, fallback config; Filament **Shipping Zones** resource (CRUD) |
| **Payment** | COD, Bank, VNPay, MoMo (P1); Stripe/PayPal (P2) | payment driver của Lunar + driver mới | ✅ `offline` (COD/bank) + **VNPay** (driver + gateway HMAC + return/IPN idempotent); ⚠️ chưa MoMo/Stripe/refund-API |
| **Location** | Địa giới hành chính VN (tỉnh/phường) cho form địa chỉ | — | ✅ chạy (2 model + seeder data + API `provinces`/`wards`, gắn dropdown checkout + account) |
| **Hook** | action/filter (Eventy) + domain events liên-module | Lunar events | ✅ `HookManager` native (action/filter theo priority) + facade `Hook` + registry `Hooks::*`. **Hook hoá toàn bộ payload + event**: FILTER trên mọi API Resource (`product.resource`/`cart.resource`/`collection.resource`/`order.resource`/`search.results`) cho phép module khác enrich payload không cần phụ thuộc; FILTER `product.purchasable` (Inventory veto oversell); ACTION `order.placed`/`order.paid`/`order.status_changed`. Consumer thật: Inventory thêm block `availability` vào product payload qua `product.resource` |
| **Analytics** | Tracking, báo cáo bán hàng, KPI | Activities (log) | ✅ `AnalyticsService` (revenue/orders/AOV/monthly/top-products, **MySQL-portable**, đếm đúng paid statuses) + Filament **Sales Dashboard** (KPI + trend + recent orders + best-sellers) |

> Cấu hình hệ thống (Channels, Languages, Taxes, Staff) dùng thẳng Settings của Lunar,
> không cần module riêng.
>
> Chú thích: ✅ = đã có code chạy được end-to-end (route + service/controller).
> ⚠️ = đã scaffold nhưng còn thiếu phần chính (đánh dấu trong cột để biết build tiếp gì).

> "✔ core / ✔" = Lunar **đã có** → module chỉ **kế thừa và mở rộng** (config → extend
> điểm mở rộng chính chủ → wrap bằng service), **tuyệt đối không** viết lại hay sửa `vendor/`.
> "custom" = phần Lunar **không có** (CMS, Theme, SectionBuilder, Search nâng cao, Analytics…)
> → mới được build mới. Xem Nguyên tắc #1.

## API như "nền tảng dùng chung"

Mọi nghiệp vụ storefront expose qua `/api/v1/*`:

```text
GET    /api/v1/products
GET    /api/v1/products/{slug}
GET    /api/v1/collections/{slug}
GET    /api/v1/search
POST   /api/v1/cart
GET    /api/v1/cart
PATCH  /api/v1/cart/lines/{id}
POST   /api/v1/checkout
POST   /api/v1/auth/login        (Sanctum SPA cookie)
POST   /api/v1/auth/token        (PAT — app/headless, trả { data, token })
POST   /api/v1/auth/token/register
POST   /api/v1/auth/token/revoke (auth:sanctum — logout token)
GET    /api/v1/customer/orders
GET    /api/v1/locations/provinces                       (Location — dropdown địa chỉ)
GET    /api/v1/locations/provinces/{province}/wards      (Location)
```

- Phase này: Vue islands trong Blade gọi các endpoint trên (cùng domain → cookie Sanctum).
- Sau này: mobile app / Nuxt headless dùng **chính các endpoint đó** với token Sanctum —
  ✅ đã sẵn: phát token qua `POST /api/v1/auth/token` (xem R5), mọi route `auth:sanctum`
  nhận luôn Bearer token, **không phải viết lại backend**.
- API Resource giữ contract ổn định để không phá vỡ client.

---

# Rà soát API-first & nền tảng Headless (2026-06-21)

> Mục tiêu rà: storefront hiện tại có thực sự "API-first" để sau này cắm app/headless
> mà **không viết lại backend** không. Kết luận: **nền tảng đã đúng hướng** (Storefront
> và API cùng gọi một service + cùng một API Resource; SSR hydrate từ chính shape của
> `/api/v1`). Còn vài **rò rỉ** cần refactor để contract sạch và headless-ready.

> ⛔ **RÀNG BUỘC BẤT BIẾN — Phần cần SEO vẫn SSR bằng Blade.** "API-first / nền tảng
> headless" ở đây nghĩa là **mọi nghiệp vụ dùng chung qua một service + API Resource**,
> **KHÔNG** phải là chuyển storefront sang render client-side. Mọi nội dung công khai
> cần crawl — **home, product, collection, search, CMS page, breadcrumb, JSON-LD,
> meta/OG** — **bắt buộc render HTML thật ở server bằng Blade** (controller gọi service,
> Blade in ra; island/JS chỉ *enhance* markup đã có). Headless (app/Nuxt) là **consumer
> thứ hai** dùng chung `/api/v1`, **không thay thế** đường SSR. Refactor dưới đây chỉ
> dọn contract phía sau — **không được biến trang SEO thành fetch-on-mount**. Đây là
> nhắc lại "Nguyên tắc SSR-first (BẮT BUỘC)" ở mục Storefront, áp cho cả phần API-first.

## ✅ Đã đạt (giữ nguyên, đây là điểm mạnh)

- **Tách controller Storefront vs `Api/V1` rõ ràng** ở mọi module trục (Product,
  Collection, Cart, Checkout, Search, Customer, Order).
- **Một service là nguồn logic duy nhất**, cả 2 path gọi chung: `ProductService`,
  `CartService`, `CheckoutService`, `SearchEngine` (interface), `RecommendationService`.
  Controller mỏng, không chứa nghiệp vụ (trừ ngoại lệ ở mục rò rỉ bên dưới).
- **SSR hydrate từ chính API Resource:** `CollectionController`/`SearchController`
  nhúng `$state` = đúng shape `{data,facets,meta}` của `GET /api/v1/search` → "một
  contract cho SSR + island", không lệch dữ liệu (đúng nguyên tắc SSR-first).
- **Versioning sẵn:** mọi route tự prefix `api/v1` → dễ mở `v2` không phá v1.
- **Sanctum đã bật** (`HasApiTokens` trên `User`), guard `web` (SPA cookie) đã chạy.

## ⚠️ Rò rỉ cần refactor (ưu tiên giảm dần)

**R1 — Shape `$state` SSR bị copy-paste (drift risk).** — ✅ **đã làm (2026-06-21)**
Khối `ProductResource::collection(...)->additional(['facets'=>…,'meta'=>…])->response()->getData(true)`
trước lặp **nguyên si** ở 3 nơi (`Api/V1/SearchController`, `Storefront/SearchController`,
`Storefront/CollectionController`).
→ ✅ Đã rút về **`Modules\Search\Http\Resources\SearchResultResource`**: `::collection($result)`
trả contract API `{data,facets,meta}`, `::toState($result,$request)` trả cùng shape dạng
mảng cho SSR hydrate. Cả 3 call site gọi chung — **một nguồn shape duy nhất**, không còn
drift. Test `SearchSizeTest` xác nhận shape không đổi.

**R2 — Nghiệp vụ + query model rò vào controller.** — ✅ **đã làm (2026-06-21)**
Trước: `Api/V1/CartController::availableCoupons()` query thẳng `Lunar\Models\Discount` +
trả mảng tay; `Checkout::shippingOptions()` và `Auth::userPayload()`/`Customer::payload()`
map mảng inline (user shape lặp 3 lần).
→ ✅ Đã đẩy xuống service + bọc Resource:
  - `PromotionService::availableCoupons()` + **`CouponResource`** ← CartController.
  - **`ShippingOptionResource`** ← CheckoutController (dùng `CheckoutService::shippingOptions()`).
  - **`UserResource`** dùng chung ở `AuthController` (register/login) **và**
    `CustomerController` (show/update) — xoá cả 2 bản `userPayload`/`payload` trùng.
  - Controller giờ chỉ điều phối; return shape giữ nguyên (41 test xanh).

**R3 — Web page "giàu" hơn API (headless thiếu data).** — ✅ **đã làm (2026-06-21)**
Trước: trang product SSR đổ `related` + `sizeChart` vào Blade, nhưng
`GET /api/v1/products/{slug}` chỉ trả `ProductResource` trần → client headless không
lấy được trong **một** call (dù size-chart/recommendations đã có endpoint riêng:
`/products/{slug}/size-chart`, `/products/{slug}/recommendations`).
→ ✅ Thêm **`?include=size_chart,related`** cho `show()`: controller resolve qua
`SizeChartService::for()` + `RecommendationService::forProduct()` (services sẵn có,
`hydrate()` đã eager-load variants/thumbnail/brand), `ProductResource` gắn extras
opt-in qua `withSizeChart()`/`withRelated()` (`related` tái dùng chính `ProductResource`).
**Backward-compatible:** không có `include` → shape cũ **y nguyên** (test xác nhận
`data.size_chart`/`data.related` vắng mặt).
**SEO:** trang product web **vẫn SSR Blade như cũ** — đây chỉ là làm endpoint API đầy
đủ cho *consumer headless*; web page **không** chuyển sang fetch qua JS.
→ Test mới `ProductIncludeTest` (3 case): default không extras, `include=size_chart`,
`include=related`.

**R4 — Envelope không nhất quán.** — ✅ **đã làm (2026-06-21)**
Trước: success đã khá thống nhất (`{data, meta?}`), nhưng **error** thì tuỳ
`Accept` header — `abort_if(...,404)` / exception có thể render HTML hoặc shape lệch
khi client headless không gửi `Accept: application/json`.
→ ✅ Đăng ký một `$exceptions->render()` trong `bootstrap/app.php` cho mọi request
`api/v1/*`: trả **`{message, errors?}`** bất kể `Accept` header. Map status chuẩn
(`AuthenticationException`→401, `AuthorizationException`→403, `ModelNotFoundException`→404,
`HttpException`→status của nó, còn lại→500), default message an toàn (không leak nội bộ
ở 500). `ValidationException` **giữ nguyên** cho Laravel render (đã đúng `{message,errors}`).
→ Test mới `ApiErrorEnvelopeTest` (3 case): 404 envelope, **404 khi KHÔNG gửi Accept json**
(case headless), và validation vẫn `{message,errors}`. Quy ước success vẫn: `{data, meta?}`.

**R5 — Auth mới chỉ SPA-cookie, chưa có token cho mobile/headless thật.** — ✅ **đã làm (2026-06-21)**
Trước: chỉ có cookie session (guard `web`); mobile app native không dùng cookie.
→ ✅ Thêm **`TokenAuthController`** (Personal Access Token): `POST /api/v1/auth/token`
(login → token), `/auth/token/register` (đăng ký → token, 201), `/auth/token/revoke`
(logout token, `auth:sanctum`). Issue/register ở group **`api`** (stateless, **không**
session); revoke trong group `auth:sanctum`. Dùng chung validate + `UserResource` với
luồng cookie, trả `{ data: User, token }`. **Thuần additive** — luồng SPA cookie không
đổi, các route `auth:sanctum` sẵn có nhận luôn Bearer token. `personal_access_tokens`
đã có migration sẵn trong repo. Test: `TokenAuthTest` (issue/reject/register/auth bằng
token/revoke→401).

**R6 — Phụ thuộc model xuyên module ở tầng controller.** — ✅ **đã làm (2026-06-21)**
Trước: storefront controller query thẳng model của module khác để lấy data cho view —
`Country::orderBy()` (Checkout **và** AuthPage, trùng nhau), `Order::where('reference')`
(Checkout), `Product::query()` + `WishlistItem` (Wishlist storefront **và** API trùng nhau).
→ ✅ Gom hết về service:
  - **`WishlistService`** (Customer): `productsFor()`/`productIdsFor()`/`toggle()` — cả
    storefront + API wishlist controller dùng chung, hết query `Product`/`WishlistItem` tay.
  - **`CountryService::forSelect()`** (Location — đúng module sở hữu geo/reference, cached
    forever) — Checkout + AuthPage dùng chung, hết `Country::orderBy()` lặp.
  - **`OrderService::findByReference()`** (Order) — Checkout confirmation dùng (+ `abort_if`
    404 → đi qua envelope R4 thay vì `firstOrFail()` raw).
→ **Kết quả:** `grep "use Lunar\Models" modules/*/Http/Controllers/Storefront/*.php` = **rỗng**.
  Còn 2 import hợp lệ (không phải vi phạm): `AddressController` (route-model-binding type
  hint, Address là domain của Customer) và `VNPayController` (Payment thao tác Order nó đang
  settle — đúng nghiệp vụ callback). 50 test vẫn xanh, không thêm test (refactor thuần).

## Lộ trình refactor (an toàn nhờ đã có test — **55 test, toàn bộ R1–R6 xong**)

1. **R1 + R2** (gom shape + đẩy logic xuống service/Resource) — ✅ **xong (2026-06-21)**,
   41 test xanh, return shape không đổi.
2. **R4** (chuẩn hoá envelope success/error) — ✅ **xong (2026-06-21)**, +3 test (44 tổng).
3. **R3** (parity web↔API: include related/size-chart) — ✅ **xong (2026-06-21)**, +3 test (47 tổng).
4. **R6** (gom model access vào service) — ✅ **xong (2026-06-21)**, storefront controller
   sạch model xuyên module, 50 test xanh.
5. **R5** (token auth — PAT cho app/headless) — ✅ **xong (2026-06-21)**, +5 test (55 tổng).
   `TokenAuthController` thêm `POST /api/v1/auth/token`, `/auth/token/register` (public,
   group `api` — stateless, không session), `/auth/token/revoke` (`auth:sanctum`). Dùng
   chung validate + `UserResource` với luồng cookie; trả `{ data: User, token }`.
   **Thuần additive:** luồng SPA cookie **không đổi**; các endpoint `auth:sanctum` sẵn có
   nhận luôn Bearer token (đã verify token auth được `/customer/orders`, revoke → 401).

> Nguyên tắc xuyên suốt khi refactor: **không đổi URL/return shape đang chạy** (web
> island phụ thuộc), chỉ rút trùng + bọc Resource phía sau; chạy `php artisan test`
> sau mỗi bước. Mọi shape mới phải là **superset** tương thích ngược của shape cũ.

> ✅ **Kết luận API-first / headless-ready (2026-06-21):** toàn bộ R1–R6 đã xong. Một lớp
> service + API Resource dùng chung cho web SSR và `/api/v1`; error envelope chuẩn;
> product endpoint parity qua `?include`; storefront controller không còn chạm model
> xuyên module; và **token auth sẵn sàng cho app/Nuxt** mà không phải viết lại backend.
> SSR Blade cho nội dung SEO **giữ nguyên** — headless là consumer thứ hai, không thay thế.

---

# Database Design

Lunar đã có sẵn: products, variants, prices, collections, customers, carts, orders,
media, attributes. **Chỉ thêm** các bảng fashion-specific — migration đặt trong
module tương ứng (`modules/<Module>/Database/Migrations`):

### product_variant_dimensions  → module **Product**
```text
variant_id, size, length, fit, shoulder, waist, bust
```

### product_materials  → module **Product**
```text
product_id, material, composition, care_instruction
```

### lookbooks  → module **CMS**
```text
id, title, slug, cover_image
```

### outfits  → module **CMS**
```text
lookbook_id, product_id, sort
```

> Size / màu / fit nên ưu tiên dùng **Lunar attributes & variant options** trước;
> chỉ tạo bảng riêng khi cần truy vấn/sizing chart phức tạp.

---

# Product Architecture

Map trực tiếp lên model Lunar:

```text
Product (Lunar)
 ├── Variants (ProductVariant + options: size, color)
 ├── Media (Spatie MediaLibrary)
 ├── Attributes (Lunar attribute system)
 ├── Collections
 ├── Prices (per variant)
 ├── Inventory (stock per variant)
 ├── SEO (url + meta)
 └── Related products
```

---

# Storefront (theme `fashion`: Blade + Vue + jQuery)

> Toàn bộ view sống trong `themes/fashion/`. Theme chỉ render; data do
> Storefront controller (gọi service/API) đổ vào hoặc do Vue islands fetch từ `/api/v1`.

## Cấu trúc trang
```text
Layout (theme::layouts, Blade SSR)
 ├── Header / mega menu (MenuRenderer) + mini-cart drawer (offcanvas, vanilla)
 ├── Page content (Blade SSR)
 │    ├── Vue islands (chỉ 2 đang active):
 │    │    ├── product-purchase   (variant picker + add-to-cart)
 │    │    └── checkout-page      (address → shipping → place)
 │    └── Vanilla enhancers (mặc định): cart drawer/page, collection+search
 │         grid (SSR-first + facets), wishlist, auth, account, add-to-cart,
 │         size-finder ("find my size")
 └── Footer
```
> **Trạng thái 2026-06-19:** theme `fashion` đã dựng đầy đủ Bước 1–6 (xem
> [lunarphp_sme_fashion_theme_plan.md](lunarphp_sme_fashion_theme_plan.md)).
> `quick-view` **hoãn lại** (chưa build); search autocomplete/modal chưa làm.

## Quy ước JS

> **Phân tầng JS (cập nhật 2026-06-19): Blade SSR + Vanilla JS là mặc định; Vue CHỈ
> dùng cho island cốt lõi.** Mọi thứ khác là Blade render + vanilla enhancement.

**Vue 3 (island) — allow-list tối đa 3, hiện 2 đang active:**
1. **Product variant picker** — `product-purchase` ✅
2. **Checkout** — `checkout-page` ✅
3. **Quick view** — `quick-view` ⬜ (đã chừa chỗ trong allow-list; **hoãn**, chưa build)

→ allow-list trong `themes/fashion/js/app.js` (`VUE_ISLANDS`); `data-vue` ngoài danh
sách này **không** được mount Vue.

**Vanilla JS (`themes/fashion/js/enhance/*.js`) — mọi thứ còn lại:**
- **Cart** (mini-cart drawer `enhance/cart.js`, header count, trang cart
  `enhance/cart-page.js`) — render từ `/api/v1/cart`, qty/remove/coupon/note,
  panel tool trượt (`.open`) đúng markup `#shoppingCart` của index.html.
- collection filters/sort/pagination, search page, search modal, wishlist
  (button/count/page), auth form + logout, **per-card add-to-cart** (delegated).
- Mỗi module export `default fn(root=document)`, tự target qua `data-*`, bootstrap
  tự động trong `app.js`. Card sản phẩm động render qua `enhance/_card.js` (khớp đúng
  markup `product-card.blade.php`).
- Đồng bộ giữa các consumer qua DOM event: add-to-cart/biến động giỏ
  `dispatchEvent('cart:updated')` → `enhance/cart.js` refresh + bắn `cart:refreshed`
  → `enhance/cart-page.js` refresh. Checkout (Vue) vẫn dùng `cart-store.js` riêng,
  nghe cùng event. Không coupling trực tiếp, không vòng lặp (refresh không bắn event).

**Chung:**
- **jQuery** chỉ cho tiện ích nhỏ / plugin sẵn (slider, lazyload) — không xử lý state chính.
- **Axios** gọi `/api/v1/*`, CSRF + Sanctum cookie tự đính kèm (cùng domain).
- State giỏ hàng là server-side (Lunar cart); JS chỉ render và đồng bộ qua API.

## ⭐ Nguyên tắc SSR-first (BẮT BUỘC cho nội dung catalog công khai)

> **Mọi nội dung công khai (product, collection, search, CMS) phải render đầy đủ
> ở server.** Vue chỉ "enhance" markup đã có sẵn, KHÔNG được là nguồn render duy nhất.

Mô hình 3 lớp, áp dụng cho mọi trang catalog:

1. **SSR shell** — controller (trong module) gọi service/engine, Blade render **HTML
   thật** (grid sản phẩm, facet, phân trang). Crawlable, không "flash" trắng, chạy
   được cả khi tắt JS. Form/link là `GET` thật để fallback no-JS hoạt động.
2. **JSON Resource (hydration payload)** — controller serialize **cùng một shape**
   với `/api/v1/*` (qua API Resource) rồi nhúng vào DOM:
   ```blade
   <script type="application/json" data-island-state>@json($state)</script>
   ```
   → **một contract duy nhất** cho SSR và island; không lệch dữ liệu, không nhân đôi shape.
3. **JS enhancement (vanilla cho catalog)** — module trong `enhance/*.js` đọc payload
   nhúng làm trạng thái đầu, **KHÔNG fetch lần đầu**, chỉ gọi API khi người dùng tương
   tác (đổi filter/sort/page/term), re-render grid tại chỗ qua `enhance/_card.js`, và
   đồng bộ URL qua `history.replaceState` để reload/share/back-forward đúng.

**Anti-pattern (cấm):** render nội dung catalog bằng `onMounted(() => fetch())` (Vue)
hoặc fetch-on-load (vanilla) rồi chỉ để SSR trong `<noscript>`. Đó là CSR trá hình →
mất SEO + flash trắng.

**Ngoại lệ hợp lệ (fetch-on-mount được chấp nhận):** nội dung **cá nhân hóa / theo
session, không cần crawl** — cart drawer/count + trang cart (vanilla, fetch
`/api/v1/cart`), quick-view (Vue), checkout (Vue), wishlist membership (vanilla,
load 1 lần). Không phải nội dung SEO nên fetch/hydrate client-side OK.

**Đã áp dụng (2026-06-18):** `collection` + `search` SSR-first; controller dùng chung
`SearchEngine` với API → SSR grid + `$state` nhúng → enhancer **vanilla**
(`enhance/collection-shop.js`, `enhance/search-results.js`) đọc state, chỉ fetch khi
tương tác. Wishlist + auth + search modal cũng đã chuyển sang vanilla; Vue thu hẹp còn
4 nhóm core (variant / cart / checkout / quick-view).

---

# CMS & Sections (Blade-driven)

```text
CMS
 ├── Pages (slug, seo)
 ├── Menus
 ├── Banners
 └── Redirects
```

## Sections builder (lưu JSON, render bằng Blade partial)
```text
page_sections: id, page_id, type, sort, settings(JSON)
```
Mỗi `type` map tới một Blade partial trong `themes/fashion/views/sections/`
(`hero`, `product-grid`, `banner`, `slider`, `collection`, `rich-text`, `video`).
Admin chỉnh JSON qua Filament; storefront render server-side → tốt cho SEO.
**Không** làm drag-drop editor sớm.

Ví dụ settings:
```json
{ "title": "Summer Collection", "subtitle": "2026", "button_text": "Shop now", "image": 15 }
```

---

# Media Pipeline

Dùng Lunar Media (Spatie MediaLibrary):

```text
Upload → Queue → Optimize → Convert (WebP/AVIF) → Responsive sizes → CDN cache
```
Conversions: `thumb, small, medium, large, zoom`. Fashion: hover image, gallery,
video, responsive `<picture>` với AVIF/WebP.

---

# Search (abstraction)

Mục tiêu: storefront/API **không phụ thuộc engine search cụ thể**. Đổi từ MySQL
sang Meilisearch/Typesense về sau **không sửa controller/Vue**, chỉ thay driver.

## Contract

Module `Search` định nghĩa một interface chung, mọi nơi gọi qua interface này:

```php
interface SearchEngine
{
    // trả về kết quả đã chuẩn hoá: items + facets + total + phân trang
    public function search(SearchQuery $query): SearchResult;
    public function suggest(string $term, int $limit = 10): array; // autocomplete
    public function index(iterable $models): void;                 // (re)index
    public function delete(Model $model): void;
}
```

- `SearchQuery`: term, filters (size/color/price/brand/material/availability),
  sort, page, perPage, collection/category scope.
- `SearchResult`: items (đã qua API Resource shape), facets (đếm theo filter),
  total, pagination → **một shape duy nhất cho mọi driver**.

## Drivers (đổi qua config `search.driver`)

| Driver | Khi nào | Ghi chú |
|---|---|---|
| `database` (MySQL full-text/LIKE) | **Phase 1**, SME nhỏ | Không thêm hạ tầng; mặc định |
| `scout` (Meilisearch/Typesense) | Catalog lớn / cần facet nhanh, typo-tolerance | Bọc Laravel Scout sau interface |
| `lunar` | Nếu phiên bản Lunar có search engine sẵn | Kế thừa, không viết lại (xem Nguyên tắc #1) |

## Quy ước
- Controller (web + API) và Vue `<search-autocomplete>` / `<collection-filters>`
  **chỉ gọi `/api/v1/search`** → service → `SearchEngine`. Không biết engine bên dưới.
- Đánh index qua queue, kích hoạt bằng event sản phẩm/tồn kho (qua module `Hook`).
- Bắt đầu bằng driver `database`; nâng cấp = đổi config + viết 1 driver, **zero** thay đổi ở client.

---

# Cart & Checkout

Cart = Lunar Cart (server-side), thao tác qua `/api/v1/cart/*`.

Features: cart drawer (Vue), save for later, coupon, free-shipping threshold,
estimated shipping, gift notes.

Checkout pipeline (Lunar):
```text
Validate → Pricing → Promotions → Shipping → Tax → Payment → Order creation
```

## Payment phase 1
- COD, Bank transfer, VNPay, MoMo

## Payment phase 2
- Stripe, PayPal, Apple Pay

---

# Promotion & Inventory

- Promotion: dùng Lunar Discounts (percentage, BXGY, free shipping, cart/coupon rules).
  **Nâng cấp (2026-06-24):** thêm flash sale (time-boxed), combo tự động ("mua 2 giảm
  10%", "áo + quần giảm 15%") qua 2 custom discount type kế thừa `AbstractDiscountType`,
  và membership theo tổng chi tiêu (Lunar CustomerGroup Silver/Gold). Hiển thị ra theme:
  promo-bar countdown, "Today's deals" strip, savings ở cart, membership card ở account.
- Inventory: per-variant stock, low-stock alert, reserve, oversell prevention (Lunar).

---

# Hook / Extensibility

Dùng Lunar pipelines/events sẵn có trước. Nếu cần hook đơn giản kiểu WordPress,
thêm `tormjens/eventy`:
```php
eventy()->action('product.created', $product);
eventy()->filter('cart.total', $total);
```

---

# SEO

canonical, meta, OG tags, schema.org (Product/Offer/BreadcrumbList), sitemap, redirects.
Storefront SSR bằng Blade → đảm bảo crawlable.

---

# Admin (Filament 3 — native Lunar)

Panel admin của Lunar (`vendor/lunarphp`) **đã có sẵn** các Filament Resource dưới đây.
**Không tạo lại** — chỉ **kế thừa & phát triển** (custom field, action, column, relation
manager, hoặc extend resource theo điểm mở rộng của Lunar). Xem Nguyên tắc #1.

## Đã có sẵn trong Lunar (kế thừa, KHÔNG build lại)

| Nhóm | Resource Lunar có sẵn |
|---|---|
| Catalog | **Products**, **Brands**, **Collections**, **Collection Groups**, **Product Options**, **Product Types**, **Product Variants**, **Tags**, **Attribute Groups** |
| Bán hàng | **Sales / Orders**, **Discounts** |
| Khách hàng | **Customers**, **Customer Groups** |
| Hệ thống / Settings | **Channels**, **Currencies**, **Languages**, **Taxes** (Tax Class/Rate/Zone), **Staff**, **Activities** |

> Tất cả map sẵn lên model + nghiệp vụ Lunar (giá, tồn, variant option, discount…).
> Cách phát triển thêm: thêm field/tab vào resource có sẵn qua extension point của Lunar,
> **không** sao chép resource ra module rồi viết lại.

## Cần phát triển thêm (Lunar KHÔNG có → build mới trong module)

| Resource mới | Module | Ghi chú |
|---|---|---|
| Pages | CMS | trang tĩnh + SEO |
| Page Sections | SectionBuilder | JSON → Blade partial trong theme |
| Menus | CMS | điều hướng storefront |
| Banners | CMS | |
| Lookbooks / Outfits | CMS | fashion-specific |
| Themes | Theme | chọn/quản lý theme active |
| Redirects | CMS | |

Product editor (kế thừa của Lunar, bổ sung tab fashion): Basic Info, Media, Variants,
Pricing, Inventory, SEO, Collections, Attributes, Related Products,
**+ Materials / Size chart** (custom field thêm vào, không thay resource gốc).

---

# Roadmap

> **Áp dụng cho mọi phase:** trước khi bắt tay một tính năng, chạy checklist
> "kiểm tra `vendor/lunarphp` đã có chưa" (Nguyên tắc #1). Có → kế thừa & phát triển;
> không → mới build mới. Không bao giờ build lại thứ Lunar đã có.

> **Trạng thái rà soát 2026-06-19:** Phase 0–3 **xong**; **theme `fashion` dựng đầy
> đủ** (storefront SSR-first hoàn chỉnh: home, product + variant island + Size
> Intelligence, collection/search + facets, cart + checkout, wishlist, account đa
> mục, SEO/JSON-LD). Còn lại chủ yếu là **backend chặn doanh thu** (cổng thanh toán
> VN, email giao dịch) + **media responsive** + **test/optimization**. Đánh dấu ✅/🚧/⬜.

## Phase 0 — Bootstrap nền tảng ✅ (xong)
- ✅ Skeleton `modules/` + autoload PSR-4 `Modules\\` + `ModulesServiceProvider`
- ✅ Filament admin của Lunar đã cài & chạy (`/lunar`)
- ✅ Sanctum cài, `/api/v1/*` gom từ module + API Resources
- ✅ Theme (`fashion`), namespace `theme::`, Vite theo `THEME` env
- ✅ Vue 3 + jQuery + axios trong `themes/<theme>/js/app.js`
- ✅ Seeders dữ liệu mẫu (product/variant/collection/size chart)

## Phase 1 — Foundation ✅ (xong)
- ✅ Auth web + Sanctum API; quản trị products/variants/collections qua Filament Lunar
- ✅ API products, collections, cart (contract đã ổn định qua Resource)
- ✅ Cart server-side qua Lunar (drawer + coupon + free-ship threshold)

## Phase 2 — Storefront ✅ (xong)
- ✅ Homepage, product, collection, search, cart, checkout, account (đa mục:
  orders + order detail + address book + profile/password), wishlist, auth (Blade SSR)
- ✅ Vue islands **đang active: `product-purchase`, `checkout-page`** (quick-view hoãn)
- ✅ Cart drawer + cart page + coupon, add-to-cart, wishlist toggle — **vanilla**
- ✅ Search (driver `database`) + suggest **API**; ✅ **collection/search filters + facets**
  (SSR-first: `computeFacets` ở `DatabaseSearchEngine` + facet sidebar + `enhance/_shop.js`,
  fallback no-JS bằng GET). ⬜ search autocomplete/modal (suggest API có, chưa gắn UI)
- ✅ **Fashion Size Intelligence trên storefront**: size chart modal + "find my size"
  (`enhance/size-finder.js` → `recommend-size`, áp size vào variant picker)
- ✅ **SEO storefront**: canonical, robots (noindex trang riêng tư), OpenGraph/Twitter,
  JSON-LD Product + BreadcrumbList ở product page

## Phase 3 — CMS + Sections ✅ (xong khung)
- ✅ Pages, Banners, Lookbooks, Redirects, Menu, SectionBuilder (Filament + render)
- ✅ Section partial storefront: hero-slider, category-grid, product-tabs, iconbox
- 🚧 Bổ sung section type còn lại (lookbook/testimonial/instagram chưa có partial → render comment),
  preview cho admin, và **trỏ ảnh seed/demo khỏi `/themes/modave/*` (đã 404)**

## Phase 4 — Checkout 🚧 (đang làm)
- ✅ Shipping cơ bản, đặt hàng COD/Bank qua Lunar, order history + order detail
- ✅ Checkout gắn order vào customer của user đăng nhập (`CheckoutService` + `CustomerResolver`)
- ✅ **Cổng thanh toán VNPay** (driver + redirect + return/IPN, idempotent) — xem Phase 7.1; ⬜ MoMo
- ✅ **Email giao dịch** (xác nhận / thanh toán / cập nhật trạng thái, queued) — xem Phase 7.2; ⬜ invoice
- 🚧 Promotion: áp dụng coupon nâng cao, hiển thị tiết kiệm

## Phase 5 — Media + Search nâng cao 🚧 (đang làm)
- 🚧 Media on-demand: ✅ hạ tầng sinh conversion khi request (`MediaUrl` +
  `ConversionGenerator`, dùng trong `ProductResource`/`CartResource`/`MediaImageResource`)
  + sizes cấu hình qua Filament (`MediaSettings` + page `MediaImageSizes`) +
  `OnDemandConversionTest`. ⬜ **Còn: render `<picture>`/`srcset` (AVIF/WebP) ở card/gallery
  trong theme** — hiện vẫn `<img>` 1 size.
- ⬜ Cân nhắc bật driver `scout` (Meilisearch/Typesense) khi catalog lớn

## Phase 6 — Optimization ⬜ (chưa)
- ⬜ Redis cache/session, Horizon queue, CDN, query/index tuning
- ✅ **Test tự động** (cập nhật 2026-06-21): **8 file Feature / 41 test method** phủ
  auth/cart/address/checkout/order/search/size/VNPay/email + **Location** +
  **on-demand conversion** — xem Phase 7.3. ⬜ Redis/Horizon/CDN/index tuning vẫn chưa.

## Phase 7 — Go-live readiness 🎯 (ĐỀ XUẤT — phase tiếp theo)

> Storefront + theme đã đủ để **trông như shop thật**. Phase 7 lấp đúng các lỗ hổng
> còn chặn việc **bán thật + chạy production an toàn**. Thứ tự = ROI giảm dần.

**7.1 — Thanh toán online VN (VNPay)** — *Payment* — ✅ **đã làm (2026-06-19)**
  - ✅ `VNPayPayment` driver (kế thừa `AbstractPayment`), đăng ký qua
    `Payments::extend('vnpay')` + `config/lunar/payments.php` (type `vnpay`); checkout
    **không đổi pipeline** (vẫn `Payments::driver()`). Driver tạo order `awaiting-payment`.
  - ✅ `VNPayGateway` (build URL + HMAC-SHA512 + verify), routes `start`/`return`/`ipn`
    (`VNPayController` + `VNPayPaymentProcessor`): verify chữ ký, ghi `Transaction`, chuyển
    order → `payment-received`, **idempotent** (return + IPN không double-record).
  - ✅ Theme: `CheckoutPage.vue` thêm option VNPay (chỉ hiện khi cấu hình) + redirect sang
    gateway. Bật bằng `VNPAY_TMN_CODE`/`VNPAY_HASH_SECRET` (`config/payment.php`).
  - ⬜ Còn: **MoMo** (cùng mẫu driver), refund qua API (hiện refund out-of-band), và lưu ý
    **VNPay chỉ nhận VND** — shop phải dùng currency VND.

**7.2 — Email giao dịch** — *Order* — ✅ **đã làm (2026-06-19)**
  - ✅ 3 mailable queued (`OrderConfirmationMail`, `OrderPaidMail`, `OrderStatusUpdatedMail`)
    + markdown templates (`order::mail.*`) + `OrderMailer` (resolve recipient từ địa chỉ giao).
  - ✅ Wiring: confirm qua `PaymentAttemptEvent` (mọi driver), paid qua event domain
    `OrderPaid` (VNPay callback), status-update qua `OrderObserver` (bỏ qua status thanh toán).
  - ✅ Verify với `MAIL_MAILER=log`. ⬜ Còn: template đẹp/branding, đa ngôn ngữ, invoice PDF.

**7.3 — Test an toàn hồi quy** — *toàn repo* — ✅ **đã làm (mở rộng 2026-06-21)**
  - ✅ **8 file Feature / 41 test method, all green.** Bao phủ: auth (register/login/logout
    + profile/password), cart (add/update/remove/coupon), address book CRUD (+ ownership),
    checkout→order COD (+ order history/detail + cách ly theo customer), search+facets+suggest,
    size-chart + recommend-size, **VNPay** (chữ ký + tamper, callback paid/idempotent/invalid/
    failed-code, return redirect, IPN RspCode) + **email** (confirm/paid qua `Mail::fake`),
    **Location** (provinces/wards API) + **on-demand media conversion** (sinh size khi request).
  - Hạ tầng: `tests/TestCase` dùng `RefreshDatabase`; chạy trên **MySQL `lunar_testing`** (app
    phụ thuộc JSON functions/facets — SQLite không emulate được); trait `CreatesStorefrontData`
    (seed base data + fixture product/size-chart). Chạy: `php artisan test`.
  - 🐞 Test bắt 1 bug thật: `AddressController::update/destroy` typehint `int` trong khi Lunar
    route-model-binding inject `Address` → đã sửa nhận model + kiểm tra ownership.
  - ⬜ Mở rộng sau: storefront Blade pages (smoke render), wishlist toggle, MoMo khi thêm.

**7.4 — Media responsive `<picture>`** — *Media + theme* — 🚧 **hạ tầng xong, theme chưa**
  - ✅ `FashionMediaDefinitions` + on-demand generation (`MediaUrl`/`ConversionGenerator`) +
    sizes cấu hình qua Filament (`MediaSettings`); API Resource đã trả URL theo conversion.
  - ⬜ **Còn lại (đúng phần chặn Core Web Vitals/mobile):** render `<picture>` + `srcset`
    (AVIF/WebP) ở `product-card` và gallery — theme hiện vẫn `<img>` 1 conversion.

**7.5 — Dọn dữ liệu seed/demo modave** — *SectionBuilder/Seeders* — 🟡 nhanh, gọn
  - Ảnh hero/lookbook/testimonial trong `SectionSchemas` + seeders vẫn trỏ
    `/themes/modave/*` (đã xoá → 404). Thay bằng ảnh thật hoặc placeholder, re-seed.

**7.6 — Hạ tầng production** — *Optimization* — 🟡 khi chuẩn bị deploy
  - Redis cache/session, Horizon queue (email/job stock), CDN cho `public/`, rà index DB.

---

# Đề xuất bổ sung tính năng (ưu tiên cho SME fashion)

> Dựa trên rà soát codebase 2026-06-18. Mỗi đề xuất tuân thủ **Nguyên tắc #1**:
> kiểm tra Lunar đã có chưa → kế thừa/mở rộng; chỉ build mới phần Lunar không có.
> Sắp xếp theo ROI cho SME fashion (doanh thu / tỉ lệ chuyển đổi / vận hành).

## P0 — Chặn doanh thu, phải làm trước

1. **Cổng thanh toán Việt Nam (VNPay + MoMo)** — *module Payment* (✅ **VNPay xong**; ⬜ MoMo)
   - ✅ VNPay driver + gateway + return/IPN idempotent đã chạy (xem Phase 7.1). ⬜ Còn MoMo
     (cùng mẫu driver) + refund qua API.
   - Cách làm: viết **Lunar PaymentDriver** mới (kế thừa contract `Lunar\Base\PaymentTypeInterface`),
     đăng ký trong `config/lunar/payments.php` → checkout đã sẵn gọi qua `Payments::driver()`.
     Thêm route callback/IPN trong module Payment, verify chữ ký, cập nhật `Transaction`.
   - Không sửa pipeline checkout (đã hoạt động) — chỉ thêm driver + callback.

2. **Email giao dịch** — *module Order (+ Notifications)*
   - Xác nhận đơn, cập nhật trạng thái, hủy/hoàn. Hiện đặt hàng xong **không gửi gì**.
   - Cách làm: lắng nghe event order của Lunar (hoặc trong `CheckoutService::placeOrder`),
     gửi Laravel Notification (mail). Template Blade trong theme/`resources`. Queue hóa.

3. **Khôi phục giỏ hàng & "còn X cái" (urgency/stock)** — *Cart + Inventory*
   - Hiển thị tồn thấp ("Chỉ còn 3"), chặn oversell khi đặt. Inventory hiện mới đọc stock.
   - Cách làm: thêm reserve khi vào checkout + validate ở `placeOrder` (Lunar có stock,
     chỉ cần wrap kiểm tra). Badge low-stock đổ từ API có sẵn.

## P1 — Tăng chuyển đổi / AOV (fashion-specific)

4. **Bộ lọc & facets cho collection/search** — ✅ **đã làm (2026-06-19)** (size + color)
   - `DatabaseSearchEngine::computeFacets` trả `{size,color}` (value+count); theme có
     facet sidebar SSR + `enhance/_shop.js` (đọc `$state`, fetch khi đổi filter/sort/page,
     `history.replaceState`), fallback no-JS bằng GET. **Zero** đổi contract.
   - ⬜ Mở rộng facet: **giá (range), brand, material, availability** — engine mới có size/color.

5. **Back-in-stock / Notify me** — *Inventory + Customer*
   - Sản phẩm fashion hay hết size hot. Cho khách đăng ký email khi có hàng lại.
   - Bảng `stock_notifications(variant_id, email)`; job quét khi stock>0 → gửi mail.

6. **Gợi ý phối đồ "Complete the look" / Lookbook shoppable** — *CMS (đã có Lookbook/Outfit)*
   - Lookbook model đã tồn tại → bổ sung render shoppable (hotspot sản phẩm) + block
     "mua cả set" thêm vào giỏ một lần. Tận dụng `outfits` đã thiết kế trong plan.

7. **Recently viewed + "You may also like"** — *Catalog/Product*
   - Recently viewed lưu localStorage (Vue island) — đã có markup trong theme fashion.
   - Related đã có ở product page; mở rộng sang "frequently bought together" (gợi ý theo collection).

8. **Size Intelligence v2** — *Product* (✅ **base đã ra storefront 2026-06-19**)
   - ✅ Size chart modal + "find my size" (`size-finder.js` → `recommend-size`, áp size
     vào variant picker qua event `size:recommended`).
   - ⬜ v2: lưu **hồ sơ số đo** của khách đăng nhập (prefill form), gợi ý fit theo lịch
     sử mua/đổi trả, cảnh báo "thường giữa hai size", facet giá theo range.

## P2 — Vận hành & giữ chân

9. **Khuyến mãi nâng cao hiển thị storefront** — *Promotion* — ✅ **đã làm (2026-06-24)**
   - Lunar Discounts đã có (BXGY, %, free-ship). ✅ Bổ sung: **Flash Sale** banner +
     countdown (SSR promo-bar + `enhance/flash-sale.js`), **combo tự động** (mua 2 giảm
     10% qua `QuantityPercentageOff`; áo + quần giảm 15% qua `ComboPercentageOff`),
     **membership theo chi tiêu** (CustomerGroup Silver/Gold + card ở account), hiển thị
     "tiết kiệm X" ở cart drawer, "Today's deals" strip ở home. API `/api/v1/promotions`
     (+`/membership`). Thanh free-ship progress đã có từ trước (CartResource `free_shipping`).

10. **Analytics dashboard trong Filament** — *Analytics*
    - Service đã có revenue/orders/monthly. Gắn vào Filament widget (Lunar dùng Filament):
      doanh thu theo ngày, top sản phẩm/size/màu bán chạy, tỉ lệ đổi trả theo size.

11. **Đổi/trả hàng (returns/RMA)** — *Order*
    - Fashion tỉ lệ đổi trả cao. Quy trình yêu cầu đổi size/hoàn tiền + trạng thái.
    - Build mới (Lunar không có RMA): bảng `return_requests` + Filament resource + email.

12. **SEO kỹ thuật** — *Catalog/CMS* (🚧 **phần lớn đã làm 2026-06-19**)
    - ✅ schema.org Product/Offer/BreadcrumbList, OG/Twitter, canonical (strip query ở
      collection), robots noindex cho trang riêng tư. ⬜ Còn: **sitemap.xml**, JSON-LD cho
      collection (ItemList) + CMS page, OG image cho home/collection.

13. **Ảnh responsive AVIF/WebP `<picture>`** — *Media* (🚧 hạ tầng xong)
    - ✅ Conversions + on-demand generation + sizes cấu hình admin đã có; ⬜ chỉ còn render
      `<picture>` với srcset ở product card + gallery để tăng tốc mobile (Core Web Vitals → SEO).

## Nợ kỹ thuật xuyên suốt (không phải feature nhưng chặn chất lượng)

- **Test tự động:** ✅ đã có **8 file Feature / 41 test method** ở `tests/Feature` (cart→
  checkout→order, auth, search, VNPay, email, Location, on-demand conversion) chạy trên
  MySQL `lunar_testing`. ⬜ Còn: smoke render Blade storefront, wishlist toggle, MoMo, và
  test sống cạnh module (`modules/*/Tests` vẫn 0).
- **Hook/event wiring:** module Hook mới có routes; chuẩn hóa event domain (order.placed,
  stock.low, product.viewed) để Email/Analytics/Notify-me cắm vào, tránh coupling.

---

# Module `Recommend` (gợi ý sản phẩm) — ✅ P1 đã implement (2026-06-18)

> Mục tiêu: một nguồn gợi ý **dùng chung** cho product page ("You may also like"),
> mini-cart drawer ("You May Also Like"), trang giỏ, quick-view và email — tránh
> mỗi nơi tự query một kiểu như hiện tại (`ProductService::related()`).
>
> **Trạng thái P1 (đã chạy end-to-end):** `modules/Recommend/` —
> `Contracts/RecommendationStrategy`, `Strategies/AssociationStrategy` (đọc Lunar
> `ProductAssociation`, curate ưu tiên đầu) + `Strategies/CollectionStrategy` (wrap
> `ProductService::related()`), `Services/RecommendationService` (điều phối theo
> `config/recommend.php` + dứt trùng + loại source/giỏ + cache id theo product),
> API `GET /api/v1/products/{slug}/recommendations` và `GET /api/v1/cart/recommendations`
> (trả `ProductResource`). Product page SSR đổi `$related` sang service; mini-cart
> drawer nạp "You May Also Like" qua `enhance/cart.js` khi mở (khối
> `[data-cart-recommendations]` trong `cart-drawer.blade.php`, render bằng `_card.js`).
> Đã verify: curate lên đầu, fallback collection lấp phần còn lại, loại sản phẩm đã
> có trong giỏ.
>
> **Cập nhật 2026-06-21:**
> - ✅ **R2+R4 cho 2 endpoint:** "ràng buộc ≥1 số đo" của `recommend-size` chuyển vào
>   `SizeRecommendRequest::withValidator` → trả 422 `{message,errors}` chuẩn (bỏ
>   response 422 tay trong `SizeController`); logic gom product trong giỏ của
>   `forCart` đẩy xuống **`CartService::products()`** (controller hết với vào cart line).
> - ✅ **Nối mini-cart "You may also like" ra storefront** (trước đây endpoint có nhưng
>   `enhance/cart.js` **chưa** gọi — sót khi refactor Vue→vanilla). Nay fetch khi mở
>   drawer, render `_card.js`, ẩn khi rỗng. Build Vite OK.
> - ✅ **Test mới:** `RecommendationTest` (product curate-first / 404 / cart loại sản
>   phẩm trong giỏ) + siết assert 422 cho `recommend-size`.
>
> **P2/P3 (CoPurchase/AlsoViewed) chưa làm.**

## Nguyên tắc #1 trước: Lunar đã có gì?

| Đã có trong Lunar (KẾ THỪA) | Ghi chú |
|---|---|
| **`Lunar\Models\ProductAssociation`** (cross-sell / up-sell / alternate) | Quan hệ curate thủ công: `$product->associate($other, 'cross-sell')`. Có sẵn **Filament relation manager** trong product editor → admin tự gắn. |
| `lunar_order_lines` | Lịch sử mua → tính "frequently bought together" tự động. |
| `ProductService::related()` (đã có trong repo) | Gợi ý theo collection — **wrap lại làm 1 strategy**, không bỏ. |

→ Module `Recommend` **không tự tạo bảng association mới**. Curate thủ công dùng
thẳng `ProductAssociation` của Lunar. Chỉ build **mới** phần Lunar không có:
lớp điều phối + các strategy tự động (co-purchase, also-viewed) + cache + API.

## Kiến trúc (theo đúng mẫu module `Search`: interface + driver/strategy)

```text
modules/Recommend/
 ├── Contracts/RecommendationStrategy.php   # interface chung
 ├── Strategies/
 │    ├── AssociationStrategy.php           # đọc Lunar ProductAssociation (curate tay)
 │    ├── CollectionStrategy.php            # wrap related() hiện có (cùng collection)
 │    ├── CoPurchaseStrategy.php            # "bought together" từ order_lines
 │    └── AlsoViewedStrategy.php            # từ event product.viewed (module Hook)
 ├── Services/RecommendationService.php     # điều phối: chọn strategy + fallback + cache
 ├── Http/
 │    ├── Controllers/Api/V1/RecommendationController.php
 │    └── Resources/  (tái dùng ProductResource — KHÔNG tạo shape mới)
 ├── Database/Migrations/  (chỉ nếu cần bảng product_views cho also-viewed)
 ├── Routes/api.php
 └── RecommendServiceProvider.php
```

```php
interface RecommendationStrategy
{
    /** @return Collection<Product>  đã loại trùng + loại sản phẩm nguồn/giỏ */
    public function for(Product $product, int $limit = 8): Collection;
}
```

`RecommendationService::for($product, context, $limit)`:
1. Chạy strategy theo **context** (`product`, `cart`, `checkout`) với thứ tự ưu tiên:
   curate (`AssociationStrategy`) → tự động (`CoPurchase` → `AlsoViewed`) →
   fallback (`CollectionStrategy`). Curate luôn lên đầu.
2. Gộp + loại trùng + loại sản phẩm đã ở trong giỏ/đang xem, cắt còn `$limit`.
3. **Cache** theo `(product_id|cart_signature, context)` (Redis ở prod), invalidate
   qua event (`order.placed`, `product.updated` — module Hook).

## API (một contract, dùng lại `ProductResource`)

```text
GET /api/v1/products/{slug}/recommendations?context=product&limit=8
GET /api/v1/cart/recommendations?limit=6        # cho mini-cart drawer
```

Trả `{ data: [ProductResource…] }` — **đúng shape** product card hiện có, nên
`enhance/_card.js` render được ngay (vanilla) cho mini-cart, không thêm component.

## Tích hợp storefront (đúng SSR-first + phân tầng JS đã chốt)

- **Product page** "You may also like": **SSR** — controller gọi
  `RecommendationService::for($product,'product')`, render `x-theme::product-card`
  (đang là `$related` → đổi nguồn sang service này, giữ SSR, crawlable).
- **Mini-cart drawer** "You May Also Like": khối `[data-cart-recommendations]` (đã
  chừa sẵn trong `cart-drawer.vue` khi refactor) → khi mở giỏ, gọi
  `/api/v1/cart/recommendations`, render bằng JSON shape có sẵn. Là phần của island
  cart (Vue) nên hợp lệ fetch-on-open.
- **Email** (sau): cùng service → "có thể bạn thích" trong mail xác nhận đơn.

## Lộ trình Recommend (nhỏ, tăng dần — đừng làm AI sớm)

1. **P1:** `AssociationStrategy` (curate tay, dùng Lunar) + `CollectionStrategy`
   (wrap `related()`) + service + cache + API. Nối product page (SSR) + mini-cart.
2. **P2:** `CoPurchaseStrategy` từ `order_lines` (job tính bảng tổng hợp định kỳ).
3. **P3:** `AlsoViewedStrategy` — cần event `product.viewed` (module Hook) + bảng
   `product_views`. Chỉ làm khi có đủ traffic.

> KHÔNG dùng ML/vector ở giai đoạn SME. Co-purchase + curate tay là đủ ROI; giữ
> interface để sau này thêm strategy `scout`/vector mà không đụng caller (giống Search).

---

# Những thứ KHÔNG nên build sớm

- multi-vendor / marketplace / app store
- visual drag-drop editor
- microservices, GraphQL-first
- headless SPA full (giữ API sẵn, nhưng chưa tách)
- AI recommendations

---

# KPI cho SME fashion

Thắng nếu: admin dễ dùng, upload sản phẩm nhanh, storefront nhanh, SEO tốt,
mobile UX tốt, conversion cao, customize dễ — và **API đủ ổn định để app/headless
sau này dùng lại mà không phải viết lại backend.**
