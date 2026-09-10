# SME Fashion Ecommerce — Laravel 12 + LunarPHP

> Tài liệu này mô tả **hiện trạng thực tế** của dự án: một storefront fashion cho
> SME (single-store) trên Laravel 12 (PHP 8.4) + LunarPHP **2.0.0-alpha.6**
> (`lunarphp/core` + `lunarphp/panel`), storefront **100% Blade SSR + vanilla JS**
> (không Vue). Chỉ ghi những gì đã có trong code.
>
> Cập nhật lần cuối: **2026-09-09** — 13 module nghiệp vụ (layout nwidart v13),
> 64 route `api/v1`, 560 test xanh.
>
> ⚠️ **Admin đang dở dang.** Fase 3 của [đợt nâng 2.0](../guides/upgrade-lunar-2.0.md)
> đã gỡ toàn bộ admin Filament và cài `lunarphp/panel` (Inertia + Vue); panel phục
> vụ 370 route của chính nó, nhưng **các trang riêng của dự án chưa được viết lại**
> (Fase 4). Mục nói về Filament bên dưới đã đổi theo, nhưng đừng đọc chúng như
> "đang chạy".
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
> test nguyên trạng. Luật: **GIỮ, KHÔNG MỞ RỘNG** — thêm endpoint vì Blade SSR cần thì
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
- Admin dùng **`lunarphp/panel`** (Inertia + Vue) của Lunar — kế thừa & mở rộng,
  không build lại. Các trang riêng của dự án là một bundle add-on riêng — xem
  [panel-addon.md](panel-addon.md).

## Nguyên tắc kiến trúc cốt lõi

> ## ⭐ Nguyên tắc số 0 — bao trùm mọi nguyên tắc còn lại
>
> ### **Laravel application layer xây trên Lunar Commerce Kernel, với business logic riêng chỉ xuất hiện khi Lunar không cung cấp.**
>
> Đây không phải một lời khuyên phong cách. Nó là **phép thử để quyết định một
> đoạn code có được phép tồn tại hay không**, và năm nguyên tắc đánh số bên dưới
> chỉ là hệ quả của nó.
>
> **Trước khi viết bất kỳ logic thương mại nào, hỏi theo đúng thứ tự này:**
>
> 1. Lunar đã có chưa? → Dùng. Kể cả khi nó khác cách mình định làm 10%.
> 2. Lunar có **điểm mở rộng** cho việc này không? → Dùng điểm đó
>    (`config/lunar/*`, action contract, pipeline, event, section/slot của panel).
> 3. Chỉ khi cả hai đều không → mới viết logic riêng, **và ghi lại vì sao**.
>
> **Cái giá của việc bỏ qua phép thử này không phải là "code hơi thừa" — nó là
> lỗi im lặng.** Bốn ca có thật trong repo này, tất cả đều chạy đúng cho tới
> ngày không đúng nữa:
>
> | Đã tự viết | Lunar đã có | Mất gì |
> | --- | --- | --- |
> | `MediaThumbnails` dựng `thumbnail` từ `media` bằng PHP | Quan hệ `thumbnail()` (MorphOne, lọc collection **và** `primary`) | Bản tự viết bỏ mất bộ lọc collection → chọn nhầm ảnh swatch làm ảnh đại diện. Chưa nổ vì chưa ai gắn `primary` cho swatch |
> | `$customer->addresses()->create()` | `CreatesCustomerAddress` | Mất `activity log` — mọi thay đổi địa chỉ từ storefront vô hình với nhân viên |
> | `$user->customers()->attach()` | `LinkCustomerUser` | `attach()` không bất biến (hai request đồng thời tạo hai dòng pivot) + mất dấu vết |
> | *(định tự viết)* hệ giao hàng | 15 action Fulfilment + đủ UI trong panel | Suýt dựng lại thứ đã hoàn chỉnh, gồm cả bảng lưu mã vận đơn mà roadmap còn ghi là "còn thiếu, phải thêm" |
>
> **Chiều ngược lại cũng là một cái bẫy.** Áp dụng nguyên tắc quá tay — cố nhét
> mọi thứ vào một cơ chế của Lunar — cũng sai. Trang chi tiết lookbook **cố ý
> không** dùng `ProductService::cardRelations()`: nó render Blade, không serialise
> nhóm option, và nhét bộ quan hệ chung vào làm trang **tăng** 14 → 17 truy vấn.
> Tiêu chí luôn là *thứ này có thật sự cùng một việc không*, không phải *có gọi
> tên giống nhau không*.
>
> **Cách kiểm nhanh khi nghi ngờ:** đọc code của Lunar trong `vendor/` xem nó lấy
> quyết định từ đâu. Gần như mọi lỗi thuộc lớp này trong dự án được tìm ra bằng
> cách đó, chứ không phải bằng cách đọc code của mình — vì code của mình *trông
> vẫn đúng*.

> Chuẩn code chi tiết ở
> [../guides/coding-standards.md](../guides/coding-standards.md).

1. **Không dựng lại tính năng Lunar đã có — chỉ kế thừa và mở rộng.** Cách mở rộng
   theo thứ tự ưu tiên: cấu hình `config/lunar/*` → điểm mở rộng chính chủ của Lunar
   (pipelines cart/checkout, custom field/attribute, cast/scope trên model core,
   section/slot của panel, events) → wrap bằng service trong module → **cuối cùng**
   mới là composer patch.
   Lunar nằm trong `vendor/` nên **không sửa trực tiếp** — mỗi patch là một thứ có
   thể vỡ khi nâng cấp, phải tự bảo trì. Hiện **không còn patch nào** (xem § dưới).
2. **Lunar là source of truth** cho catalog, cart, pricing, order, customer. Bọc qua
   service/API, không nhân bản dữ liệu/logic.
3. **Một service là nguồn logic duy nhất**, cả Storefront controller lẫn API
   controller đều gọi nó.
4. **Storefront SSR bằng Blade**; vanilla JS enhance các phần tương tác. **Không dùng
   Vue cho storefront** (panel admin là Vue, nhưng là stack tách hẳn — xem
   [../guides/upgrade-lunar-2.0.md](../guides/upgrade-lunar-2.0.md) §1).
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
| Admin panel (`Lunar\Panel\`) | `vendor/lunarphp/panel` — `Panel::section()` / slot (Fase 4 chưa viết) |
| Thêm quan hệ vào model core | `Model::resolveRelationUsing()` |
| Thêm/đổi cast trên model core | `Model::addCasts()` — ví dụ `FilledTranslations` |
| Thêm scope | `Model::addLocalScope()` |
| ~~Thay hẳn model core~~ | **Không còn.** Lunar 2.0 gỡ `ModelManifest::replace()`; core gọi thẳng class cụ thể |
| Sửa thứ không có extension point | **composer patch** — bậc cuối, hiện không dùng |

**Hệ quả cần nhớ:**

* **`composer update` nâng cấp Lunar bình thường**, security patch tự về.
* **Không sửa `vendor/`.** Muốn đổi hành vi thì leo thang mở rộng dưới đây. Hiện
  **không còn patch nào** — `cweagans/composer-patches` đã gỡ khỏi `composer.json`
  (2026-08-27).
* **Không swap được model nữa — vá xuống tầng dữ liệu.** Bản vá locale fallback
  trong `HasTranslations` đã đi ba chặng: composer patch → trait gắn qua
  `ModelManifest::replace()` (1.5) → cast `FilledTranslations` cài bằng
  `addCasts()` (2.0, vì `replace()` biến mất). Bài học giữ nguyên qua cả ba: khi
  không sửa được hàm đọc, hãy sửa **dữ liệu nó đọc** — lọc locale rỗng lúc decode
  thì hàm của upstream trở thành đúng. Xem [../upstream/README.md](../upstream/README.md).
* **Trước khi viết patch, hỏi lại câu đó.** Patch làm `composer update` fail cứng mỗi
  khi upstream đụng vào method; một subclass thì không.
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
└─ Thêm cast / scope?                     → (4) Model::addCasts() / addLocalScope()
   (Đổi hẳn một METHOD của model core: 2.0 không còn đường — vá xuống tầng dữ
    liệu, xem (4).)

Cần PHẢN ỨNG khi có sự kiện?             → (5) Event::listen(LunarEvent)
Cần đổi ADMIN?                           → (6) Panel Section / Slot / TableExtension

Không cách nào ở trên chạm tới được?     → (7) composer patch — BẬC CUỐI, hiện KHÔNG dùng:
                                           trước khi tới đây, hỏi xem có vá được ở
                                           tầng dữ liệu mà hàm đó đọc không
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
  - ❌ **Đã bỏ:** stage `DecrementStock` của shop. Lunar 2.0 tự điều khiển tồn kho
    bằng sự kiện `OrderPlaced`/`OrderCancelled`. Thứ shop còn cắm vào là
    `CartStockAtOrderCreation` ở hook validator `order_create` — guard oversell,
    vì `ValidateCartForOrderCreation` của Lunar kiểm *purchasable* chứ không kiểm
    *available*.
  - Các stage core có sẵn để tham chiếu/sắp lại: `FillOrderFromCart`, `CreateOrderLines`,
    `CreateOrderAddresses`, `CreateShippingLine`, `CleanUpOrderLines`, `MapDiscountBreakdown`
    (order); `CalculateLines`, `ApplyShipping`, `ApplyDiscounts`, `CalculateTax`,
    `Calculate` (cart).
- **Payment types / media definitions / cart_session / cart eager-load**:
  - ✅ **Đang dùng:** `Checkout/config/payment-overrides.php` (COD/bank/vnpay/momo type),
    `Assets/config/overrides.php` (FashionMediaDefinitions), cart_session auto_create.
  - ❌ **Đã bỏ:** `Catalog/config/cart-eager-load-overrides.php`. Nó tồn tại vì
    purchasable cũ (`ProductSku`) không có quan hệ `values`. `ProductVariant` của
    Lunar thì có, nên `cart.eager_load` mặc định dùng được nguyên trạng.

> **Bất biến:** mọi tuỳ biến `lunar.*` phải nằm ở `modules/*/config/*.php` + gọi
> `LunarConfigOverride::applyFrom()` trong `boot()` — **không** sửa tay `config/lunar/*.php`.
> `config/lunar/` được giữ **y hệt bản vendor**, nên `php artisan lunar:install` /
> `vendor:publish --tag=lunar --force` chạy lúc nào cũng an toàn, không mất gì.
> Kiểm chứng nhanh sau khi nâng cấp Lunar: `git status config/lunar/` phải sạch.

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

## (4) `addCasts()` / `addLocalScope()` — thêm cast, scope lên model core

```php
// trong register()
ProductVariant::addCasts(['image_asset_ids' => 'array']);
Product::addLocalScope('featured', fn (Builder $q) => $q->where(...));
```

✅ **Đang dùng:** `FilledTranslations` cài lên `Product`, `Collection`, `Brand`,
`ProductOption`, `ProductOptionValue` (`CatalogServiceProvider::guardEmptyTranslations()`).

> **Lunar 2.0 gỡ hẳn `ModelManifest::replace()`.** 1.x cho phép thay cả model bằng
> subclass của mình — dự án dùng nó cho bốn model. 2.0 gọi thẳng class cụ thể ở
> khắp core và `ModelManifest` chỉ còn route binding + morph map, nên **không còn
> cách nào override một method của model core**.
>
> Hệ quả thực tế: khi cần đổi hành vi một method, hãy **vá xuống tầng dữ liệu mà
> method đó đọc**. Ví dụ có thật: `HasTranslations::translate()` trả về chuỗi rỗng
> khi khoá locale tồn tại nhưng rỗng. Không sửa được hàm — nhưng một cast lọc
> locale rỗng ngay lúc decode JSON khiến tình huống đó **không xảy ra được**, và
> hàm của upstream trở thành đúng như đang viết. Rộng hơn cách cũ: mọi model đọc
> cột đó đều được vá, không chỉ những model ta nhớ mà subclass.

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
> nhưng khách trả khi giao. Listener cần "tiền đã về tay" phải tự kiểm
> `OrderStatus::of($order) === OrderStatus::PAYMENT_RECEIVED`.

## (6) Panel admin — Section / Slot / TableExtension

⚠️ **Chưa dùng — Fase 4 chưa bắt đầu.** Mục này ghi API sẵn có để lúc viết không
phải khảo sát lại.

`lunarphp/panel` (Inertia + Vue) mở rộng qua `Lunar\Panel\Facades\Panel`:

| Việc | API |
|---|---|
| Thêm cả một khu vực (nav + route + slot) | `Panel::section(new MySection)` |
| Chèn UI vào trang có sẵn | `PageZone` / `Panel::slots()` |
| Thêm cột / filter / bulk action vào bảng | `Panel::extendTable($tableId, $class)` |
| Thêm nút vào header trang | `Panel::addPageAction($pageId, $class)` |
| Widget dashboard | `Panel::widget($class)` |
| Chuỗi ngôn ngữ của addon | `Section::langNamespaces()` |

Thứ tự mọi thứ (nav, cột, action) dùng `Lunar\Panel\Support\Position`:
`priority(int)` hoặc `before(key)` / `after(key)`.

> **Món nợ đã trả tự động:** admin Filament cần reflection để hoán đổi
> `Panel::$resources` và `$navigationGroups` (xem [1.5 §13.1–13.2](../guides/upgrade-lunar-1.5.md)).
> Panel mới có `section()` chính thức nên `ModulesServiceProvider` — vốn chỉ tồn
> tại để làm việc đó — đã bị xoá.

## Chốt: nơi đăng ký & thứ tự

| Kỹ thuật | Provider hook | Ghi chú |
|---|---|---|
| Config / pipeline override | `boot()` | qua `LunarConfigOverride::applyFrom()` |
| `Payments::extend`, `ShippingModifiers->add` | `boot()` | facade cần app booted |
| `Discounts::addType` | `register()` | admin đọc type sớm |
| `resolveRelationUsing` | `boot()` | model đã load |
| `Model::addCasts` / `addLocalScope` | `register()` | trước khi model được dùng |
| `Event::listen` | `boot()` | |
| `Panel::section()` | `boot()` | facade cần app booted (Fase 4) |

> **Core (`Modules\Core`) đăng ký đầu tiên** → `Settings`,
> `LunarConfigOverride`, `Queues` sẵn sàng cho mọi module. Core **chỉ hạ tầng**, tuyệt
> đối không chứa business logic hay điểm mở rộng domain-specific.

---

# Tech stack (theo repo hiện tại)

| Layer | Công nghệ |
|---|---|
| Backend | Laravel 12 (PHP 8.4) |
| Kiến trúc | Modular monolith (`modules/`) |
| Commerce core | LunarPHP 2.0.0-alpha.6 (`lunarphp/core`) |
| Admin | `lunarphp/panel` — Inertia v3 + Vue 3 + Tailwind 4 (asset biên dịch sẵn của vendor) |
| Storefront render | Blade (SSR) |
| Storefront JS | Vanilla JS + Bootstrap 5 — **không Vue, không jQuery** |
| Build | Vite 7 + Laravel Vite Plugin |
| HTTP client (JS) | Axios |
| CSS (storefront) | Bootstrap 5 + SCSS (`themes/fashion/css`, entry `app.scss`) — **không Tailwind** |
| CSS (admin) | Tailwind 4 — **nằm trong asset dựng sẵn của panel**, dự án không build (đã gỡ Tailwind khỏi `package.json`) |
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
 └── Models/User.php                         # auth user (Lunar customer riêng)

config/modules.php                           # cấu hình nwidart (paths.modules → base_path('modules'))
modules_statuses.json                        # bật/tắt module — PHẢI commit
routes/{web,api}.php                         # gom routes từ các module
modules/                                     # 13 module (12 feature + Core hạ tầng)
themes/fashion/                              # theme active (view + JS + CSS)
docs/                                        # tài liệu kỹ thuật
```

## 13 module (12 feature + Core)

Codebase từng có 24 module scaffold; đã **hợp nhất còn 11 feature module** để hợp quy mô
single-store (gộp các sub-domain tương đồng vào một module, bỏ ~13 service provider),
cộng **1 module `Core`** chứa hạ tầng dùng chung.

**Core** — hạ tầng cross-cutting, **không chứa business logic** (đăng ký đầu tiên nên
mọi module khác dùng được): `Support\Settings` (DB settings store key→JSON + fallback
config/env), `Support\Queues` (tên queue tập trung), `Casts\FilledTranslations` (vá lỗi locale
fallback của Lunar, xem [../upstream/README.md](../upstream/README.md)),
`Support\LunarConfigOverride` (re-apply override lên `config/lunar/*`),
`Support\UntranslatedContentReport`, migration `app_settings`.

| Module | Gộp từ | Trách nhiệm | Nội dung chính |
|---|---|---|---|
| **Catalog** | Catalog + Product + Pricing + Review + Recommend + Search + Collection | Toàn bộ hiển thị/truy vấn sản phẩm | Services: `ProductService`, `PricingService`, `ReviewService`, `RecommendationService`, `CollectionService`, `SitemapService`, `SizeChartService`, `SizeRecommender`, `FitHistoryService`. Models: `ProductMaterial`, `SizeChart`, `SizeChartRow`, `Review`. Contracts/Drivers: `SearchEngine` + `DatabaseSearchEngine`. Strategies: `Association`, `Collection`. Admin: bảng size + Size&Fit (slot). Home/sitemap/health + seeders demo. |
| **Content** | CMS + SectionBuilder + Menu | Nội dung storefront admin-managed | Models: `Page`, `Banner`, `Lookbook`(+Image/Item), `Redirect`, `PageSection`, `Menu`(+Item). Services: `ContentService`, `SectionRenderer`, `MenuRenderer`, `MenuTree`. Admin: 6 màn hình khai báo dưới `/panel/shop`. |
| **Assets** | Media + FileManager | Ảnh/file | Services: `MediaUrl`, `ConversionGenerator`, `MediaRegenerator`, `MediaSettings`, `MediaLibraryService`. On-demand conversion + media library. Admin: bộ chọn ảnh cho mọi trường ảnh của panel. |
| **Checkout** | Checkout + Cart + Payment | Luồng cart → checkout → payment | Services: `CartService`, `CheckoutService`, `TokenAwareCartSession`, `RefundService`. Gateway: `VNPayGateway`/`MoMoGateway` + `*PaymentProcessor` kế thừa **`GatewayReconciler`** (nơi duy nhất giữ luật callback: chữ ký → số tiền → đơn đã đóng → khoá chống race). PaymentTypes: `VNPayPayment`, `MoMoPayment`. Config override `cart-overrides.php` + `payment-overrides.php`. |
| **Customer** | Customer + Location | Khách, địa chỉ, auth, wishlist, địa giới VN | Services: `CustomerResolver`, `AuthService`, `TokenIssuer`, `WishlistService`, `RecentlyViewedService`, `CountryService`. Models: `WishlistItem`, `Province`, `Ward`. Auth web + Sanctum (cookie + PAT), address book, order history, VN provinces/wards API + seeder dataset. |
| **Order** | — | Order, trạng thái, email giao dịch, RMA | Services: `OrderService`, `OrderMailer`, `ReturnService`, `InvoiceService`, `OrderTimeline`. Support: `OrderStatus` (**một nguồn** cho status handle, nhãn i18n, `PAID`/`CLOSED`/`RETURNABLE`). Events: `OrderPaid`, `OrderStatusUpdated`. 4 mailable queued + observer/listeners. |
| **Promotion** | — | Discount nâng cao hiển thị storefront | Services: `PromotionService` (facade: queries + coupon + memoization), `PromotionTargetResolver` (targeting/eligibility), `SaleBadgeService` (badge/banner/describe), `MembershipService`. Custom discount types + flash sale + membership. |
| **Inventory** | — | Stock per-variant, reserve **+ release**, notify-me | Services: `InventoryService` (facade mỏng trên rollup của Lunar), `StockNotificationService`, `BackInStockNotifier`. Validator `CartStockAtOrderCreation`. Command `orders:expire-abandoned`. Model `StockNotification`. Admin: hàng đợi báo hàng về (chỉ đọc). |
| **Shipping** | — | Zone/rate DB-backed | Services: `ShippingService`, `ShippingZoneResolver`. Model `ShippingZone`. Admin: vùng vận chuyển + cài đặt. |
| **Notification** | — | In-app inbox + push cho mobile | Notification `OrderStatusChanged` (channel `database` + `PushChannel`). Contract `PushSender` + `NullPushSender`. Models `DeviceToken`. Service `DeviceRegistry`. Support `PushSettings` (kill-switch push, admin). API inbox + device registry. **Không** đụng 4 mailable đang chạy. |
| **Analytics** | — | Dashboard bán hàng | `AnalyticsService`. Admin: một widget dashboard (luỹ kế + xu hướng 6 tháng). |
| **Theme** | — | Active theme, locale, view namespace | Services: `LocaleService`, `ThemeSettings`. Admin: tab cài đặt Giao diện. Middleware storefront/locale. |

> Cấu hình hệ thống (Channels, Languages, Taxes, Staff) dùng thẳng Settings của Lunar.

## Nguyên tắc Theme

- **Theme = lớp trình bày thuần.** `themes/fashion/` chỉ chứa Blade + JS + CSS, không
  query DB, không gọi model Lunar trực tiếp, không business logic.
- **Data đến từ service layer.** Storefront controller (trong module) gọi service, đổ
  data (qua API Resource shape) vào Blade. View composer inject data trình bày (ảnh,
  giá, menu) để Blade không resolve service (coding standards §7).
- Active theme: `fashion` (theme duy nhất active). Đổi brand = copy `themes/fashion`.

## Cấu trúc một Module

Layout **nwidart/laravel-modules v13**: mã PSR-4 nằm trong `app/`, thư mục dữ liệu
viết thường ở gốc module.

```text
modules/<Name>/
 ├── module.json                             # manifest: providers[] + priority (thứ tự nạp)
 ├── composer.json                           # PSR-4 root (wikimedia/composer-merge-plugin)
 ├── app/
 │   ├── Http/Controllers/{Storefront,Api/V1}/   # Blade (theme::) và JSON (/api/v1)
 │   ├── Http/{Requests,Resources}/              # validation + API Resource (JSON contract)
 │   ├── Services/                               # business logic (web + API gọi chung, ≤ 500 dòng)
 │   ├── Support/                                # value object, hằng số (vd OrderStatus)
 │   ├── Data/                                   # DTO / result object, bất biến
 │   ├── Contracts/                              # CHỈ khi có ≥ 2 implementation (SearchEngine, PushSender)
 │   ├── Models/ Events/ Listeners/ Jobs/ Observers/
 │   ├── Palidation/                             # hook validator của Lunar (guard oversell)
 │   ├── DiscountTypes/ | PaymentTypes/ | Modifiers/ | Strategies/ | Drivers/
 │   ├── Console/                                # artisan command của module
 │   └── Providers/<Name>ServiceProvider.php
 ├── config/
 ├── database/{migrations,seeders}/
 ├── resources/views/
 └── routes/{web,api}.php                    # api tự prefix /api/v1
```

Namespace PSR-4 `Modules\<Name>` → `modules/<Name>/app`, khai trong `composer.json`
của từng module (merge-plugin gom lại) — **không** còn dòng `"Modules\\": "modules/"`
ở composer.json gốc. Seeder nằm ngoài `app/` nên module có seeder phải khai thêm root
`Modules\<Name>\Database\Seeders\`.

**Ai nạp module:** nwidart đọc `module.json` của từng module và đăng ký provider theo
`priority` tăng dần (Core=1 … Analytics=13). Thứ tự có ý nghĩa khi module này phụ thuộc
binding của module kia — `Notification` phải sau `Order` vì nghe domain event của nó.
Đổi thứ tự = sửa `priority`, không sửa code.

`app/Providers/ModulesServiceProvider` **đã bị xoá** ở đợt nâng 2.0. Nó tồn tại chỉ
để dựng panel Filament — gom page/resource các module đóng góp rồi hoán đổi
`Panel::$resources` bằng reflection. Panel mới có `Panel::section()` chính thức nên
mỗi module tự đăng ký phần admin của mình trong provider của chính nó; không còn
chỗ tập trung nào phải chạy sau tất cả.

`modules_statuses.json` (bật/tắt module) **phải commit**: nwidart coi module không có
tên trong file là *disabled*, nên thiếu file thì clone mới boot ra zero module.

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
  (`?màu-sắc=Đen&kích-cỡ=M` — key là **nhãn đã localise** đã slugify, không phải handle):
  SSR preselect SKU + active buttons + **giá đúng SKU** (no-JS/crawler), JS đồng bộ URL
  qua `replaceState`. **Gallery đổi theo màu** (mỗi màu một bộ ảnh; đổi size giữ nguyên
  gallery). Size chart modal + "find my size".
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
  qua tab cài đặt Giao diện (`/panel/settings/shop/theme`). Single-market: bật 1 ngôn ngữ → khoá locale, ẩn switcher.

## Quy ước JS

- **Vanilla + Bootstrap 5, không Vue, không jQuery.** 25 file trong
  `themes/fashion/js/enhance/`; file `_*.js` là helper (import, không auto-run).
- `enhance/*.js`: mỗi module export `default fn(root=document)`, target qua `data-*`,
  bootstrap tự động trong `app.js`, idempotent để chạy lại trên fragment mới. Card động
  render qua `enhance/_card.js` (khớp `product-card.blade.php` 1:1).
- Đồng bộ giữa consumer qua DOM event (`cart:updated` → `cart.js` refresh →
  `cart:refreshed`; `size:recommended` → variant picker). Không coupling trực tiếp.
- Axios gọi `/api/v1` (CSRF + Sanctum cookie cùng domain). State giỏ là server-side
  (Lunar cart).
- `app.js` phải gán `window.bootstrap` — enhancer gọi Offcanvas/Modal bằng tay; chỉ
  import side-effect thì các lệnh đó im lặng không chạy.

---

# Các domain nghiệp vụ

## Catalog / Product / Search / Recommend
- **Purchasable là `ProductVariant` của Lunar** — không còn tầng SKU riêng. Trục
  biến thể là `ProductOption` / `ProductOptionValue` dùng chung của Lunar, variant
  liên kết tới value qua bảng pivot của nó.
  - `Modules\Catalog\Support\VariantAxes` suy ra **chỉ số vị trí** của mỗi
    variant (`[1, 0]`) từ các option của product, nên hợp đồng JS của storefront
    không đổi khi bỏ tầng SKU.
  - Ảnh swatch nằm ở `meta` của chính `ProductOptionValue`.
  - Cart/order line trỏ vào variant qua morph alias **`product_variant`**.
  - `products.variables` (blob trục tự định nghĩa), `lunar_product_skus`,
    `sku_variant_map` và các cột `status`/`model` trên variant **đã bị bỏ**. Xem
    [migrate-skus-to-variants.md](../guides/migrate-skus-to-variants.md).
  - Cột duy nhất shop còn thêm vào variant: `image_asset_ids` (danh sách Asset id
    của thư viện ảnh) và `cost_price`. **Không đặt tên cột trùng tên method của
    `ProductVariant`** — cột thật luôn thắng quan hệ trùng tên trong Eloquent, và
    cột `images` cũ từng làm hỏng mọi trang sửa sản phẩm của panel vì thế.
- `ProductService` là nguồn read duy nhất (list qua `SearchEngine`, `findBySlug`,
  `bySlugs` giữ thứ tự, `related`, `resolveSelectedVariant` cho deep-link).
  ⚠️ `resolveSelectedVariant` khớp theo **nhãn đã localise** (`?màu-sắc=Đen`), không phải
  handle — `product-variant.js` slugify đúng nhãn đó nên hai bên khớp nhau.
- **Ảnh theo màu:** `ProductVariant.image_asset_ids` giữ danh sách **Asset id**; `ProductVariantResource`
  resolve chúng qua `MediaImageResource` để ra **cùng shape** với gallery cấp product
  (`{small,large,zoom,width,height}`), giữ nguyên thứ tự do admin đặt. SSR đã render
  đúng bộ ảnh của variant đang chọn (view composer trong Assets), nên deep-link không bị
  nháy; JS chỉ đổi gallery khi **tập ảnh** đổi — đổi size cùng màu không rebuild.
  Variant không có ảnh riêng → fallback về gallery product.
- **Bộ quan hệ của thẻ sản phẩm nằm ở MỘT chỗ:**
  `ProductService::cardRelations()`. Mọi đường sinh ra thẻ (search engine,
  collection, gợi ý, khuyến mãi) đều `->with()` bộ này.

  Trước đây bốn service mỗi chỗ giữ một bản chép, và **mỗi bản thiếu một thứ
  khác nhau**. Thiếu sót kiểu này vô hình cho tới khi có người đếm truy vấn:
  trang vẫn render đúng, chỉ là mỗi thẻ tự đi lấy dữ liệu. `/search` từng chạy
  **1298 truy vấn cho 24 thẻ**; gợi ý 112 truy vấn cho 8 sản phẩm.

  Bắt buộc phải có `productOptions.values` **và** `variants.values`:
  `ProductResource` serialise nhóm option cho từng thẻ, và phép nối đó cần cả
  hai vế.

  `thumbnail` cũng bắt buộc, **dù `media` đã có trong danh sách**. Lunar khai
  `thumbnail()` là một `MorphOne` riêng (lọc theo collection +
  `custom_properties->primary`), và Eloquent không trả lời một quan hệ bằng
  collection đã nạp của quan hệ khác. View composer của thẻ đọc nó ba lần
  (`$image`, `$picture`, `$hoverImage`), nên bỏ ra là **một truy vấn mỗi thẻ**.
  Đây từng là nhận định sai trong chính tài liệu này — sửa sau khi đo section
  `product-tabs`: 19 → 12 truy vấn cho 8 thẻ, truy vấn `media` lẻ 8 → 0.

  Danh sách này là bộ của thẻ JSON. Trên các đường **chỉ render Blade** (section
  `product-tabs` ở trang chủ) thì `productOptions.values` là nạp thừa, giá 3 truy
  vấn gom lô — đã đo và vẫn giữ: 3 truy vấn hằng số rẻ hơn một danh sách thứ hai
  rồi lại lệch khỏi danh sách này, đúng con bug mà `cardRelations()` sinh ra để
  chặn.

  Ngược lại, KHÔNG phải đường nào chạm product cũng dùng bộ này. Trang chi tiết
  lookbook (`ContentService::lookbook()`) render thẻ bằng Blade và không serialise
  nhóm option, nên nó giữ danh sách hẹp của riêng nó — nhét `cardRelations()` vào
  đó làm trang **tăng** 14 → 17 truy vấn. Tiêu chí là *thẻ đó có đi qua
  `ProductResource` không*, không phải *có phải là thẻ sản phẩm không*.

  📏 Đo phải ấm cache: chạy request một lần bỏ đi rồi mới bật `DB::enableQueryLog()`
  cho lần thứ hai. Lần đầu trong một tiến trình mới còn nạp config/view/section
  cache — chính trang lookbook này đếm nguội ra 39 truy vấn, ấm ra 14. Và đừng
  `git stash` một file mà file khác đang gọi: trang sẽ lỗi và cho ra "2 truy vấn",
  trông như tối ưu chứ thực ra là trang chết.

  Đi kèm là việc xoá `Modules\Catalog\Support\MediaThumbnails` — một helper
  dựng `thumbnail` từ `media` bằng PHP để né truy vấn thứ hai, gọi ở 5 chỗ. Nó
  chép lại `thumbnail()` của Lunar nhưng **bỏ mất một trong hai bộ lọc**: Lunar
  lọc `collection_name = lunar.media.collection` **và** `primary = true`, helper
  chỉ lọc `primary` trên toàn bộ `media`. Sản phẩm của shop có cả collection
  `swatch` (FashionMediaDefinitions), nên một swatch gắn cờ primary sẽ bị chọn
  làm ảnh đại diện. Chưa xảy ra — không chỗ nào gắn `primary` cho swatch — nhưng
  đó là lỗi chờ sẵn, đổi lấy đúng một truy vấn gom lô mỗi lượt render.

  Tổng cộng có **tám** bản chép được gom về đây: search engine, collection, gợi
  ý, khuyến mãi (×2), `ProductService::bySlugs()/byIds()/related()`,
  `WishlistService` và section `product-tabs`. Bản của wishlist thiếu cả
  `defaultUrl` lẫn `prices` — trang yêu thích vừa truy vấn link theo từng thẻ vừa
  tự đi lấy giá cho từng variant: **108 → 10 truy vấn cho 8 thẻ**. Ba lời gọi
  `loadMissing([...])` trong SearchController (web + API) và CollectionController
  cũng đã gỡ: search engine nạp sẵn bộ này rồi, nên chúng là no-op.

  ⚠️ Gọi `productOptions()` (phương thức quan hệ) **luôn** truy vấn mới, kể cả
  khi caller đã eager-load. Dùng `loadMissing` — đó là lỗi khiến ngay cả trang
  chi tiết, vốn eager-load đúng, vẫn trả thêm một truy vấn mỗi lần.

- **Search abstraction:** interface `SearchEngine` + driver `DatabaseSearchEngine`
  (MySQL, `computeFacets` trả size/color/brand/price, `applyFilters`). Đổi engine sau =
  thêm driver, không sửa caller. **Facet và filter đọc chung một nguồn** — bảng
  option của Lunar; trước đây facet giải mã blob `variables` còn filter đã dùng
  bảng option, và panel chỉ ghi bảng option nên blob lệch ngay lần sửa đầu tiên.
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
  khi request), sizes cấu hình qua `MediaSettings`, responsive `<picture>`
  + width-srcset (WebP) ở product card + gallery LCP (`fetchpriority`, dimensions chống
  CLS).
- **MediaPicker (2026-08-05):** picker **hiện ảnh thật** (thumbnail của file đã chọn,
  kèm nút bỏ chọn; bản `multiple` thêm nút đổi thứ tự) và mở **thư viện trong modal**
  ngay tại form — lưới ảnh + tìm theo tên + lọc theo thư mục + phân trang + upload tại
  chỗ. Trước đó nó là `Select` chỉ hiện tên file, và nút "mở thư viện" **rời khỏi form**
  sang tab mới → admin mất context đang sửa dở.
  - **State không đổi:** vẫn đúng một Asset id (hoặc mảng id) — nên mọi consumer đang
    resolve id đó (storefront, API Resource) không phải sửa gì.
  - Cấu tạo: `MediaPickerField` (hiển thị + xoá/đổi thứ tự) + `MediaBrowser` (lưới trong
    modal) + `MediaPicker` (factory nối hai cái, giữ API cũ). Truy vấn/lưu file nằm ở
    `MediaLibraryService::browse()/folders()/preview()` — **trang Media Library dùng
    chung** đúng các method đó, không còn copy query.
  - Upload trong modal tự chọn luôn file vừa lên; `type:` giới hạn cả lưới lẫn MIME
    được upload (picker ảnh không nhận PDF).

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

> **Trạng thái đơn hàng là PHÁI SINH, không phải một cột.** Lunar 2.0 xoá
> `lunar_orders.status` và cố ý không mô hình hoá vòng đời do người vận hành tự
> bấm; thay bằng hai rollup dẫn xuất (`payment_status` từ sổ giao dịch,
> `fulfilment_status` từ fulfilment) cộng `closed_at` / `cancelled_at`. Bảy handle
> của shop vẫn còn nhưng là **khung nhìn** trên bốn sự thật đó — `OrderStatus::of()`.
> **Không nơi nào set status:** ghi lại sự thật (một transaction, một fulfilment,
> `cancel()`, `close()`) rồi status tự theo. Chi tiết + bốn cái bẫy:
> [../guides/upgrade-lunar-2.0.md](../guides/upgrade-lunar-2.0.md) §9.6.

- **Panel gửi lại được email THẬT của shop, không phải bản chung chung.** Trang đơn
  hàng của Lunar có nút "Gửi thông báo" (`POST panel/orders/{order}/notify`), dựng
  notification từ `OrderNotificationManifest`. Danh mục đó ship sẵn **đúng một** mục
  — `order-update` — còn email của shop là Mailable phát từ listener, nên nhân viên
  gửi được một "cập nhật đơn hàng" trống rỗng mà **không** gửi lại được đúng cái
  email khách đang hỏi. Lại đúng hình dạng lỗi lặp lại của dự án: *thứ ta ghi và
  thứ panel đọc là hai chỗ khác nhau.*

  `ResendableOrderMail` là adapter mỏng: Lunar dựng notification bằng
  `new $class($order, $message)`, adapter giữ đúng chữ ký đó và `toMail()` trả về
  chính Mailable sẵn có — không chép lại gì của email. `OrderStatusUpdatedMail`
  **cố ý** không đăng ký: nó render một chuyển tiếp (`previousStatus` → hiện tại),
  nên không có khái niệm "gửi lại nó một mình".

  Đăng ký bằng **khoá dịch**, không phải `__()` của khoá: manifest tự dịch lúc đọc,
  dịch sẵn lúc boot sẽ đóng băng locale của nhân viên đang đăng nhập.

  `NotifyCustomerWithUserFallback` mở rộng action của Lunar qua contract (không
  fork) để thêm một nấc người nhận: Lunar chỉ đọc contact email của hai địa chỉ,
  còn `CheckoutController` của ta để `contact_email` **nullable** trên đường API
  (khác `PlaceOrderRequest` của web vốn bắt buộc). Không có nấc này thì hộp thoại
  panel là đường DUY NHẤT từ chối gửi, ném "no recipients" trên một đơn mà shop đã
  gửi email thành công. Chỉ tập người nhận đổi; phần gửi, entry activity
  `email-notification` và event `OrderCustomerNotified` vẫn là của Lunar.
- Order history + order detail + **timeline** (`OrderTimeline` đọc `activity_log`,
  **không** tạo bảng riêng; chỉ lấy event `status-update`, vì cùng bảng đó chứa row
  `updated` với full column diff — không được lộ ra). 1.x có entry đó do Lunar ghi;
  2.0 không còn cột status nên `RecordOrderStatusHistory` tự ghi, đúng shape cũ.
- **`OrderStatus` là một nguồn duy nhất** cho status handle, nhãn i18n (`label()`),
  các tập `PAID` / `CLOSED` / `RETURNABLE`, phép suy `of()` và **vị từ SQL**
  (`scopePaid()` / `paidSql()` — không còn cột để `whereIn`). Trước đây mảng "đã
  thanh toán" bị copy-paste ra 5 service và đã trôi khỏi nhau (COD tính doanh thu
  nhưng không lên hạng).
- **`meta.payment_type` ghi cho MỌI đơn.** Đây là thứ duy nhất phân biệt
  `payment-offline` (COD — đã bán, thu tiền khi giao) với `awaiting-payment` (cổng
  thanh toán bỏ dở, hoặc chuyển khoản chưa về): cả hai đều payment-pending. Danh
  sách "trả khi nhận" đọc từ `lunar.payments.types.*.authorized`, không chép lại.
- **4 mailable queued** (`OrderConfirmationMail`, `OrderPaidMail`, `OrderStatusUpdatedMail`,
  `ReturnStatusMail`) + markdown templates + `OrderMailer` (locale-aware).
  Wiring: confirm qua `PaymentAttemptEvent`, paid qua event `OrderPaid` (gateway callback
  **và** COD qua `DispatchOrderPaidForOfflineOrder` — gate theo `OrderStatus::paid()` nên
  bank-transfer/gateway lúc authorize không bắn; `SendOrderPaidEmail` chỉ gửi khi
  `payment-received` vì khách COD chưa trả tiền lúc đặt),
  status-update qua `SendOrderStatusEmail` — một listener của `OrderStatusUpdated`
  như mọi consumer khác, giữ skip-list riêng. **Không** để trong observer được:
  `RecomputeOrderStatus` ghi rollup bằng `saveQuietly()` nên observer không bao giờ
  thấy hai chuyển trạng thái quan trọng nhất; `OrderStatusUpdated` vì thế dựng từ
  chính năm sự kiện của Lunar (payment/fulfilment status, cancelled, closed,
  reopened) trong `RaiseOrderStatusUpdated`.

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

### Ba con số, không phải một

Tồn kho tách làm hai cột, số thứ ba là suy ra. **Lunar 2.0 sở hữu cả ba** — shop
không còn giữ cột tồn kho nào của riêng mình:

| | Ý nghĩa | Nguồn |
|---|---|---|
| **on-hand** | Hàng đang nằm trong kho — **kiểm kê đếm ra đúng số này** | `lunar_product_variants.stock_on_hand` |
| **committed** | Trong số đó, đã bán nhưng **chưa xuất kho** | `lunar_product_variants.stock_committed` |
| **sellable** | Thực sự còn bán được | `stock_available` / `getTotalInventory()` |

**Vì sao cần tách:** trước đây đặt hàng trừ thẳng số tồn, nên một con số trả lời
đúng câu "còn bán được bao nhiêu" và **sai** câu "trong kho còn bao nhiêu". Chủ
shop đi kiểm kê không bao giờ khớp được với hệ thống.

**Vòng đời — do Lunar 2.0 điều khiển, không phải code của shop:**

```text
đặt hàng      OrderPlaced      → SyncStockForOrder
tạo fulfilment FulfilmentCreated → AllocateStockForFulfilment
giao/huỷ kiện  chuyển trạng thái  → ApplyStockForFulfilmentTransition
huỷ đơn        OrderCancelled    → SyncStockForOrder
```

Bên dưới là các action của Lunar: `AdjustStock`, `RecordStockMovement`,
`RecomputeStockRollup`, `SyncStockCommitment`, ghi vào `lunar_stock_levels` /
`lunar_stock_movements` / `lunar_stock_reservations`.

`StockLedger`, `StockReleaser`, `StockSettler`, `DecrementStock`,
`ProductSkuObserver` và bảng `stock_movements` của shop **đã bị xoá** cùng đợt
hợp nhất SKU → variant. Cột `lunar_orders.dispatched_at` (cờ idempotency của
`SettleStockOnDispatch`) cũng đã bỏ: một đơn chia nhiều kiện không thể mô tả
bằng một mốc thời gian, và `fulfilment_status` mới là câu trả lời.

⚠️ **Mọi guard phải đọc `sellable`, không đọc `stock_on_hand`.** Hàng đã giữ vẫn
nằm trong kho — đọc nhầm cột là bán chồng đơn. `canBeFulfilledAtQuantity()` của
Lunar là đường đọc chuẩn; `InventoryService` chỉ là facade mỏng trên nó.

⚠️ **Guard oversell ở bước tạo đơn** là của shop:
`Modules\Inventory\Validation\CartStockAtOrderCreation` cắm vào hook validator
`order_create` của Lunar. Cần nó vì `ValidateCartForOrderCreation` của Lunar kiểm
*purchasable*, không kiểm *available* — không có nó thì giỏ vẫn thành đơn khi
hàng đã hết giữa lúc thêm giỏ và lúc thanh toán.

⚠️ **Cảnh báo tồn đọng:** committed chỉ được giải phóng khi giao hoặc huỷ. Đơn đã
thanh toán quá `STALE_COMMITMENT_DAYS` (3) mà chưa giao xong sẽ giữ hàng vô hạn →
`InventoryService::staleCommitments()` liệt kê. Màn hình Stock Overview cũ đã bỏ;
dashboard của panel có sẵn `LowStockWidget`.

- **Báo hàng về:** `stock_notifications` + `BackInStockObserver` (bắt rollup tồn
  của Lunar chuyển từ 0 lên dương) → `BackInStockMail`. Hàng đợi xem ở
  `/panel/shop/stock-notifications` (chỉ đọc).

## Shipping
- Zone/rate DB-backed (`ShippingZone`: country + states → rate + free-threshold,
  most-specific-wins) qua `ShippingZoneResolver` + `FlatRateShippingModifier`, fallback
  config. Quản trị: `/panel/shop/shipping-zones` + tab cài đặt Vận chuyển.
- **Nhận tại cửa hàng phải bật cờ `collect` của Lunar.** `ShippingOption` có tham số
  `collect: bool`; `CreateShippingLine` đóng dấu nó vào `meta` của dòng phí ship, và
  fulfilment method `Collection` của Lunar đọc đúng chỗ đó để giành các dòng hàng của
  đơn. `PickupShippingModifier` để nguyên mặc định `false` nên method `Shipping` giành
  mất — đơn khách tự tới lấy vẫn vào panel như một kiện phải gửi, kèm nút giao hàng và
  nhập mã vận đơn. **Storefront không có triệu chứng nào**: giá vẫn 0, địa chỉ vẫn là
  cửa hàng, đơn vẫn đặt được. Lỗi chỉ tồn tại phía quản trị, nên không test nào của
  checkout thấy được — `PickupCheckoutTest` giờ khẳng định thẳng đơn có fulfilment
  `collection` và KHÔNG có `shipping`.

  Đây là cùng một lớp lỗi với `MediaSettings` và facet tìm kiếm: *ta ghi một chỗ, Lunar
  đọc một chỗ khác*. Cách tìm ra không phải đọc code của mình mà là đọc driver của Lunar
  xem nó lấy quyết định từ đâu.

## Analytics
- `AnalyticsService` (revenue/orders/AOV/monthly/top-products, MySQL-portable, đếm đúng
  paid statuses).
- Trên panel chỉ có **một widget**: luỹ kế + xu hướng 6 tháng. Phần còn lại của
  trang Sales Dashboard cũ — KPI, biểu đồ doanh thu, đơn gần đây, best-seller,
  sắp hết hàng — dashboard của Lunar 2.0 đã ship sẵn, nên dựng lại là làm trùng.
  Thứ nó thiếu là tầm nhìn xa hơn 90 ngày, và đó là lý do widget này tồn tại.

---

# Admin (`lunarphp/panel` — Inertia + Vue)

Panel Lunar có sẵn trang cho Catalog, Sales, Customers, Settings — **kế thừa, không
build lại**. Phục vụ 370 route dưới `/panel`; asset là bản biên dịch sẵn của vendor,
publish bằng `lunar:panel:install` (đã gắn vào `post-autoload-dump`).

**Phần admin riêng của dự án đã viết lại xong** (Fase 4 + 5 của
[đợt nâng 2.0](../guides/upgrade-lunar-2.0.md)). Điểm chốt: **25 trong 64 file
Filament đã xoá là panel lo sẵn** — sản phẩm, biến thể, collection, product type,
product option, attribute group, customer group, tag, thuế. Phần còn lại đi qua
điểm mở rộng chính thức, không fork:

| Cách | Dùng cho |
| --- | --- |
| Engine resource khai báo | 12 màn hình CRUD (Nội dung, RMA, vùng ship, bảng size, báo hàng về, duyệt đánh giá, nhật ký scheduler) |
| `SettingsGroup` | 8 trang cài đặt cũ + kích thước ảnh → một màn hình, 9 tab |
| `Slot` | Size & Fit chèn vào trang sửa sản phẩm chính chủ |
| `widgets()` | hai thẻ dashboard: luỹ kế 6 tháng, đơn giữ hàng quá lâu |
| Không làm | QueueWorkers → Horizon; MediaImageSizes → `media-library:regenerate` |

Chi tiết và các hợp đồng của panel: [panel-addon.md](panel-addon.md).


---

# SEO

Canonical, meta, OG/Twitter, robots (noindex trang riêng tư), schema.org
(Product/Offer/BreadcrumbList ở product; ItemList + BreadcrumbList ở collection),
`sitemap.xml` (`SitemapService` gom product/collection/CMS qua Lunar `Url`,
morph-alias-aware, cache 1h) + `robots.txt`. Storefront SSR Blade → crawlable.

---

# Test

**506 test / 2036 assertion, all green (2026-08-05)** — 75 file trong `tests/Feature/`,
chạy trên MySQL `lunar_testing` (app phụ thuộc JSON functions/facets — SQLite không
emulate được; các test cascade menu cũng cần đúng engine MySQL). `tests/TestCase` dùng
`RefreshDatabase`; trait `CreatesStorefrontData` seed base data + fixture
product/size-chart. Chạy: `php artisan test`.

Bao phủ: auth (register/login/logout + profile/password), cart (add/update/remove/
coupon), address book CRUD + ownership, checkout→order COD + API + order history/detail
+ cách ly theo customer, search + facets + suggest, size-chart + recommend-size, VNPay
(chữ ký + tamper + callback paid/idempotent/invalid), email (Mail::fake), Location
(provinces/wards), on-demand media conversion, token auth, recommendations, i18n,
product/collection page smoke render, SEO (sitemap + JSON-LD), facet price/brand +
recently-viewed, email branding (logo absolute URL + accent, chặn CSS injection),
fit history (kept/returned → size, between-sizes, cách ly theo customer), cart headless
(X-Cart-Token, không claim được cart của người khác), token policy (expiry/abilities/refresh),
oversell + release tồn kho, RMA (không trả 2 lần, không trả đơn chưa giao), notification,
gallery theo màu (shape ảnh SKU, giữ thứ tự, fallback khi SKU không có ảnh, **guard N+1**
trên `/api/v1/products?slugs=`), rebuild menu lồng nhau (self-cascade MySQL).

**Kỷ luật test:**
- **Mutation-check mọi guard**: tắt guard → test phải đỏ. Không đỏ = test không bảo vệ gì.
- Test guard phải chạy trên **dữ liệu như production**. Nếu fixture phải sửa một trường để
  guard hoạt động → hỏi ngay *production có trường đó không?* (bug `purchasable = always`
  lọt qua vì test tự set `in_stock`).
- ⚠️ **Luôn `php artisan optimize:clear` trước khi chạy test.** `config:cache` che các `<env>`
  trong `phpunit.xml` → `DB_DATABASE` trỏ về DB dev và `RefreshDatabase` **xoá sạch nó**.

⬜ **Còn thiếu:** `modules/<Name>/tests/` vẫn trống (toàn bộ 68 file ở `tests/Feature`);
chưa phủ phần thuần-JS (picture/srcset, search-panel, lookbook) — cần browser driver.

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
| 23 | 2026-07-24 | **Tách tồn thực khỏi hàng đã giữ** (học `ordered_inventories` của Bagisto, rút về 1 cột cho shop một kho). Đặt hàng nay **giữ** chứ không trừ: `quantity` = hàng trong kho (kiểm kê khớp), `committed` = đã bán chưa giao, `sellable = quantity - committed`. Hàng rời kho ở `dispatched` (`dispatched_at` chống trừ hai lần). Huỷ **trước** giao chỉ nhả giữ chỗ — cộng lại `quantity` sẽ **đẻ ra hàng không có thật**. Cảnh báo đơn đã thanh toán >3 ngày chưa giao | 450 test; mutation-check: bỏ trừ `committed` → 3 test đỏ, gồm cả guard oversell |
| 22 | 2026-07-23 | **Đồng bộ tài liệu với code** — gom `.md` vào `docs/` rồi rà lại từng khẳng định. Sửa những chỗ tài liệu **mô tả sai hệ thống**: layout module còn là bản tiền-v13, `ModulesServiceProvider` vẫn được mô tả là nơi nạp module, tầng SKU linh hoạt (purchasable thật) **hoàn toàn vắng mặt**, sổ cái tồn kho không được nhắc, và `theme.md` vẫn là *kế hoạch dựng theme* mô tả 3 Vue island chưa từng tồn tại + Tailwind trong khi theme chạy Bootstrap 5 + SCSS với 25 enhancer vanilla | Không đổi code; 432 test nguyên trạng |
| 23 | 2026-08-05 | **MediaPicker thành picker ảnh thật** — trước đó chọn ảnh là một `Select` chỉ hiện *tên file* (admin phải nhớ tên mới biết mình chọn đúng ảnh chưa), và nút "mở thư viện" `->url(..., newTab: true)` **ném admin sang tab khác**, rời khỏi form đang sửa dở. Nay: thumbnail thật + nút bỏ chọn/đổi thứ tự ngay trên field, và thư viện mở **trong modal** (lưới ảnh + tìm theo tên + lọc thư mục + phân trang + upload tại chỗ, upload xong tự chọn). Tách `MediaPickerField` (hiển thị) + `MediaBrowser` (lưới modal), `MediaPicker` giữ nguyên chữ ký factory nên **0 callsite phải sửa** (14 chỗ ở Content/Catalog/Theme). Query lưới + folder gom về `MediaLibraryService::browse()/folders()/preview()` — trang Media Library dùng chung, hết copy query | +15 test (506 tổng); mutation-check 2 guard: bỏ reset-page khi đổi filter → đỏ, bỏ lọc `type` → đỏ. **State không đổi** (vẫn là Asset id) nên VariantSwatch/VariantGallery/MigrateLegacyImages xanh nguyên trạng |
| 24 | 2026-08-27 | **Nâng Lunar 1.3 → 1.5 + Filament v3 → v4**, rồi dọn nợ đi kèm. Hai lỗi tìm ra là của upstream: migration đổi tên cột 2FA không chuyển mã giá trị (**khoá mọi staff bật 2FA ra khỏi admin**, mà khoá bằng cách 500 chứ không báo mã sai), và `translate()` trả chuỗi rỗng khi key locale tồn tại nhưng blank. **Gỡ được composer patch cuối cùng**: lý do cũ ("`HasTranslations` là trait nên `ModelManifest` không swap được") sai — không swap được *trait*, nhưng swap được *model dùng trait*. Nay là `SkipsEmptyTranslations` trên đúng 4 model có cột `name` JSON. `composer.json` bỏ 12 gói mà `lunarphp/lunar` tự kéo, giữ lại 5 gói code mình import trực tiếp | 544 test (baseline trước nâng cấp 506); `patches/` biến mất, `composer audit` 0 advisory |
| 25 | 2026-08-30 | **Bốn năng lực từ đợt rà soát nền tảng.** (a) *Nhận tại cửa hàng* — năng lực giao hàng duy nhất không cần hợp đồng hãng vận chuyển, nên gỡ được phần lớn giá trị của P0.5 đang bị chặn; Lunar bắt buộc có địa chỉ giao nên đơn mang địa chỉ CỬA HÀNG mà giữ tên + điện thoại khách. (b) *Dây bảo hiểm cho cron* — `orders:expire-abandoned` ngừng chạy thì tồn kho khoá vĩnh viễn, mà im lặng trông y hệt "không có đơn quá hạn". (c) *Điều hướng đáy + bộ lọc bottom-sheet* — không phải THÊM nav mà DỜI nav: header mobile từ 6 điểm chạm còn hamburger + logo; bất biến chống trùng lặp là **ghép cặp breakpoint**, không phải "mỗi đích đến một link" (SSR buộc render cả hai bộ). (d) *Báo cáo nội dung thiếu bản dịch* — locale rỗng là lỗi im lặng, không ném lỗi cũng không ghi log | 582 test (từ 544); chạy thử báo cáo phát hiện ngay 13 bản ghi thiếu tiếng Việt |
| 26 | 2026-09-09 | **Nâng Lunar 1.5 → 2.0.0-alpha.6, bỏ Filament, sang `lunarphp/panel`** (Fase 0→3; Fase 4 viết lại admin bằng Vue chưa bắt đầu — **hiện không có giao diện quản trị**). Ba thứ kế hoạch không lường được. (a) *`ModelManifest::replace()` biến mất, không có cái thay thế* — bản vá locale phải **hạ xuống tầng dữ liệu**: lọc locale rỗng lúc decode JSON thì `translate()` của upstream trở thành đúng, và vá rộng hơn cách cũ. (b) *`lunar:upgrade` ghi lại ledger đánh dấu toàn bộ baseline v2 là đã chạy* — nên cột nào 19 data migration bỏ sót thì **không bao giờ** được tạo nữa; tìm ra 3 cột thiếu bằng cách dựng baseline vào DB nháp rồi diff `information_schema`, cả 3 đều có code 2.0 đang đọc. (c) *`orders.status` bị xoá* — vòng đời 7 trạng thái thành **phái sinh** từ hai rollup + hai timestamp; không nơi nào set status nữa, và cái duy nhất bốn sự thật không phân biệt được (COD vs cổng thanh toán bỏ dở) phải đọc từ `meta.payment_type`, ghi cho mọi đơn + backfill đơn cũ | 560 test (baseline trước nâng cấp 597 — chênh lệch là test canh Filament đã xoá); panel phục vụ 370 route |

> **Quy tắc cho mọi refactor:** giải thích *why* trước khi viết code · composer patch
> là **bậc cuối** (thử hết extension point trước; nếu patch thì kèm PR upstream)
> và không sửa `vendor/` (package bên thứ ba) · public API (service + shape `/api/v1`) chỉ
> mở rộng tương thích ngược · `vendor/bin/phpunit` xanh + `pint --test` xanh **trên file đã
> sửa** (không phải toàn repo — xem standards §15) trước khi coi là xong · cập nhật tài liệu
> này (ngày tuyệt đối).
