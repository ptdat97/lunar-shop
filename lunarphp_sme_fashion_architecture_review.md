# Kiến trúc Modular Monolith — Đánh giá & Lộ trình tiến hoá

> Đối chiếu codebase với mục tiêu **enterprise-grade Modular Monolith trên Laravel +
> Lunar** (Lunar = commerce engine, application sở hữu toàn bộ business logic).
> Nguyên tắc đánh giá: *Laravel-first, Lunar-first, không abstraction thừa, refactor
> tăng dần, bảo toàn hành vi.* Đọc kèm
> [lunarphp_sme_fashion_plan.md](lunarphp_sme_fashion_plan.md) (hiện trạng) và
> [lunarphp_sme_fashion_coding_standards.md](lunarphp_sme_fashion_coding_standards.md).
>
> Cập nhật lần cuối: **2026-07-08**.

---

## 1. Kết luận tổng quan

Codebase **đã là** modular monolith đúng triết lý mục tiêu:

```text
Laravel → app/ (bootstrap mỏng) → modules/ (12 module, business logic)
        → Lunar (commerce engine, vendor untouched) → MySQL
```

- `app/` chỉ còn bootstrap (`ModulesServiceProvider`, `User`, Horizon) — đúng vai
  "Application Layer mỏng".
- 12 module tự chứa (routes + migrations + Filament + services), namespace
  `Modules\<Name>`, giao tiếp cross-module qua service công khai + domain event
  (`OrderPaid`), không đụng model nội bộ của nhau.
- Lunar không bị sửa: mọi can thiệp qua config override, `Payments::extend`,
  `Discounts::addType`, `ShippingModifiers->add`, pipeline chèn stage
  (`DecrementStock`), `resolveRelationUsing`, Filament `ResourceExtension`.
  → **Lunar vẫn replaceable/upgradable** (Golden Rule đạt).

Phần còn lại của tài liệu này là **bảng ánh xạ** yêu cầu kiến trúc → hiện trạng,
các **quyết định có chủ đích KHÔNG làm**, và **lộ trình increment**.

## 2. Ánh xạ yêu cầu kiến trúc → hiện trạng

| Yêu cầu | Hiện trạng | Đánh giá |
|---|---|---|
| Modular monolith, module độc lập | 12 module trong `modules/`, provider riêng | ✅ đạt |
| Controller mỏng (validate → DTO/service → Resource) | Controller lớn nhất 133 dòng, không query DB | ✅ đạt |
| Service = orchestration, một nguồn logic | `*Service` trong từng module; web + API dùng chung | ✅ đạt |
| Action pattern | Không dùng class `*Action` riêng — service method nhỏ đóng vai action | ✅ tương đương (xem §3.1) |
| Pipelines | Dùng **pipeline của Lunar** (cart/order/pricing) + stage tự viết (`DecrementStock`); promotion chạy trong cart pipeline qua custom `DiscountType` | ✅ đạt, không tự chế pipeline engine |
| DTO bất biến | `Checkout/Data/*` (VNPayResult, MoMoResult, RefundResult), `Catalog/Data/*`; Request không lọt vào service | ✅ đạt ở ranh giới cần |
| Domain Events | `OrderPaid` (Order) + Lunar events (`PaymentAttemptEvent`); listener chỉ side-effect (email, membership sync) | ✅ mẫu đã chuẩn; thêm event mới **khi có consumer** |
| Queue-first integrations | Mail/notification/media đều queued (Horizon, queue tách theo loại qua `Core\Support\Queues`) | ✅ đạt |
| Service Contracts cho tích hợp ngoài | `SearchEngine` (+ `DatabaseSearchEngine`); Payment/Shipping dùng **contract của Lunar** (`AbstractPayment`, `ShippingModifier`) — chính là Strategy Pattern, swappable | ✅ đạt — không bọc thêm interface trùng vai Lunar |
| Payment Strategy, không switch | Driver `offline`/`vnpay`/`momo` qua `Payments::extend()`; refund qua `RefundService` per-driver | ✅ đạt |
| Catalog decorate Lunar, không sửa model | `resolveRelationUsing` + service wrap; chưa cần `ModelManifest::replace` | ✅ đạt |
| CMS đơn giản: Blade Components + View Composers + View Models | Module Content + SectionRenderer; composer inject data (standards §7) | ✅ đạt |
| Filament: không sửa Lunar Resources | `ResourceExtension` + `AdminPages::add()` cho resource mới | ✅ đạt |
| Theme thuần Laravel (`themes/`) | `themes/fashion` chỉ Blade+JS+CSS | ✅ đạt |
| Cache chỉ read-model | Sitemap 1h, recommendation TTL, settings cache; không cache workflow | ✅ đạt |
| Test: action/unit + module/feature | 163 test / 541 assertion (2026-07-08), MySQL `lunar_testing` | ✅ xanh; test cạnh module (`modules/*/Tests`) còn trống (todo #13) |
| KHÔNG build: plugin SDK, hook engine, registry, dynamic loader, workflow engine | Không có; hook registry cũ đã gỡ khi gộp 24→12 module | ✅ đúng chủ đích |

## 3. Quyết định kiến trúc có chủ đích (không phải thiếu sót)

### 3.1 Không tách class `*Action` riêng
Nghiệp vụ hiện là **method nhỏ, testable trong service** (vd
`CheckoutService::placeOrder`, `ReturnService::approve`). Với quy mô SME
single-store, tách mỗi method thành một class Action chỉ thêm file + indirection,
không thêm testability (test đã gọi thẳng service). **Ngưỡng chuyển đổi:** khi một
operation cần tái sử dụng ở ≥ 2 orchestrator khác nhau hoặc service vượt 500 dòng →
tách phần đó ra (như đã làm với Promotion, §5).

### 3.2 Không tạo module ERP / CRM / Marketing / Loyalty rỗng
- **Loyalty** = membership tiers, đã sống trong `Promotion` (`MembershipService`,
  sync qua event `OrderPaid`) — đúng chỗ vì nó là biến thể discount.
- **Marketing** = promotions + content sections, đã phủ bởi `Promotion` + `Content`.
- **ERP / CRM**: chưa có hệ thống ngoài nào để tích hợp. Khi có, mẫu chuẩn đã sẵn:
  listener nghe domain event (`OrderPaid`…) → dispatch **queued Job** trong module
  `Integrations/<System>` mới, sau một contract (`interface ErpClient`). Tạo trước
  module rỗng là abstraction thừa.

### 3.3 Không thêm interface cho service nội bộ
Contract chỉ đặt ở **ranh giới thay thế được** (SearchEngine, payment driver,
shipping modifier — nơi có ≥ 2 implementation thực tế hoặc chắc chắn sẽ đổi).
Service nội bộ module (ProductService, CartService…) là class cụ thể — Laravel
container vẫn cho swap/mock khi test.

### 3.4 Không đổi cây thư mục `app/` thành Application/Domain/Infrastructure
`app/` hiện ~4 file bootstrap. Toàn bộ "Domain + Application" sống trong
`modules/` (mỗi module tự chứa Actions≈Services, Events, Jobs, Contracts, Http,
Providers, Config, Data≈DTO). Dựng 4 tầng trong `app/` khi không có code để đặt
vào là cấu trúc rỗng. **Ngưỡng xem lại:** khi xuất hiện logic cross-module không
thuộc module nào (hiện chưa có — `Core` giữ hạ tầng thuần).

## 4. Cấu trúc module chuẩn (đã áp dụng)

```text
modules/<Name>/
├── Http/{Controllers/{Storefront,Api/V1},Requests,Resources}
├── Services/            # business logic + orchestration (≤ 500 dòng/file)
├── Data/                # DTO/result objects (immutable)
├── Contracts/           # chỉ khi có ≥ 2 implementation (vd SearchEngine)
├── Models/  Events/  Listeners/  Jobs/  Observers/
├── Pipelines/           # stage chèn vào pipeline Lunar
├── DiscountTypes/ | PaymentTypes/ | Modifiers/ | Strategies/ | Drivers/
├── Filament/  Database/{Migrations,Seeders}  Config/  Routes/  Providers/
└── Tests/               # (todo #13 — smoke test cạnh module)
```

Quy tắc phụ thuộc (dependency direction, kiểm bằng review):

```text
themes/ ──view──▶ modules/<X>/Http ──▶ modules/<X>/Services ──▶ Lunar
                     │ cross-module chỉ qua: service công khai của module khác,
                     ▼ domain event, hoặc contract — KHÔNG model nội bộ
routes/ ──gom──▶ modules/*/Routes
app/    ──boot──▶ modules/*/Providers (Core trước, Lunar panel cuối)
```

## 5. Increment log

### Increment 1 — Tách PromotionService (2026-07-08) ✅
**Lý do:** `PromotionService` 712 dòng — vi phạm duy nhất chuẩn §15 (service ≤ 500)
toàn repo; trộn 3 trách nhiệm (query, targeting/eligibility, hiển thị badge).

**Cách làm (bảo toàn hành vi — public API giữ nguyên, caller không sửa):**
- `PromotionTargetResolver` — matching/targeting thuần: `appliesToProduct`,
  `targetedProductIds/CollectionIds`, `isMembershipDiscount`,
  `isDisplayablePromotion`, `productPercentage`. Stateless.
- `SaleBadgeService` — hiển thị: `saleFor(product, promotions)`, `appliedTo(cart)`,
  `toBanner`, `badge`, `describe`. Stateless, nhận collection promotions từ facade.
- `PromotionService` (~370 dòng) — queries + coupon + **memoization per-request**
  (singleton) + delegation giữ nguyên chữ ký cũ.

**Verify:** `vendor/bin/pint` + full suite `vendor/bin/phpunit` — 163 test /
541 assertion xanh, không sửa test nào (bằng chứng behaviour-preserving).

### Các increment tiếp theo (theo ROI, làm khi chạm ngưỡng)
1. **Test cạnh module** (`modules/<Name>/Tests` smoke) — todo #13, làm dần khi
   chạm module nào thì thêm cho module đó.
2. **Domain event mới chỉ khi có consumer thứ hai** — vd `OrderCancelled` khi có
   RMA-flow tự động hoặc ERP sync cần nghe; mẫu theo `OrderPaid`.
3. **ERP/CRM integration** (khi có yêu cầu thật): module mới + contract + queued
   job + listener — theo §3.2, không làm trước.
4. **Scout search engine** (todo #15): driver mới sau `SearchEngine` — zero đổi caller.

## 6. Checklist cho mọi refactor (nhắc lại từ standards)

1. Giải thích **WHY** trước khi viết code; ưu tiên increment nhỏ.
2. Không sửa `vendor/`; không nhân bản logic Lunar; kiểm tra Lunar có sẵn chưa (§5 standards).
3. Public API (service + `/api/v1` shape) chỉ được mở rộng tương thích ngược.
4. `vendor/bin/pint` + `vendor/bin/phpunit` xanh trước khi coi là xong.
5. Cập nhật tài liệu (plan.md + file này, ngày tuyệt đối).
