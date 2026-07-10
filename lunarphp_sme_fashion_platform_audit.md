# SME Fashion — Audit nền tảng: API-first / Headless / Mobile / Omnichannel

> Khảo sát **codebase thực tế** (không suy đoán) ngày **2026-07-09**, đối chiếu
> [plan](lunarphp_sme_fashion_plan.md) · [coding standards](lunarphp_sme_fashion_coding_standards.md) ·
> [architecture review](lunarphp_sme_fashion_architecture_review.md).
> `SKILL-PLUGIN.md` **không tồn tại** trong repo.
>
> Baseline khi audit: **191 test / 596 assertion xanh**, `vendor/` sạch (không sửa),
> 12 module, 52 route `api/v1`, 1 channel (`webstore`).
>
> Mỗi phát hiện dưới đây đều **kiểm chứng được bằng lệnh**, không phải ý kiến. Những
> gì đã đúng thì ghi "giữ nguyên" — không đề xuất lại thứ đã có.

---

## 0. Tóm tắt điều hành

Nền tảng **đã** là modular monolith đúng triết lý: Lunar không bị sửa, mọi can thiệp
qua extension point chính chủ, controller mỏng, service là nguồn logic duy nhất,
error envelope thống nhất. Standards §15 (giới hạn dòng) **đạt tuyệt đối**.

Nhưng có **một rào chắn kiến trúc thật** cho Headless/Mobile/POS, và **một bug doanh thu**:

> **Cập nhật 2026-07-09:** Phase 1 hoàn thành — B, C, D, E, H + health-check (218 test xanh).
> **Cập nhật 2026-07-10:** Phase 2 hoàn thành — A, F, I, J, L (279 test xanh).
> **Cập nhật 2026-07-10:** Phase 3 hoàn thành — **G** (Notification). Rà soát ecommerce cốt lõi
> tìm + sửa **5 bug thật** (E1–E5, xem [mục riêng](#rà-soát-ecommerce-cốt-lõi--5-bug-thật-2026-07-10--✅-đã-sửa)).
> **347 test / 1502 assertion xanh.** Mọi phát hiện A–L đã đóng.

| # | Phát hiện | Bằng chứng | Mức | TT |
|---|---|---|---|---|
| **A** | **Cart/Checkout bị khoá vào session cookie** → app native / POS không dùng được | `CartSessionManager` inject `Illuminate\Session\SessionManager`; routes cart+checkout chạy group `storefront` | **P0** | ✅ |
| **B** | **COD (`payment-offline`) không bao giờ lên hạng thành viên** | `OrderPaid::dispatch` chỉ có ở VNPay + MoMo processor | **P0** | ✅ |
| **C** | **Hai định nghĩa "đã thanh toán"** | `analytics.paid_statuses` có `payment-offline`, `promotion.membership.paid_statuses` **không**; ngược lại có `'paid'` (status không tồn tại ở đâu khác) | **P0** | ✅ |
| **D** | **48/52 route `api/v1` không có rate limit** — gồm `POST /api/v1/checkout` | `throttleApi()` chỉ áp cho group `api`; cart/checkout/orders/customer chạy group `web`/`storefront` | **P0** | ✅ |
| **E** | **Hai `OrderResource` khác shape cho cùng entity** | `Order\...\OrderResource` (23 khoá) vs `Checkout\...\OrderResource` (9 khoá) | **P1** | ✅ |
| **F** | **Phân trang không thống nhất** | `/products`,`/search` → `meta{page,per_page,last_page,total}`; `/orders` → `{data,links,meta{current_page,…}}` (mặc định Laravel) | **P1** | ✅ |
| **G** | Không có tầng Notification (không bảng `notifications`, không `->notify()` thật) | mọi thông báo là Mailable; `notify()` trong `ReturnService` là method private | **P1** | ✅ |
| **H** | `GET /api/user` còn sót (không version, trả thẳng model User) | `routes/api.php:6` | **P2** | ✅ |
| **I** | Sanctum token **không hết hạn**, không abilities | `config/sanctum.php:53 'expiration' => null`; `createToken($name)` không truyền abilities | **P1** | ✅ |
| **J** | `SetApiLocale` chỉ áp group `api` → 26/52 route không resolve locale (đo lại bằng `gatherRouteMiddleware`) | route nào ở group `web`/`storefront` đều thiếu | **P2** | ✅ |
| **K** | Standards §10 mô tả module `Hook`/facade `Hook` **đã bị gỡ** | `modules/` không còn `Hook`; không có `Hook::` nào trong code | **P2** (tài liệu sai) | ✅ |
| **L** | **Body JSON lặp đôi trên mọi route `api/v1` khi Redis chết** — exception renderer fire lại lúc shutdown | tái hiện ở `/api/v1/products`: `{"message":"Server error."}{"message":"Server error."}` (54 byte); kernel trả đúng 27 | **P1** | ✅ |

> **Điểm mấu chốt:** A + B + C là *lỗi/nợ thật*, không phải thiếu abstraction. Sửa
> chúng mang lại giá trị lớn hơn mọi tầng kiến trúc mới cộng lại.

---

## 1. Nguyên tắc thẩm định đề xuất

Với mỗi layer/module mà đề bài liệt kê, tôi hỏi: **có code nào hiện tại đang đau vì
thiếu nó không?** Nếu không → **không đề xuất** (standards §16, architecture-review §3).

| Đề xuất trong đề bài | Kết luận | Lý do dựa trên code |
|---|---|---|
| Application Layer / UseCase / Action | ❌ **Không** | Service method nhỏ đã đóng vai action; **0 file > 500 dòng**, **0 controller > 100 dòng**. Thêm lớp = indirection thuần. (architecture-review §3.1 vẫn đúng) |
| Repository | ❌ **Không** | Lunar Eloquent đã là tầng data; **không có** Repository nào trong repo và cũng không có nhu cầu đổi store. |
| DTO | ✅ **Đã có, mở rộng nhẹ** | `Catalog/Data/{SearchQuery,SearchResult}`, `Checkout/Data/{VNPayResult,MoMoResult,RefundResult}`. Đủ ở ranh giới cần. |
| ViewModel / Presenter | ❌ **Không** | Blade đã sạch (0 `app()`/`DB::` trong theme); API Resource **chính là** presenter. |
| Domain Events | ✅ **Mở rộng có mục đích** | `OrderPaid` đã có **2 consumer** (email + membership) → mẫu đã chứng minh. Cần thêm **đúng 1** event (`OrderPaid` cho COD) vì đã có consumer. Không tạo event "phòng xa". |
| Notification Center | ✅ **Có** (khi làm Mobile) | Hiện **0** hạ tầng notification. Push mobile bắt buộc phải có. |
| BFF | ❌ **Không** | 1 storefront + 1 app; `/api/v1` đã là contract chung. BFF khi có ≥ 2 client mâu thuẫn nhau về shape. |
| Driver Pattern | ✅ **Đã có** | `SearchEngine` + `DatabaseSearchEngine`; payment qua `Payments::extend`; shipping qua `ShippingModifiers`. |
| Strategy Pattern | ✅ **Đã có** | `RecommendationStrategy` + `Association`/`CoPurchase`/`Collection`. |
| Repository/Hook/Plugin engine | ❌ **Không** | Đã cố ý gỡ khi gộp 24→12 module. |

---

## 2. Domain Modules

## Current
12 module (11 feature + `Core` hạ tầng), namespace `Modules\<Name>`, provider riêng,
`vendor/` **sạch**. Extension point Lunar dùng đúng: `Payments::extend` (vnpay, momo),
`Discounts::addType`, `ShippingModifiers->add`, `resolveRelationUsing`
(`Product::material`, `Product::sizeChart`, `Customer::measurement`), pipeline stage
`DecrementStock`, `LunarPanel::extensions`, config override (4 file).
`ModelManifest::replace` **chưa dùng** — đúng, chưa cần.

## Gap
- **0 thư mục `modules/*/Tests`** — toàn bộ 34 file test nằm ở `tests/Feature` (todo #13).
- Chỉ **1** domain event (`OrderPaid`), **0** Policy, **0** Notification.
- `Content` và `Inventory` expose API **không qua `JsonResource`** (vi phạm standards §6).

## Recommendation
1. Giữ nguyên cấu trúc — nó đúng. **Không** dựng `app/Domain|Application|Infrastructure`.
2. Thêm `modules/<Name>/Tests` **dần theo module bị chạm**, không làm một lượt.
3. Không tạo Policy: authorization hiện làm bằng **query scoping**
   (`OrderService::findForCustomer($id, $customerId)`) — an toàn, đã chặn IDOR. Chỉ thêm
   Policy nếu xuất hiện resource có nhiều actor (staff + customer) trên cùng endpoint.

## Priority P2 · Complexity S · Breaking Change NO

---

## 3. API / Headless

## Current
- 52 route `api/v1`, tự prefix version, Sanctum (cookie SPA + PAT).
- **Error contract chuẩn và tập trung**: `bootstrap/app.php` map 401/403/404/HTTP/500 →
  `{message, errors?}` cho mọi `api/v1/*` bất kể `Accept`. **Không leak internal ở 500.**
  → Đây là phần **làm tốt nhất** của repo, giữ nguyên.
- Success envelope `{data, meta?}`; SSR hydrate đúng shape Resource (một contract).

## Gap (đo được)

**G1 — Cart/Checkout không headless được (chặn Mobile + POS).**
`Lunar\Managers\CartSessionManager` phụ thuộc `Illuminate\Session\SessionManager`;
route cart/checkout chạy middleware group `storefront` (= `web` + locale + Lunar session).
Client Bearer-token **không có session** → không giữ được giỏ.

**G2 — 48/52 route `api/v1` không rate limit**, trong đó có `POST /api/v1/checkout`
(middleware chỉ `storefront`). `throttleApi()` chỉ gắn vào group `api`; các route đăng ký
trong group `web`/`storefront` **không** thừa hưởng. Deploy doc mô tả "throttle:api toàn
nhóm api" — đúng chữ, nhưng **đa số route không nằm trong nhóm đó**.

**G3 — Phân trang 2 chuẩn.** `{page,per_page,last_page,total}` (products/search/collection)
vs `{data,links,meta{current_page,from,to,path,links,…}}` (orders). Client không dùng
chung một parser.

**G4 — `OrderResource` trùng tên, khác shape** (9 khoá ở Checkout vs 23 khoá ở Order).

**G5 — Không `JsonResource`** ở `Content` (pages, banners) và `Inventory`; `ReviewController`
build mảng inline trong controller.

**G6 — Không filtering/sorting chuẩn hoá** ngoài `SearchQuery` của Catalog; `/orders`,
`/promotions`, `/pages` không có filter/sort/pagination param.

**G7 — `GET /api/user`** (routes/api.php) trả thẳng model `User`, không version.

**G8 — Token vô hạn, không scope.** `sanctum.expiration = null`; `createToken($device)`
không truyền abilities → token app = toàn quyền, không thu hồi được theo phạm vi.

## Recommendation

| Việc | Cách làm (Lunar-native, không sửa vendor) |
|---|---|
| **Cart headless** | Bind lại `Lunar\Base\CartSessionInterface` (Lunar **đã** đăng ký nó bằng `$app->singleton(...)` tại `LunarServiceProvider:150`) sang `TokenAwareCartSession` trong `Checkout` provider: có session → hành vi cũ; có Bearer token → resolve cart theo `cart_id` gắn `user_id`/header `X-Cart-Token`. **Zero thay đổi caller** (`CartService` vẫn gọi facade `CartSession`). |
| **Rate limit** | Định nghĩa limiter `api` cho **mọi** route `api/v1` bằng một middleware group riêng, không dựa vào `throttleApi()`. Thêm `throttle:checkout` chặt hơn cho `POST /api/v1/checkout`. |
| **Pagination** | Một `ApiPaginator` trait/Resource `->additional()` trả **duy nhất** `meta{page,per_page,last_page,total}` cho mọi list endpoint. Đây là **breaking change có kiểm soát** cho `/orders` (xem cột dưới). |
| **OrderResource** | Xoá `Checkout\...\OrderResource`, dùng `Order\...\OrderResource` (superset). Contract giàu hơn = tương thích ngược cho client đọc 9 khoá cũ. |
| **Resource hoá** | `PageResource`, `BannerResource`, `ReviewResource`. |
| **Filtering/Sorting** | Chuẩn `?sort=-created_at&filter[status]=…&page[size]=` áp cho list endpoint; tái dùng `SearchQuery` DTO làm mẫu. |
| **Xoá `/api/user`** | Xoá route; `/api/v1/customer` đã thay thế. |
| **Token** | `sanctum.expiration` (vd 60 ngày) + abilities (`cart:*`, `order:read`, `pos:*`) + `POST /auth/token/refresh`. |

## Priority
- G1 **P0** · G2 **P0** · G4 **P1** · G3 **P1** · G8 **P1** · G5 **P2** · G6 **P2** · G7 **P2**

## Estimated Complexity
G1 **L** · G2 **S** · G3 **S** · G4 **XS** · G5 **S** · G6 **M** · G7 **XS** · G8 **S**

## Breaking Change
- G1 **NO** (thêm đường token, giữ session)
- G2 **NO** · G4 **NO** (superset) · G5 **NO** · G7 **NO** (route rác)
- **G3 YES** cho `/api/v1/orders` (đổi `meta`) → phát hành ở `v2` hoặc thêm `meta` phẳng
  song song rồi bỏ `links` sau 1 nhịp.
- **G8 YES** nếu bật expiration cho token đã phát hành → phát hành kèm `/token/refresh`.

---

## 4. Business logic / Correctness (nợ thật, không phải kiến trúc)

## Current
`OrderPaid` là domain event duy nhất, có **2 consumer**: `SendOrderPaidEmail` (Order) và
membership sync (Promotion). Dispatch từ `VNPayPaymentProcessor:89` và
`MoMoPaymentProcessor:88`.

## Gap
**Bug B — COD không lên hạng.** `OrderPaid` **không** được dispatch ở luồng
offline/COD. Khách COD (kênh thanh toán phổ biến nhất của SME Việt) không bao giờ
được `MembershipService::syncCustomer` → không lên Silver/Gold.

**Bug C — hai định nghĩa "đã thanh toán".**
```
analytics.paid_statuses            = [payment-offline, payment-received, dispatched, completed]
promotion.membership.paid_statuses = [payment-received, paid,            completed, dispatched]
```
`payment-offline` có ở Analytics (tính doanh thu) nhưng **thiếu** ở membership (không
tính chi tiêu). `'paid'` chỉ tồn tại trong config membership — **không status Lunar nào
tên `paid`** (`config/lunar/orders.php` dùng `payment-received`). Plan tuyên bố
"paid statuses đọc từ `analytics.paid_statuses` (single source)" — thực tế **không**.

Hệ quả kép: doanh thu và hạng thành viên **lệch nhau** cho mọi đơn COD.

## Recommendation
1. Dispatch `OrderPaid` cho luồng offline khi order chuyển sang `payment-offline`
   (Observer đã theo dõi `status` dirty — thêm nhánh dispatch, **không** thêm bảng).
   Consumer đã tồn tại → đúng ngưỡng "thêm event khi có consumer".
2. **Một nguồn duy nhất** cho paid statuses: `MembershipService` đọc
   `config('analytics.paid_statuses')` (đúng như `CoPurchaseStrategy` và
   `FitHistoryService` đang làm). Bỏ `promotion.membership.paid_statuses`.
3. Test hồi quy: đơn COD → membership sync; `paid_statuses` chỉ còn một nơi định nghĩa.

## Priority P0 · Complexity S · Breaking Change NO
> (Khách COD sẽ **lên hạng** sau khi sửa — thay đổi dữ liệu mong muốn, nên chạy
> `syncCustomer` backfill một lần.)

---

## 5. Mobile App (Flutter / React Native)

## Current
Đủ cho **đọc**: catalog, search + facet + suggest, collection, product + size chart +
recommend-size (+ `fit_history`), reviews (đọc), promotions, membership, wishlist
(server-side), locations, auth PAT (`/auth/token`, `/token/register`, `/token/revoke`).

## Gap
1. **Cart + Checkout không dùng được bằng token** (G1) — chặn toàn bộ luồng mua.
2. **Không có push notification**: `notifications` table **MISSING**, không `->notify()`
   thật, không device-token registry. Toàn bộ thông báo là email.
3. **Recently-viewed chỉ ở `localStorage`** (`themes/fashion/js/enhance/recently-viewed.js`)
   → không đồng bộ đa thiết bị.
4. **Không Home Feed endpoint**: home SSR render 8 section từ `SectionBuilder`; app phải
   tự ghép ~5 request.
5. **Order timeline**: không có endpoint; `status` trả **raw handle** (`payment-received`),
   không nhãn i18n.
6. Token vô hạn, không abilities (G8).

## Recommendation
| API bổ sung | Ghi chú |
|---|---|
| Sửa **G1** trước | Không có nó thì mọi thứ dưới đây vô nghĩa. |
| `GET /api/v1/home-feed` | Trả **đúng** cấu trúc section hiện có (`SectionRenderer`), không tạo nội dung mới. |
| `GET /api/v1/orders/{id}/timeline` | **Đọc `activity_log` của Lunar** — `Lunar\Models\Order` đã `use LogsActivity` và bảng `activity_log` **đã tồn tại**. ⚠️ **Không** tạo bảng `order_timeline` mới. |
| `GET/POST /api/v1/customer/recently-viewed` | Nâng recently-viewed lên server (đồng bộ đa thiết bị). Bảng nhỏ hoặc cột JSON trên customer. |
| `POST /api/v1/devices` + Notification | Xem §6. |
| `status_label` trong `OrderResource` | i18n hoá nhãn (todo #6 còn treo). |
| Coupon/Wishlist/Membership/Search-suggest | ✅ **Đã có** — không làm lại. |
| Wallet / Loyalty points | ❌ Chưa cần (xem §8). |

## Priority
G1 **P0** · Notification **P1** · timeline **P1** · home-feed **P2** · recently-viewed **P2** · status_label **P2**

## Estimated Complexity
G1 **L** · Notification **M** · timeline **S** · home-feed **S** · recently-viewed **S**

## Breaking Change NO (đều additive)

---

## 6. Notification Center

## Current
**Không tồn tại.** Kiểm chứng: bảng `notifications` **MISSING**; không có lời gọi
`->notify()` nào tới Laravel Notification (các `notify()` bắt gặp là **method private**
trong `ReturnService` và `BackInStockNotifier`). Mọi thông báo là **Mailable queued**
(`OrderConfirmationMail`, `OrderPaidMail`, `OrderStatusUpdatedMail`, `ReturnStatusMail`).

## Gap
Email-only. Mobile app không có kênh nhận. Không có device-token registry, không có
lịch sử thông báo in-app, không có preference (opt-out) của khách.

## Recommendation
Module **`Notification`** mỏng (đây là module mới **có lý do**, khác với ERP/Loyalty rỗng):
- `php artisan notifications:table` (Laravel-native, không tự chế).
- `Notifiable` trên `Customer`; channel `mail` (tái dùng Mailable đang có) + `database`
  (in-app) + `fcm` (push) sau một `interface PushChannel` → provider swappable.
- Chuyển 4 Mailable hiện tại thành `Notification` với `via()` = mail (+ database/push).
  **Giữ nguyên template + i18n + locale-stamping** đang chạy.
- `GET /api/v1/notifications`, `POST /api/v1/notifications/{id}/read`, `POST /api/v1/devices`.

Không gọi là "Notification Center" cồng kềnh — chỉ là Laravel Notification chuẩn.

## Priority P1 · Complexity M · Breaking Change NO

---

## 7. Đánh giá theo Domain (SME Fashion)

| Domain | Current | Đánh giá |
|---|---|---|
| **Product** | Lunar + `resolveRelationUsing` (material, sizeChart), variant deep-link SSR, `ProductResource` | ✅ Mạnh |
| **Collection** | `CollectionService`, SSR + facet, JSON-LD | ✅ Đủ |
| **Search** | `SearchEngine` interface + `DatabaseSearchEngine`, facet size/color/brand/price/material/availability | ✅ Đúng abstraction (Scout = đổi driver, không đổi caller) |
| **Recommendation** | `RecommendationStrategy` chain: Association → CoPurchase → Collection | ✅ Mạnh, đúng Strategy |
| **Review** | Model + service + API | ⚠️ **Yếu**: không `JsonResource`, không phân trang, shape build inline ở controller |
| **Wishlist** | Server-side (`WishlistItem`) | ✅ Đủ (mobile-ready) |
| **Cart** | Lunar CartSession qua `CartService` | 🔴 **Chặn headless** (G1) |
| **Checkout** | Pipeline Lunar + `CheckoutService`, 1 form SSR POST | 🔴 Cùng vấn đề G1 + không rate limit |
| **Promotion** | `Discounts::addType` (QuantityPercentageOff, ComboPercentageOff), flash sale, membership | ⚠️ Bug B/C (COD + paid_statuses) |
| **Inventory** | Stock per-variant, `DecrementStock` pipeline, notify-me | ⚠️ Không `JsonResource`; ✅ logic đúng |
| **Shipping** | `ShippingZone` DB + `FlatRateShippingModifier` | ✅ Đủ |
| **Order** | `OrderService`, RMA/returns, invoice PDF, 4 mailable | ⚠️ 2 `OrderResource`; `status` raw; không timeline API |
| **Customer** | Auth cookie+PAT, address book, measurements | ✅ Đủ |
| **Membership** | `MembershipService` → Lunar `CustomerGroup` | 🔴 Bug B (COD) |
| **Analytics** | `AnalyticsService` + Filament dashboard | ✅ Đủ; mở rộng top size/màu khi cần |
| **Theme** | `themes/fashion` thuần Blade+JS+CSS | ✅ Sạch (0 `app()`/`DB::`) |
| **CMS/Content** | Page/Banner/Lookbook/Menu/PageSection | ⚠️ API không Resource, không phân trang |
| **Lookbook** | Hotspot shoppable | ✅ Đủ |
| **Brand** | Lunar native | ✅ Đủ |
| **Assets** | On-demand conversion, queued jobs | ✅ Mạnh |

---

## 8. Đề xuất Module mới — **chỉ 1**

## Current
12 module. `Loyalty` = membership (đã ở `Promotion`, đúng chỗ vì là biến thể discount).
`Marketing` = `Promotion` + `Content`. `Return/Exchange` = **đã có** (`ReturnRequest`,
`ReturnService`, `ReturnRequestResource`). Lunar **không** ship GiftCard/Wallet/Loyalty
(kiểm chứng: 0 file).

## Gap / Recommendation

| Module | Quyết định | Lý do |
|---|---|---|
| **Notification** | ✅ **Làm** (P1) | Có consumer thật: mobile push + in-app. Xem §6. |
| Return / Exchange | ❌ Đã có | `ReturnService` + RMA đầy đủ. |
| Loyalty | ❌ Không | Đã là `MembershipService`. Điểm thưởng (points) chưa có nhu cầu SME. |
| Wallet / Gift Card | ❌ **Chưa** | Không có yêu cầu thật; là *tiền* → cần kế toán/đối soát. Làm khi có nghiệp vụ, không làm "phòng xa". |
| Customer Insight | ⚠️ **Đổi**: read-model, không phải module | Ban đầu đánh giá "không cần". [Điều chỉnh §3](#a3--customer-insight-read-model-không-phải-module) giữ mục tiêu nhưng làm dưới dạng **service read-model** trong `Customer` — dữ liệu **đã suy ra được** từ bảng hiện có, **không cần schema mới**. |
| Campaign / Marketing / Affiliate | ❌ Không | Chưa có consumer. |
| OMS / Warehouse / ERP / POS Connector | ❌ **Chưa tạo module rỗng** | Mẫu đã sẵn (listener → queued job sau contract). Tạo khi có hệ thống thật để nối. |
| Gift Wrap | ❌ Không | Là một line item / custom field, không phải module. |
| AI | ⚠️ Xem §9 | Chỉ khi có use-case sinh lời. |

## Priority: Notification **P1** · còn lại **P3 / không làm**
## Complexity: Notification **M** · Breaking Change **NO**

---

## 9. AI Provider

## Current
**Không có gì.** (0 file OpenAI/Anthropic/Gemini/Ollama.) Standards §16 ghi rõ:
"AI/vector recommendations — **KHÔNG build sớm**; với SME co-purchase + curate tay là đủ ROI."
`CoPurchaseStrategy` đang chạy tốt và **realtime** (không cần job tổng hợp).

## Gap
Không phải gap — là **quyết định có chủ đích** đang còn hiệu lực.

## Recommendation
**Chưa build driver.** Theo [điều chỉnh sau audit §2](#a2--ai-provider-contract-trước-driver-sau):
chuẩn bị **contract `AiProvider` + DTO**, **không** kèm driver nào. Khi có use-case sinh
lời thật (viết mô tả sản phẩm hàng loạt, phân loại ảnh, hỗ trợ CSKH), thêm driver theo
**đúng mẫu đã có trong repo** — giống hệt `SearchEngine`:

```
modules/AI/
├── Contracts/AiProvider.php        # generate(prompt, opts): AiResult
├── Drivers/{Claude,OpenAI,Gemini,Ollama}Provider.php
├── Data/AiResult.php               # DTO bất biến
└── Config/ai.php                   # 'driver' => env('AI_DRIVER', 'claude')
```

Ràng buộc: **provider-agnostic** (không rò `openai` ra caller), gọi trong **queued job**
(không chặn request), cache theo hash prompt, ngân sách token/ngày. Caller chỉ biết
`AiProvider` — đổi provider = đổi config, **zero** thay đổi caller (đúng bài học từ
`SearchEngine`).

**Không** đưa AI vào recommendation trước khi CoPurchase hết dư địa.

## Priority P3 · Complexity M · Breaking Change NO

---

## 10. Omnichannel / POS

## Current
Lunar **đã** hỗ trợ multi-channel native — nhưng repo chỉ có **1 channel** (`webstore`),
và chỉ 7 chỗ chạm `Channel::`. Nền tảng có sẵn, chưa dùng.

## Gap
- POS cần: cart theo token (G1), staff auth + abilities (G8), channel `pos`,
  giá/tồn theo channel.
- Không có endpoint tra cứu khách/đơn tại quầy.

## Recommendation
Không tạo "POS Connector" module. Thứ tự **bắt buộc**:
1. **G1** (cart token) — nếu không, POS không tồn tại.
2. **G8** (token abilities) — phân quyền staff vs customer.
3. Thêm `Channel` `pos` (Lunar native) + `?channel=` trong `SetApiLocale`-style middleware.
4. Chỉ khi 1–3 xong mới bàn tới đồng bộ tồn kho đa kho (Warehouse).

## Priority P2 (sau P0) · Complexity L · Breaking Change NO

---

## 11. Tài liệu (nợ nhỏ nhưng gây hiểu sai)

| Tài liệu | Sai gì | Sửa |
|---|---|---|
| `coding_standards.md` §10 | Mô tả module `Hook` + facade `Hook::applyFilters` — **đã gỡ** khi gộp 24→12 module | Viết lại: cross-module = **service công khai + domain event** |
| `coding_standards.md` §2 | Liệt kê 24 module cũ (Product, Cart, Payment, Hook…) | Cập nhật còn 12 |
| `plan.md` | "paid statuses qua `analytics.paid_statuses` (single source)" | Chưa đúng cho membership (Bug C) — sửa code, rồi câu này mới đúng |
| `deploy.md` | "throttle:api 120/phút **toàn nhóm api**" | Đúng chữ, sai ngụ ý: 48/52 route `api/v1` **không** ở nhóm đó |

## Priority P2 · Complexity XS · Breaking Change NO

---

# ROADMAP

> Nguyên tắc: **sửa nợ trước, mở rộng sau.** Mỗi phase kết thúc bằng
> `vendor/bin/pint` + `vendor/bin/phpunit` xanh (baseline hiện tại 191 test).

## Phase 1 — Hoàn thiện nền tảng (P0, không breaking) — ✅ **HOÀN THÀNH 2026-07-09**

> Baseline trước: 191 test. Sau: **218 test / 1078 assertion xanh**, `pint` sạch,
> `config:cache`/`route:cache`/`event:cache`/`view:cache` chạy được, `vendor/` không đụng.

| # | Việc | Complexity | Breaking | Trạng thái |
|---|---|---|---|---|
| 1.1 | **Một nguồn `paid_statuses`**: `MembershipService` đọc `analytics.paid_statuses`; bỏ `promotion.membership.paid_statuses` | S | NO | ✅ |
| 1.2 | **Dispatch `OrderPaid` cho COD/offline** + backfill `syncCustomer` một lần | S | NO | ✅ |
| 1.3 | **Rate limit toàn bộ `api/v1`** (không dựa `throttleApi()`), thêm `throttle:checkout` | S | NO | ✅ |
| 1.4 | Xoá `GET /api/user` | XS | NO | ✅ |
| 1.5 | Gộp 2 `OrderResource` → dùng bản superset | XS | NO | ✅ |
| A.6 #1 | Health check thật (DB/cache/queue → 503) | XS | NO | ✅ |
| 1.6 | Cập nhật tài liệu ở §11 | XS | NO | ✅ |

### Đã làm gì (chi tiết)

**1.1 + 1.2 — Bug doanh thu COD.** `MembershipService::lifetimeSpend()` nay đọc
`config('analytics.paid_statuses')` (cùng nguồn với `AnalyticsService`,
`CoPurchaseStrategy`, `FitHistoryService`); khoá `promotion.membership.paid_statuses`
đã xoá. `OrderPaid` được bắn cho COD qua listener mới
`Order\Listeners\DispatchOrderPaidForOfflineOrder` — hook vào `PaymentAttemptEvent`
(sự kiện chính chủ Lunar mà `OfflinePayment::authorize()` bắn), **gate theo
paid_statuses** nên:

| Loại | status khi authorize | có bắn `OrderPaid`? |
|---|---|---|
| `cod` | `payment-offline` | ✅ (nằm trong paid_statuses) |
| `bank-transfer` | `awaiting-payment` | ❌ (chờ xác nhận tay) |
| `vnpay` / `momo` | `awaiting-payment` | ❌ ở authorize; callback tự dispatch sau khi capture |

→ **không double-dispatch** với gateway.

> ⚠️ **Quyết định nghiệp vụ:** `OrderPaid` nghĩa là *"được tính là đã thanh toán"*
> (chi tiêu + doanh thu), **không** phải *"đã nhận tiền"*. Khách COD trả tiền khi giao
> hàng, nên `SendOrderPaidEmail` nay chỉ gửi mail "Số tiền đã thanh toán" khi
> `status === payment-received` (gateway capture thật). COD lên hạng nhưng **không**
> nhận mail đó.

Kèm command idempotent `php artisan membership:backfill [--dry-run]` để settle khách cũ.

**1.3 — Rate limit.** `Modules\Core\Http\Middleware\ThrottleApiV1` prepend vào global
stack, guard theo **URI** (`api/v1/*`) chứ không theo middleware group → route mới của
module bất kỳ được phủ mặc định, không thể quên. Delegate sang `ThrottleRequests` của
Laravel nên header `X-RateLimit-*` + 429 giữ nguyên. Thêm limiter `checkout` (10/phút)
cho `POST /api/v1/checkout`. **`throttleApi()` đã gỡ** khỏi `bootstrap/app.php`.
`api/v1/health` được **miễn trừ** (xem dưới).

**A.6 #1 — Health check thật.** `HealthController` probe DB + cache (round-trip
put/pull) + queue, trả **503 `degraded`** khi bất kỳ cái nào hỏng; mỗi probe độc lập
(một cái chết không che cái khác) và chỉ lộ **tên class exception** (message của PDO
chứa DSN + mật khẩu).

> 🔑 **Phát hiện khi verify thật (tắt Redis):** route `health` ban đầu vẫn **500** chứ
> không 503 — vì cả `ThrottleApiV1` (limiter cache-backed) lẫn `SetApiLocale` →
> `LocaleService` → `ThemeSettings` đều **đọc cache trước khi tới controller**. Một
> probe phải sống sót đúng lúc hạ tầng nó cần báo cáo đang chết. → `health` nay chạy
> **không middleware** (tách khỏi group `api`) và được ThrottleApiV1 miễn trừ. Verify
> end-to-end với Redis tắt: `HTTP 503`, `database.ok=true`,
> `cache.error=RedisException`, `queue.error=RedisException`.

**Nợ phát hiện thêm (ngoài phạm vi Phase 1, chưa sửa):** khi Redis chết, **mọi** route
`api/v1` trả body JSON **lặp đôi** (`{...}{"message":"Server error."}`) — exception
renderer trong `bootstrap/app.php` fire thêm một lần lúc shutdown. Kernel trả đúng 167
byte; phần thừa sinh ra sau khi response đã gửi. Có sẵn trước Phase 1 (tái hiện được ở
`/api/v1/products`), không do thay đổi này. → nên xử lý ở Phase 2.

**Kết quả:** hết bug doanh thu COD, hết lỗ hổng rate-limit (48/52 route → 0), contract
order thống nhất, health probe không còn nói dối.

## Phase 2 — Headless — ✅ **HOÀN THÀNH 2026-07-10**

> Baseline trước: 218 test. Sau: **279 test / 1258 assertion xanh**, `pint` sạch,
> `config:cache`/`route:cache`/`event:cache`/`view:cache` chạy được, `vendor/` không đụng.
> Verify end-to-end trên server thật: guest app đặt được đơn COD **không cookie, không session, không CSRF token**.

| # | Việc | Complexity | Breaking | TT |
|---|---|---|---|---|
| 2.1 | **`TokenAwareCartSession`**: rebind `CartSessionInterface` (Lunar singleton) — cart/checkout chạy được bằng Bearer token | **L** | NO | ✅ |
| L | Body JSON lặp đôi khi Redis chết | S | NO | ✅ |
| 2.6 | `SetApiLocale` cho **mọi** route `api/v1` (kể cả nhóm `web`) | XS | NO | ✅ |
| 2.3 | Chuẩn hoá pagination `meta{page,per_page,last_page,total}` | S | NO¹ | ✅ |
| 2.4 | `PageResource` / `BannerResource` / `ReviewResource` + phân trang | S | NO | ✅ |
| 2.2 | Token `expires_at` + abilities + `/auth/token/refresh` | S | NO² | ✅ |
| 2.5 | Page params trên order endpoints + clamp dùng chung | S | NO | ✅ |

¹ `/orders` bỏ `links` + đổi `meta` — **không có consumer nào** (JS/test) đọc chúng, đã kiểm chứng.
² **Không** bật `sanctum.expiration` (nó so với `created_at` → giết mọi token đã phát hành).
Thay vào đó stamp `expires_at` **từng token lúc phát hành**; token cũ (`abilities:['*']`, không
`expires_at`) vẫn chạy — có test `test_a_legacy_wildcard_token_keeps_working`.

### Đã làm gì (chi tiết)

**2.1 — Cart/checkout headless.** Đây là rào chắn thật, và nguyên nhân sâu hơn audit mô tả:

| Triệu chứng | Nguyên nhân |
|---|---|
| Mỗi request token tạo **một cart mới** (đo được: 75→76→77) | `CartSessionManager::fetchOrCreate()` đọc session key (rỗng), rồi fallback `authManager->user()` — nhưng `AuthManager::user()` dùng guard mặc định `web` (session-backed) nên **không thấy Bearer token** → nhánh "cart của user" **không bao giờ chạy** |
| Mọi POST/PATCH/DELETE trả **419** | cart/checkout nằm group `storefront` (= `web`) nên thừa hưởng CSRF |

Sửa bằng **extension point chính chủ**: Lunar đăng ký `CartSessionInterface` bằng
`$app->singleton(...)` → rebind sang `TokenAwareCartSession extends CartSessionManager`,
chỉ override chỗ đụng session. Request có session đi **nguyên đường Lunar cũ**.

Ba tín hiệu "headless" (gom một chỗ `TokenAwareCartSession::isStatelessRequest()` để CSRF
middleware và cart session **không thể bất đồng**):
- `Authorization: Bearer …` — app đã đăng nhập / POS;
- `X-Cart-Token: …` — guest lấy lại giỏ (cột `lunar_carts.public_token`, nullable → web cart không có);
- `X-Client: app` — **lần gọi đầu** của guest, khi chưa có handle nào để gửi.

CSRF: `VerifyCsrfTokenUnlessStateless` (thay `ValidateCsrfToken` trong group `web`) miễn trừ
request stateless. Lý do an toàn: request đó **không mang credential ngầm** (browser không tự
gắn `Authorization` cross-site; `X-Cart-Token` là custom header → phải qua CORS preflight, và
`cors.supports_credentials=false`). **Khách đã đăng nhập bằng cookie thì không bao giờ được
miễn trừ** — nếu không, một trang cross-site chỉ cần thêm header là bỏ qua được CSRF.

**Bảo mật:** cart có `user_id` **không** claim được bằng handle. Test
`test_a_users_cart_cannot_be_claimed_by_its_handle_alone` bắt được đúng lỗi này khi viết
(mutation-check xác nhận nó cắn).

**L — body JSON lặp đôi.** `HandleExceptions::renderHttpResponse()` gọi `->send()` **không
kiểm tra `headers_sent()`**. Khi Redis chết: request fail → gửi 27 byte; rồi shutdown ném
`RedisException` thứ hai → renderer chạy **lần nữa** → nối thêm body. Đo được 2 lần gọi
renderer (`headers_sent=0` rồi `=1`). Sửa: tách `Modules\Core\Support\ApiErrorResponse` (test
được, vì `headers_sent()` không giả lập được trong phpunit) và **trả body rỗng** khi response
đã gửi. `/api/v1/products` với Redis tắt: **54 byte → 27 byte**, JSON hợp lệ.

**2.6 — locale.** `pushMiddlewareToGroup('api', …)` chỉ phủ **17/52** route. Đẩy vào **cả**
`api` lẫn `web`, guard theo URI `api/v1/*` (no-op cho trang HTML). Phát hiện thêm:
`SetStorefrontLocale` chạy **sau** nên **ghi đè** `?locale=` — đã cho nó bỏ qua `api/v1/*`.
Nay **51/52** (trừ `health`, cố ý không middleware). Lựa chọn ngôn ngữ khách bấm trên UI
(session) **vẫn thắng** `?locale=` — có test.

**2.3 — pagination + 2 bug thật lòi ra khi viết test:**
- 🔴 **`total`/`last_page` sai trên mọi trang > 1.** `DatabaseSearchEngine` gọi
  `(clone $builder)->count()` **sau** `forPage()` — mà `forPage()` đặt LIMIT/OFFSET lên builder,
  nên clone chỉ đếm số dòng của trang hiện tại. Đo: `count()` = **0** thay vì 55. Pager của
  collection/search hỏng. Sửa: đếm **trước** khi phân trang.
- 🔴 **`Accept: */*` → 500 thay vì 401.** Laravel mặc định
  `redirectGuestsTo(fn () => route('login'))` và `Authenticate` chỉ bỏ qua redirect khi
  `expectsJson()`. Client gửi `Accept: */*` (mặc định của curl và nhiều HTTP lib) bị đẩy sang
  trang login HTML — mà route tên `login` **không tồn tại** (ở đây là `storefront.login`) →
  `RouteNotFoundException` → 500. Sửa: `redirectGuestsTo` trả `null` cho `api/v1/*`.
  Nay cả ba `Accept` đều `401 {"message":"Unauthenticated."}`; trang web vẫn redirect.

**2.2 — token.** `expires_at` **per-token** (không bật `sanctum.expiration`, xem ² ở trên) +
ability `customer:*` + `POST /auth/token/refresh` (thu hồi token cũ, giữ `device_name`).

> ⚠️ **Không dùng `abilities:` của Sanctum.** `CheckAbilities` ném `AuthenticationException`
> cho **bất kỳ** user không có `currentAccessToken()` — làm request cookie-session bình thường
> hoá 401 (bắt được qua `AddressBookTest` đỏ). Viết `EnsureTokenAbility` thay thế: chỉ ràng
> buộc **bearer token**, cookie session đi qua nguyên vẹn (nó đã có cookie + CSRF).

**2.5 — page params.** `/orders` và `/customer/orders` **quảng cáo `last_page` nhưng bỏ qua
`?page=`** — client thấy có 3 trang mà không lấy được trang 2. Nay dùng
`ApiPagination::page()/perPage()` (clamp `[1, 100]`).

> **Không** đổi sang JSON:API (`?filter[x]=`, `?page[size]=`) như audit gợi ý ban đầu: Catalog
> **đã có** `?q=`, `?filters[size][]=`, `?sort=newest`, `?page=`, `?per_page=` chạy đúng và
> storefront SSR + JS phụ thuộc vào chúng. Thêm cách viết thứ hai = phá storefront để đổi lấy
> chính thứ đang có.

### Nợ ghi nhận (không do Phase 2)

- ⚠️ **`config:cache` che `phpunit.xml` env** → `runningUnitTests()` false → CSRF chạy thật →
  checkout test 419. Tái hiện được **trên code trước Phase 2**. Luôn `optimize:clear` trước khi
  chạy test.
- `/api/v1/pages` không phân trang (CMS ít trang, chưa cần).
- Các group `auth:sanctum` khác (Order, Promotion) chưa gắn `token.ability` — làm khi có token
  staff/POS thật (Phase 4).

## Phase 3 — Mobile — ✅ **HOÀN THÀNH 2026-07-10** (trừ 3.5, hoãn có lý do)

> Baseline trước: 279 test. Sau Phase 3 + sửa bug ecommerce: **347 test / 1502 assertion xanh**.

| # | Việc | Complexity | TT |
|---|---|---|---|
| 3.1 | Module **`Notification`** (Laravel Notification + channel `database` + `PushSender` contract) | M | ✅ |
| 3.2 | `POST /api/v1/devices` (device token registry) | S | ✅ |
| 3.3 | `GET /api/v1/orders/{id}/timeline` — **đọc `activity_log` của Lunar**, không tạo bảng | S | ✅ |
| 3.4 | `status_label` i18n trong `OrderResource` | XS | ✅ |
| 3.5 | `GET /api/v1/home-feed` | S | ⏸ **hoãn** |
| 3.6 | Recently-viewed lên server | S | ✅ |

### Ghi chú triển khai

**3.1 — Notification chạy *song song*, không thay mailable.** 4 mailable hiện tại gửi được
cho **khách vãng lai** (resolve email từ địa chỉ đơn hàng), còn `Notifiable` chỉ gắn trên
`User` — đơn guest **không có** `User` (`lunar_customers` không có cột email). Nên
Notification chỉ mang channel `database` + `push`, email giữ nguyên. Guest vẫn nhận email,
không nhận notification — đúng bản chất.

**Domain event mới `OrderStatusUpdated`** (Order sở hữu) — đúng ngưỡng "chỉ thêm event khi
có consumer thứ hai": Notification cần nghe. Email status-update giữ skip-list riêng
(payment status đã có email riêng); event **không** skip vì app không có kênh nào khác.

**Push: contract, chưa driver.** `PushSender` + `NullPushSender` (log, không gửi). Thêm FCM
sau = viết driver + đổi config, zero thay đổi caller. `PushChannel` **không bao giờ ném**:
provider chết không được phá giao dịch đã đặt hàng thành công. Token chết bị prune.

**3.3 — `OrderTimeline` đọc `activity_log`.** Lunar's `OrderObserver` đã ghi event
`status-update` với `{previous, new}`. ⚠️ Chỉ đọc `event = 'status-update'` — log **cũng**
chứa row `updated` với full column diff (kể cả `notes` nội bộ), tuyệt đối không lộ ra.

**3.4 — `OrderStatus::label()`.** `config('lunar.orders.statuses')` chỉ có nhãn **tiếng Anh**
và chỉ cho 4 status Lunar ship sẵn; `completed`/`refunded`/`cancelled` **được dùng trong
code nhưng không có ở đó** → khách nhìn thấy handle thô.

**3.6 — `sequence` thay vì timestamp.** Bug thật, bắt được nhờ test flaky: hai lần
`record()` liên tiếp rơi vào **cùng một millisecond** (đo: ~100% cặp liên tiếp), nên sắp
xếp theo `viewed_at` là tung đồng xu — sản phẩm vừa xem có thể xuống cuối. Đổi sang cột
`sequence` đơn điệu tăng, cấp phát dưới `lockForUpdate`.

**3.5 — hoãn có lý do.** Audit ước lượng "tái dùng `SectionRenderer`" là S. Thực tế
`SectionRenderer::render()` trả **HTML**, và 3 provider dynamic trả view-data chứa
**Eloquent model** — serialise thẳng sẽ lộ model internals (đúng lỗi vừa sửa ở 2.4). Làm
đúng phải viết resource cho từng loại section, mà **chưa có app nào tiêu thụ** để biết
shape cần gì. Làm khi có client thật.

## Rà soát ecommerce cốt lõi — 5 bug thật (2026-07-10) — ✅ **ĐÃ SỬA**

> Rà soát cart / checkout / stock / pricing / discount / refund. Mỗi bug **tái hiện được
> bằng test trước khi sửa**, và mỗi bản sửa đều **mutation-check** (tắt guard → test đỏ).

| # | Bug | Bằng chứng đo được | Mức |
|---|---|---|---|
| **E5** | **Guard chống oversell chưa từng chạy.** `lunar_product_variants.purchasable` mặc định `always` (migration của Lunar) và **toàn bộ 66 variant** trong DB đều `always`. Cả `DecrementStock` lẫn `CartService` đều *cố ý* miễn trừ `backorder`/`always` → không cái nào từng kích hoạt | stock=2, đặt 10 → checkout **200 OK**, stock = **−8** | 🔴 P0 |
| **E1** | **Stock không bao giờ được trả lại.** Đơn tạo ra (và giữ stock) **trước khi** khách thanh toán gateway. Không có scheduler, không có đường release: bỏ ngang VNPay / thanh toán fail / hoàn tiền / RMA / admin huỷ — units mất vĩnh viễn | bank-transfer: stock 5 → **3**, không ai trả đồng nào | 🔴 P0 |
| **E3** | **Một order line trả hàng được nhiều lần.** `validLines()` so với **full** quantity của line, không trừ phần đã claim. Gateway được `RefundService` chặn, nhưng **COD/bank không có trần nào** | đơn 23.000 → 2 RMA × 20.000 = **40.000 hoàn** | 🔴 P0 |
| **E2** | **Cart bỏ qua stock.** `add()` chỉ kiểm tra *phần thêm* (thêm 1 năm lần lọt qua 3 units); `updateLine()` **không kiểm tra gì** | stock=3: `PATCH quantity=999` → **201 OK** | 🟠 P1 |
| **E4** | Double-submit checkout → **500** (`CartException: A billing address is required`), vì submit thứ hai gặp cart rỗng vừa được tạo mới | | 🟡 P2 |
| **E6** | **Trả hàng được trên đơn chưa từng giao.** `ReturnService::open()` **không hề** kiểm tra status đơn; `can_return` trong `OrderResource` chỉ để **ẩn nút** trên UI. Gọi thẳng endpoint là mở RMA trên đơn `awaiting-payment` — rồi staff hoàn tiền được. Phát hiện *khi dọn code*, không phải khi rà soát | đơn `awaiting-payment` (chưa trả xu nào) → RMA mở OK, `refundableAmount` = **10.000** | 🔴 P0 |

### Đã sửa thế nào

**E5 — `purchasable` mặc định `in_stock`.** Migration backfill 66 variant (tất cả đang ở
default chưa ai đụng → không xoá quyết định backorder nào của admin) + đổi default cột.
Admin vẫn chọn `backorder`/`always` cho từng variant khi muốn bán trước. Shop thời trang
SME thì **hết hàng là hết**.

> Bài học: một guard *tồn tại và đúng* vẫn có thể **chưa từng chạy**. `DecrementStock`
> viết chuẩn (conditional UPDATE atomic), test cũ cũng xanh — vì test tự set `in_stock`,
> còn dữ liệu thật thì không.

**E1 — `StockReleaser` + `orders:expire-abandoned`.**
- Cột `lunar_orders.stock_released_at` làm **idempotency là sự thật trong DB**: restock hai
  lần sẽ *bịa ra* hàng, tệ hơn cả rò rỉ.
- Listener nghe `OrderStatusUpdated` → trả stock khi `cancelled`/`refunded`. **Cố ý đồng bộ**
  (không queue): stock là bất biến đúng-sai, queue chết thì hàng mất im lặng.
- Command `orders:expire-abandoned --minutes=60` (scheduler 10'/lần) huỷ đơn gateway chưa
  trả tiền → listener trả stock.
  ⚠️ **Chỉ đơn gateway.** `bank-transfer` cũng nằm ở `awaiting-payment` nhưng do người thu
  tay — lọc theo `meta.payment_type` (VNPay/MoMo có, driver `offline` của Lunar không có).
  Có test `test_a_bank_transfer_is_never_expired_by_the_timer`.

**E3 — trừ phần đã claim + trần hoàn tiền.** `remainingQuantities()` trừ mọi RMA chưa
`rejected`; validate **bên trong** transaction sau `lockForUpdate` (hai RMA mở đồng thời
không cùng thấy một số dư). Thêm `cappedRefund()`: tổng hoàn **không bao giờ** vượt order
total — phòng thủ chiều sâu cho COD (không có gateway làm trần). Đồng thời chuyển
`notify()` ra **ngoài** transaction (side effect không rollback được, standards §4).

**E2 — guard theo *kết quả*, dùng chung cho `add` + `updateLine`.** Vẫn gọi
`canBeFulfilledAtQuantity()` của Lunar nên `backorder`/`always` vẫn vượt stock được — đó là
ý nghĩa của mode đó.

**E4 — cart rỗng → 422** (`Your cart is empty.`), thay vì vỡ sâu trong Lunar.
Kèm: `InsufficientStockException` nay implement `HttpExceptionInterface` → **422** thay vì
500. Người khác lấy mất units cuối là chuyện của người mua, không phải lỗi server.
(Test cũ `InventoryTest` từng **assert 500** — nó ghi lại hành vi xấu như thể là đúng.)

**E6 — `ReturnService::open()` chặn status không trả được** (`OrderStatus::isReturnable()`:
`payment-received` / `dispatched` / `completed`). `OrderResource.can_return` nay đọc **cùng
một nguồn** — flag chỉ còn là gợi ý UI, còn service mới là chỗ ép luật.

> COD ở `payment-offline` **không** trả được: hàng còn trên đường, khách chưa cầm gì.
> Trả được từ khi `dispatched`. Đây đúng là ý định vốn có — UI đã ẩn nút từ trước, chỉ có
> API là chấp nhận.

### Không phải bug (đã kiểm chứng rồi loại)

- **Giá đổi sau khi vào giỏ.** Trong cùng một request, cart hiển thị giá cũ (memoize
  `Price` object) còn order tạo ra theo giá mới → *trông như* charge khác giá hiển thị.
  Kiểm chứng qua **request thật**: `lunar_cart_lines` **không lưu giá**, cart luôn tính
  live → request mới hiện `0,01 US$`, khớp với order. Thiết kế của Lunar, **đúng**.

---

## Dọn mã nguồn Phase 1–3 (2026-07-10) — ✅

> Rà lại chính code mình vừa viết, theo coding standards. **349 test xanh** (+2 test cho E6).

| Việc | Trước | Sau |
|---|---|---|
| Định nghĩa "đã thanh toán" | fallback array **copy-paste 5 nơi** (`AnalyticsService`, `MembershipService`, `CoPurchaseStrategy`, `FitHistoryService`, `DispatchOrderPaidForOfflineOrder`) | **1 nơi**: `OrderStatus::PAID` / `::paid()` |
| Status literal | 52 chuỗi rải rác | hằng số `OrderStatus::*` (§12) |
| `can_return` + RMA guard | 2 danh sách status riêng, chỉ 1 cái được ép | `OrderStatus::RETURNABLE` — **service ép, resource chỉ hiển thị** |
| `TokenAuthController` | **133 dòng**, giữ policy token (TTL, abilities, revoke) | **96** — policy → `TokenIssuer` service |
| `CustomerController` | **117 dòng**, tự split tên + verify password | **82** — → `CustomerResolver::syncName`, `AuthService::changePassword` |
| `/orders` vs `/customer/orders` | **~30 dòng trùng nhau y hệt** ở 2 controller | `CustomerOrderPage` (một shape, một chỗ) |
| `MembershipService::currentTier()` | **4 query** với 2 tier (1 query **mỗi tier**) | **2 query**, không phụ thuộc số tier |
| `CoPurchaseStrategy` / `FitHistoryService` | tham số `?array $paidStatuses` **không ai truyền** | gỡ (§16: không build sớm) |

> **Bài học:** E6 (bug tiền, P0) lộ ra **trong lúc dọn code**, không phải lúc rà soát. Khi
> gom `can_return` về một hằng số, câu hỏi "ai *ép* luật này?" mới bật ra — và câu trả lời
> là **không ai cả**.

---

## Phase 4 — Omnichannel / POS — ⏸ **DỪNG** (2026-07-10)

Điều kiện tiên quyết (2.1 cart-token, 2.2 token abilities) **đã xong**, nhưng khảo sát
trước khi code cho thấy audit ban đầu ước lượng sai vài chỗ. Ghi lại để lần sau không
phải đo lại:

| # | Việc | Thực tế đo được |
|---|---|---|
| 4.1 | Channel `pos` | Lunar hỗ trợ; cart có `channel_id`; **discount CÓ lọc theo channel**. Nhưng catalog **không hề** scope theo channel (0 lời gọi `->channel()`), và `initChannel()` đọc **session** → client headless/POS không chọn được channel. → việc thật là: cho token client khai báo channel. |
| 4.2 | Staff abilities | guard `staff` có, bảng `lunar_staff` có, nhưng **0 dòng staff** và model `Lunar\Admin\Models\Staff` **không có `HasApiTokens`** → chưa cấp token cho staff được. |
| 4.3 | Giá/tồn theo channel | `lunar_prices` **không có cột channel** (chỉ `customer_group_id` + `currency_id`). **Không phải khái niệm của Lunar** → làm là tự bịa ra tính năng. |
| 4.4 | Endpoint tra cứu tại quầy | phụ thuộc 4.1 + 4.2. |

**Chưa có client POS nào tồn tại.** Làm bây giờ là đoán shape rồi sửa lại — đúng cái bẫy
đã tránh ở 3.5 (`/home-feed`). Làm khi có quầy thật.

## Phase 5 — AI
5.1 `modules/AI` + `interface AiProvider` + driver (Claude/OpenAI/Gemini/Ollama) ·
5.2 use-case đầu tiên trong **queued job** (mô tả sản phẩm / phân loại ảnh).
**Không** đụng recommendation cho tới khi CoPurchase hết dư địa.

---

## Điều KHÔNG làm (giữ nguyên chủ đích)

Multi-vendor · visual drag-drop editor · microservices · GraphQL · headless SPA tách rời ·
plugin/hook engine · Repository · Action/UseCase layer · ViewModel/Presenter · BFF ·
module rỗng (ERP/CRM/Loyalty/Wallet/Campaign/Affiliate) · `app/Domain|Application|Infrastructure` ·
AI recommendations.

**Lý do chung:** không có code nào hiện đang đau vì thiếu chúng. Thêm vào = indirection
thuần, đi ngược standards §16 và architecture-review §3.

---

# Phụ lục A — Điều chỉnh sau Audit (2026-07-09)

> Các mục dưới đây là **định hướng mở rộng dài hạn**, **không phải hạng mục triển khai
> ngay**, và **không đổi triết lý kiến trúc**. Chúng bổ sung/ghi đè một phần kết luận ở
> §8 và §9 ở trên (đã đánh dấu chéo tại chỗ).
>
> Nguyên tắc chung của phụ lục: **ghi ngưỡng kích hoạt, không dựng khung rỗng.** Mỗi mục
> nêu rõ *điều kiện nào xảy ra thì mới làm* — để sau này quyết định bằng dữ kiện, không
> bằng cảm tính.

## A.1 — Application Layer (Deferred)

**Current.** 0 service > 500 dòng, 0 controller > 100 dòng (kiểm chứng lại khi audit).
Service method nhỏ đang đóng vai Action.

**Recommendation.** Giữ nguyên. **Không** dựng `Actions/` rỗng.

**Ngưỡng kích hoạt** (thoả **một** là tách):
1. Một service vượt **500 dòng** (standards §15), **hoặc**
2. Một nghiệp vụ được gọi từ **≥ 2 orchestrator** khác nhau (vd `placeOrder` dùng bởi cả
   Web checkout, POS, và Marketplace connector).

**Cách tách khi tới ngưỡng:** rút *đúng nghiệp vụ đó* ra `modules/<X>/Actions/<Verb><Noun>.php`,
service cũ gọi vào action và **giữ nguyên chữ ký public** → caller không sửa, test không
sửa (đúng mẫu đã làm ở Increment 1 khi tách `PromotionService`).

**Priority P3 · Complexity S (khi tới ngưỡng) · Breaking Change NO**

## A.2 — AI Provider: contract trước, driver sau

**Current.** 0 file AI trong repo. `CoPurchaseStrategy` (realtime, không cần job) đang
phục vụ recommendation tốt.

**Recommendation.** Chuẩn bị **duy nhất** contract + DTO, **không driver, không config
driver, không module rỗng**:

```
modules/AI/
├── Contracts/AiProvider.php   # generate(AiPrompt): AiResult
└── Data/{AiPrompt,AiResult}.php
```

Ràng buộc bắt buộc, ghi ngay vào contract để driver sau này không lệch:
- **Provider-agnostic**: không rò `openai`/`claude` ra caller (bài học từ `SearchEngine`
  — đổi engine = đổi config, zero thay đổi caller).
- Mọi lời gọi chạy trong **queued job** (không chặn request).
- Cache theo hash prompt; có trần token/ngày.

> ⚠️ **Không** tạo `Providers/AIServiceProvider.php` hay `config/ai.php` lúc này — chưa có
> gì để bind. Contract là *file interface*, chưa phải module đăng ký. Tránh đúng cái bẫy
> "module rỗng" đã từ chối ở §8.

**Ngưỡng kích hoạt driver:** xuất hiện use-case nghiệp vụ thật, ưu tiên theo ROI:
sinh mô tả sản phẩm hàng loạt → phân loại/tag ảnh → hỗ trợ CSKH.
**Không** đụng recommendation cho tới khi CoPurchase hết dư địa (standards §16).

**Priority P3 · Complexity XS (chỉ contract) · Breaking Change NO**

## A.3 — Customer Insight: read-model, không phải module

> Điều chỉnh kết luận §8 ("không cần"). Mục tiêu được chấp nhận; **cách làm** thay đổi.

**Current — dữ liệu đã có sẵn, tôi đã kiểm chứng bằng truy vấn thật:**

| Facet yêu cầu | Nguồn dữ liệu hiện có | Suy ra được? |
|---|---|---|
| Favorite **Size** | `lunar_product_option_values` (handle `size`) qua order line | ✅ (và `FitHistoryService` **đã** làm sâu hơn: giữ vs trả, between-sizes) |
| Favorite **Color** | `lunar_product_option_values` (handle `color`) | ✅ |
| Favorite **Brand** | `lunar_products.brand_id` | ✅ |
| Favorite **Category** | `lunar_collection_product` | ✅ |
| **Purchase Preference** (giá TB, tần suất, AOV) | `lunar_orders` + `analytics.paid_statuses` | ✅ |
| Favorite **Style** | — | ❌ **Không có nguồn** |

Một truy vấn `GROUP BY customer_id, option_handle, value` trên
`lunar_order_lines → variants → option_values` đã trả về favorite color/size ngay hôm nay.

**Gap.**
1. **"Style" chưa tồn tại.** Product option chỉ có `size`, `color`. Attribute chỉ có `den`.
   `product_materials` là *chất liệu* (cotton, composition…), **không phải** style
   (casual/office/streetwear). → Cần **quyết định mô hình dữ liệu trước**: thêm
   `ProductOption` `style`, hay Lunar `Attribute`, hay `Collection` dạng "style".
   **Không suy ra được** nếu không có nguồn.
2. Insight khoá theo `customer_id`. Checkout **có** gắn `customer_id` cho user đăng nhập
   (`CheckoutService:163`), nên coverage thật sẽ ổn — nhưng **khách vãng lai (guest) không có
   insight**, đúng bản chất.

**Recommendation.**
- Làm **`CustomerInsightService`** trong module **`Customer`** (đã có), **không** tạo module
  `CustomerInsight`. Lý do: nó là **read-model** trên dữ liệu Lunar, không sở hữu bảng nào,
  không có nghiệp vụ ghi → không đủ tư cách là module (đúng chuẩn đã áp cho `Loyalty`).
- **Không thêm schema.** Nếu đo được là chậm → cache read-model (TTL hoặc invalidate qua
  `OrderPaid`), đúng standards §4 ("chỉ Service được cache"). Chỉ materialize thành bảng
  khi có số đo chứng minh cần.
- **Tái dùng, không nhân bản:** favorite size **phải** đọc `FitHistoryService` (đã có logic
  kept-vs-returned), không viết lại phép đếm size.
- **Một nguồn "đã mua":** dùng `config('analytics.paid_statuses')` (sau khi Phase 1.1 gộp).
- API: `GET /api/v1/customer/insights` (auth) → phục vụ Recommendation / Marketing / Mobile.
- **Style: chưa làm** cho tới khi chốt mô hình dữ liệu. Ghi rõ là *blocked*, không hứa suông.

**Priority P2** (sau Phase 1) · **Complexity S** (5 facet) / **M** (kèm style + data model)
· **Breaking Change NO**

## A.4 — Omnichannel Readiness

**Current.** Lunar hỗ trợ multi-channel native; repo mới dùng **1 channel** (`webstore`).
`/api/v1` **đã** tách khỏi Storefront (controller riêng `Api/V1/`, Resource riêng).

**Recommendation.** Không xây ERP / Marketplace / POS Connector. Giữ **kỷ luật ranh giới**
để sau này cắm vào không phải sửa Business Layer:
1. **Business logic không bao giờ nằm trong controller** (đang đạt: 0 controller > 100 dòng).
2. Mọi tích hợp ngoài (TikTok Shop, Facebook Shop, ERP) đi theo mẫu **đã có**:
   listener nghe domain event → **queued job** trong `modules/Integrations/<System>` → sau một
   `interface <System>Client`. Không gọi thẳng từ service nghiệp vụ.
3. **Chặn cứng:** POS/marketplace **không thể** dùng `/api/v1` cho tới khi xong
   **G1 (cart theo token)** và **G8 (token abilities)**. Đây là điều kiện tiên quyết, không
   phải tuỳ chọn.

**Priority P2 (kỷ luật) / P3 (connector) · Complexity XS · Breaking Change NO**

## A.5 — BFF

**Current.** 1 storefront + (sắp có) 1 app. `/api/v1` là contract chung.

**Recommendation.** Chưa thêm. BFF là **lớp triển khai phía trên** API hiện có — thêm sau
**không** phá Domain/Business Layer, nên hoãn là an toàn và đúng.

**Ngưỡng kích hoạt:** ≥ 2 client **mâu thuẫn nhau về shape/chattiness** đến mức phải
đánh đổi (vd app cần 1 request gộp, web cần shape tách). Khi đó BFF nằm ở
`Http/Controllers/Bff/<Client>` — **không** đẻ business logic mới, chỉ *compose* service
đã có. Trước ngưỡng đó, `?include=` (đã dùng ở `/products/{slug}`) và `/home-feed` (§5)
giải quyết đủ.

**Priority P3 · Complexity M (khi tới ngưỡng) · Breaking Change NO**

## A.6 — Observability

**Current (kiểm chứng).**
- Horizon **đã cài** (`laravel/horizon`), queue tách theo loại.
- **Audit log gần như miễn phí**: 23 model Lunar (`Order`, `Product`, `Customer`,
  `Transaction`, `Discount`…) đã `use LogsActivity`; bảng `activity_log` **đã tồn tại** và
  ghi thật (`{"previous":"payment-offline","new":"dispatched"}`).
- ❌ Không có Sentry / Telescope / Pulse / metrics.
- 🔴 **`GET /api/v1/health` là health-check giả**: `HealthController` có **0** lời gọi
  `DB::`/`Cache::`/`Queue::` — luôn trả `{"status":"ok"}` **kể cả khi DB sập**. Load balancer
  tin vào nó sẽ tiếp tục đẩy traffic vào node chết. Đây là *nguy hiểm hơn* việc không có
  health endpoint.

**Recommendation** (xếp theo ROI thực, không theo danh sách buzzword):

| # | Việc | Vì sao |
|---|---|---|
| 1 | **Sửa `/api/v1/health` kiểm tra thật** DB + cache + queue; trả **503** khi lỗi | Đang là *false positive*. Rẻ nhất, rủi ro cao nhất. |
| 2 | **Audit log cho model của ta**: thêm `LogsActivity` vào `ReturnRequest`, `ShippingZone`, `SizeChart` | Dùng lại hạ tầng Lunar/Spatie **đã có**, không dựng mới |
| 3 | Error tracker (Sentry) | Đã ghi trong todo #4 "khi có ngân sách" |
| 4 | Logging chuẩn hoá: JSON + `request_id` correlation | Điều kiện cần để log có ích khi có nhiều client |
| 5 | Metrics / APM | Sau cùng; Horizon đã cho thấy queue health |

> ⚠️ **Không** dựng "Observability module". Đây là cấu hình + trait, không phải domain.

**Priority: #1 P1 · #2 P2 · #3–5 P3 · Complexity XS–S · Breaking Change NO**

---

## Ảnh hưởng lên Roadmap

Phụ lục A **không đổi** Phase 1 → 5 đã chốt. Chỉ chèn thêm:

- **Phase 1** (+): sửa `/api/v1/health` kiểm tra thật (A.6 #1) — **XS**, cùng nhóm "trả nợ"
  với rate limit, vì cùng bản chất *tưởng an toàn nhưng không*.
- **Phase 3** (+): `GET /api/v1/customer/insights` (A.3, 5 facet; **hoãn** Favorite Style)
  — phục vụ Mobile/Recommendation.
- **Phase 5** (+): contract `AiProvider` (A.2) — file interface, chưa module.

Các mục A.1 / A.4 / A.5 **không sinh việc**: chúng là *ngưỡng kích hoạt* được ghi lại để
quyết định sau bằng dữ kiện.
