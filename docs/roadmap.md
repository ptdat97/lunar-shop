# SME Fashion Ecommerce — Việc còn lại

> **Chỉ ghi việc CHƯA làm.** Hiện trạng ở
> [architecture/overview.md](architecture/overview.md); lịch sử bug đã sửa ở
> [history/2026-07-platform-audit.md](history/2026-07-platform-audit.md).
> Xếp theo ROI giảm dần. Cập nhật: **2026-09-18** (mục 16 điểm thưởng + phần ảnh còn lại của mục 17 xong).
>
> **Thứ tự ưu tiên đã đảo lại (2026-07-13).** Trước đây danh sách này mở đầu bằng
> tính năng chuyển đổi (size intelligence, search engine). Rà lại code cho
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

### 1. ⚠️ Rotate `APP_KEY` — CHẶN DEPLOY

**Đã rà lại toàn bộ 314 commit (2026-09-18). Phạm vi hẹp hơn mục này từng ghi.**

- ⬜ **`APP_KEY`** nằm trong git history (9 commit, `.env` bị commit) và **`.env`
  hiện tại vẫn đang dùng đúng khoá đó** — rotate chưa từng xảy ra. Quy trình 4
  bước: [guides/deployment.md §10](guides/deployment.md#10-rotate-app_key).
- ✅ **Và nó không chỉ nằm trong history: `.env.example` — file ĐANG tracked —
  mang đúng khoá đó.** Ai clone repo cũng có, không cần đào history. Đã thay
  bằng chỗ trống (2026-09-18). Lượt rà đầu tiên bỏ sót vì chỉ quét `.env`, nên
  giờ có `KeyRotationTest::no_tracked_file_carries_a_compromised_key` quét mọi
  file tracked — chính lớp lỗi mà mắt người vừa trượt.
- ✅ **Phần còn lại KHÔNG bị lộ** — đo được, không phải phỏng đoán:
  `DB_PASSWORD` / `MAIL_PASSWORD` / `REDIS_PASSWORD` / AWS đều **rỗng hoặc
  `null`** ở cả 10 bản `.env` từng commit; key VNPay/MoMo **chưa từng** nằm trong
  `.env` (chúng đọc qua `Settings`, không qua env); không có secret nào hardcode
  trong PHP ở bất kỳ commit nào.
- ✅ **Có lưới an toàn rồi.** `shop:preflight` **chặn deploy production** khi
  `APP_KEY` đang chạy khớp một digest trong `config/security.php`
  (`compromised_app_keys` — lưu SHA-256, không lưu khoá, vì file này nằm đúng
  trong repo đã làm lộ khoá). Ở máy dev chỉ cảnh báo.
- ✅ **Và có lệnh cho bước không ai nghĩ tới:** `shop:reencrypt` chuyển dữ liệu
  đã mã hoá sang khoá mới. Thiếu nó thì đổi khoá = **khoá mọi staff bật 2FA ra
  khỏi panel**, và khoá bằng cách 500 chứ không báo "mã sai". Bỏ bước này
  **không có triệu chứng gì** cho tới lúc ai đó dọn `APP_PREVIOUS_KEYS`, có thể
  vài tháng sau.

> **Việc của anh, không ai làm hộ được:** chạy 4 bước ở §10 trên server
> production. Mọi thứ quanh nó — cổng chặn, lệnh chuyển dữ liệu, runbook — đã
> xong.

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

- 🟡 **Còn thiếu: `CSP_REPORT_URI`.** Header bảo mật đã có và CSP đang chạy chế độ
  `report`, nhưng chưa có chỗ nhận báo cáo — nên vi phạm chỉ tồn tại trong DevTools của
  người đang mở trang. Sentry có sẵn endpoint nhận CSP report; đặt cùng lúc với DSN thì
  mới đọc được vi phạm thật để quyết định có bật `CSP_MODE=enforce` hay không.
  `shop:preflight` đã cảnh báo (không chặn) nếu production chạy `report`/`enforce` mà
  thiếu URI (2026-09-14, chốt bằng 3 test trong `PreflightTest`).

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
code trước theo tài liệu (đoán shape rồi sửa lại là lãng phí — đúng bài học Phase 4
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
- 🟡 Phần thuần-JS: **browser driver không còn là câu hỏi** — Dusk đã chạy trong
  CI từ 2026-09-10, và `tests/Browser` nay có 13 test. Nhưng đích **không phải
  phủ hết JS**: [e2e-testing.md §1](guides/e2e-testing.md) chốt rằng chỉ viết E2E
  cho thứ test feature không chứng minh được, và `tests/Browser` cố ý giữ nhỏ.
  Hai bề mặt tương tác mới nhất đã có (2026-09-18): gửi đánh giá kèm ảnh, và ô
  tiêu điểm ở trang thanh toán — cả hai kiểm *thao tác có chạy không*, không
  kiểm *phần tử có hiện không*.
  ✅ **Bốn mục còn lại đã phủ xong (2026-09-18)** — nhưng không phải bằng bốn
  test Dusk. Soi từng cái theo bảng quyết định trước:
  - **picture/srcset** → **test feature**, không phải E2E: component không có
    một dòng JS nào, bằng chứng nằm ở HTML server trả về
    (`ResponsivePictureTest`). Phần thuộc trình duyệt duy nhất — ảnh 404 —
    `StorefrontSmokeTest` đã canh sẵn.
  - **search-panel** → E2E: biểu tượng là link thật, nên `preventDefault` hỏng
    thì triệu chứng là **trang đi mất**, server không thấy gì.
  - **notify-me** → E2E: hợp đồng `variant:changed` giữa hai file JS, không đi
    qua server lần nào. Ghim từ cả hai đầu mà **không ghi gì vào DB dev**.
  - **lookbook "mua cả set"** → E2E: vòng lặp POST tuần tự; hỏng thì chỉ món đầu
    vào giỏ, mà giỏ vẫn hợp lệ nên không có gì bất thường phía server.
- ✅ ~~1 test đỏ (`OnDemandConversionTest:76`)~~ — đã xanh (kiểm lại 2026-07-23:
  `OnDemandConversionTest` 7/7 pass). Toàn bộ suite **432 test xanh**.

### 6. Ảnh theo màu không có chỗ sửa trên panel — *Catalog + Assets* ✅ **XONG 2026-09-19**
- Chọn hướng **"storefront đọc pivot của Lunar, bỏ cột"**: gallery giờ là liên kết tới
  thư viện, nên lý do cột `image_asset_ids` tồn tại (không copy file) đã hết, và giữ
  hai kho cho một thứ chính là lỗi đã gặp. Ảnh biến thể = `media_product_variant` của
  Lunar; sửa theo màu ở ô **"Ảnh theo màu"** trên trang sản phẩm, theo từng biến thể ở
  ô chính chủ của Lunar — cùng dữ liệu. Cột đã xoá bằng migration (dữ liệu chuyển
  sang pivot). Kèm theo: sửa lỗi seeder ghi id media vào cột "asset id" khiến ảnh theo
  màu hiện sai. Chi tiết ở [overview.md § Ảnh theo màu](architecture/overview.md).

---

## P2 — Tăng chuyển đổi / trải nghiệm

### 7. Size Intelligence — phần nice-to-have
Đã có: hồ sơ số đo, fit history (giữ vs trả, between-sizes).
- ⬜ Gợi ý fit theo **lịch sử mua của người có số đo tương tự** (cần đủ dữ liệu mới có nghĩa).

### 8. Admin nhập nội dung đa ngôn ngữ
`translateAttribute` của Lunar đã sẵn; đây là **hướng dẫn vận hành**, không phải code.

> **Chốt 2026-09-10** sau một đợt khảo sát xem shop đã có gì. Bốn mục 13–16 dưới đây
> là phần được chọn để làm; ba việc làm TRƯỚC chúng (việt hoá chuỗi JS → cứu giỏ hàng
> bỏ quên → xin đánh giá sau mua) nằm ở mục 17.
>
> Nguyên tắc xếp hạng: **tính theo hạ tầng đã có**, không tính theo độ hấp dẫn của tính
> năng. Cái gì chỉ còn thiếu lớp hiển thị thì rẻ; cái gì cần một sổ cái mới thì đắt, dù
> nghe hấp dẫn hơn.

### 13. Thanh tiến độ freeship — *theme* · **công: rất thấp**

Dữ liệu **đã tính sẵn**: `CartResource::freeShippingInfo()` trả về `qualified`,
`threshold`, `remaining` và `progress` (phần trăm). Hiện chỉ được in ra **một dòng chữ**
trong `enhance/cart.js`.

- ✅ **Dựng thành thanh tiến độ trong mini-cart và trang giỏ** (2026-09-13). Helper
  chung `freeShippingHtml()` trong `enhance/cart.js` render thanh Bootstrap progress
  + dòng nhắc đã dịch, dùng ở cả `cart-drawer` (qua `renderDrawer`) lẫn trang giỏ
  (`[data-cart-shipping]` mới thêm trong `cart.blade.php`, render bởi `cart-page.js`).
  Trang giỏ trước đó **không có** chỗ hiển thị freeship nào. Kèm `is-complete` class
  cho trạng thái đạt ngưỡng. Không đụng backend — chốt bằng
  `tests/Feature/FreeShippingProgressTest.php` (hợp đồng `free_shipping` vẫn đủ
  `qualified`/`threshold`/`remaining`/`progress`, và khối SSR `data-cart-shipping`).
- ✅ Không đụng backend. Đây là lý do nó đứng đầu danh sách: đòn bẩy AOV mà phần đắt tiền
  đã làm xong từ trước.

### 14. Badge "đúng size của bạn" — *Catalog + theme* · **công: thấp** ✅ **XONG 2026-09-14**

`FitHistoryService` (giữ vs trả, between-sizes) và `SizeRecommender` **đã có** và đang
chạy ở trang chi tiết. Đã gắn lên **thẻ sản phẩm**:

- ✅ Badge lên thẻ cho khách đã đăng nhập và đã có lịch sử vừa vặn — SSR
  (`theme::components.product-card` qua composer trong `CatalogServiceProvider`) và
  lưới JS (`enhance/_card.js` đọc `fit_size`) hiển thị cùng một thẻ.
- ✅ Chỉ hiện khi tin cậy đủ cao: `FitHistoryService::badge()` là luật BẢO THỦ riêng
  với trang chi tiết — chỉ size khách đã mua và GIỮ mới thành badge; suy đoán bước
  lên/xuống từ đồ trả và cảnh báo between-sizes ở lại trang chi tiết (`for()`), nơi
  có chỗ giải thích. Khách vãng lai, user chưa link customer, và size suy đoán →
  không badge (null), nhưng key `fit_size` luôn có để hợp đồng grid ổn định.
- ✅ Không N+1: `FitBadgeService` (scoped) memoise badge theo product + resolve
  customer một lần/request; lịch sử size load 1 query/request và chart map 1 pivot +
  1 rows query — cả lưới tốn số query phẳng. Chốt bằng
  `tests/Feature/ProductCardFitBadgeTest.php` (8 test: JSON, SSR, guest, user lạ,
  giữ-thắng-trả, suy-đoán-không-thành-badge, không rò lịch sử chéo, flat queries)
  và cập nhật hợp đồng `HomeFeedTest` (`fit_size`).

### 15. Giới thiệu bạn (referral) — *Promotion + Customer* · **công: trung bình** ✅ **XONG 2026-09-15**

Hạ tầng mã giảm giá đã có (`CartService::applyCoupon`, `lunar_discounts` với cột
`coupon`). SME thời trang lớn lên bằng truyền miệng, nên đây là kênh thu khách rẻ nhất.

- ✅ Mỗi khách một mã riêng (`referral_codes`), link `/r/{code}` giữ mã trong
  session tới lúc đăng ký. Người được mời giảm giá lần đầu (coupon Lunar thật
  một lần dùng, phát lúc đăng ký), người mời nhận thưởng **khi đơn kia đã qua
  hạn đổi/trả**, không phải khi trả tiền: listener `OrderPaid` chỉ ĐÁNH DẤU
  (`awaiting`), lệnh `referrals:release` (hằng ngày 09:30) mới PHÁT.
- ✅ Chống tự giới thiệu chính mình và chống trại mã: ghép theo `user_id`
  + vân tay thiết bị (ghi lúc mở trang tài khoản, chặn lượt đăng ký từ cùng máy
  mà `user_id` không thấy được), một người chỉ được giới thiệu một lần.
  Đơn đã trả lại hoặc hoàn tiền **không bao giờ** được thưởng (chung luật với
  email xin đánh giá qua `OrderStatus::wasReturnedOrRefunded`).
- ✅ Hàng đợi giới thiệu trong panel (chỉ xem + phát sớm + đóng lượt gian lận),
  trang cài đặt riêng (group `referral`, TẮT mặc định), khối SSR trên trang tài
  khoản + nút copy link (JS chỉ enhance). Chốt bằng
  `tests/Feature/ReferralTest.php` + `tests/Feature/ReferralRewardTest.php`
  (mã dùng một lần kể cả khi bị từ chối, đơn đầu-tiên-tính, thưởng một lần,
  `--dry-run`, chỉ-phát-sau-hạn).

### 16. Điểm thưởng — *Promotion* · **công: cao** ✅ **XONG 2026-09-18**

Hạng thành viên là **thụ động**: `MembershipService` xếp hạng theo tổng chi tiêu và cho
giảm giá theo hạng — khách không có việc gì để làm. Điểm là phần chủ động.

- ✅ **Sổ cái `loyalty_entries`, không phải một cột số dư.** Số dư là
  `SUM(points) WHERE available_at IS NULL OR available_at <= now` — dẫn xuất, đối
  soát được. Mỗi bút toán cộng là một **lô**; mỗi bút toán trừ trỏ về lô nó ăn
  (`lot_id`), nên "lô này còn lại bao nhiêu" cũng dẫn xuất. Không có `lot_id` thì
  tới lúc hết hạn không ai biết đóng bao nhiêu.
- ✅ **Hạn dùng là bút toán, không phải điều kiện truy vấn.** `loyalty:expire`
  (hằng ngày 09:45) ghi dòng trừ cho phần chưa tiêu của lô quá hạn. Lọc ngầm
  trong câu truy vấn thì sổ không cộng lại thành số dư được nữa.
- ✅ **Không có lệnh "phát điểm".** Khác §15 (thưởng giới thiệu là một coupon phải
  được TẠO), ở đây `available_at = ngày trả tiền + hạn đổi/trả` làm xong việc:
  điểm nằm ngoài số dư cho tới lúc đó, và đơn bị trả lại trong lúc chờ thì lô bị
  thu hồi khi còn nguyên.
- ✅ **Tiêu điểm: điểm là hình thức THANH TOÁN, không phải khuyến mãi.** Chặng
  `RedeemLoyaltyPoints` nối đuôi `lunar.cart.pipelines.cart` (sau `Calculate`,
  nếu không `Calculate` ghi đè `total`), trừ SAU thuế và **không đụng**
  `discountTotal`. Hệ quả có chủ đích: tiêu điểm không giảm thuế phải nộp, và vì
  điểm tính trên `total` cuối cùng nên tiêu điểm **không đẻ ra điểm** — vòng lặp
  tự nuôi bị chặn bởi chính chỗ đặt phép trừ.
- ✅ Hoàn tiền / huỷ đơn: hai chiều ngược nhau và **cả hai** phải xảy ra — thu hồi
  điểm đã cộng (`wasReturnedOrRefunded`, chung luật với §15 và email xin đánh giá)
  **và** trả lại điểm đã tiêu (`isClosed`).
- ✅ Sổ cái trong panel **chỉ đọc** (sửa dòng cũ là phá mất khả năng đối soát),
  trang cài đặt riêng (group `loyalty`, TẮT mặc định), khối SSR trên trang tài
  khoản + ô tiêu điểm ở trang thanh toán (JS chỉ enhance). Chốt bằng
  `tests/Feature/LoyaltyPointsTest.php` (24 test).

  **Lỗi thiết kế tìm ra khi viết test, đáng ghi lại:** bút toán trừ ban đầu để
  `available_at` NULL nên có hiệu lực NGAY, trong khi lô nó ăn vào còn đang chờ →
  thu hồi điểm của một đơn vừa bị trả lại cho ra **số dư −10**. Luật đúng: **dòng
  trừ thừa hưởng `available_at` của lô nó ăn**. Cả bốn đường trừ đi qua một hàm
  duy nhất (`LoyaltyService::debit()`) vì thế.

- ⚠️ **Chỗ còn hở, cố ý:** hai lượt thanh toán ĐỒNG THỜI của cùng một khách có thể
  tiêu quá số dư (giỏ kẹp theo số dư lúc TÍNH, bút toán trừ ghi lúc đơn đã tạo).
  Kết quả là số dư âm — **nhìn thấy được, đối soát được**, và khách không tiêu
  tiếp được cho tới khi nó dương lại. Đổi lấy việc không phải dựng một vòng đời
  "giữ chỗ điểm" song song với giữ chỗ tồn kho. Nếu sau này sai, chỗ sửa là
  `LoyaltyService::commitRedemption()`.

### 17. Ba việc làm trước mục 13–16 — ✅ **XONG 2026-09-10**

- ✅ **Việt hoá chuỗi JS storefront** — storefront **không có cơ chế i18n cho JS**, nên
  ~30 chuỗi nằm cứng trong code, rơi đúng vào lúc khách sẵn sàng mua nhất: giỏ hàng
  (*"Add 250.000 ₫ more for free shipping."*), áp mã giảm giá (*"Coupon applied."*),
  hạng thành viên (*"Spend … more to reach …"*), size finder, báo hàng về, và 12 chuỗi
  trong `account.js`. Đây là lỗi đang chảy máu tiền, không phải việc đánh bóng.
- ✅ **Cứu giỏ hàng bỏ quên** — `carts:remind-abandoned`, một email mỗi giỏ, **tắt mặc
  định** (Cài đặt → Thanh toán & giỏ hàng). Đánh dấu TRƯỚC khi gửi: mất một lời nhắc còn
  hơn gửi hai. Không kèm mã giảm giá — email cứu giỏ luôn tặng coupon là dạy khách bỏ giỏ
  có chủ đích. Link khôi phục khoá theo `public_token`, chết theo giỏ (đã mua/đã gộp/quá
  7 ngày).
- ✅ **Xin đánh giá sau mua** — `orders:request-reviews`, mốc tính từ
  `lunar_fulfilments.shipped_at` (2.0 đã bỏ `orders.dispatched_at`, vòng đời là phái
  sinh). Đơn đã trả hàng hoặc hoàn tiền **không bao giờ** bị hỏi.

  ⚠️ **Phát hiện giữa chừng:** storefront **chưa từng hiển thị đánh giá ở đâu cả** —
  đánh giá chỉ tồn tại ở API và màn hình duyệt trong panel. Email xin đánh giá mà không
  có chỗ để đánh giá thì tệ hơn không gửi, nên phần hiển thị + form đã được dựng cùng
  lượt này (`partials/reviews.blade.php`, neo `#danh-gia` — chính là neo email trỏ tới,
  đổi tên là hỏng mọi link đã gửi). SSR trước: đọc đánh giá KHÔNG phụ thuộc JS.

  ✅ **Đánh giá kèm ảnh — XONG 2026-09-18.** `product_reviews` nay có `user_id`,
  `order_id` và media (collection `photos` treo thẳng vào đánh giá, **không** vào
  thư viện ảnh của admin).

  - Nhãn "đã mua hàng" đọc `order_id`, **không** đọc `user_id`: đăng nhập chỉ chứng
    minh một tài khoản, không chứng minh đã mua. Server tự tra đơn ĐÃ THANH TOÁN của
    chính khách có chứa sản phẩm đó (`OrderStatus::scopePaid` — nên đơn COD cũng
    tính); client không có trường nào chạm tới được.
  - **Ảnh LUÔN qua hàng đợi duyệt**, kể cả khi `review.auto_approve` đang bật: văn
    bậy thì đọc rồi gỡ, ảnh bậy thì người ta đã nhìn thấy rồi mới gỡ được. Hai rủi
    ro khác hạng nên không dùng chung một công tắc. Tên file được đặt lại ngẫu
    nhiên — ảnh nằm trên đĩa công khai, tên đoán được = xem được trước khi duyệt.
  - Hàng đợi duyệt hiện **mỗi ảnh một cột** (`Review::MAX_PHOTOS` sinh ra số cột).
    Trông thừa, nhưng panel chạy bundle biên dịch sẵn của vendor nên không thêm được
    cell renderer nhiều ảnh; gộp lại thì staff chỉ thấy ảnh đầu.

  Chốt bằng `tests/Feature/ReviewPhotoTest.php` (16 test). **Lỗi thật tìm ra khi
  viết test:** file bị chốt chặn của model từ chối thì dòng đánh giá ĐÃ được tạo
  rồi mới ném — 500 cho khách và một đánh giá mồ côi nằm chờ duyệt vĩnh viễn. Sửa
  bằng transaction + một danh sách mime duy nhất (`Review::PHOTO_MIMES`) cho cả
  request lẫn model.

  🎨 **Form đánh giá nay nằm trong popup (`#reviewForm`), 2026-09-18.** Mục đánh giá để
  ĐỌC trước; sáu ô nhập nằm giữa danh sách làm loãng đúng thứ khách vào đó để xem. Nút
  "Viết đánh giá" nằm cạnh tiêu đề và mở popup. Nhưng popup cần JS để Bootstrap gỡ
  `display:none`, nên khối `<noscript>` ngay dưới trả form về dạng tĩnh: **khách không JS
  vẫn gửi được**. Phần đọc không đổi (SSR, không phụ thuộc JS).

  Kéo theo hai chỗ sửa cùng lượt: `ReviewRequestTest` chốt thêm "có nút mở popup" (một
  popup không có nút mở là một form không ai gửi được), và `ReviewPhotoUploadTest` phải
  **mở popup trước khi gõ** — xem `docs/guides/e2e-testing.md` §"Khi test E2E đỏ".

### Cân nhắc rồi CỐ Ý chưa làm

- **Zalo OA** — ở Việt Nam tỷ lệ mở Zalo bỏ xa email, và module `Notification` đã có sẵn
  khuôn driver (`OrderSmsNotifier`, `DeviceRegistry`) nên thêm driver Zalo là đi theo mẫu
  chứ không dựng mới. Nó nhân hiệu quả cho **cả** cứu giỏ hàng lẫn báo trạng thái đơn và
  báo hàng về vốn đã chạy. ⏸ **Chặn ngoài code**: cần OA đã xác thực doanh nghiệp — cùng
  nhóm với vận chuyển (P0.5) và hoá đơn điện tử (P0 §3).
- **Analytics nâng cao** (mục 10, P3) — là đo đạc chứ không phải doanh thu, và shop
  chưa có traffic thật để đo.

---

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

### 12. AI ⏸
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

- ✅ **Lunar 2.0 + panel Inertia/Vue — XONG.** Fase 0→3 (2026-09-09) và
  **Fase 4 + 5 đã hoàn tất**: phần admin riêng của dự án đã viết lại trên panel
  (13 resource khai báo + 9 tab cài đặt + 2 widget dashboard + Slot Size & Fit).
  Nguồn sự thật là
  [architecture/overview.md § Admin](architecture/overview.md#admin-lunarphppanel--inertia--vue).
  *(Đoạn dưới đây giữ lại làm lịch sử của đợt nâng cấp.)*

  🟡 **Lunar 2.0 + panel Inertia/Vue** — **Fase 0→3 xong** (2026-09-09):
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

- ✅ **Phân quyền của panel — đã trả lời.** `Lunar\Core\Models\Staff` dùng đúng
  `Spatie\Permission\Traits\HasRoles` mà dự án đang dùng. Điều **không** hiển
  nhiên: `Gate::after` của panel chỉ cấp một ability khi manifest access-control
  biết đến nó, mà manifest dựng từ bảng `permissions` — nên quyền mới phải có hàng
  trong bảng, thiếu nó thì `can:` chặn tất cả, kể cả admin. Chi tiết:
  [guides/upgrade-lunar-2.0.md §8](guides/upgrade-lunar-2.0.md).

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
| Hộp thư hợp kênh | Chỉ có nghĩa khi bán trên nhiều kênh cùng lúc. Shop một cửa hàng, một storefront |
| Boosting sản phẩm, gói đăng ký người bán, hoa hồng | Chỉ tồn tại khi có nhiều người bán |
| Marketplace nhiều gian hàng, sản phẩm số, trợ lý bán hàng AI | Ngoài phạm vi SME một cửa hàng |

---

## Cấu hình: cái gì ra admin, cái gì ở lại config

**Đã ra admin** (đọc qua `Modules\Core\Support\Settings`, DB → fallback config/env): payment keys
(VNPay/MoMo) + default method · shipping flat-rate + free-threshold · **nhận tại cửa hàng**
(bật/tắt + địa chỉ + giờ mở cửa + hướng dẫn) · membership tiers ·
recommendations (limit/TTL) · review auto-approve · media on-demand mode · low-stock threshold ·
**thời gian giữ hàng đơn chưa trả** (`inventory.hold_minutes`) · **bật/tắt push**
(`notification.push_enabled`) · **TTL đăng nhập app** (`customer.ttl_days`) ·
**giới thiệu bạn** (group `referral`) · **điểm thưởng** (group `loyalty`: tỉ lệ cộng, giá
trị điểm, hạn chờ, hạn dùng, tiêu tối thiểu, trần % mỗi đơn).

> **Trần % của điểm không phải để tiết kiệm.** Để 100 thì một đơn trả hết bằng điểm là
> đơn 0 đồng, mà cổng thanh toán từ chối số tiền 0 — lỗi nổ ở đúng bước cuối của khách.

**Cố ý giữ trong config** (kỹ thuật/bảo mật, không phải quyết định kinh doanh):
`recommend.strategies` · `inventory`/`cart`/`media` pipeline-overrides · tax-inclusive ·
`review.photos` (trần kỹ thuật của đường tải lên; **số ảnh tối đa** thì không ở config mà
là hằng số `Review::MAX_PHOTOS`, vì nó phải khớp số cột ảnh của hàng đợi duyệt) ·
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
