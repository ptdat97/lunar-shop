# SME Fashion Ecommerce — Việc còn lại

> **Chỉ ghi việc CHƯA làm.** Hiện trạng ở
> [architecture/overview.md](architecture/overview.md); lịch sử bug đã sửa ở
> [history/2026-07-platform-audit.md](history/2026-07-platform-audit.md).
> Xếp theo ROI giảm dần. Cập nhật: **2026-07-23**.
>
> **Thứ tự ưu tiên đã đảo lại (2026-07-13).** Trước đây danh sách này mở đầu bằng
> tính năng chuyển đổi (quick-view, size intelligence, search engine). Rà lại code cho
> thấy sai trọng tâm: **shop chưa giao được hàng tự động và chưa xuất được hoá đơn hợp
> lệ**, còn lỗi production thì vô hình (không error tracker, không CI). Đó là **P0**.
> Tính năng chuyển đổi chỉ có nghĩa sau khi bán–giao–xuất hoá đơn chạy trơn.
>
> **Hướng đi đã chốt: Blade SSR là storefront duy nhất.** Storefront Next.js headless
> **cố ý hoãn** (2026-07-13) — xem mục 11. `/api/v1` giữ nguyên làm nền, nhưng
> **giữ, KHÔNG mở rộng**: không thêm endpoint/shape cho client chưa tồn tại.
>
> **Trước khi build bất cứ gì:** kiểm tra Lunar đã có chưa (Nguyên tắc #1). Lunar là
> composer package `lunarphp/lunar` trong `vendor/` — **bản fork cũ trong repo đã được
> gỡ (2026-07-20)**. Có sẵn thì kế thừa/mở rộng qua điểm mở rộng chính chủ, không thì
> mới build trong module tương ứng; **không sửa `vendor/`** — composer patch là lựa
> chọn cuối ([guides/coding-standards.md](guides/coding-standards.md) §5). Giữ phạm vi
> single-store SME. `php artisan test` xanh trước khi coi là xong, và
> `vendor/bin/pint --dirty` xanh — **không** phải cả repo: chạy `pint` toàn repo sẽ
> reformat hàng loạt file không liên quan, tạo commit khổng lồ trộn lẫn với thay đổi
> thật → xem P0 mục 2.

---

## P0 — Chặn "bán hàng thật"

Ba việc dưới đây **không phải tính năng**: thiếu chúng thì shop hoặc không vận hành nổi,
hoặc vi phạm nghĩa vụ pháp lý, hoặc hỏng mà không ai biết.

### 1. ⚠️ Rotate secrets — CHẶN DEPLOY
- ⬜ Secrets cũ **vẫn nằm trong git history**. Phải rotate (VNPay/MoMo key, `APP_KEY`,
  DB, mail) **trước** khi lên production. Không có ngoại lệ, không có "để sau".

### 2. Lỗi production đang vô hình + không có lưới an toàn
- ⬜ **Error tracker** (Sentry — free tier là đủ cho SME). Hiện **chỉ có**
  `storage/logs/laravel-*.log`: khách gặp 500 lúc thanh toán thì **không ai biết**, trừ
  khi khách chịu khó nhắn tin. Đây là việc rẻ nhất trong cả danh sách và có ROI cao nhất.
- ⬜ **CI** — `.github/` **chưa tồn tại**. Tối thiểu: chạy `phpunit` mỗi push (~20 dòng
  YAML). 432 test đang chạy **bằng tay** → chỉ cần quên một lần là bug lên production.
  Muốn thêm `pint --test` thì trước đó phải chạy `pint` một lượt trên code của mình
  trong **commit riêng, không kèm thay đổi logic** — bật thẳng vào CI sẽ đỏ ngay ngày
  đầu vì còn nhiều file chưa từng format. (Con số 241 file đỏ trong bản ghi cũ đã lỗi
  thời: 119 file trong đó thuộc bản fork Lunar, nay đã gỡ khỏi repo.)

### 3. Hoá đơn điện tử (HĐĐT) — nghĩa vụ pháp lý, không phải tính năng
- ⬜ `Modules\Order\Services\InvoiceService` hiện sinh **PDF qua dompdf** — đó là *phiếu
  giao hàng*, **không** phải hoá đơn hợp lệ. Nghị định 123/2020 yêu cầu HĐĐT **có mã cơ
  quan thuế** (Viettel / VNPT / MISA / EasyInvoice…). Thiếu → không xuất hoá đơn cho
  khách công ty được, và có rủi ro thuế.
- **Cách làm** (đúng mẫu đã có trong repo): `interface InvoiceProvider` + driver cho một
  nhà cung cấp, gọi trong **queued job** nghe `Modules\Order\Events\OrderPaid`. Lưu mã
  hoá đơn + link tra cứu vào `orders.meta`. Job thất bại **không** được làm hỏng đơn.

---

## P0.5 — Vận chuyển ⏸ **CHỜ HỢP ĐỒNG** (2026-07-13)

**Không phải "chưa nghĩ tới" — là đang bị chặn bởi việc ngoài code.** Chưa ký hợp đồng
với GHN/GHTK nên chưa có API key, chưa có sandbox → **hoãn**, không ước lượng, không
code trước theo tài liệu (đoán shape rồi sửa lại là lãng phí — đúng bài học Phase 4/POS
trong [audit](history/2026-07-platform-audit.md#phần-4--việc-đã-khảo-sát-rồi-cố-ý-dừng)).

**Hiện trạng đo được:** `modules/Shipping` chỉ có `ShippingZone` với 4 field
(`country_code`, `states`, `rate`, `free_threshold`) → **flat-rate tính tay theo tỉnh**.
**Không có** một dòng nào gọi GHN/GHTK/ViettelPost/VNPost. `lunar_orders` **không có cột
tracking** nào.

**Cái giá đang trả (chấp nhận có ý thức, không phải quên):** mỗi đơn chủ shop phải tự
sang web hãng vận chuyển tạo vận đơn, tự copy mã tracking, tự trả lời khách "hàng tới
đâu rồi". Với SME fashion — gần như 100% đơn là COD nội địa — **đây là chi phí vận hành
lớn nhất và đang 100% thủ công.** Càng nhiều đơn, càng đau.

**Ngưỡng kích hoạt:** ký xong hợp đồng + có API key sandbox của **một** hãng (một là đủ,
đừng làm hai).

**Nền đã sẵn, khi làm chỉ cần cắm vào** — *ghi ở đây để lúc bắt tay không phải khảo sát lại*:
- `OrderStatus::DISPATCHED` (`dispatched`) **đã có** trong `modules/Order/app/Support/OrderStatus.php`
  và trong `config/lunar/orders.php`.
- Domain event `Modules\Order\Events\OrderStatusUpdated` **đã có** + đã có consumer
  (Notification gửi thông báo, Inventory trả tồn kho) → webhook của hãng chỉ cần bắn vào
  đây, **không** phải sửa Order.
- `ShippingService` + `ShippingZoneResolver` là chỗ cắm phí ship động (thay flat-rate).
- **Còn thiếu, phải thêm:** cột/bảng lưu **mã vận đơn + link tracking** (`lunar_orders`
  chưa có) → nhiều khả năng dùng `orders.meta` hoặc bảng `shipment` riêng.
- Làm đúng mẫu `SearchEngine`/`PushSender`: `interface Carrier` + driver, **queued job**,
  webhook có xác thực chữ ký (§17.5: *chữ ký hợp lệ ≠ nội dung đúng* — xác thực xong mới
  bắt đầu kiểm tra nội dung).

---

## P1 — Vận hành / chất lượng

### 4. Hạ tầng production còn lại
- ⬜ **CDN** cho `public/` (media + build assets) khi deploy.
- ⬜ Rà lại DB index sau khi có traffic thật (`add_performance_indexes` đã làm nền).

Xem [guides/deployment.md](guides/deployment.md) cho runbook đầy đủ.

> **Đã xong (2026-07-20 → 07-23), gỡ khỏi danh sách:** Lunar về lại composer package ·
> 13 module chuyển sang layout nwidart v13 (`module.json` + `priority`) · seed đủ tầng
> SKU/review/tồn kho · gallery đổi theo màu + xoá N+1 trên `/api/v1/products` ·
> sửa `db:seed` chết ở `HeaderMenuSeeder` (self-cascade MySQL) · gom tài liệu vào `docs/`.
> Chi tiết ở changelog [architecture/overview.md](architecture/overview.md) #17–#21.

### 5. Test còn thiếu
- ⬜ `modules/<Name>/tests/` **vẫn trống** — toàn bộ 68 file ở `tests/Feature`. Thêm smoke
  test cạnh module **khi chạm module đó**, không làm một lượt.
- ⬜ Phần thuần-JS chưa phủ: picture/srcset, search-panel, notify-me UI, lookbook-shoppable.
  Cần browser driver (Dusk/Playwright) — quyết định riêng, không phải việc nhỏ.
- ✅ ~~1 test đỏ (`OnDemandConversionTest:76`)~~ — đã xanh (kiểm lại 2026-07-23:
  `OnDemandConversionTest` 7/7 pass). Toàn bộ suite **432 test xanh**.

---

## P2 — Tăng chuyển đổi / trải nghiệm

### 6. Quick-view — *theme*
Modal xem nhanh sản phẩm từ grid (vanilla, đọc `/api/v1/products/{slug}`), add-to-cart không
rời trang listing. Đã chừa chỗ, cố ý hoãn.

### 7. Size Intelligence — phần nice-to-have
Đã có: hồ sơ số đo, fit history (giữ vs trả, between-sizes).
- ⬜ Gợi ý fit theo **lịch sử mua của người có số đo tương tự** (cần đủ dữ liệu mới có nghĩa).

### 8. Admin nhập nội dung đa ngôn ngữ
`translateAttribute` của Lunar đã sẵn; đây là **hướng dẫn vận hành**, không phải code.

---

## P3 — Khi quy mô lớn hơn

### 9. Scout search driver (Meilisearch / Typesense) — *Catalog*
`config/scout.php` đã có; engine active vẫn `DatabaseSearchEngine`. Chỉ cần khi catalog lớn
hoặc cần typo-tolerance + facet nhanh.
**Cách làm:** viết `ScoutSearchEngine` sau interface `SearchEngine` → đổi config,
**zero** thay đổi caller.

### 10. Analytics nâng cao — *Analytics*
Dashboard KPI/trend/best-seller đã có. Mở rộng: top size/màu bán chạy, tỉ lệ đổi-trả theo
size (gắn với RMA), export báo cáo.

### 11. Storefront Next.js (headless) ⏸ — **ĐÃ ĐÓNG BĂNG 2026-07-13**
Đã từng chạy (Next.js 16 ở `../storefront`, tiêu thụ `/api/v1` qua bearer + `X-Cart-Token`),
nay **dừng để tập trung Blade SSR** — storefront chính thức và duy nhất.

**Không phải công cốc:** chính client đó làm lộ bug bearer-token ở 3 probe công khai
(architecture/overview.md increment #14) — thứ chỉ lộ ra khi có client thật.

#### ⚠️ `/api/v1` **KHÔNG** phải "API cho headless"
Nó là **xương sống của chính Blade SSR**: **14 file JS** trong `themes/fashion` đang gọi nó
(cart, coupon, search + suggest, notify-me, recommend-size, locations, membership, auth).
**Gỡ/khoá API = gãy storefront ngay.** Vì thế "đóng băng" ở đây là đóng băng **bề mặt**,
không phải đóng băng code — **không đụng một dòng code nào**, 394 test giữ nguyên.

#### Luật: **GIỮ, KHÔNG MỞ RỘNG** (đã ghi vào [routes/api.php](../routes/api.php))
- Thêm endpoint/shape vì **Blade SSR cần** → bình thường, cứ làm.
- Thêm vì *"sau này app dùng"* / *"để sẵn cho lúc quay lại headless"* → **KHÔNG**. Đó là
  build cho một consumer không tồn tại — đúng cái bẫy audit § Phần 4 đã tránh khi hoãn
  `/home-feed`.

**Nhóm route hiện KHÔNG có consumer Blade** (đo bằng grep trên `themes/fashion`, 2026-07-13)
— giữ cho chạy, **đừng nuôi lớn**:
`/home-feed` · `/devices` · `/notifications` (+read, read-all) · `/orders/{id}/timeline` ·
`/auth/token/*` · `/banners` · `/pages` · `/collections/{slug}` · `/wishlist` · `/orders` ·
`/products/{product}/reviews` · `/checkout/*` · `/health` (probe hạ tầng).

> **Không gỡ `/auth/token/*` để "dọn cho sạch".** Nó mang theo token expiry + abilities —
> guard đã có mutation-check (increment #4). Giữ thì tốn 0 đồng; gỡ thì **xoá mất một lớp
> bảo mật đã được chứng minh**, đổi lấy không gì cả.

**Ngưỡng quay lại (bỏ đóng băng):** quyết định làm headless/mobile app **thật** — có người
dùng thật, không phải "phòng xa".

### 12. Omnichannel / POS ⏸ và AI ⏸
Đã khảo sát rồi **cố ý dừng** — lý do + số liệu đo được ở
[platform_audit.md § Phần 4](history/2026-07-platform-audit.md#phần-4--việc-đã-khảo-sát-rồi-cố-ý-dừng).

---

## Cấu hình: cái gì ra admin, cái gì ở lại config

**Đã ra Filament** (đọc qua `Modules\Core\Support\Settings`, DB → fallback config/env): payment keys
(VNPay/MoMo) + default method · shipping flat-rate + free-threshold · membership tiers ·
recommendations (limit/TTL) · review auto-approve · media on-demand mode · low-stock threshold ·
**thời gian giữ hàng đơn chưa trả** (`inventory.hold_minutes`) · **bật/tắt push**
(`notification.push_enabled`) · **TTL đăng nhập app** (`customer.ttl_days`).

**Cố ý giữ trong config** (kỹ thuật/bảo mật, không phải quyết định kinh doanh):
`recommend.strategies` · `inventory`/`cart`/`media` pipeline-overrides · tax-inclusive ·
Scout/Typesense · FFmpeg · media disks · `theme.locales` (cần file dịch tồn tại) ·
`notification.push.driver` (tên **class**, resolve trong `register()` **trước khi** DB sẵn
sàng — chọn driver chưa cài là vỡ mọi request) · `customer.tokens.abilities` (scope bảo
mật; nới rộng từ web form là privilege escalation).

> **Bẫy `Settings::put()`:** nó thay **cả group**. Trang admin phải ghi **mọi** khoá nó sở
> hữu ở mỗi lần lưu, nếu không lưu một field sẽ xoá field kia thành `NULL` (đo được).
> Group chỉ **một cấp**: `get('customer.ttl_days')` → group `customer`, key `ttl_days` —
> nên khoá đưa ra admin phải nằm **phẳng** trong config, không lồng.

> `analytics.paid_statuses` vẫn là config key (admin chỉnh được), nhưng **code không đọc
> trực tiếp** — mọi nơi đi qua `Modules\Order\Support\OrderStatus::paid()`. Trước đây mảng
> fallback bị copy-paste ra 5 service và đã trôi khỏi nhau.

---

## Nguyên tắc phạm vi (nhắc lại)

**KHÔNG** build ở giai đoạn SME single-store: multi-vendor/marketplace · visual drag-drop
editor · microservices/GraphQL · headless SPA tách rời · plugin/hook engine · Repository ·
Action layer · BFF · module rỗng (ERP/CRM/Loyalty/Wallet) · AI recommendations.

Lý do + **ngưỡng kích hoạt** từng mục:
[plan.md § Quyết định có chủ đích](architecture/overview.md#quyết-định-có-chủ-đích--không-phải-thiếu-sót).
