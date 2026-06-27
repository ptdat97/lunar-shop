# Platform Refactor — Core tối giản + Lunar Commerce Engine + Plugin

> **Tài liệu phân tích & roadmap** (không phải code). Đọc sau khi đã rà toàn bộ codebase
> (23 module, Hook/Plugin SDK đã có ở E0–E5). Bám nguyên tắc đã chốt trong
> [lunarphp_sme_fashion_plan.md](lunarphp_sme_fashion_plan.md): **Nguyên tắc #1** (không
> build lại thứ Lunar có), **SSR-first**, **một service = một nguồn logic**, và **Plugin SDK
> là mở rộng NỘI BỘ, không SaaS/marketplace**.

---

## 0. Tóm tắt đánh giá (đọc cái này trước)

Codebase **đã đi đúng hướng platform** hơn là phải "refactor lớn":

- ✅ Đã có **Hook Framework** (`HookManager` action/filter + registry `Hooks::*` 22 hook),
  **Event bridging** (Laravel `Login`/`Registered` → hook), **Plugin SDK** (contract +
  manager + lifecycle + CLI + doctor + admin page + `PluginConfig`), **2 plugin thật** chạy
  zero-core-edit (reviews, preorder), **payload contract versioned**.
- ✅ Đã có **interface-driven extension** ở `Search` (`SearchEngine`) và `Recommend`
  (`RecommendationStrategy` + config registry) — đúng mẫu Contract → Strategy.
- ✅ Mọi nghiệp vụ đi qua **một service layer**; storefront + API dùng chung; controller mỏng.

→ Vì vậy refactor này **chủ yếu là RE-LABEL + dời ranh giới**, không viết lại. Phần lớn
"Core Platform" mà prompt mô tả **đã tồn tại nằm trong module `Hook`** — việc cần làm là
**tách `Hook` thành `Platform` core thật sự** và **bóc business module ra thành plugin dần dần**.

> ⚠️ **Phạm vi.** Tài liệu này **tuân theo** mục "Những thứ KHÔNG nên build sớm" của
> [plan](lunarphp_sme_fashion_plan.md): mọi đề xuất dưới đây **chỉ gồm** thứ phù hợp SME và
> không bị plan loại trừ. Hạng mục đáng chú ý duy nhất build mới ở Core là **Workflow Engine
> + Rule Engine** (generic, nhỏ, automation thật). Mọi business feature khác đi theo SDK plugin
> đã có.

---

## 1. Những gì NÊN GIỮ trong Core (đổi tên `Hook` → `Platform`)

Core = thứ **không có business logic**, chỉ là khung mở rộng + hạ tầng. Hiện đang nằm rải ở
`Hook` + một phần `Theme`. Đề xuất gom về một module **`Platform`** (rename từ `Hook`,
backward-compatible qua alias):

| Core capability | Hiện ở đâu | Trạng thái |
|---|---|---|
| **Hook Framework** (action/filter + `Hooks::*`) | `Hook/Services/HookManager` + `Support/Hooks` | ✅ có |
| **Event Framework** (bridge Laravel events → hook) | `Customer`/`Order` providers (rải) | ⚠️ có nhưng rải — gom về Core |
| **Plugin SDK** (contract/manager/lifecycle/CLI/doctor/config) | `Hook/Plugin/*` + `Hook/Console/*` | ✅ có |
| **Extension registry admin** (`AdminPages`) | `Theme/Support/AdminPages` | ⚠️ ở Theme — nên về Core |
| **Payload contract** (`PayloadContract`) | `Hook/Support/PayloadContract` | ✅ có |
| **Service Container / DI** | Laravel | ✅ dùng sẵn |
| **API Framework** (envelope, versioning `/api/v1`, Resource base) | rải ở `bootstrap/app.php` + Resources | ⚠️ chưa gom thành "framework" |
| **Theme Framework** (namespace, Vite, view composer infra) | `Theme` (phần infra) | ✅ có (tách khỏi phần resource-swap) |
| **Infrastructure** (`LunarConfigOverride`, locale, session middleware) | `Theme/Support` + middleware | ✅ có |
| **Workflow Engine** | — | ❌ chưa có (xây mới, generic) |
| **Rule Engine** | — | ❌ chưa có; **một phần đã ngầm tồn tại** trong Promotion (discount conditions) |

**KHÔNG đưa vào Core:** bất kỳ thứ gì biết về "product/order/cart/review/loyalty". Core chỉ
biết `Hook`, `Plugin`, `Event`, `Workflow`, `Rule`, `Resource` — generic.

### Việc cần làm (an toàn, additive)
1. **Rename `Hook` module → `Platform`** bằng *alias*, không đổi namespace cũ ngay: tạo
   `Modules\Platform\*` re-export `Modules\Hook\*` (class_alias) → code cũ vẫn chạy.
2. **Dời `AdminPages` về Core** (`Platform/Support/AdminPages`), giữ alias ở `Theme`.
3. **Bỏ leak trong Core**: docblock `Hooks::SEARCH_RESULTS` đang nhắc `Modules\Search\Data\
   SearchResult` — đổi sang mô tả generic (`array {data,facets,meta}`) để Core không tham
   chiếu business module.

---

## 2. Những gì NÊN chuyển thành PLUGIN (theo độ sẵn sàng)

Tiêu chí: (a) là **value-add**, không phải commerce-core Lunar; (b) **self-contained**
(bảng riêng / chỉ đọc Lunar qua service); (c) coupling ra ngoài có thể thay bằng hook.

### Nhóm A — Tách được NGAY (coupling thấp, gần như plugin sẵn)
| Module | LOC | Vì sao dễ | Việc cần trước khi tách |
|---|---|---|---|
| **Recommend** | 315 | Đã là `RecommendationStrategy` + config registry; chỉ `Cart`/`Product` đọc qua service | Đổi 2 call-site sang hook `product.related` (đã có) / API |
| **Review** (acme/reviews) | plugin | ✅ **đã là plugin** | — (mẫu) |
| **Pre-order** (acme/preorder) | plugin | ✅ **đã là plugin** | — (mẫu) |
| **Analytics** | 426 | Chỉ đọc Order/Lunar, ra Filament dashboard; không ai phụ thuộc nó | Đọc qua event `order.paid` thay vì query trực tiếp (đã có hook) |
| **Wishlist** (trong Customer) | ~ | Bảng riêng `wishlist_items` + service; tự chứa | Bóc khỏi `Customer` → plugin, giữ route/API |

### Nhóm B — Tách được nhưng cần dọn coupling trước
| Module | LOC | Coupling phải gỡ | Cách |
|---|---|---|---|
| **CMS** (Pages/Banner/Lookbook/Redirect) | 1598 | `Catalog` đọc CMS để render home | Catalog đọc qua hook/service contract, không `use Modules\CMS` |
| **SectionBuilder** | 715 | đọc `Product/Promotion/Search` để render section | Section resolver nhận data qua **Extension Point** (registry section type) |
| **Menu** | 703 | Theme đọc menu | Đã qua hook `menu.items` — chỉ cần bóc bảng + Filament |
| **Promotion** (phần nâng cao: flash/combo/membership) | 2015 | `Cart`/`Pricing`/`Customer`; **Discount core của Lunar GIỮ LẠI** | Tách phần *custom* (flash sale, membership, combo type) thành plugin; coupon/discount cơ bản = Lunar |
| **Search** (driver `database`/`scout`) | 661 | Nhiều nơi gọi `SearchEngine` | Giữ **interface ở Core**, driver thành plugin (`scout` plugin) |

### Nhóm C — KHÔNG tách (là Lunar wrapper / presentation / infra)
| Module | Lý do giữ |
|---|---|
| **Product, Catalog, Collection** | Wrap Lunar catalog — **mở rộng**, không phải plugin (Nguyên tắc #1) |
| **Cart, Checkout** | Wrap Lunar cart/checkout pipeline |
| **Pricing** | Thin wrap Lunar Pricing facade (178 LOC) — presentation helper |
| **Inventory** | Stock = Lunar; phần thêm (reserve/notify-me) gắn chặt commerce → giữ, expose qua hook |
| **Order, Payment, Shipping** | Wrap Lunar; driver mới (VNPay/MoMo) đã đúng pattern `Payments::extend` |
| **Customer** | Auth + Lunar customer; **bóc Wishlist ra**, phần còn lại giữ |
| **Location** | Reference data VN (17k LOC chủ yếu seed) — infra, giữ |
| **Theme, Media, FileManager** | Presentation/infra |

> **Nguyên tắc quyết định:** nếu module **wrap Lunar** → giữ (Core-adjacent). Nếu module là
> **value-add tự chứa** → plugin. Nếu **mơ hồ** (Promotion, Search) → **tách phần custom,
> giữ phần wrap Lunar**.

### Plugin TƯƠNG LAI (SME-phù-hợp, làm khi có nhu cầu thật)
Blog, SEO nâng cao, Loyalty, Affiliate, Reward, Coupon nâng cao, Notification center,
Compare → **chưa build**, nhưng cắm được qua hook/event/contract **đã có chung** cho mọi
plugin (xem §5). Không cần Core làm gì thêm riêng cho từng cái.

---

## 3. Extension Point CÒN THIẾU

Đã có: filter trên mọi API Resource, `product.purchasable`, `checkout.payment_methods`,
`menu.items`, `product.related`, `search.results`; action vòng đời order/customer/cart/
checkout/catalog/inventory/search; `AdminPages`, `PluginConfig`. Còn thiếu:

| Extension Point | Cho ai | Loại |
|---|---|---|
| **Section type registry** | SectionBuilder cho phép plugin thêm `type` section mới | Registry (như Recommend strategies) |
| **Storefront route/nav registry** | Plugin thêm trang storefront + menu item không sửa Theme | Registry + hook `menu.items` (mở rộng) |
| **Checkout step / payment-method registry** | Plugin thêm bước checkout (gift-wrap, note) | Pipeline contract |
| **Search driver registry** | `scout`/`meili` plugin tự đăng ký driver | Contract đã có → thêm `SearchManager::extend` |
| **Recommendation strategy registry (runtime)** | Plugin thêm strategy không sửa `config/recommend.php` | `Recommend::extend($strategy)` |
| **Workflow trigger/action registry** | Core Workflow Engine (xem §6) | Registry |
| **Rule condition registry** | Core Rule Engine | Registry |
| **Notification channel registry** | Plugin Notification (tương lai) | Contract |
| **Admin "settings tab" ngoài plugin** | Module core góp tab settings (giống PluginConfig nhưng cho module) | Generalize `PluginConfig` → `ConfigurableExtension` |

---

## 4. Service cần DECORATOR

Decorator = bọc service Lunar/core để thêm hành vi **không sửa class gốc**, bind lại qua
container. Ứng viên (theo ROI):

| Service | Decorate để làm gì | Thay cho |
|---|---|---|
| **`Lunar\Pricing` / `PricingService`** | giá theo membership/flash-sale (Promotion plugin) | hiện tính rải trong PromotionService + hook |
| **`SearchEngine`** | thêm boosting/synonym/log mà không đụng driver | wrap `DatabaseSearchEngine` |
| **`CartService`** | gift-wrap fee, line-level rule (Rule Engine) | sửa trực tiếp service |
| **`CheckoutService`** | fraud-check / extra validation per plugin | thêm if trong service |
| **`RecommendationService`** | personalization layer (AlsoViewed / CoPurchase) | thêm strategy (đã hỗ trợ) |

> Decorator pattern: bind `app()->extend(ServiceX::class, fn($inner) => new DecoratorX($inner))`
> trong `boot()` của plugin. Core cấp helper `Platform::decorate(Contract::class, Decorator::class)`
> để chuẩn hoá + log thứ tự decorate.

**Điều kiện tiên quyết:** các service trên phải có **Contract (interface)** để decorate sạch.
Hiện chỉ `SearchEngine`/`RecommendationStrategy` có. → §6 bước D1.

---

## 5. Hook / Event cần BỔ SUNG

| Hook/Event mới | Loại | Cho |
|---|---|---|
| `cart.totals` | FILTER | ✅ **D2** — plugin chỉnh tổng (gift-wrap, surcharge) |
| `checkout.validate` | FILTER (errors[]) | ✅ **D2** — fraud/rule check veto trước khi đặt (→422) |
| `price.display` | FILTER | ✅ **D2** — đổi giá hiển thị (không cần decorate cả service) |
| `order.refunded` / `order.cancelled` / `order.completed` | ACTION | khi cấu hình status tương ứng (đã chừa trong plan E0) |
| `product.indexing` | FILTER | plugin enrich document trước khi index (scout) |
| `customer.group_changed` | ACTION | membership/loyalty react |
| `section.render` | FILTER | ✅ **E.1** — plugin wrap/inject/replace HTML section (+ type registry) |
| `workflow.*` / `rule.*` | ACTION/FILTER | Core engine (§6) |

> Tất cả thêm vào registry `Hooks::*` với docblock payload (giữ kỷ luật E0). Ưu tiên **bắc
> cầu event Lunar** nếu có trước khi tự bắn (Nguyên tắc #1).

---

## 6. Roadmap refactor (TỪNG BƯỚC, không rewrite, không breaking)

> Mỗi bước: additive, giữ nguyên URL/return shape/namespace cũ (alias nếu rename), chạy
> `php artisan test` sau mỗi bước (160 test làm lưới). Thứ tự = rủi ro thấp → cao.

### Giai đoạn 1 — Kết tinh Core (chỉ re-label + dời, 0 logic mới)
- **P1.** ✅ **Đã làm (2026-06-26).** Move thật `modules/Hook` → `modules/Platform`
  (`git mv`, giữ history), đổi **toàn bộ** namespace `Modules\Hook\*` → `Modules\Platform\*`
  (50 file: module + 5 module dùng hook + 2 plugin + tests + fixtures), rename
  `HookServiceProvider` → `PlatformServiceProvider`, `'Hook'` → `'Platform'` trong
  `ModulesServiceProvider`, view namespace `hook::` → `platform::`. **Theo hướng đã chốt:
  chỉ 1 namespace mới, KHÔNG alias** — namespace `Modules\Hook\*` biến mất hoàn toàn (cũ
  fix-luôn, không fix-dần). Facade `Hook` (class) + bindings + commands + admin page giữ
  nguyên hành vi. **160 test xanh**, verify runtime (singleton/facade/view/commands ok,
  `Modules\Hook` gone). *(Lưu ý: tên kỹ thuật bên trong vẫn là `HookManager`/`Hooks::*`/facade
  `Hook` — đó là tên feature hook framework, không phải tên module; có thể đổi sau nếu muốn.)*
- **P2.** ✅ **Đã làm (2026-06-26).** Dời `AdminPages` `Theme/Support` → `Platform/Support`
  (`git mv` + đổi namespace, sửa **13 file** dùng nó — **không alias**, theo hướng đã chốt;
  `Modules\Theme\Support\AdminPages` biến mất). Docblock cập nhật: nó là **extension registry
  của Core** cho admin (module + plugin góp page/resource), không phải presentation. Bỏ leak
  Core→Search: docblock `Hooks::SEARCH_RESULTS` không còn nhắc `Modules\Search\Data\SearchResult`
  (đổi sang `object $result` generic). **Kết quả: Platform core giờ 0 reference tới bất kỳ
  business module nào** (grep sạch, kể cả Theme). 160 test xanh; verify panel vẫn gom 6 page
  (gồm PluginsPage), Theme chỉ còn là *caller* của `AdminPages::add`.
- **P3.** ✅ **Đã làm (2026-06-26).** Tạo `Platform\Events\EventBridge` (singleton) cấp
  **cơ chế** generic `bridge($eventClass, $hook, $argsUsing)` = `Event::listen → doAction`.
  **Phân tách trách nhiệm:** Core sở hữu *cơ chế* (plumbing), module *khai báo* mapping
  (business knowledge ở provider của nó). Chuyển 3 bridge đang rải sang dùng nó: Customer
  (`Login→customer.logged_in`, `Registered→customer.registered`), Order (`OrderPaid→order.paid`)
  — bỏ `Event::listen` + `Hook::doAction` thủ công trong provider (Order vẫn giữ 2 `Event::listen`
  cho email — đó là listener nghiệp vụ, không phải bridge). Core vẫn **0 reference business
  module**. +2 test `EventBridgeTest` (cơ chế + singleton). **162 test xanh.**
- **P4.** ✅ **Đã làm (2026-06-26).** `Platform\Support\Decorator` helper bọc binding qua
  container `->extend()`: `Decorator::wrap($abstract, $decoratorClass)` (inner truyền vào ctor
  param `$inner`, các dependency khác auto-resolve) + `Decorator::wrapUsing($abstract, fn($inner,$app)=>…)`
  cho closure. **Lazy** (chạy khi resolve), **stackable** (đăng ký sau bọc ngoài → thứ tự
  xác định). Chưa ai dùng (chuẩn bị cho D-series GĐ2). +4 test `DecoratorTest` (wrap / stacking
  last-outermost / closure / auto-resolve dependency). **166 test xanh.**
- *Kết quả:* **Giai đoạn 1 xong** — Core (`Platform`) có tên, có ranh giới, **0 reference
  business module**, có sẵn `EventBridge` + `Decorator` + `AdminPages` + Hook/Plugin SDK.
  **0 thay đổi hành vi** (120→166 test, toàn additive).

### Giai đoạn 2 — Contract hoá service trục (mở đường Decorator)
- **D1.** ✅ **Đã làm (2026-06-26).** Trích interface khớp-100%-API: `Pricing\Contracts\
  PricingContract`, `Cart\Contracts\CartContract`, `Checkout\Contracts\CheckoutContract`
  (`SearchEngine` đã có sẵn → D1 không phải làm gì). Mỗi service `implements` contract; provider
  bind **concrete singleton + alias contract→concrete** → caller type-hint **concrete HAY
  contract đều ra cùng 1 instance**, decorate được. **Caller không đổi** (thuần additive).
  *(Bẫy đã tránh: `singleton(Contract, Concrete) + alias(Contract, Concrete)` gây đệ quy vô tận
  — đúng cách là `singleton(Concrete) + alias(Concrete, Contract)`.)* +3 test `ServiceContractTest`
  (implements / same-singleton / **decorate contract chạm tới caller type-hint concrete**).
  **169 test xanh.**
- **D2.** ✅ **Đã làm (2026-06-26).** Thêm 3 FILTER vào `Hooks::*` (docblock payload đầy đủ):
  `cart.totals` (CartResource — plugin thêm/sửa dòng tổng vd gift-wrap), `price.display`
  (PricingService::displayPrice — plugin đổi giá hiển thị vd "from …", membership),
  `checkout.validate` (CheckoutService::placeOrder — plugin append lỗi để veto checkout →
  422 ValidationException; rỗng = qua). Thuần additive (không listener = pass-through).
  +4 test `CommerceFiltersTest` (thêm total line / rewrite giá / veto order / pass-through).
  **173 test xanh.** Core vẫn 0 reference business.
- *Kết quả:* **Giai đoạn 2 xong** — service trục có Contract (decorate được) + 3 điểm móc
  filter cho commerce; chưa ai decorate/veto thật (an toàn, mở đường cho plugin).

### Giai đoạn 3 — Section/Search/Recommend registry (Extension Point §3)
- **E.1** ✅ **Đã làm (2026-06-26).** `SectionRenderer` thành **type registry**:
  `registerType($type, ?view, ?provider)` cho phép plugin thêm section type **mới hoàn toàn**
  với **view riêng** (không cần theme partial) + data provider. **Tương thích ngược:**
  `provide()` cũ vẫn chạy (chỉ set provider); type chưa đăng ký vẫn fallback
  `theme::sections.{type}` (hành vi legacy + "missing partial" marker không đổi). Thêm FILTER
  **`section.render`** (post-process HTML mỗi section — wrap/inject/replace; docblock generic,
  Core không ref SectionBuilder). +3 test `SectionRegistryTest` (custom type dùng view+provider
  riêng / filter post-process / fallback theme partial). **176 test xanh**, Core vẫn sạch.
- **E.2** ✅ **Đã làm (2026-06-27).** `Search\Services\SearchManager` = **registry driver
  runtime**: `extend($name, class|closure)` + `driver(?$name)` resolve (runtime registry thắng
  config). `SearchEngine` binding đi qua manager. **Tách `scout` thành plugin**
  `plugins/acme/scout-search/` (`ScoutSearchEngine` + `ScoutSearchPlugin::boot` gọi
  `SearchManager::extend('scout', …)`) → bỏ `scout` khỏi config hardcode (giờ plugin cấp).
  **Driver-as-plugin, ZERO sửa Search module/config.** Tương thích ngược: `database` (config)
  vẫn resolve qua manager. +6 test `SearchManagerTest` + 2 `ScoutSearchPluginTest` (plugin
  extend driver / SearchEngine resolve scout khi `SEARCH_DRIVER=scout`). **184 test xanh**,
  `plugin:list` thấy `acme/scout-search`, Core sạch.
- **E.3** ✅ **Đã làm (2026-06-27).** `Recommend\Services\RecommendManager` = registry
  strategy: seed từ `config('recommend.strategies')` (priority 0..n theo thứ tự khai báo →
  curated vẫn đầu) + plugin `extend($strategy, $priority)` runtime (class hoặc closure).
  `RecommendationService` đọc strategies **lazily per-call** từ manager (plugin extend ở boot
  được nhận). **Tương thích ngược:** thứ tự config giữ nguyên (test "curated association first"
  vẫn xanh). +4 test `RecommendManagerTest` (seed config order / plugin sau config / priority
  override / closure). **188 test xanh**, Core sạch.
- *Kết quả:* **Giai đoạn 3 xong** — 3 extension point registry (Section type / Search driver /
  Recommend strategy) đều cho plugin mở rộng runtime, không sửa core/config; Search có thêm
  **plugin driver thật** (scout). Tổng `120 → 188 test`.

### Giai đoạn 4 — Bóc business module → plugin (Nhóm A trước)
- **B.1** ✅ **Đã làm (2026-06-27).** Bóc **Wishlist** khỏi Customer → plugin
  `plugins/acme/wishlist/` (namespace `Acme\Wishlist`): `git mv` model + service + 2 controller
  (storefront `__invoke` + API index/toggle), route tách khỏi Customer sang plugin **giữ
  nguyên path/name/middleware** (`storefront.wishlist`, `api.v1.wishlist.index|toggle`).
  **First-party plugin, enabled mặc định** trong `config/plugins.php` (storefront feature
  default-on). Bảng `wishlist_items` **giữ migration ở Customer** (đã provision, không xoá
  data → plugin tái dùng, `install()` no-op). Customer giờ **0 file PHP wishlist** (chỉ còn
  migration + comment trong route). +4 test `WishlistPluginTest` (guest empty / toggle cần
  auth / add+list+toggle-off / page render) — trước đây **chưa có** test wishlist. **192 test
  xanh**, `route:list` xác nhận 3 route do `Acme\Wishlist\*` phục vụ.
- **B.2** ✅ **Đã làm (2026-06-27).** Bóc **Recommend** khỏi module → plugin
  `plugins/acme/recommend/` (namespace `Acme\Recommend`): `git mv` contracts/strategies/2
  service/controller/routes/config; module `Recommend` xoá hẳn (khỏi `ModulesServiceProvider`).
  **Gỡ coupling Product→Recommend (mấu chốt):** bỏ filter `product.related` khỏi
  `ProductService::related()` (giờ là **fallback collection thuần**, không recurse); 2
  controller Product **thôi type-hint `RecommendationService`**, thay bằng
  `Hook::applyFilters(PRODUCT_RELATED, $products->related($product), …)`. Plugin hook
  `product.related` → trả `forProduct` (curated-first). **Product giờ 0 coupling tới Recommend**
  (grep `Modules\Recommend|RecommendationService` trong Product = rỗng; `Recommend` trong size
  là feature khác). **Graceful degradation:** tắt plugin → không listener → controller dùng
  fallback collection, trang product vẫn render. First-party plugin enabled mặc định. +3 test
  `RecommendPluginTest` (fallback thuần / page render không recommender / listener enrich) +
  endpoint cũ (`RecommendationTest`) vẫn xanh. **195 test xanh**, Core sạch.
- **B.3** ✅ **Đã làm (2026-06-27).** Bóc **Analytics** khỏi module → plugin
  `plugins/acme/analytics/` (namespace `Acme\Analytics`): `git mv` service + Filament dashboard
  + config + view; module `Analytics` xoá hẳn (routes rỗng nên bỏ). Plugin góp dashboard qua
  `AdminPages::add` trong **`register()`** (panel gom page ở pha register, trước khi Lunar panel
  build — `PluginManager::load` chạy đúng lúc). Đọc thẳng order data của Lunar (không bảng
  riêng). Coupling = **0** (chỉ test ref nó → đổi namespace). First-party plugin enabled mặc
  định (dashboard hiện out-of-the-box). +0 test mới (`AnalyticsTest` cũ đổi namespace, 5 case
  vẫn xanh). **195 test xanh**; verify dashboard trong `AdminPages`, view + config resolve.
- *Sau mỗi bước:* enable plugin trong `config/plugins.php`, verify storefront/admin.

> ✅ **Giai đoạn 4 xong (2026-06-27)** — Nhóm A bóc hết: **Wishlist / Recommend / Analytics**
> giờ là plugin first-party (enabled mặc định), `modules/` giảm còn business core wrap Lunar.
> Mẫu nhất quán: `git mv` → namespace `Acme\*` → plugin class (register binding/AdminPages +
> boot routes/views/hooks) → enable mặc định → giữ bảng/route/tên. Gỡ coupling qua hook
> (Product↔Recommend dùng `product.related`). `120 → 195 test`, Core sạch, 6 plugin tham chiếu
> (reviews/preorder/scout-search/wishlist/recommend/analytics).

### Giai đoạn 5 — Workflow Engine + Rule Engine (Core framework MỚI, generic)
> Đây là phần Core **chưa có**, build mới — nhưng **generic, nhỏ**, không business logic.
- **W.1** ✅ **Đã làm (2026-06-27).** Rule Engine trong Platform core (`Modules\Platform\Rule\`):
  `Operator` (enum `== != > >= < <= in not_in contains`, so sánh thuần), `RuleRegistry` (đăng
  ký **field resolver** `key => fn(context)` — Core **không** ship field nào, module/plugin
  đăng ký `cart.subtotal`…), `Rule` (`{field, operator, value}`, `fromArray/toArray`), `RuleSet`
  (combine `all`/`any`, empty = pass, serialisable JSON). **Đánh giá thuần, không side-effect,
  fail-closed** với field lạ. `RuleRegistry` singleton trong PlatformServiceProvider. +8 test
  `RuleEngineTest` (operator / single rule / unknown-fail-closed / all / any / empty-pass /
  **JSON round-trip** / singleton). **203 test xanh**, Core vẫn business-free (chưa đăng ký
  field nào — Promotion sẽ dùng lại engine này ở bước sau). Đây là nền cho Workflow conditions (W.2).
- **W.2** ✅ **Đã làm (2026-06-27).** Workflow Engine trong Platform core
  (`Modules\Platform\Workflow\` + model `Workflow` + bảng `workflows`): `Trigger (Hooks::* event)
  → Conditions (RuleSet JSON) → Action (queued)`. **`WorkflowRegistry`** (module/plugin đăng ký
  *trigger* = hook + context-builder, và *action* = `WorkflowAction` theo id — Core ship 0);
  **`WorkflowEngine::listen()`** subscribe mọi trigger hook (gọi qua `app->booted()` sau khi
  mọi module/plugin đăng ký, **idempotent** không double-subscribe); khi trigger fire → build
  context → lọc workflow enabled theo trigger → eval `RuleSet` → **dispatch `RunWorkflowAction`
  (ShouldQueue)** chạy async. Workflow lưu JSON (`conditions`/`action_config`) → cấu hình được
  không-code. **Business-free:** engine chỉ wire + dispatch, trigger/action/field do business
  đăng ký. +6 test `WorkflowEngineTest` (match→dispatch / fail→no-dispatch / disabled skip /
  empty-pass / queued job chạy action / **idempotent listen 1 lần**). **209 test xanh**, Core sạch.
  ⬜ Trigger/action **thật** (`order.paid` + webhook/email/tag) để dành W.3 (plugin workflow +
  admin) — đúng boundary: build context từ `Order` là business, không thuộc Core.
- **W.3** ✅ **Đã làm (2026-06-27).** (a) **Plugin `acme/workflow`** đăng ký trigger/action/field
  **thật** lên engine generic: trigger `order.paid` (context builder → `{order_total, order_reference,
  customer_email,…}` phẳng, queue-safe), rule field `order.total`/`order.reference`/`customer.id`,
  action `notify.email` (Mail::raw + interpolate `{token}`) + `webhook.post` (Http POST). Business
  knowledge (Order total/email) ở plugin → Core vẫn generic. (b) **Admin UI** `WorkflowsPage`
  (Filament, group Settings): bảng list + **form** (trigger/action dropdown từ registry, conditions
  = repeater field/operator/value, action_config = key/value, enabled toggle) — lưu JSON, **không
  drag-drop** (đúng plan). +3 test `WorkflowPluginTest` (đăng ký trigger/action / order ≥N →
  dispatch email / order <N → no dispatch) với **`order.paid` hook thật**. **212 test xanh**;
  verify WorkflowsPage trong AdminPages + view resolve; Core sạch.

> ✅ **Giai đoạn 5 xong (2026-06-27)** — Workflow + Rule Engine: Core có 2 engine **generic**
> (Rule: field/operator/RuleSet JSON; Workflow: trigger→conditions→action queued), 0 business.
> Plugin `acme/workflow` wire `order.paid`→email/webhook thật + admin form. **"Khi order.paid +
> total ≥ N → gửi email" cấu hình qua admin, không viết code.** `195 → 212 test`.

### Giai đoạn 6 — Hardening platform ✅ **Đã làm (2026-06-27)**
- ✅ **Versioning:** `Workflow\WorkflowContract` (`VERSION` + `validate()`/`validateConditions()`
  thuần — match all/any, operator hợp lệ, field/trigger/action bắt buộc), cạnh `PayloadContract`.
  `WorkflowsPage::save()` validate trước khi lưu.
- ✅ **`platform:doctor`** (`Support\PlatformDoctor` + command): phát hiện **drift** giữa workflow
  đã lưu và registry sống — trigger/action/rule-field không còn đăng ký (plugin bị tắt) hoặc
  definition sai cấu trúc. Read-only, in cả 2 contract VERSION. Verify thật: "Platform healthy".
- ✅ **`PLATFORM.md`** — tài liệu tổng Core (capability table, extension points, CLI, versioning,
  7 plugin tham chiếu), trỏ qua `PLUGIN_SDK.md` cho chi tiết viết plugin.
- +6 test `PlatformHardeningTest` (contract valid/invalid / empty-conditions / doctor clean /
  doctor flag orphan / command exit codes). **218 test xanh**, Core sạch.

> ✅ **TOÀN BỘ REFACTOR XONG (GĐ1–6, 2026-06-27).** Core (`Platform`) tối giản + business-free:
> Hook/Event/Plugin SDK/Decorator/Rule/Workflow + AdminPages + Contract versioning + doctor.
> 4 module business bóc thành plugin (Nhóm A) + 3 plugin tiện ích → **7 plugin tham chiếu**,
> zero-core-edit. Lunar vẫn là commerce engine (catalog/cart/order/pricing wrap, không viết lại).
> **120 → 218 test**, toàn bộ additive/non-breaking, mỗi bước verify Core purity. Tài liệu:
> `PLATFORM.md` + `PLUGIN_SDK.md` + doc này.

---

## 7. Checklist áp cho MỖI đề xuất thêm vào Core (từ prompt)

> 1. Lunar đã có chưa? 2. Contract được không? 3. Decorator? 4. Event? 5. Hook? 6. Extension?
> 7. Plugin được không? — **Có** ở bất kỳ bước nào → **không thêm vào Core.**

Bảng quyết định nhanh cho các hạng mục prompt nêu:

| Hạng mục | Kết luận |
|---|---|
| Product/Cart/Order/Pricing/Tax/Discount/Inventory/Shipping/Payment/Attribute | **Lunar có** → wrap, không vào Core, không plugin-hoá lõi |
| Hook/Event/Plugin/Container/API/Theme framework | **Core** (đã có, chỉ re-label) |
| Workflow/Rule Engine | **Core** (build mới, generic) |
| CMS/Blog/SEO/Review/Wishlist/Compare/Recommendation/Loyalty/Coupon/Affiliate/Membership/Reward | **Plugin** (một số đã/đang là plugin; số còn lại = roadmap GĐ4+) |
| Analytics/Notification | **Plugin** |

---

## 8. Rủi ro & nguyên tắc giữ an toàn

- **Không đổi namespace cũ** khi rename → luôn `class_alias`, xoá alias ở major version sau.
- **Không bóc module khi còn ai `use Modules\X` trực tiếp** — gỡ coupling (sang hook/contract)
  *trước*, tách *sau*.
- **Promotion/Search tách phần custom, GIỮ phần wrap Lunar** — đừng plugin-hoá cả module kẻo
  vi phạm Nguyên tắc #1.
- **Workflow/Rule Engine giữ generic** — nếu thấy mình viết `if order...` trong Core, dừng:
  đó là business, thuộc plugin.
- Mỗi bước có test; rollback = gỡ alias/registry, không ai phụ thuộc cái mới cho tới khi ổn.
