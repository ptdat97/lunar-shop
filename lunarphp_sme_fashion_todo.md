# SME Fashion Ecommerce — Việc còn lại

> **Chỉ ghi việc CHƯA làm.** Hiện trạng ở [plan.md](lunarphp_sme_fashion_plan.md);
> lịch sử bug đã sửa ở [platform_audit.md](lunarphp_sme_fashion_platform_audit.md).
> Xếp theo ROI giảm dần. Cập nhật: **2026-07-10**.
>
> **Trước khi build bất cứ gì:** kiểm tra `vendor/lunarphp` đã có chưa (Nguyên tắc #1) —
> có thì kế thừa/mở rộng, không thì mới build trong module tương ứng. Giữ phạm vi
> single-store SME. `vendor/bin/phpunit` xanh trước khi coi là xong (349 test), và
> `vendor/bin/pint --test <file bạn đã sửa>` xanh — **không** phải cả repo: chạy
> `pint --test` trên toàn repo hiện đỏ **131 file** có từ trước (không có `pint.json`,
> preset `laravel` mặc định bắt cả migration/seeder/config publish từ Lunar).
> Dọn một lượt là commit khổng lồ trộn lẫn với thay đổi thật → xem P1 mục 1.

---

## P1 — Vận hành / chất lượng

### 1. Hạ tầng production còn thiếu
- ⬜ **CDN** cho `public/` (media + build assets) khi deploy.
- ⬜ **Error tracker** (Sentry) — chưa cài, chờ ngân sách.
- ⬜ **CI** — chưa có `.github/workflows`. Tối thiểu: `phpunit`.
  Muốn thêm `pint --test` thì trước đó phải quyết một trong hai: (a) thêm `pint.json`
  `exclude` các thư mục publish-from-vendor (migration/seeder/config), hoặc (b) chạy
  `pint` một lần trên toàn repo trong **commit riêng, không kèm thay đổi logic**.
  Hiện `pint --test` đỏ **131 file** có sẵn → bật thẳng vào CI sẽ đỏ ngay ngày đầu.
- ⬜ Rà lại DB index sau khi có traffic thật (`add_performance_indexes` đã làm nền).

Xem [deploy.md](lunarphp_sme_fashion_deploy.md) cho runbook đầy đủ.
⚠️ **Secrets cũ vẫn nằm trong git history — phải rotate trước khi deploy.**

### 2. Test còn thiếu
- ⬜ `modules/<Name>/Tests` **vẫn trống** — toàn bộ 54 file ở `tests/Feature`. Thêm smoke
  test cạnh module **khi chạm module đó**, không làm một lượt.
- ⬜ Phần thuần-JS chưa phủ: picture/srcset, search-panel, notify-me UI, lookbook-shoppable.
  Cần browser driver (Dusk/Playwright) — quyết định riêng, không phải việc nhỏ.

---

## P2 — Tăng chuyển đổi / trải nghiệm

### 3. Quick-view — *theme*
Modal xem nhanh sản phẩm từ grid (vanilla, đọc `/api/v1/products/{slug}`), add-to-cart không
rời trang listing. Đã chừa chỗ, cố ý hoãn.

### 4. `GET /api/v1/home-feed` — *Content* (hoãn có lý do)
Audit ước lượng "tái dùng `SectionRenderer`" là dễ. Thực tế `render()` trả **HTML**, và 3
provider dynamic trả view-data chứa **Eloquent model** — serialise thẳng sẽ lộ model
internals. Làm đúng phải viết resource cho từng loại section.
**Chưa có app nào tiêu thụ** → làm khi có client thật, để biết shape cần gì.

### 5. Size Intelligence — phần nice-to-have
Đã có: hồ sơ số đo, fit history (giữ vs trả, between-sizes).
- ⬜ Gợi ý fit theo **lịch sử mua của người có số đo tương tự** (cần đủ dữ liệu mới có nghĩa).

### 6. Admin nhập nội dung đa ngôn ngữ
`translateAttribute` của Lunar đã sẵn; đây là **hướng dẫn vận hành**, không phải code.

---

## P3 — Khi quy mô lớn hơn

### 7. Scout search driver (Meilisearch / Typesense) — *Catalog*
`config/scout.php` đã có; engine active vẫn `DatabaseSearchEngine`. Chỉ cần khi catalog lớn
hoặc cần typo-tolerance + facet nhanh.
**Cách làm:** viết `ScoutSearchEngine` sau interface `SearchEngine` → đổi config,
**zero** thay đổi caller.

### 8. Analytics nâng cao — *Analytics*
Dashboard KPI/trend/best-seller đã có. Mở rộng: top size/màu bán chạy, tỉ lệ đổi-trả theo
size (gắn với RMA), export báo cáo.

### 9. Omnichannel / POS ⏸ và AI ⏸
Đã khảo sát rồi **cố ý dừng** — lý do + số liệu đo được ở
[platform_audit.md § Phần 4](lunarphp_sme_fashion_platform_audit.md#phần-4--việc-đã-khảo-sát-rồi-cố-ý-dừng).

---

## Cấu hình: cái gì ra admin, cái gì ở lại config

**Đã ra Filament** (đọc qua `App\Support\Settings`, DB → fallback config/env): payment keys
(VNPay/MoMo) + default method · shipping flat-rate + free-threshold · membership tiers ·
recommendations (limit/TTL) · review auto-approve · media on-demand mode · low-stock threshold.

**Cố ý giữ trong config** (kỹ thuật, không phải quyết định kinh doanh):
`recommend.strategies` · `inventory`/`cart`/`media` pipeline-overrides · tax-inclusive ·
Scout/Typesense · FFmpeg · media disks.

> `analytics.paid_statuses` vẫn là config key (admin chỉnh được), nhưng **code không đọc
> trực tiếp** — mọi nơi đi qua `Modules\Order\Support\OrderStatus::paid()`. Trước đây mảng
> fallback bị copy-paste ra 5 service và đã trôi khỏi nhau.

---

## Nguyên tắc phạm vi (nhắc lại)

**KHÔNG** build ở giai đoạn SME single-store: multi-vendor/marketplace · visual drag-drop
editor · microservices/GraphQL · headless SPA tách rời · plugin/hook engine · Repository ·
Action layer · BFF · module rỗng (ERP/CRM/Loyalty/Wallet) · AI recommendations.

Lý do + **ngưỡng kích hoạt** từng mục:
[plan.md § Quyết định có chủ đích](lunarphp_sme_fashion_plan.md#quyết-định-có-chủ-đích--không-phải-thiếu-sót).
