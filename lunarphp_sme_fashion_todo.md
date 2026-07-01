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

### 1. MoMo payment gateway — *Checkout*
- Chỉ có VNPay; MoMo chưa có (không class/config).
- Cách làm: theo đúng mẫu VNPay — driver kế thừa `Lunar\Base\...\AbstractPayment`
  (tham chiếu `modules/Checkout/PaymentTypes/VNPayPayment.php`), gateway build URL +
  verify chữ ký (HMAC), routes `start`/`return`/`ipn` idempotent (ghi `Transaction`,
  chuyển order → payment-received). Đăng ký qua `Payments::extend('momo')` +
  `payment-overrides.php`. **Không đổi pipeline checkout.**
- Bật bằng env (giống `VNPAY_TMN_CODE`/`VNPAY_HASH_SECRET`).
- Kèm test kiểu `VNPayPaymentTest` (chữ ký + tamper + callback idempotent).

### 2. Refund qua API — *Checkout*
- Hiện `VNPayPayment::refund()` trả `PaymentRefund(true)` nhưng refund thực tế làm
  out-of-band (portal VNPay). Cần gọi API refund thật của cổng + ghi `Transaction`
  refund + cập nhật order status.
- Bắt đầu với VNPay refund API; MoMo tương tự khi có gateway.

### 3. Invoice PDF — *Order*
- Chưa có package PDF hay template. Khách cần hóa đơn/biên nhận.
- Cách làm: thêm `barryvdh/laravel-dompdf`, tạo template Blade hóa đơn (dùng lại data
  `OrderResource`), route tải PDF ở account order-detail + đính kèm vào
  `OrderPaidMail`. Song ngữ EN/VI.

### 4. Bật hạ tầng production — *cấu hình*
- Env đã set sẵn `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=phpredis`;
  Horizon đã cài (`laravel/horizon`). **Chưa** verify chạy thật.
- Việc còn lại: cấu hình Redis prod + chạy Horizon (queue email/job restock), CDN cho
  `public/` (media + build assets), rà lại DB index sau khi có traffic thật
  (đã có `add_performance_indexes` migration làm nền).

---

## P1 — Tăng chuyển đổi / AOV (fashion-specific)

### 5. Đổi/trả hàng (RMA / returns) — *Order* (build mới)
- Fashion tỉ lệ đổi trả cao → ROI lớn. Lunar không có RMA.
- Cách làm: bảng `return_requests` (order_id, line, reason, status, refund_amount) +
  Filament resource (duyệt/từ chối) + email trạng thái + link yêu cầu đổi-trả ở
  account order-detail. Gắn với refund (mục 2) khi hoàn tiền.

### 6. Email transactional — hoàn thiện — *Order*
- 3 mailable đã chạy nhưng template markdown **chưa i18n** (0 file dùng `__()`) và chưa
  branding.
- Cách làm: thay chuỗi cứng bằng `__()` (lang `mail.*` EN/VI), thêm logo/màu brand,
  render theo locale của khách. Đính kèm invoice PDF (mục 3).

### 7. Facet material + availability — *Catalog (Search)*
- Engine mới có `size/color/brand/price`. Thiếu `material` (đã có bảng
  `product_materials`) và `availability` (in-stock/out).
- Cách làm: mở rộng `DatabaseSearchEngine::computeFacets` + `applyFilters`; UI thêm 2
  nhóm facet ở sidebar (`_shop.js` đã parse cả list + object facet). Giữ nguyên contract.

### 8. Size Intelligence v2 — *Catalog*
- Base (size chart + find-my-size) đã có. v2:
  - Lưu **hồ sơ số đo** của khách đăng nhập (bảng `customer_measurements`) → prefill
    form find-my-size.
  - Gợi ý fit theo lịch sử mua/đổi-trả; cảnh báo "thường giữa hai size".

### 9. "Frequently bought together" — *Catalog (Recommend)*
- Hiện có `AssociationStrategy` + `CollectionStrategy`. Thiếu strategy co-purchase.
- Cách làm: `CoPurchaseStrategy` đọc `lunar_order_lines` (job tính bảng tổng hợp định
  kỳ), cắm vào chain sau curate. Giữ interface `RecommendationStrategy` — không đụng
  caller. **Không** dùng ML/vector ở giai đoạn SME.

---

## P2 — Hoàn thiện & giữ chân

### 10. Nội dung sản phẩm song ngữ (VI) — *Catalog / seeders*
- UI đã song ngữ EN/VI, nhưng tên/mô tả sản phẩm chưa có bản dịch `vi` (seeders không
  set `translateAttribute` vi) → trang VI vẫn hiện tên tiếng Anh.
- Cách làm: bổ sung bản dịch vi trong seeder demo + hướng dẫn admin nhập đa ngôn ngữ
  qua product editor (Lunar `translateAttribute` đã sẵn).

### 11. Dịch động các chuỗi JS còn lại — *theme*
- `account.js` còn ~16 chuỗi cứng (edit/error states). Đưa vào `data-*-i18n` như
  `product-variant.js` đã làm.

### 12. Hoàn thiện SEO — *Catalog / Content*
- Đã có: Product/Collection JSON-LD, sitemap, robots, OG ở product.
- Còn: JSON-LD cho **CMS page** (Article/WebPage), **OG image** cho home + collection.

### 13. Test cho tính năng JS-heavy + module tests — *toàn repo*
- 110 test ở `tests/Feature`. Chưa phủ: picture/srcset, search-panel, notify-me UI,
  lookbook-shoppable, variant deep-link (SSR price + query sync), MoMo (khi có).
- **Chuẩn hóa test cạnh module:** hiện `modules/*/Tests` = 0 (toàn bộ ở `tests/Feature`
  root). Sau đợt gộp 24→11 module, nên thêm test smoke cho từng module ở
  `modules/<Name>/Tests` để ranh giới rõ.

### 14. Quick-view — *theme*
- Đã chừa chỗ, cố ý hoãn. Làm khi cần: modal xem nhanh sản phẩm từ grid (vanilla, đọc
  `/api/v1/products/{slug}`), add-to-cart không rời trang listing.

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
