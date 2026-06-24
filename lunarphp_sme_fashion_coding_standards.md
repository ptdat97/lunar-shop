# SME Fashion Ecommerce — Coding Standards

> Chuẩn code cho repo **Laravel 12 + LunarPHP 1.0 + Filament 3 + theme `fashion` (SSR)**.
> Đọc kèm [lunarphp_sme_fashion_plan.md](lunarphp_sme_fashion_plan.md) (kiến trúc
> tổng thể) và [lunarphp_sme_fashion_theme_plan.md](lunarphp_sme_fashion_theme_plan.md)
> (chi tiết theme).
>
> Mỗi quy tắc dưới đây có **một nguồn sự thật duy nhất** — đã bám đúng codebase
> thực tế (không có khối "đính chính" tách rời). Mục **0** là 5 nguyên tắc cốt lõi;
> các mục sau là chi tiết theo tầng.

---

## 0. Nguyên tắc cốt lõi

1. **Inherit Lunar, đừng dựng lại** — Lunar là source of truth cho catalog, cart,
   pricing, order, customer, discount, media, payment. Kiểm tra `vendor/lunarphp`
   trước; có → extend, không → mới build (xem §5).
2. **Service-first** — business logic chỉ sống trong Service. Cấm ở Controller,
   Blade, JS, Model, Resource (§3, §4, §7).
3. **SSR-first** — nội dung SEO render HTML thật ở server; JS chỉ *enhance* (§7, §9).
4. **API-first, một contract** — web SSR và `/api/v1` cùng gọi một service + một
   API Resource; không nhân đôi logic/shape (§6).
5. **Extension-first** — module giao tiếp qua Hook/Event, không hardcode logic
   xuyên module (§10).

> Ưu tiên khi đánh đổi: **Maintainability → Readability → Extensibility →
> Performance.** Không tối ưu sớm, không "clever code".

---

## 1. Kiến trúc theo tầng

```text
Request → Controller → Service → Lunar/Model → Resource → Response
                                      ↑
                         (Blade SSR đọc Resource shape để hydrate)
```

**Cấm các đường tắt:**

```text
Controller → Model → View       ❌ (controller query + render thẳng)
View → Service (ghi dữ liệu)     ❌ (xem ngoại lệ presentation service ở §7)
View → Database                  ❌
```

> **Không bắt buộc Repository.** Lunar đã là tầng data; Service wrap thẳng
> model/facade Lunar (`Pricing`, `Discounts`, `CartSession`…). Chỉ thêm Repository
> khi thật sự cần cache/đổi store.

---

## 2. Cấu trúc module

Mỗi module ở `modules/<Name>`, namespace `Modules\<Name>`, đăng ký qua
`<Name>ServiceProvider` (gom trong `app/Providers/ModulesServiceProvider`).
Cấu trúc thực tế:

```text
modules/<Name>/
├── Http/
│   ├── Controllers/        # Storefront/ (Blade) + Api/V1/ (JSON)
│   ├── Requests/
│   └── Resources/          # API Resource — JSON contract ổn định
├── Services/               # business logic (web + API gọi chung)
├── Models/                 # model fashion-specific (wrap/extend Lunar)
├── Support/                # *Hooks (đăng ký hook), value objects
├── Database/Migrations/
├── Config/
├── Routes/                 # web.php + api.php
├── Tests/
└── Providers/<Name>ServiceProvider.php
```

> Tên module hiện có: Catalog, Product, Collection, Inventory, Pricing, Cart,
> Checkout, Customer, Order, CMS, Menu, Theme, SectionBuilder, Media, FileManager,
> Search, Recommend, Promotion, Shipping, Payment, Location, Hook, Analytics.

---

## 3. Controller

**Chỉ được:** validate request → authorize → gọi Service → trả Response.

**Không được:** query DB, tính giá, xử lý promotion/inventory, chứa business logic,
gọi thẳng model của module khác.

* Tách rõ `Storefront/` (trả Blade `theme::…`) và `Api/V1/` (trả JSON Resource).
* Giới hạn **≤ 100 dòng**.

---

## 4. Service

Service là **nguồn business logic duy nhất**: business rules, transaction,
validation nghiệp vụ, dispatch event, tích hợp ngoài.

* Một service/action là logic dùng chung cho **cả** Storefront lẫn API controller.
* Giới hạn **≤ 500 dòng** → vượt thì tách (`CheckoutService` →
  `CheckoutValidator` / `CheckoutCalculator` / `CheckoutProcessor`).
* Tên hợp lệ: `*Service`, `*Resolver`, `*Engine`/`*Strategy` (Search/Recommend),
  `*Hooks` (đăng ký hook). **Cấm** `Helper`/`Utils`/`Common`/`Manager` (dễ thành
  thùng rác) — trừ `*Manager` chính chủ của Lunar/Filament khi extend.

---

## 5. Lunar — kiểm tra trước khi build (BẮT BUỘC)

Trước khi viết một dòng code cho tính năng mới, **phải** kiểm tra Lunar đã có chưa.
Checklist (dừng ngay khi tìm thấy):

1. **Model/nghiệp vụ?** `grep -ri "<feature>" vendor/lunarphp/*/src --include=*.php -l`
2. **Admin (Filament resource)?** `find vendor/lunarphp -path "*Resources*Resource.php" | grep -i "<feature>"`
3. **Config / điểm mở rộng?** xem `config/lunar/*` + pipelines/events Lunar expose.

**Kết luận:**
* **Có** → kế thừa: config → extend (model bind / pipeline / custom field /
  Filament hook) → wrap bằng service. **Không** copy ra module viết lại.
* **Không** → build mới trong module, ghi rõ "Lunar không có" trong PR/commit.

**Cấm:** copy code từ `vendor/`, fork logic Lunar, nhân bản dữ liệu Lunar, sửa
code trong `vendor/`.

---

## 6. API

* Mọi endpoint dùng `JsonResource`; route tự prefix `api/v1` (mở `v2` không phá v1).
* **Success** `{ data, meta? }` — **Error** `{ message, errors? }` (envelope chuẩn
  cho mọi request `api/v1/*`, bất kể `Accept` header).
* Web SSR hydrate từ **chính** shape của Resource (xem §7), không nhân đôi shape.
* Shape mới phải là **superset tương thích ngược** — không đổi URL/return shape
  đang chạy (island + headless phụ thuộc).
* Auth: Sanctum (SPA cookie + Personal Access Token cho app/headless).

---

## 7. Blade (Presentation Layer)

**Được phép:** hiển thị + format dữ liệu; **resolve một presentation service** qua
`app(...)` để đọc dữ liệu trình bày.

```blade
{{-- OK — service đọc-trình-bày, không có business logic trong view --}}
@php
    $price = app(\Modules\Pricing\Services\PricingService::class)->displayPrice($product);
    $sale  = app(\Modules\Promotion\Services\PromotionService::class)->saleFor($product);
    $img   = app(\Modules\Media\Services\MediaUrl::class)->conversion($product->thumbnail, 'medium');
@endphp
```

**Cấm trong Blade:** `DB::`, query Eloquent (`Model::where/query/first/get`),
`::create/save/update/delete`, gọi **facade/model Lunar trực tiếp** (`Pricing::`,
`Discounts::`, `Currency::getDefault()`), và **tự tính giá / promotion / inventory**.
→ Mọi phép tính đó nằm trong service; Blade chỉ *gọi và nhận kết quả*.

> Ranh giới: không phải "Blade có nhắc tới giá không", mà "**logic tính giá ở
> trong view hay trong service**". `->map()/->filter()` để format collection hiển
> thị (gallery, breadcrumb JSON-LD) là **format dữ liệu** → hợp lệ.

**Component:** UI lặp ≥ 3 lần phải tách (`<x-price>`, `<x-product-card>`…). Không
copy-paste HTML. **Blade ≤ 300 dòng.**

---

## 8. SSR-first (BẮT BUỘC cho nội dung SEO)

Nội dung công khai cần crawl (home, product, collection, search, CMS, breadcrumb,
JSON-LD, meta/OG) **render HTML thật ở server**. Mô hình 3 lớp:

1. **SSR shell** — controller gọi service, Blade render HTML thật (grid/facet/giá).
   Form/link là `GET` thật để chạy được no-JS.
2. **Hydration payload** — nhúng **cùng shape** `/api/v1` (qua API Resource):
   `<script type="application/json" data-island-state>@json($state)</script>`.
3. **JS enhance** — đọc payload nhúng làm state đầu, **KHÔNG fetch lần đầu**, chỉ
   gọi API khi người dùng tương tác; sync URL qua `history.replaceState`.

**Cấm:** render nội dung catalog bằng fetch-on-mount rồi để SSR trong `<noscript>`
(CSR trá hình → mất SEO).

**Ngoại lệ hợp lệ (fetch-on-mount OK):** nội dung cá nhân hóa/theo session, không
cần crawl — cart drawer/page, checkout, wishlist, membership card.

---

## 9. JavaScript (theme)

Storefront là **Blade SSR + Vanilla JS**. **Không** Vue/React/**Alpine** (đã bỏ
Vue hoàn toàn). jQuery chỉ cho plugin/tiện ích nhỏ; Axios gọi `/api/v1/*`.

* Mỗi enhancer: `themes/fashion/js/enhance/*.js`, export `default fn(root=document)`,
  tự target qua `data-*`, auto-bootstrap qua `app.js` (glob). File `_*.js` (gạch
  dưới đầu) là **helper**, không auto-run.
* Đồng bộ giữa consumer qua **DOM event** (`cart:updated`/`cart:refreshed`), không
  coupling trực tiếp, không vòng lặp.
* CSRF + Sanctum cookie tự đính kèm (cùng domain). State giỏ là server-side; JS
  chỉ render + đồng bộ.
* `themes/<brand>/` **chỉ** chứa Blade + JS + CSS — không query DB, không model
  Lunar, không business logic. Đổi brand = copy theme, không đụng `app/`.

---

## 10. Hook & Event (liên-module)

Module giao tiếp qua module `Hook` (facade `Hook`, registry `Hooks::*`) — **không**
truy cập thẳng logic/model nội bộ module khác.

* **FILTER** — enrich payload không tạo phụ thuộc:
  `Hook::applyFilters(Hooks::PRODUCT_RESOURCE, $data, [$product])`. Consumer đăng
  ký `Hook::addFilter(...)` trong `<Module>\Support\*Hooks::register()` gọi từ
  provider (mẫu: `InventoryHooks`, `PromotionHooks`).
* **ACTION (domain event)** bắt buộc cho Order/Payment/Customer/Inventory/Promotion:
  `order.placed` / `order.paid` / `order.status_changed`. Email / đồng bộ dữ liệu
  đi qua **Event + Listener**, không gọi thẳng trong service đang xử lý nghiệp vụ.

---

## 11. Admin = Filament của Lunar

Resource Lunar đã có (Products, Discounts, Orders, Customers, CustomerGroups,
Taxes…) **không tạo lại** — chỉ thêm field/tab/action qua extension point, hoặc
swap subclass trong `ModulesServiceProvider`. Resource mới chỉ cho phần Lunar
không có (Pages, Menus, Banners, Lookbooks, Themes…). Discount type mới đăng ký
qua `Discounts::addType(...)` để hiện trong panel + chạy trong cart pipeline.

---

## 12. Tiền tệ, Enum & i18n

* **Tiền** luôn thao tác **minor units** (`Price->value`); format qua
  `->formatted()` / `Number::currency`. Không tự nhân/chia currency factor rải rác.
* **Magic number → Enum / Constants / Config.** Không `status = 1`; dùng
  `OrderStatus::PAID`. Paid statuses qua `config('analytics.paid_statuses')`.
* App chạy `APP_LOCALE=vi`; chuỗi hiển thị qua `__()` / lang file khi có thể.
  **Lưu ý format tiền theo locale** (`12,34 US$`) khi viết assert test.

---

## 13. Naming

| Loại | Mẫu |
|---|---|
| Service | `CheckoutService`, `PricingService` |
| Resolver / Strategy / Engine | `CustomerResolver`, `AssociationStrategy`, `DatabaseSearchEngine` |
| Hook registrar | `PromotionHooks` |
| API Resource | `ProductResource` |
| Event / Listener | `OrderPaid` / `SendOrderEmail` |
| DTO | `CheckoutData` |

**Cấm:** `Helper`, `Utils`, `Common`, `Manager` (trừ Manager chính chủ Lunar/Filament).

---

## 14. Testing

* Feature test ở `tests/Feature`, chạy **MySQL `lunar_testing`** (app dùng JSON
  functions/facets — SQLite không emulate được), `RefreshDatabase`, trait
  `Tests\Concerns\CreatesStorefrontData` (seed base data + fixture product).
* **Bắt buộc test** cho mọi thay đổi chạm Cart, Checkout, Promotion, Payment,
  Search, Auth, Order.
* Chạy `php artisan test` sau mỗi bước refactor; **không merge nếu chưa xanh**.
* Shape API mới = superset tương thích ngược (test xác nhận shape cũ không đổi).

---

## 15. Chất lượng & refactor

* **Format bắt buộc:** `vendor/bin/pint` (đã cài) trước khi commit.
* **Khuyến nghị (chưa wire CI):** PHPStan/Larastan level cao dần khi thêm; bật khi
  cài. Không merge code có dead code hoặc duplicated business logic.
* **Phải refactor khi:** Controller > 100, Blade > 300, Service > 500, function >
  50 dòng, hoặc cyclomatic complexity quá cao. Refactor *trước* khi thêm tính năng.
* **Tài liệu là một nguồn:** cập nhật `lunarphp_sme_fashion_plan.md` (trạng thái +
  **ngày tuyệt đối**) khi hoàn thành tính năng; chi tiết theme ở
  `lunarphp_sme_fashion_theme_plan.md`.

---

## 16. KHÔNG build sớm

multi-vendor / marketplace, visual drag-drop editor, microservices, GraphQL-first,
headless SPA full (giữ API sẵn, chưa tách), AI/vector recommendations. Với SME:
co-purchase + curate tay là đủ ROI.
