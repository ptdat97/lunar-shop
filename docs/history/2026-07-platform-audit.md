# Biên bản audit & sửa lỗi (2026-07-09 → 2026-07-10)

> **File này là lịch sử, không phải hiện trạng.** Hiện trạng ở
> [plan.md](../architecture/overview.md). Mọi số liệu dưới đây là **snapshot của ngày
> ghi**, cố ý **không** cập nhật theo thời gian — chúng là bằng chứng, không phải mô tả.
>
> Giữ lại vì hai lý do: (1) mỗi bug đều **đo được**, để sau này không ai phải chứng minh
> lại; (2) các bài học rút ra đã đưa vào [coding_standards.md](../guides/coding-standards.md)
> §17 — đây là chỗ chúng có ngữ cảnh đầy đủ.

**Baseline lúc bắt đầu (2026-07-09):** 191 test / 596 assertion xanh · `vendor/` sạch ·
12 module · 52 route `api/v1` · 1 channel (`webstore`).
**Kết thúc (2026-07-10):** 349 test / 1513 assertion · 13 module · 62 route.

---

## Phần 1 — Audit nền tảng (Headless / Mobile / Omnichannel)

Câu hỏi: nền tảng có sẵn sàng phục vụ đồng thời Blade storefront, mobile app, POS,
marketplace connector — qua **một** business layer không?

Kết luận: kiến trúc **đúng** (modular monolith, Lunar không bị sửa, controller mỏng,
service là nguồn logic duy nhất). Nhưng có **một rào chắn kiến trúc thật** và **một bug
doanh thu**, cộng 10 nợ nhỏ hơn. Tất cả đã đóng.

| # | Phát hiện | Bằng chứng đo được |
|---|---|---|
| **A** | Cart/Checkout khoá vào session cookie → app/POS không dùng được | `CartSessionManager` inject `SessionManager`; mỗi request token tạo **cart mới** (75→76→77); mọi POST **419 CSRF** |
| **B** | COD (`payment-offline`) **không bao giờ** lên hạng thành viên | `OrderPaid::dispatch` chỉ có ở VNPay + MoMo processor |
| **C** | **Hai định nghĩa** "đã thanh toán" | `analytics.paid_statuses` có `payment-offline`, `promotion.membership.paid_statuses` **không**; ngược lại có `'paid'` (status không tồn tại) |
| **D** | **48/52** route `api/v1` không rate limit — gồm `POST /checkout` | `throttleApi()` chỉ phủ group `api`; cart/checkout/orders chạy group `web`/`storefront` |
| **E** | Hai `OrderResource` khác shape cho cùng entity | 23 khoá vs 9 khoá |
| **F** | Phân trang 2 chuẩn | `/products` → `meta{page,…}`; `/orders` → `{links, meta{current_page,…}}` |
| **G** | Không có tầng Notification | bảng `notifications` **MISSING**; `notify()` trong `ReturnService` là method private, không phải Laravel Notification |
| **H** | `GET /api/user` còn sót — không version, trả thẳng model `User` | `routes/api.php:6` |
| **I** | Token Sanctum **không hết hạn**, không abilities | `sanctum.expiration = null`; `createToken($name)` không truyền abilities |
| **J** | Locale chỉ resolve trên **17/52** route | `SetApiLocale` push vào group `api`; và `SetStorefrontLocale` chạy **sau** nên ghi đè `?locale=` |
| **K** | Standards §10 mô tả module `Hook` **đã bị gỡ** | không còn `Hook::` nào trong code |
| **L** | Body JSON **lặp đôi** trên mọi route `api/v1` khi Redis chết | `/api/v1/products`: 54 byte = envelope 2 lần; kernel trả đúng 27 |

### Ba chỗ audit ước lượng sai — chỉ lộ ra khi bắt tay làm

- **`sanctum.expiration`**: bật lên sẽ **giết mọi token đã phát hành** (nó so với
  `created_at`). → stamp `expires_at` **từng token** lúc phát hành. Token cũ vẫn chạy.
- **`abilities:` của Sanctum**: `CheckAbilities` ném `AuthenticationException` cho **bất kỳ**
  user không có `currentAccessToken()` → biến request cookie-session bình thường thành 401
  (`AddressBookTest` đỏ). → viết `EnsureTokenAbility` chỉ ràng buộc bearer token.
- **`/home-feed`** (ước lượng "S"): `SectionRenderer::render()` trả **HTML**, provider trả
  view-data chứa **Eloquent model** — serialise thẳng sẽ lộ model internals. Làm đúng phải
  viết resource cho từng section, mà **chưa có app nào tiêu thụ**. → **hoãn**.

---

## Phần 2 — Rà soát ecommerce cốt lõi: 6 bug thật

Mỗi bug **tái hiện bằng test trước khi sửa**; mỗi bản sửa **mutation-check** (tắt guard →
test đỏ).

| # | Bug | Bằng chứng đo được |
|---|---|---|
| **E5** | **Guard chống oversell chưa từng chạy.** Cột `purchasable` mặc định `always` (migration của Lunar) và **toàn bộ 66 variant** đều `always`. Cả `DecrementStock` lẫn `CartService` đều *cố ý* miễn trừ `backorder`/`always` | stock=2, đặt 10 → checkout **200 OK**, stock = **−8** |
| **E1** | **Stock không bao giờ được trả lại.** Đơn tạo ra (và giữ stock) **trước khi** khách trả tiền gateway. Không scheduler, không đường release: bỏ ngang / fail / hoàn tiền / RMA / admin huỷ đều mất units | bank-transfer: stock 5 → **3**, không ai trả đồng nào |
| **E3** | **Một order line trả hàng được nhiều lần.** `validLines()` so với **full** quantity, không trừ phần đã claim. Gateway được `RefundService` chặn; **COD/bank không có trần nào** | đơn 23.000 → 2 RMA × 20.000 = **40.000 hoàn** |
| **E6** | **Trả hàng được trên đơn chưa từng giao.** `ReturnService::open()` **không kiểm status**; `can_return` chỉ **ẩn nút** trên UI | đơn `awaiting-payment` → RMA mở OK, `refundableAmount` = **10.000** |
| **E2** | **Cart bỏ qua stock.** `add()` chỉ kiểm *phần thêm*; `updateLine()` **không kiểm gì** | stock=3: `PATCH quantity=999` → **201 OK** |
| **E4** | Double-submit checkout → **500** (`CartException: A billing address is required`) | submit thứ hai gặp cart rỗng vừa tạo mới |

> **E6 lộ ra *trong lúc dọn code*, không phải lúc rà soát.** Khi gom `can_return` về một
> hằng số, câu hỏi "ai *ép* luật này?" mới bật ra — và câu trả lời là **không ai cả**.

### Không phải bug (đã kiểm chứng rồi loại)

**Giá đổi sau khi vào giỏ.** Trong cùng một request, cart hiển thị giá cũ (memoize `Price`
object) còn order tạo theo giá mới → *trông như* charge khác giá hiển thị. Kiểm chứng qua
**request thật**: `lunar_cart_lines` **không lưu giá**, cart luôn tính live → request mới
hiện `0,01 US$`, khớp order. Thiết kế của Lunar, **đúng**.

---

## Phần 3 — Bài học (đã đưa vào standards §17)

1. **Một guard viết đúng vẫn có thể chưa từng chạy.** `DecrementStock` có conditional
   UPDATE atomic, test cũ xanh — vì test tự `update(['purchasable' => 'in_stock'])`, còn
   dữ liệu thật thì không. → test guard phải chạy trên **dữ liệu như production**.
2. **Cờ hiển thị ≠ guard.** `can_return` trông như luật nghiệp vụ nhưng chỉ ẩn nút. Với mọi
   cờ `can_*` / `is_*` trong Resource: **ai ép luật này ở phía service?**
3. **Nghi ngờ mọi default của vendor.** `purchasable = always`, `sanctum.expiration = null`,
   `throttleApi()` chỉ phủ group `api` — cả ba biến một lớp bảo vệ thành trang trí.
4. **Copy-paste một hằng số là mầm bug.** Mảng `paid_statuses` có **5 bản sao**; hai bản đã
   trôi khỏi nhau → COD tính doanh thu nhưng không lên hạng. Nay một nguồn: `OrderStatus::paid()`.
5. **Queued hay đồng bộ?** Side-effect (mail, push) → queued. **Bất biến đúng-sai** (trả tồn
   kho, ghi sổ tiền) → **đồng bộ**: queue chết thì hàng/tiền sai im lặng.
6. **`config:cache` che `phpunit.xml`.** `DB_DATABASE` trỏ về DB dev → `RefreshDatabase`
   **xoá sạch nó**. (Tôi đã tự dính, phải `db:seed` khôi phục.) Luôn `optimize:clear` trước test.

---

## Phần 4 — Việc đã khảo sát rồi *cố ý dừng*

### Phase 4 — Omnichannel / POS ⏸

Điều kiện tiên quyết (cart-token, token abilities) **đã xong**, nhưng khảo sát trước khi
code cho thấy:

| Việc | Thực tế đo được |
|---|---|
| Channel `pos` | Lunar hỗ trợ; cart có `channel_id`; **discount CÓ lọc theo channel**. Nhưng catalog **không hề** scope theo channel (0 lời gọi `->channel()`), và `initChannel()` đọc **session** → client headless không chọn được channel |
| Staff abilities | guard `staff` có, bảng `lunar_staff` có, nhưng **0 dòng staff** và `Lunar\Admin\Models\Staff` **không có `HasApiTokens`** |
| Giá/tồn theo channel | `lunar_prices` **không có cột channel** (chỉ `customer_group_id` + `currency_id`) → **không phải khái niệm của Lunar**, làm là tự bịa tính năng |

**Chưa có client POS nào tồn tại.** Làm bây giờ là đoán shape rồi sửa lại.

### Phase 5 — AI ⏸

0 file AI trong repo. Standards §16 ghi rõ: với SME, co-purchase + curate tay là đủ ROI.
Khi có use-case sinh lời thật (sinh mô tả sản phẩm, phân loại ảnh), làm đúng mẫu
`SearchEngine`: `interface AiProvider` + driver, gọi trong **queued job**, cache theo hash
prompt. **Không** đụng recommendation cho tới khi CoPurchase hết dư địa.

### Những gì cố ý **không** xây

Xem [plan.md § Quyết định có chủ đích](../architecture/overview.md#quyết-định-có-chủ-đích--không-phải-thiếu-sót)
— mỗi mục kèm **ngưỡng kích hoạt** để sau này quyết bằng dữ kiện.
