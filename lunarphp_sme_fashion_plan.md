# SME Fashion Ecommerce — Laravel 12 + LunarPHP

> Trạng thái hiện tại của repo: Laravel 12 + Lunar 1.0 (skeleton). Lunar đã kéo theo
> **Filament 3** làm admin panel, và frontend đã có **Vite 7 + Tailwind 4 + Axios**.
> Kế hoạch dưới đây bám sát đúng những gì đã cài, không thêm stack chưa cần.

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
| Storefront JS | Vue 3 (islands) + jQuery | cần thêm |
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

modules/                         # TOÀN BỘ logic ở đây
 ├── Catalog
 ├── Product
 ├── Collection
 ├── Inventory
 ├── Pricing
 ├── Cart
 ├── Checkout
 ├── Customer
 ├── Order
 ├── CMS
 ├── Theme
 ├── SectionBuilder
 ├── Media
 ├── Search
 ├── Promotion
 ├── Shipping
 ├── Payment
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

| Module | Trách nhiệm | Resource/feature Lunar đã có (kế thừa) | Trạng thái |
|---|---|---|---|
| **Catalog** | Điều phối product/collection, listing, facet | Products, Brands, Tags, Attribute Groups, Product Types | ✔ core |
| **Product** | Product, variant, options (size/color), attributes, related | Products, Product Variants, Product Options | ✔ core |
| **Collection** | Collection, collection groups, gán product | Collections, Collection Groups | ✔ core |
| **Inventory** | Stock per-variant, reserve, low-stock, oversell | stock trên Product Variant | ✔ |
| **Pricing** | Giá theo variant/customer-group, tax-inclusive | Prices, Currencies, Customer Groups | ✔ core |
| **Cart** | Lunar Cart wrap, line, coupon, free-ship threshold | Lunar Cart | ✔ core |
| **Checkout** | Pipeline validate→pricing→ship→tax→pay→order | Lunar checkout pipeline | ✔ core |
| **Customer** | Khách, địa chỉ, auth (web + Sanctum), order history | Customers, Customer Groups | ✔ core |
| **Order** | Order, trạng thái, fulfilment, invoice | Sales / Orders | ✔ core |
| **CMS** | Pages, menus, banners, lookbooks, redirects | — | custom |
| **Theme** | Active theme, view namespace, Vite, manifest | — | custom |
| **SectionBuilder** | page_sections (JSON) → Blade partial trong theme | — | custom |
| **Media** | Conversions WebP/AVIF, responsive | Lunar Media (Spatie) | ✔ |
| **Search** | MySQL full-text (P1) → Scout sau; filters API | — (xem Search abstraction) | custom→Scout |
| **Promotion** | %, BXGY, free-ship, cart/coupon rules | Discounts | ✔ core |
| **Shipping** | Shipping methods/zones/rates | shipping của Lunar | ✔ core |
| **Payment** | COD, Bank, VNPay, MoMo (P1); Stripe/PayPal (P2) | payment driver của Lunar + driver mới | ✔ core + custom |
| **Hook** | action/filter (Eventy) + domain events liên-module | Lunar events | custom |
| **Analytics** | Tracking, báo cáo bán hàng, KPI | Activities (log) | custom |

> Cấu hình hệ thống (Channels, Languages, Taxes, Staff) dùng thẳng Settings của Lunar,
> không cần module riêng.

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
POST   /api/v1/auth/login        (Sanctum)
GET    /api/v1/customer/orders
```

- Phase này: Vue islands trong Blade gọi các endpoint trên (cùng domain → cookie Sanctum).
- Sau này: mobile app / Nuxt headless dùng **chính các endpoint đó** với token Sanctum.
- API Resource giữ contract ổn định để không phá vỡ client.

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
 ├── Header / mega menu
 ├── Page content (Blade)
 │    └── Vue islands:
 │         ├── <cart-drawer>
 │         ├── <variant-picker>
 │         ├── <quick-view>
 │         ├── <collection-filters>
 │         └── <search-autocomplete>
 └── Footer
```

## Quy ước JS
- **Vue 3** mount theo "island": từng component gắn vào `data-vue` element, không SPA.
- **jQuery** chỉ cho tiện ích nhỏ / plugin sẵn (slider, lazyload) — không xử lý state chính.
- **Axios** gọi `/api/v1/*`, CSRF + Sanctum cookie tự đính kèm (cùng domain).
- State giỏ hàng là server-side (Lunar cart), Vue chỉ render và đồng bộ qua API.

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

## Phase 0 — Bootstrap nền tảng (vài ngày)
- Tạo skeleton `modules/` + autoload PSR-4 `Modules\\` trong `composer.json`
  + `ModulesServiceProvider` quét/đăng ký provider, gom routes `web`/`api` của module
- Publish & cấu hình Filament admin của Lunar (`lunar:install`)
- Cài Sanctum, scaffold `routes/api.php` gom `/api/v1/*` từ module + Resources
- Tạo `themes/fashion/` (views + js + css + theme.json); module **Theme** đăng ký
  view namespace `theme::` + trỏ Vite input vào theme active
- Bootstrap Vue 3 + jQuery + axios trong `themes/fashion/js/app.js`
- Seed dữ liệu mẫu (product/variant/collection)

## Phase 1 — Foundation (2–3 tuần)
- Auth (web + Sanctum API), admin products/variants/collections
- API: products, collections, cart (contract đầu tiên)
- Cart server-side qua Lunar

## Phase 2 — Storefront (3–4 tuần)
- Homepage, product page, collection page (Blade SSR)
- Vue islands: cart drawer, variant picker, filters, search autocomplete
- Search (MySQL), filters qua API

## Phase 3 — CMS + Sections (3 tuần)
- Pages, sections (JSON → Blade partial), menus, banners, theme manifest

## Phase 4 — Checkout (2 tuần)
- Shipping, payments (COD/Bank/VNPay/MoMo), promotions, orders

## Phase 5 — Media + Search nâng cao (2 tuần)
- WebP/AVIF, responsive images; cân nhắc Scout nếu catalog lớn

## Phase 6 — Optimization (ongoing)
- Redis cache/session, Horizon, CDN, query/index tuning

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
