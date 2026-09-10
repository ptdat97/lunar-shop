# SME Fashion Ecommerce — Việc còn lại

> **Chỉ ghi việc CHƯA làm.** Hiện trạng ở
> [architecture/overview.md](architecture/overview.md); lịch sử bug đã sửa ở
> [history/2026-07-platform-audit.md](history/2026-07-platform-audit.md).
> Xếp theo ROI giảm dần. Cập nhật: **2026-08-30**.
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

- ✅ **Error tracker** — đã cắm Sentry (2026-09-10). **TẮT mặc định**: không có
  `SENTRY_LARAVEL_DSN` thì SDK không gửi gì và không mở kết nối nào. Cố ý như vậy — bật
  một đường truyền dữ liệu ra bên thứ ba phải là quyết định có người bấm nút, không phải
  hệ quả phụ của việc cài package. **Việc còn lại của anh: tạo project trên Sentry và đặt
  DSN vào `.env` production.** `shop:preflight` cảnh báo (không chặn) nếu production chạy
  mà chưa đặt.

  Phần tốn công nhất không phải cắm SDK mà là **lọc dữ liệu trước khi gửi đi**.
  `send_default_pii => false` chặn được phần lớn, nhưng không chặn những thứ mang dữ liệu
  khách theo đường vòng — và với shop thì đó mới là chỗ nguy hiểm: một lỗi ở
  `/payment/vnpay/return?vnp_SecureHash=…&vnp_TxnRef=…` mang nguyên chữ ký thanh toán
  trong URL, một trường Sentry không có lý do gì để coi là đặc biệt. `SentryScrubber` xoá
  query string, header xác thực, email/điện thoại/địa chỉ, và ràng buộc câu SQL; giữ lại
  **đúng id** người dùng vì không có định danh nào thì không khớp báo lỗi với người báo được.

- ✅ **CI** — `.github/workflows/ci.yml` chạy trên mọi push và PR vào `main`: PHPUnit trên
  MySQL 8 thật, Dusk (smoke trình duyệt), `composer audit` + `npm audit`, và Pint. Xem
  [deployment.md §9](guides/deployment.md).

- ⬜ **Còn thiếu: `CSP_REPORT_URI`.** Header bảo mật đã có và CSP đang chạy chế độ
  `report`, nhưng chưa có chỗ nhận báo cáo — nên vi phạm chỉ tồn tại trong DevTools của
  người đang mở trang. Sentry có sẵn endpoint nhận CSP report; đặt cùng lúc với DSN thì
  mới đọc được vi phạm thật để quyết định có bật `CSP_MODE=enforce` hay không.

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
- ~~**Còn thiếu, phải thêm:** cột/bảng lưu **mã vận đơn + link tracking**~~
  ✅ **Bản nâng cấp Lunar 2.0 đã giải quyết (2026-09-10).** `lunar_fulfilment_trackings`
  có sẵn (carrier / tracking_number / tracking_url / shipping_method, nhiều mã cho một
  fulfilment), panel có `AddTrackingDialog` viết vào đó, và `ShippingCarrier::getTrackingUrl()`
  là chỗ cắm link tra cứu khi nào có hợp đồng. Không phải nghĩ tới `orders.meta` hay
  bảng `shipment` tự chế nữa.

  **Việc mở khoá được NGAY, không cần hợp đồng:** `AddFulfilmentTracking` tra hãng trong
  `CarrierManifest` và **bỏ qua** phần validate định dạng khi không tìm thấy. Nghĩa là gõ
  tay `carrier: "ghtk"` + mã vận đơn là lưu bình thường. Toàn bộ quy trình thủ công mô tả
  ở trên — chủ shop tự sang web hãng tạo vận đơn rồi copy mã — giờ có chỗ lưu trong panel
  thay vì nằm ngoài hệ thống, và khách hỏi "hàng tới đâu" thì tra được trên đơn.
  Chốt bằng `tests/Feature/FulfilmentTrackingTest.php`.

  ⚠️ `CarrierManifest` đang có 4 mặc định của Lunar: `royal-mail`, `dpd`, `ups`, `fedex`
  — hãng Anh/Mỹ, vô dụng với shop Việt. **Cố ý chưa đăng ký hãng Việt Nam nào**: chọn
  hãng nào là quyết định vận hành, và đúng nguyên tắc ghi ở đầu mục này là không code
  trước theo tài liệu. Khi ký hợp đồng thì `Carriers::set()` thay cả bốn cái đó bằng
  đúng một hãng đã ký.
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

## Rà soát năng lực nền tảng (2026-08-27)

Đối chiếu một lượt năng lực mà nền tảng thương mại điện tử thường có với hiện trạng repo,
để biết còn thiếu gì đáng làm. **Bốn mục tìm ra đã làm xong** (§13–16 cũ) — chi tiết ở
[architecture/overview.md § Nhật ký](architecture/overview.md): nhận tại cửa hàng, dây bảo
hiểm cho cron, điều hướng đáy + bộ lọc bottom-sheet mobile, báo cáo nội dung thiếu bản dịch.

### Đã có sẵn — *đừng đề xuất lại*

Rà thấy phần storefront gần như đã đủ. Những thứ sau **đã chạy**, kiểm bằng grep chứ không
phải phỏng đoán:

breadcrumb + JSON-LD (`Product`/`Offer`/`Brand`/`ItemList`/`BreadcrumbList`/`WebPage`) ·
sitemap · canonical + hreflang · wishlist · hạng thành viên · size guide · notify-me hết
hàng · review · lookbook · push · giỏ bỏ quên · sổ địa chỉ · infinite scroll · thanh tiến
trình free-ship · giá vốn + biên lợi nhuận · timeline đơn · khoá tài khoản sau nhiều lần
sai mật khẩu · GA + Facebook pixel.

### Còn treo

- 🟡 **Lunar 2.0 + panel Inertia/Vue** — **Fase 0→3 xong** (2026-09-09):
  `lunarphp/core` + `lunarphp/panel` `2.0.0-alpha.6`, Filament đã gỡ, 560 test
  xanh, panel phục vụ 370 route. Món nợ reflection trong `ModulesServiceProvider`
  đã trả (file đó bị xoá). Nhật ký thực thi + năm câu hỏi mở đã trả lời:
  [guides/upgrade-lunar-2.0.md](guides/upgrade-lunar-2.0.md) §8–9.

  **Còn lại — Fase 4: viết lại admin bằng Vue.** Hiện **không có giao diện quản
  trị riêng của dự án**; backend (`Settings`, `SkuBuilderService`,
  `MediaLibraryService`…) còn nguyên, chỉ thiếu UI. Thứ tự rủi ro giảm dần:
  MediaPicker/MediaBrowser (14 call site) → ManageProductVariants → 9 trang cấu
  hình → 6 resource Nội dung → RMA/ShippingZone/SizeChart/Kho/MediaLibrary/
  QueueWorkers → Analytics widget. Kèm Fase 5: dựng lại 8 method test đã cắt khỏi
  bốn file test settings, và một smoke test mọi route panel.

  ⚠️ Vẫn là **alpha**: API đổi theo tuần. Đọc kỹ changelog trước mỗi lần bump.

- ⬜ **Phân quyền của panel** — câu hỏi mở duy nhất còn lại từ đợt khảo sát:
  panel dùng chuỗi permission riêng (`sales:manage-customers`), chưa rõ khớp thế
  nào với `spatie/laravel-permission` dự án đang dùng. **Phải trả lời trước khi
  viết trang admin đầu tiên.**

- ⬜ **Uptime check bên ngoài.** Dây bảo hiểm cho cron chỉ báo khi *một job* lặng đi; nếu
  **cả scheduler** chết thì heartbeat chết theo. Cần một dịch vụ ngoài gọi lệnh kiểm tra
  (xem [deployment.md §4.1](guides/deployment.md)). Đây là mảnh cuối, và nó nằm ngoài code.

### Cố ý KHÔNG làm

| Năng lực | Vì sao không |
|---|---|
| Theme engine + trình dựng khối kéo-thả | Đúng thứ [Nguyên tắc phạm vi](#nguyên-tắc-phạm-vi-nhắc-lại) đã loại: *visual drag-drop editor*. Một shop, một theme — chi phí bảo trì không đổi lấy được gì |
| Sổ cái kế toán kép, đóng sổ cuối tháng, hoá đơn hoa hồng | Quy mô doanh nghiệp nhiều gian hàng. Repo đã có giá vốn + biên lợi nhuận trên từng dòng đơn — đủ trả lời "lãi bao nhiêu" |
| Khám phá theo vị trí, sắp xếp theo khoảng cách | Địa lý của marketplace: chỉ có nghĩa khi nhiều người bán ở nhiều nơi. Một cửa hàng thì phần dùng được là **nhận tại cửa hàng**, đã làm |
| So sánh sản phẩm | Mạnh với đồ điện tử nhiều thông số; với thời trang khách so sánh bằng **ảnh và size**, mà cả hai đã có ở gallery + size guide |
| Hộp thư hợp kênh | Đã khảo sát rồi cố ý dừng — xem [§12](#12-omnichannel--pos--và-ai-) |
| Boosting sản phẩm, gói đăng ký người bán, hoa hồng | Chỉ tồn tại khi có nhiều người bán |
| POS, marketplace nhiều gian hàng, sản phẩm số, trợ lý bán hàng AI | Ngoài phạm vi SME một cửa hàng |

---

## Cấu hình: cái gì ra admin, cái gì ở lại config

**Đã ra admin** (đọc qua `Modules\Core\Support\Settings`, DB → fallback config/env — backend
còn nguyên, UI chờ Fase 4): payment keys
(VNPay/MoMo) + default method · shipping flat-rate + free-threshold · **nhận tại cửa hàng**
(bật/tắt + địa chỉ + giờ mở cửa + hướng dẫn) · membership tiers ·
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
