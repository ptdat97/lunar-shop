# SME Fashion Ecommerce — TODO / Roadmap

> Danh sách việc cần làm để hoàn thiện mục tiêu **SME Fashion Ecommerce (single-store,
> Laravel 12 + LunarPHP)**. Trạng thái hiện tại của repo ở
> [lunarphp_sme_fashion_plan.md](lunarphp_sme_fashion_plan.md) (mô tả những gì đã có).
> Danh sách này chỉ gồm phần **chưa làm / chưa hoàn thiện**, xếp theo ROI giảm dần.
>
> **Nguyên tắc xuyên suốt:** trước khi build tính năng mới, kiểm tra `vendor/lunarphp`
> đã có chưa (Nguyên tắc #1) — có thì kế thừa/mở rộng, không thì mới build mới trong
> module tương ứng. Giữ phạm vi single-store SME: không platform/plugin/hook engine.
> Mọi thay đổi phải chạy `vendor/bin/phpunit` xanh trước khi coi là xong.

---

## P0 — Chặn doanh thu / vận hành cơ bản

### 1. MoMo payment gateway — *Checkout* — ✅ **đã làm**
- ✅ Theo đúng mẫu VNPay: `MoMoPayment` driver (`AbstractPayment`), `MoMoGateway`
  (create-payment JSON POST → payUrl, verify HMAC-SHA256, sign, isSuccessful,
  orderIdFrom), `MoMoPaymentProcessor` (reconcile + ghi `Transaction` driver `momo` +
  chuyển order → payment-received, **idempotent**), `MoMoResult` DTO, `MoMoController`
  (return redirect + ipn POST trả 204). Đăng ký `Payments::extend('momo')` +
  `payment-overrides.php` + `paymentMethods()`. **Không đổi pipeline checkout.**
- ✅ Config `payment.momo.*` (env `MOMO_PARTNER_CODE`/`MOMO_ACCESS_KEY`/`MOMO_SECRET_KEY`…),
  routes `payment/momo/return` (GET) + `payment/momo/ipn` (POST, CSRF-exempt),
  checkout `place()` redirect sang payUrl, UI radio MoMo (`momoEnabled`) + i18n EN/VI.
- ✅ Test: `MoMoPaymentTest` (9 case — chữ ký round-trip + tamper, place order awaiting,
  createPayment payUrl/error qua `Http::fake`, callback paid + Transaction + email,
  idempotent, invalid signature, failed resultCode, IPN 204).
- ✅ Refund qua API MoMo đã làm (mục 2).

### 2. Refund qua API — *Checkout* — ✅ **đã làm**
- ✅ Gateway refund API thật: `VNPayGateway::refund()` (POST merchant API, ký
  HMAC-SHA512, full/partial 02/03) + `MoMoGateway::refund()` (POST refund API, ký
  HMAC-SHA256). Config `payment.vnpay.api_url` + `payment.momo.refund_url`.
- ✅ `RefundService` (shared): tìm capture transaction, gọi gateway đúng driver, ghi
  `Transaction` type `refund` (parent = capture), cập nhật order → `refunded` (full) hoặc
  giữ paid (partial); guard over-refund + refundedTotal/isRefundable. `RefundResult` DTO.
- ✅ **Admin action dùng NATIVE Lunar**: `VNPayPayment::refund()`/`MoMoPayment::refund()`
  delegate sang RefundService → nút "Refund" sẵn có trong Lunar OrderResource (ManageOrder)
  hoạt động end-to-end, không cần build action mới (Lunar chỉ đọc `PaymentRefund` +
  đọc refund-type transactions ta ghi).
- ✅ Test: `RefundTest` (6 case — VNPay/MoMo full refund, partial giữ paid, gateway fail
  không ghi gì, over-refund bị chặn) qua `Http::fake`. Verify path Lunar admin
  `$transaction->refund()` → driver → RefundService → `refunded`.

### 3. Invoice PDF — *Order* — ✅ **đã làm**
- ✅ `barryvdh/laravel-dompdf` + template `order::invoice` (self-contained CSS, DejaVu
  font → Unicode VN OK) + `InvoiceService` (make/bytes/filename). Song ngữ EN/VI qua
  `lang.mail.invoice.*` (render theo locale).
- ✅ Route tải PDF owner-only `account/orders/{order}/invoice`
  (`InvoiceController`, route-model-bound + ownership check → guest/non-owner 404) +
  nút "Download invoice" ở account order-detail (`account.js`, URL template + label từ
  account-state, không hardcode).
- ✅ Đính kèm invoice PDF vào `OrderPaidMail` (build lazy → theo locale mail).
- ✅ Test: `InvoiceTest` (generate PDF, owner tải được, non-owner/guest 404).

### 4. Bật hạ tầng production — *cấu hình* — ✅ **queue/Horizon xong**
- ✅ Redis + Horizon chạy thật (verify end-to-end): `.env` → redis, 2 supervisor
  (`supervisor-app` mails/notifications/default; `supervisor-media` cho ảnh nặng),
  queue tách theo loại (`App\Support\Queues`), retry/backoff cho mailable + job.
- ✅ Mail đơn hàng → queue `mails`; back-in-stock → `notifications`; resize ảnh →
  `media` (on-demand async fallback + pre-warm sau upload + batch regenerate với
  progress/ETA/worker-status Horizon-aware).
- Việc còn lại: CDN cho `public/` (media + build assets) khi deploy; rà lại DB index
  sau khi có traffic thật (đã có `add_performance_indexes` làm nền).

---

## P1 — Tăng chuyển đổi / AOV (fashion-specific)

### 5. Đổi/trả hàng (RMA / returns) — *Order* — ✅ **đã làm**
- ✅ Bảng `return_requests` + `return_request_lines` (line-level qty) + models
  `ReturnRequest`/`ReturnRequestLine`.
- ✅ `ReturnService`: `open` (validate ownership + qty ≤ line qty), `approve` (+refund
  tùy chọn), `reject`, `refund` (prorate theo qty → gọi `RefundService` cho gateway
  order, hoặc mark refunded cho COD/bank), `refundableAmount`. Full refund → order
  `refunded`; partial → giữ paid.
- ✅ Storefront: `POST /account/orders/{order}/returns` (owner-only, 404 guest/non-owner)
  + form đổi/trả ở account order-detail (`account.js`: chọn line + qty + lý do, i18n
  EN/VI, URL/label từ account-state). `OrderResource` thêm `can_return` + line `id`.
- ✅ Admin: `ReturnRequestResource` (Sales group, read + actions Approve/Reject/Refund,
  không cho create) — Approve có toggle "refund ngay".
- ✅ Email trạng thái: `ReturnStatusMail` (queued, i18n `mail.return.*`, gửi qua
  `OrderMailer` locale-aware) fire ở open/approve/reject/refund.
- ✅ Test: `ReturnRequestTest` (6 case — prorate, approve+refund+transaction, reject+email,
  invalid qty, storefront owner tạo được, non-owner 404).

### 6. Email transactional — i18n ✅ **đã làm** (branding + invoice còn lại)
- ✅ **i18n EN/VI:** `lang/{en,vi}/mail.php` (subject + heading + intro + table + button +
  shipping + thanks); 3 template markdown dùng `__()`; subject trong `envelope()` cũng
  i18n. `OrderMailer::send()` set `->locale()` = locale khách đang dùng (fallback
  `LocaleService::default()`) → **queued mail giữ đúng ngôn ngữ** qua serialize.
- ✅ Test: `OrderMailI18nTest` (render EN, render VI, locale-stamping trên queued mail).
- ✅ Đính kèm invoice PDF vào `OrderPaidMail` (mục 3 đã xong).
- ⬜ Còn: branding (logo/màu). Nhãn order-status (`statusLabel`) vẫn theo config Lunar
  (English) — tách khỏi storefront i18n.

### 7. Facet material + availability — *Catalog (Search)* — ✅ **đã làm**
- ✅ `DatabaseSearchEngine`: `computeFacets` trả thêm `material` (từ `product_materials`,
  value+count) và `availability` (bucket `in_stock` đơn) + `applyFilters` lọc theo cả hai
  (material: `whereHas('material')`; availability: `whereHas('variants', stock>0)`).
- ✅ UI: sidebar render tự động (bucket facet generic) + i18n `facet_material`/
  `facet_availability`/`in_stock` (EN/VI); `_shop.js` dịch value enum (`in_stock` →
  "Còn hàng") qua `data-value-label-*`. Facet counts tính từ facetBase (trước filter).
- ✅ Test: `test_material_facet_and_filter` + `test_availability_facet_and_in_stock_filter`.

### 8. Size Intelligence v2 — *Catalog / Customer* — ✅ **hồ sơ số đo xong**
- ✅ Lưu **hồ sơ số đo** khách đăng nhập: bảng `customer_measurements` (1/customer) +
  model `CustomerMeasurement` + relation `Customer::measurement` (extend, không fork) +
  `MeasurementService` (for/save, bỏ blank + unknown key).
- ✅ API `GET/PUT /api/v1/customer/measurements` (auth cookie/token) + prefill find-my-size
  (`size-finder.js` load profile khi mở modal, ẩn/hiện toggle "lưu số đo" theo login,
  tự lưu sau khi tìm size nếu opted-in). i18n `save_measurements` EN/VI.
- ✅ Test: `CustomerMeasurementTest` (5 case — service save/read drop blank+unknown,
  idempotent 1 profile, API get/update, guest 401, validate range).
- ⬜ Còn (nice-to-have, chưa làm): gợi ý fit theo lịch sử mua/đổi-trả, cảnh báo "giữa
  hai size".

### 9. "Frequently bought together" — *Catalog (Recommend)* — ✅ **đã làm**
- ✅ `CoPurchaseStrategy` (implements `RecommendationStrategy`): 1 query aggregate đọc
  `lunar_order_lines` (join variants → product), lấy product cùng xuất hiện trong các
  đơn PAID chứa product nguồn, rank theo tần suất co-occurrence. Hydrate published
  products giữ thứ tự.
- ✅ Cắm vào `config/recommend.php` chain giữa Association (curated) và Collection
  (fallback) — **không đụng caller** (service tự combine/de-dup/cache). Tự động vào cả
  product-page + mini-cart recommendations.
- ✅ Paid statuses đọc từ `analytics.paid_statuses` (single source — "purchased" nhất
  quán toàn app). Realtime query, không cần job tổng hợp ở quy mô SME.
- ✅ Test: `RecommendationTest` (co-purchase trả product mua cùng; bỏ qua đơn chưa paid).

---

## P2 — Hoàn thiện & giữ chân

### 10. Nội dung sản phẩm song ngữ (VI) — *Catalog / seeders* — ✅ **đã làm (2026-07-03)**
- ✅ Seed `vi` Language row (`BaseDataSeeder`, idempotent per-code; `en` vẫn là default
  của Lunar) → Lunar resolve được `attribute_data` theo ngôn ngữ.
- ✅ `Demo50ProductsSeeder` chuyển name + description sang `TranslatedText` (EN+VI):
  50 tên VI song song + 5 mô tả VI, helper `t($en, $vi)` fallback EN khi thiếu. Trang VI
  hiện đúng tên tiếng Việt qua `ProductResource::translateAttribute('name')` (read path
  đã sẵn, không đổi).
- ✅ **API locale resolution** (`/api/v1/*`): trước đây API chỉ chạy middleware `api`
  (không `SetStorefrontLocale`) → không đổi ngôn ngữ theo request. Thêm middleware
  session-less `SetApiLocale` (đăng ký qua `pushMiddlewareToGroup('api', …)` trong
  `ThemeServiceProvider`) + `LocaleService::resolveForApi()` (`?locale=` → `Accept-Language`
  → default). Nay product/collection/search payload trả `translateAttribute` đúng ngôn ngữ
  client chọn. Nhóm route `web`/`storefront` (checkout/order/wishlist) vẫn resolve qua
  session như cũ.
- ✅ Test: `ProductTranslationTest` (6 case — vi/en Language seeded + en default;
  `translateAttribute` resolve theo locale; SSR product page locale=vi; API `?locale=vi`,
  `Accept-Language: vi`, và default=en). Trait `CreatesStorefrontData::createProduct(['name_vi' => …])`
  tạo product song ngữ.
- ⬜ Còn: admin nhập đa ngôn ngữ qua product editor (Lunar `translateAttribute` sẵn có —
  chỉ là hướng dẫn vận hành, không phải code).

### 11. Dịch động các chuỗi JS còn lại — *theme* — ✅ **đã làm**
- ✅ `account.js`: chuỗi cứng (View/Edit/Delete/Default/Shipping to/Subtotal/Total/
  loading/errors/Phường-Xã…) đưa vào bundle `i18n` từ `data-account-state`; helper `tr()`
  fallback English. Thêm ~20 key `storefront.account.*` EN/VI. Return-form giữ fallback
  `returns.i18n.*` (đã có từ #5).

### 12. Hoàn thiện SEO — *Catalog / Content* — ✅ **đã làm**
- ✅ **CMS page view** (`theme::pages.page` trước đó **thiếu hẳn** → 500; nay tạo mới):
  render content + featured image + JSON-LD `WebPage` + `BreadcrumbList` + OG. `fileUrl`
  composer mở rộng cho `pages.page`.
- ✅ **OG image**: layout fallback `general.og_image`/`general.logo` (home + mọi page
  không set riêng) + collection page dùng thumbnail (composer `pages.collection` →
  `$ogImage`).
- ✅ Test: `SeoTest` (CMS page WebPage+BreadcrumbList JSON-LD; unpublished → 404).

### 13. Test cho tính năng JS-heavy + module tests — *toàn repo*
- 110 test ở `tests/Feature`. Chưa phủ: picture/srcset, search-panel, notify-me UI,
  lookbook-shoppable, variant deep-link (SSR price + query sync), MoMo (khi có).
- **Chuẩn hóa test cạnh module:** hiện `modules/*/Tests` = 0 (toàn bộ ở `tests/Feature`
  root). Sau đợt gộp 24→11 module, nên thêm test smoke cho từng module ở
  `modules/<Name>/Tests` để ranh giới rõ.

### 14. Quick-view — *theme*
- Đã chừa chỗ, cố ý hoãn. Làm khi cần: modal xem nhanh sản phẩm từ grid (vanilla, đọc
  `/api/v1/products/{slug}`), add-to-cart không rời trang listing.

### 15b. Config tính năng ra admin Filament — ✅ **đã làm**
- ✅ Hạ tầng chung: `App\Support\Settings` (bảng `app_settings` key→JSON, cached, đọc
  DB → fallback config/env) + singleton. Migration `create_app_settings_table`.
- ✅ 4 Filament settings page (đọc/ghi qua Settings, i18n EN/VI):
  - **Payment** (Checkout, Settings group): keys VNPay + MoMo, để trống primary key =
    tắt cổng. Gateway `fromConfig()` đọc qua Settings.
  - **Shipping** (Shipping, Settings group): flat rate + free-ship threshold. Resolver +
    CartResource đọc qua Settings.
  - **Membership** (Promotion, Sales group): Toggle enabled + Repeater tiers, auto-sort
    theo min_spend. `MembershipService` đọc qua Settings.
  - **Recommendations** (Catalog group): product_limit/cart_limit/cache_ttl.
- ✅ **Đợt 2** thêm 3 config business nữa: **Review auto-approve** (moderation, gộp vào
  `CatalogSettingsPage` — đổi tên từ RecommendSettingsPage), **Default payment method**
  (Select vào Payment Settings, `defaultPayment` inject vào checkout), **Media on-demand
  mode** sync/async (Toggle vào Image Sizes page). Đều đọc qua Settings.
- ✅ **Đợt 3** thêm **Low-stock threshold** (`InventorySettingsPage` mới, Settings group
  `settings`): ngưỡng "sắp hết hàng" cho Stock Overview badge/filter +
  `InventoryService::lowStock()`. Gỡ const `LOW_THRESHOLD` hardcode (5) → configurable.
- ✅ Test: `AppSettingsTest` (fallback config, DB override wins, per-key fallback,
  review auto-approve điều khiển visibility đánh giá mới).
- ⬜ Giữ trong config (kỹ thuật): recommend.strategies, analytics paid_statuses,
  inventory/cart/media pipelines-overrides, tax-inclusive, Scout/Typesense, FFmpeg,
  media disks.

---

## P3 — Nâng cấp khi quy mô lớn hơn

### 15. Scout search driver (Meilisearch/Typesense) — *Catalog (Search)*
- `config/scout.php` đã có; engine active vẫn `DatabaseSearchEngine`. Chỉ cần khi
  catalog lớn / cần typo-tolerance + facet nhanh.
- Cách làm: viết `ScoutSearchEngine` sau interface `SearchEngine` → đổi config, **zero**
  thay đổi caller.

### 16. Analytics nâng cao — *Analytics*
- Dashboard KPI/trend/best-seller đã có. Mở rộng: top size/màu bán chạy, tỉ lệ đổi-trả
  theo size (gắn với RMA mục 5), export báo cáo.

---

## Nguyên tắc phạm vi (nhắc lại)

**KHÔNG** build trong giai đoạn SME single-store: multi-vendor/marketplace, visual
drag-drop editor, microservices/GraphQL, headless SPA tách rời, AI recommendations,
plugin/platform SDK, hook/workflow engine. Cross-module gọi service trực tiếp, giữ ít
lớp nhất.
