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
   pricing, order, customer, discount, media, payment. Kiểm tra `modules/Lunar` +
   `modules/LunarAdmin` trước; có → extend, không → mới build (xem §5).
2. **Service-first** — business logic, transaction (`DB::transaction`) và cache
   chỉ sống trong Service. Cấm ở Controller, Blade, JS, Model, Resource (§3, §4, §7).
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

> Tên module hiện có (**13**): **Core** (hạ tầng), Analytics, Assets, Catalog, Checkout,
> Content, Customer, Inventory, Notification, Order, Promotion, Shipping, Theme.
> Danh sách sống ở `app/Providers/ModulesServiceProvider::$modules`.

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

**Transaction — bắt buộc bọc `DB::transaction` cho:** checkout, payment,
inventory, order (mọi thao tác ghi nhiều bảng liên quan), để tránh bug đồng bộ dữ
liệu (đặt hàng nửa chừng, trừ kho không khớp, ghi transaction lệch order).

```php
return DB::transaction(function () use ($data) {
    $order = $this->createOrder($data);   // ghi order + lines
    $this->reserveStock($order);          // trừ/giữ kho
    $this->recordPayment($order);         // ghi transaction
    return $order;
});
```

> Lunar đã bọc transaction cho phần lõi của nó; chỗ ta **thêm** thao tác ghi quanh
> pipeline (reserve stock, ghi domain record, side-effect) phải tự bọc transaction.
> Side-effect không-thể-rollback (gửi email, gọi API ngoài) đưa **ra ngoài**
> transaction — qua Event + Listener (§10), không gọi trong khối transaction.

**Cache — chỉ Service được cache.** Không cache trong Blade, Controller, JS. Tách
service cache riêng khi cần (`ProductCacheService`, `CollectionCacheService`);
invalidate qua event (`product.updated`, `order.placed`…). Controller/Blade chỉ
gọi service, không tự `Cache::remember`.

---

## 5. Lunar — kiểm tra trước khi build (BẮT BUỘC)

> **Lunar không còn là composer package.** Nó đã được fork vào repo:
> `modules/Lunar` (core engine, namespace `Lunar\`) và `modules/LunarAdmin`
> (Filament panel, namespace `Lunar\Admin\`). Autoload khai báo trong
> `composer.json`; provider đăng ký tay ở `bootstrap/providers.php` (không còn
> package-discovery). Xem plan.md § "Lunar là code trong repo".

Trước khi viết một dòng code cho tính năng mới, **phải** kiểm tra Lunar đã có chưa.
Checklist (dừng ngay khi tìm thấy):

1. **Model/nghiệp vụ?** `grep -ri "<feature>" modules/Lunar/src --include=*.php -l`
2. **Admin (Filament resource)?** `find modules/LunarAdmin -path "*Resources*Resource.php" | grep -i "<feature>"`
3. **Config / điểm mở rộng?** xem `config/lunar/*` + pipelines/events Lunar expose.

**Kết luận:**
* **Có** → kế thừa: config → extend (model bind / pipeline / custom field /
  Filament hook) → wrap bằng service. **Không** copy ra module viết lại.
* **Không** → build mới trong module, ghi rõ "Lunar không có" trong PR/commit.

**Sửa thẳng `modules/Lunar` — được, nhưng là lựa chọn cuối.** Fork bỏ rào cản kỹ
thuật, **không** bỏ lý do của quy tắc: mỗi dòng sửa trong core là một dòng phải tự
bảo trì và tự merge lại khi lấy fix từ upstream. Thứ tự vẫn là:

```text
config/lunar/* → điểm mở rộng chính chủ (pipeline, ModelManifest::replace,
Payments::extend, Discounts::addType, Event::listen, ResourceExtension)
→ wrap bằng service trong module  →  (cuối cùng) sửa modules/Lunar
```

Chỉ sửa core khi **không có điểm mở rộng nào chạm tới** được. Khi đó:
* Commit **riêng**, message nói rõ *tại sao không dùng được extension point*.
* Sửa **tối thiểu**, không refactor tiện tay — diff càng nhỏ càng dễ đối chiếu upstream.

**Vẫn cấm:** copy code Lunar ra module rồi viết lại (fork logic), nhân bản dữ liệu
Lunar sang bảng của mình, và sửa code trong `vendor/` (nay chỉ còn package bên thứ ba
thật sự: Laravel, Filament, Spatie…).

---

## 6. API

> 🧊 **ĐÓNG BĂNG BỀ MẶT (2026-07-13) — GIỮ, KHÔNG MỞ RỘNG.** Storefront Next.js đang
> hoãn, Blade SSR là storefront duy nhất. Nhưng `/api/v1` **không** phải "API cho
> headless" — **14 file JS của theme đang gọi nó** (cart, search, notify-me,
> recommend-size, locations…), nên nó vẫn sống và phải khoẻ.
>
> * Thêm endpoint/shape vì **Blade SSR cần** → bình thường, cứ làm.
> * Thêm vì *"sau này app dùng"* → **KHÔNG**. Build cho consumer không tồn tại là
>   abstraction thừa (cùng loại với "event phòng xa" ở §10).
>
> Danh sách route hiện không có consumer Blade + ngưỡng bỏ đóng băng: `routes/api.php`
> và [todo.md § 11](lunarphp_sme_fashion_todo.md).

* Mọi endpoint **trả model** đều dùng `JsonResource`; route tự prefix `api/v1`
  (mở `v2` không phá v1).
* **Success** `{ data, meta? }` — **Error** `{ message, errors? }` (envelope chuẩn
  cho mọi request `api/v1/*`, bất kể `Accept` header).
* **Ba ngoại lệ hợp lệ** (không có model để bọc — đừng "sửa" chúng):
  `HealthController` (probe hạ tầng), `StockNotificationController` (chỉ trả
  `{message}` xác nhận), `SizeController` (trả mảng tính toán, không phải bản ghi).
* `->response()` của Resource trả **201** khi `wasRecentlyCreated`. Với `PUT` upsert
  phải `->setStatusCode(200)`, nếu không sẽ đổi status mà client đang phụ thuộc.
* Web SSR hydrate từ **chính** shape của Resource (xem §7), không nhân đôi shape.
* Shape mới phải là **superset tương thích ngược** — không đổi URL/return shape
  đang chạy (island + headless phụ thuộc).
* Auth: Sanctum (SPA cookie + Personal Access Token cho app/headless).

---

## 7. Blade (Presentation Layer)

**Được phép:** hiển thị + format dữ liệu đã được đưa vào view.

**Dữ liệu trình bày đến từ đâu** (Blade **không** tự resolve service):

* **Controller** đẩy vào view (`return view('theme::…', [...])`), hoặc
* **View Composer** (đăng ký ở service provider) inject cho partial/layout dùng
  chung, hoặc
* **Class-based Blade component** — component class DI service trong PHP, Blade
  view của component chỉ nhận biến đã tính (mẫu: `<x-theme::price>`,
  `<x-theme::product-card>`).

**CẤM trong Blade:**

```blade
@php app(SomeService::class)   {{-- ❌ Blade không resolve service --}}
@php resolve(...)              {{-- ❌ --}}
DB::                           {{-- ❌ --}}
Model::where(...) / ::create() {{-- ❌ query / ghi dữ liệu --}}
Pricing:: / Discounts:: / Currency::getDefault()  {{-- ❌ facade·model Lunar --}}
```

…và **tự tính giá / promotion / inventory**. Mọi phép tính + việc lấy data nằm ở
service (qua controller/composer/component); Blade chỉ *nhận và in ra*.

> Ngoại lệ: helper trình bày thuần của Laravel (`app()->getLocale()`, `__()`,
> `route()`, `asset()`) vẫn được dùng — đó không phải resolve service nghiệp vụ.
> `->map()/->filter()` để format collection hiển thị (gallery, breadcrumb JSON-LD)
> là **format dữ liệu** → hợp lệ.

**View của mail** không do controller render. Đẩy dữ liệu vào bằng **View Composer**
(xem `ThemeServiceProvider`: `View::composer('mail.default', …)` bơm `$accent`).

> ⚠️ Composer **không** với tới Blade **component** (`@props`). Override
> `resources/views/vendor/mail/html/header.blade.php` là component, nên nó vẫn phải
> resolve `ThemeSettings` inline. Đã thử composer cho nó: logo biến mất, test đỏ.
> Đây là ngoại lệ **đã kiểm chứng**, không phải nợ kỹ thuật.

**Component:** UI lặp ≥ 3 lần phải tách (`<x-theme::price>`,
`<x-theme::product-card>`…). Component có logic lấy data → **class-based**. Không
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

Storefront là **Blade SSR + Vanilla JS**:

* **Vanilla JS** — mặc định cho mọi enhancer.
* **AlpineJS** — được phép cho **tương tác UI nhỏ** (toggle, dropdown, accordion…)
  khi vanilla quá rườm rà. Không dùng Alpine làm tầng state/data chính.
* **Vue / React** — **cần kiến trúc phê duyệt riêng** trước khi thêm (storefront
  đã bỏ Vue hoàn toàn; đừng tự thêm lại).

jQuery chỉ cho plugin/tiện ích nhỏ; Axios gọi `/api/v1/*`.

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

## 10. Giao tiếp liên-module (service công khai + Event)

> Module `Hook` và facade `Hook::applyFilters()` **đã bị gỡ** khi gộp 24→12 module (nay 13, thêm Notification)
> (hook/plugin engine nằm ngoài phạm vi SME, xem §16). Không dùng lại.

Module giao tiếp qua **service công khai** của module khác hoặc **domain event** —
**không** truy cập model/logic nội bộ của nhau.

* **Gọi trực tiếp service công khai** khi cần dữ liệu ngay và chấp nhận phụ thuộc
  (vd `Checkout` → `CustomerResolver`, `Catalog\FitHistoryService` → `Order` models
  qua truy vấn đọc). Đây là mặc định: ít lớp nhất.
* **Domain event** khi cần **coupling lỏng / nhiều consumer / side-effect**:
  `Modules\Order\Events\OrderPaid` (consumer: email thanh toán ở Order, sync
  membership ở Promotion); `Modules\Order\Events\OrderStatusUpdated` (consumer:
  notification ở Notification, trả tồn kho ở Inventory). Đặt event trong module
  **sở hữu** nghiệp vụ, không nhét vào `Core`.
* **Event của Lunar** (`PaymentAttemptEvent`, `MediaHasBeenAddedEvent`…) là điểm hook
  chính chủ — ưu tiên trước khi tự tạo event mới.
* **Chỉ thêm domain event mới khi đã có consumer thứ hai.** Event "phòng xa" là
  abstraction thừa.
* Side-effect không-rollback-được (gửi mail, gọi API ngoài) đi qua **Event + Listener**
  (queued), **không** gọi trong khối `DB::transaction` (§4).

> ⚠️ Một event phải có **ngữ nghĩa rõ**. Ví dụ `OrderPaid` = *"được tính là đã thanh
> toán"* (chi tiêu + doanh thu), **không** phải *"đã nhận được tiền"* — COD bắn
> `OrderPaid` lúc đặt hàng nhưng khách trả tiền khi giao. Listener nào cần "tiền đã về
> tay" phải tự kiểm tra `status === 'payment-received'`.

> ⚠️ **Listener queued hay đồng bộ?** Side-effect (mail, push, sync ngoài) → **queued**.
> **Bất biến đúng-sai** (trả tồn kho, ghi sổ tiền) → **đồng bộ**: queue chết thì hàng/tiền
> sai im lặng, không ai biết. Xem `ReleaseStockOnOrderClosed` (đồng bộ) so với
> `SendOrderStatusNotification` (queued).

---

## 17. Guard phải được *chứng minh là có chạy*

Một guard viết đúng vẫn có thể **chưa từng chạy một lần nào**.

Thực tế đã xảy ra: `DecrementStock` có conditional UPDATE atomic chống oversell, và
`CartService` cũng kiểm tra tồn kho — nhưng cả hai đều (đúng theo thiết kế) **miễn trừ**
variant `backorder`/`always`. Mà cột `lunar_product_variants.purchasable` mặc định là
`always` trong migration của Lunar, và **không một seeder/fixture/admin nào từng đổi** →
toàn bộ 66 variant đều `always` → **không guard nào từng kích hoạt**. Test cũ vẫn xanh vì
nó tự `update(['purchasable' => 'in_stock'])`.

Kết quả đo được: stock 2, đặt 10 → checkout **200 OK**, stock **−8**.

**Quy tắc:**
1. Test guard phải chạy trên **dữ liệu như production**, không phải dữ liệu tự dựng cho
   guard đó chạy. Nếu fixture phải sửa một trường để guard hoạt động → hỏi ngay: *production
   có trường đó không?*
2. **Mutation-check mọi guard**: tắt guard → test phải đỏ. Test không đỏ = test không bảo vệ gì.
3. Nghi ngờ mọi **default của vendor**. `purchasable = always`, `sanctum.expiration = null`,
   `throttleApi()` chỉ phủ group `api` — cả ba đều từng làm cả một lớp bảo vệ thành trang trí.
4. **Cờ hiển thị ≠ guard.** `OrderResource.can_return` trông y như một luật nghiệp vụ, nhưng
   nó chỉ **ẩn cái nút**: `ReturnService::open()` không hề kiểm tra status, nên gọi thẳng
   endpoint là mở được RMA trên đơn chưa từng giao — rồi hoàn tiền. Với mỗi cờ dạng
   `can_*` / `is_*` trong Resource, hỏi: **ai ép luật này ở phía service?** Nếu không ai,
   đó là lỗ hổng, không phải cờ.
5. **Chữ ký hợp lệ ≠ nội dung đúng.** Chữ ký của payment gateway chứng minh *ai gửi*, không
   chứng minh *bao nhiêu tiền* hay *có còn muốn nhận không*. `VNPayPaymentProcessor` từng
   tin luôn `vnp_Amount` trong callback → callback ký đúng cho **1 đồng** vẫn đẩy đơn sang
   `payment-received`. Và callback về muộn còn **hồi sinh đơn đã `cancelled`** — đơn đã trả
   stock về kho, `stock_released_at` đã đóng dấu nên không bao giờ trừ lại → bán hàng không
   có hàng. Với mọi input từ bên ngoài đã xác thực: **xác thực xong mới bắt đầu kiểm tra.**
6. **Guard chống race không mutation-check được bằng phpunit.** `lockForUpdate` trong
   `GatewayReconciler` tắt đi thì test **vẫn xanh**, vì phpunit chạy tuần tự — race cần hai
   connection đồng thời. Đừng tự nhận là đã kiểm chứng. Cách duy nhất chứng minh được ở
   tầng test: đẩy bất biến xuống **DB constraint** (unique index) rồi mutation-check *nó*.
   Khoá vẫn giữ để tránh ném exception vào mặt khách; index là lưới an toàn cuối.
7. **Guard nằm nhờ trên một nhánh thì nhánh kia không có guard.** Trần chống hoàn tiền hai
   lần thật ra sống trong `RefundService::refundedTotal()` — nhưng `ReturnService::refund()`
   chỉ gọi `RefundService` **khi có gateway capture**. Đơn COD/bank không có capture → bỏ qua
   luôn cả trần. Bấm "Refund" hai lần là chuyển tiền đôi. Khi thấy `if (điều kiện) { gọi thứ
   đang giữ luật }`, hỏi ngay: **nhánh `else` ai giữ luật?** Đặt guard ở nơi *luật thuộc về*
   (chính RMA), không ở nơi tình cờ có nó.
8. **Đọc-rồi-ghi quanh một lệnh gọi mạng là cửa sổ cho double-spend.** Claim trạng thái
   **dưới khoá trước** khi gọi gateway; gọi gateway **ngoài** transaction (§4); gateway fail
   thì **nhả claim** để retry. Không claim → double-click hoàn tiền hai lần. Claim mà không
   nhả → outage biến RMA thành `refunded` vĩnh viễn dù chưa chuyển đồng nào.
9. **"Vendor đã bọc transaction" không có nghĩa thao tác của bạn nằm trong đó.**
   `Lunar\Actions\Carts\CreateOrder` bọc `DB::transaction` quanh pipeline (nên order lines +
   `DecrementStock` là atomic — tốt), **rồi commit**. Driver thanh toán mới `update(status,
   placed_at, meta)` ở câu lệnh **riêng, ngoài** transaction đó. Chết giữa hai bước → order
   tồn tại, kho đã trừ, `meta` rỗng. Trước khi dựa vào một cột do vendor/driver ghi để nhận
   diện bản ghi (ở đây: `meta.payment_type`), hỏi: **cột đó được ghi ở transaction nào?**
   Job dọn dẹp phải nhận diện bằng thứ được ghi **trong** transaction, hoặc bằng sự *vắng
   mặt* của thứ ghi sau nó (ở đây: `placed_at IS NULL`).
10. **`Settings::put()` thay cả group, không merge.** Trang admin phải ghi **mọi** khoá nó
    sở hữu ở mỗi lần lưu. Đo được: lưu riêng `hold_minutes` làm `low_stock_threshold`
    thành `NULL`. Và group chỉ **một cấp** — `get('customer.ttl_days')` đọc group
    `customer`, key `ttl_days`; khoá lồng như `customer.tokens.ttl_days` **không** ra
    admin được, phải làm phẳng trong config trước.
11. **Cái gì ra admin: quyết định kinh doanh. Cái gì ở lại config: kỹ thuật + bảo mật.**
    Thời gian giữ hàng, TTL đăng nhập, bật/tắt push → admin (chủ shop đổi lúc 2 giờ sáng).
    Tên **class** driver → config (resolve trong `register()`, trước khi DB sẵn sàng; chọn
    driver chưa cài là vỡ mọi request). **Scope quyền** (`tokens.abilities`) → config; nới
    rộng từ web form là privilege escalation. Mọi giá trị ra admin phải **clamp** ở service:
    admin gõ `0` vào "giữ hàng" thì đơn bị huỷ khi khách còn ở trang ngân hàng.

**Định nghĩa "đã thanh toán" chỉ có một nguồn:** `Modules\Order\Support\OrderStatus::paid()`
(bọc `config('analytics.paid_statuses')`, dùng bởi `AnalyticsService`, `MembershipService`,
`CoPurchaseStrategy`, `FitHistoryService`, `DispatchOrderPaidForOfflineOrder`). Trước đây
mảng fallback bị copy-paste ra **5 nơi** — đúng loại drift đã sinh ra bug COD-không-lên-hạng.
Tương tự: `OrderStatus::RETURNABLE`, `OrderStatus::CLOSED`. Không tạo danh sách status riêng
cho từng module — chúng sẽ trôi khỏi nhau.

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

* **Format bắt buộc:** `vendor/bin/pint <file bạn đã sửa>` trước khi commit.
  **Đừng** chạy `vendor/bin/pint` trần trên toàn repo: chưa có `pint.json`, nên preset
  `laravel` mặc định sẽ format lại **241 file có sẵn** và trộn chúng vào diff của bạn.
  Từ khi fork Lunar vào repo, **119/241 file đó nằm trong `modules/Lunar` +
  `modules/LunarAdmin`** — reformat code upstream là tự tay làm mọi diff đối chiếu với
  Lunar gốc thành vô dụng. Dọn một lượt = commit riêng + `pint.json` loại trừ hai thư mục
  fork (xem todo P1).
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
