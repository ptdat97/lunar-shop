# Migration Runbook — `ProductSku` → `Lunar\Core\Models\ProductVariant`

> **Trạng thái:** kế hoạch, chưa thực thi. Là phần tiếp theo của
> [upgrade-lunar-2.0.md](upgrade-lunar-2.0.md) và **chặn Fase 4** của nó.
>
> Đọc [§0](#0-tại-sao-lại-đảo-quyết-định-cũ) trước — mục này giải thích vì sao
> một quyết định kiến trúc cũ bị đảo, và bằng chứng nào cho phép đảo.

---

## 0. Tại sao lại đảo quyết định cũ

Dự án tự dựng `ProductSku` (mô hình VaniCommerce) vì Lunar **1.x** không đủ:
`ProductVariant` hồi đó không có tồn kho theo địa điểm, không có selling policy
tử tế, không giữ được swatch màu, và trục biến thể bị buộc vào
`ProductOption` dùng chung trong khi dự án muốn **trục tự do theo từng sản
phẩm**.

Ba dữ kiện đo được ngày 2026-09-09 làm lý do đó hết hiệu lực:

| Dữ kiện | Con số |
|---|---|
| Sản phẩm dùng trục tự do (khác Color + Size) | **0 / 54** |
| Đơn hàng / giỏ dùng morph `product_variant` | 0 |
| Variant "ma" còn trong DB (2.828 đơn vị không bán được) | 66 |

Khả năng "trục tự do" — thứ đắt nhất phải tự viết — **chưa từng được dùng**.
Trong khi đó Lunar 2.0 đã có sẵn gần hết phần còn lại:

| Việc dự án tự viết | Bản chính chủ 2.0 |
|---|---|
| `SkuBuilderService::save()` (ma trận biến thể) | `GenerateProductVariants` + `MapVariantsToProductOptions` |
| `StockLedger` (sổ kho) | `RecordStockMovement` + `RecomputeStockRollup` |
| `committed` + `StockReleaser` + `orders:expire-abandoned` | `ReserveStock` / `CommitReservation` / `ReleaseReservation` + `ReleaseExpiredStockReservations` |
| `variables[].display_type` + swatch | `ProductOptionType` + `ProductOptionValue.meta.colour` |
| `origin_price` | `Price.list_price` |
| `status` published/disabled | `enabled` |
| — (không có) | Tồn kho **theo địa điểm** (`StockLevel`) |

**Cái quyết định hướng đi, không phải danh sách trên:** panel đọc
`$variant->stockLevels()` và `stock_on_hand`. Giữ `ProductSku` nghĩa là màn hình
sản phẩm chính chủ của panel **nói dối** — hiện 2.828 đơn vị không tồn tại — và
mọi màn hình catalog phải tự viết lại bằng Vue. Hợp nhất thì chúng chạy ngay.

### Cái mất — ghi ra để không ai tưởng là quên

| Mất | Thay bằng |
|---|---|
| Trục biến thể **tự do theo sản phẩm** (`products.variables`) | `ProductOption` dùng chung. Muốn trục mới thì tạo option mới, dùng lại được — đắt hơn một chút, nhưng đây là thứ chưa từng dùng |
| `ProductSku.position` (thứ tự thủ công) | Thứ tự suy từ `position` của `ProductOptionValue` |
| `ProductSku.is_default` | Variant đầu tiên theo thứ tự trên |
| Soft delete SKU | `enabled = false` (2.0 bỏ soft delete khỏi variant) |

Ba cái đầu chưa có UI để chỉnh (admin cũ đã bị xoá), nên **không có dữ liệu người
dùng nào bị mất** — chỉ mất khả năng.

---

## 1. Thứ tự thực thi

Mỗi pha để lại app chạy được và test xanh. **Không gộp pha.**

| # | Pha | Kết quả kiểm chứng được |
|---|---|---|
| A | Dọn variant ma + dừng seeder tạo chúng | Panel hết hiện số liệu ảo; `lunar_product_variants` rỗng |
| B | Chuyển 648 SKU → variant (dữ liệu) | Mỗi SKU có đúng một variant, ánh xạ id ghi lại được |
| C | Chuyển sổ kho sang `StockLevel` / `StockMovement` | `stock_on_hand` khớp `quantity` cũ từng dòng |
| D | Đổi purchasable trong code | 560 test xanh với `ProductVariant` |
| E | Bộ chọn biến thể storefront đọc option value | Trang sản phẩm giữ nguyên hành vi |
| F | Gỡ `ProductSku`, `SkuBuilderService`, sổ kho cũ | Không còn tham chiếu; bảng cũ drop ở migration riêng |

> **Pha A chạy được ngay và độc lập.** Nó không phụ thuộc quyết định nào ở B–F,
> nên làm trước cả khi chốt phần còn lại: panel đang nói dối *ngay bây giờ*.

---

## 2. Bản đồ cột

```
ProductSku                    →  ProductVariant
──────────────────────────────────────────────────────────────
product_id                    →  product_id
tax_class_id                  →  tax_class_id
sku                           →  sku
model                         →  model            (2.0 có sẵn)
cost_price                    →  cost_price       (2.0 có sẵn)
weight                        →  weight_value (+ weight_unit)
status 'published'|'disabled' →  enabled true|false
quantity                      →  StockLevel.on_hand  (+ movement opening_balance)
committed                     →  StockReservation → SyncStockCommitment
price                         →  Price.price      (đã có dòng, đổi morph)
origin_price                  →  Price.list_price (đã có)
images (asset ids)            →  media của variant
variants (mảng chỉ số)        →  pivot product_option_value_product_variant
position                      →  (suy từ ProductOptionValue.position)
is_default                    →  (variant đầu theo thứ tự trên)
deleted_at                    →  enabled = false
```

**Morph phải đổi theo** (`product_sku` → `product_variant`):
`lunar_prices.priceable_type`, `lunar_cart_lines.purchasable_type`,
`lunar_order_lines.purchasable_type`, `lunar_discountables.discountable_type`.
Cộng hai bảng của dự án khoá theo `product_sku_id`: `stock_movements`,
`stock_notifications`.

> ⚠️ **`lunar_order_lines` là dữ liệu lịch sử.** DB dev hiện có 0 đơn nên pha B
> trông dễ; production thì không. Đơn cũ phải trỏ được sang variant tương ứng,
> nếu không hoá đơn và RMA cũ mất purchasable. Ánh xạ id **phải ghi ra một bảng
> tạm** (`_sku_variant_map`) chứ không giữ trong bộ nhớ, để chạy lại được và để
> kiểm chứng sau khi chạy.

---

## 3. Ba chỗ dễ mất dữ liệu — kiểm bằng số, không bằng mắt

1. **Tồn kho.** Sau pha C: `sum(stock_levels.on_hand)` phải bằng
   `sum(product_skus.quantity)` cũ, **và** từng dòng phải khớp — tổng bằng nhau
   vẫn có thể sai từng SKU. Chốt số trước khi chạy.
2. **Giá.** 660 dòng `lunar_prices` đang trỏ `product_sku`. Đổi morph, đừng tạo
   dòng mới — tạo mới thì tier price và customer-group price mất.
3. **Ảnh biến thể.** `images` là mảng id Asset, không phải media của SKU. Chuyển
   thành media của variant qua `MediaUrl::assetMedia()` — cùng đường mà SSR
   gallery đang dùng, xem [1.5 §17](upgrade-lunar-1.5.md).

---

## 4. Rollback

Pha B–F đổi dữ liệu một chiều. Backup trước mỗi pha:

```bash
mysqldump -h127.0.0.1 -uroot --single-transaction lunar > ../backup-pre-phase-<X>.sql
```

Bảng `_sku_variant_map` giữ lại **cho tới khi pha F xong và đã chạy production
một thời gian** — nó là thứ duy nhất trả lời được "đơn hàng cũ này trỏ vào SKU
nào".
