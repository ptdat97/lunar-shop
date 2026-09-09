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
| A | ✅ **Xong** — dựng lại được môi trường dev, sửa lỗi hai không gian id | xem §5 |
| B | ✅ **Xong** — chuyển 648 SKU → variant (dữ liệu) | 0 dòng lệch tồn kho; xem §6 |
| C | ✅ **Xong** (gộp vào B) — tồn kho sang `StockLevel` | `stock_on_hand` khớp `quantity` cũ từng dòng |
| D | ✅ **Xong** — đổi purchasable trong code | xem §6 |
| E | ✅ **Xong** — bộ chọn đọc option value | Trang sản phẩm giữ nguyên hành vi; JS không đổi |
| F | 🟡 Gỡ code xong; **bảng `lunar_product_skus` còn nguyên** | Giữ làm lưới an toàn — xem §7 |

> **Pha A chạy được ngay và độc lập.** Nó không phụ thuộc quyết định nào ở B–F.
> Đã xong — và nó đào ra hai lỗi thật, xem [§5](#5-nhật-ký-pha-a).

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


---

## 5. Nhật ký pha A

Pha A định là "dọn variant ma". Vừa chạm vào thì lộ hai thứ nặng hơn, nên phạm
vi đổi: **sửa hai lỗi thật trước, dọn variant ma để lại cho pha B** (ở đó chúng
biến mất tự nhiên, vì SKU sẽ TRỞ THÀNH variant — xoá bây giờ rồi tạo lại là công
bỏ đi).

### 5.1 `migrate:fresh --seed` hỏng suốt từ lúc nâng cấp

Không dựng được môi trường dev mới. Suite vẫn xanh vì test dựng dữ liệu bằng
`CreatesStorefrontData`, chưa bao giờ đi qua seeder demo.

Hai nguyên nhân, cùng một họ với ba migration ở [2.0 §9.3](upgrade-lunar-2.0.md):
**thứ mà đường NÂNG CẤP tự backfill nên DB đã nâng cấp thì có, DB cài mới thì
không.** Ai chỉ thử đường nâng cấp sẽ không bao giờ thấy.

- `'stock' => N`: 2.0 không còn cột `stock`.
- `Location` và `Region`: khái niệm mới của 2.0, `lunar:upgrade` backfill cả hai
  nhưng **không có gì tạo chúng khi cài mới** — kể cả Lunar. Thiếu Location thì
  mọi lệnh ghi tồn kho ném lỗi.

Phủ bởi `BaseDataSeederTest`.

### 5.2 "Shop the set" thêm nhầm sản phẩm của người khác

Trang lookbook render `$product->variants->first()->id` trong khi cart endpoint
phân giải id đó thành `ProductSku`. Hai không gian id, cả hai bắt đầu từ 1, nên
**mọi id đều khớp một SKU nào đó** — chỉ là của sản phẩm khác. Nút báo thành
công vì request thành công thật.

Đây chính là **cái giá của việc để hai mô hình "thứ khách mua" cùng tồn tại**, và
là lý do độc lập thứ hai để hợp nhất — ngoài chuyện panel hiện số liệu sai.

Phủ bởi `LookbookShopTheSetTest` (có mutation-check).

### 5.3 Việc còn lại của pha A, đã chuyển sang pha B

- Xoá 66 variant ma + 66 dòng giá của chúng.
- Seeder thôi tạo variant rời (`Demo50ProductsSeeder`, `DemoCatalogSeeder`,
  `MultiSizeProductsSeeder`), thay bằng: ma trận variant sinh từ cùng dữ liệu mà
  `ProductSkuMatrixSeeder` đang dùng để sinh SKU.


---

## 6. Nhật ký pha B–D

Chạy trong một lượt: pha B chuyển dữ liệu thì **giá rời khỏi SKU ngay lập tức**,
nên code phải đi cùng. Không tách được, và điều đó đáng ghi ra vì nó cũng có
nghĩa là **test suite không nhìn thấy được lỗi này**: DB test dựng từ migration
trên nền rỗng, không có SKU nào để chuyển, nên migration là no-op và mọi test
vẫn xanh trong khi site dev đã mất giá. Kiểm bằng site thật, không bằng suite.

### 6.1 Số liệu nghiệm thu

| Kiểm | Kết quả |
|---|---|
| SKU → variant | 648 → 648, ánh xạ đầy đủ trong `sku_variant_map` |
| Tồn kho lệch **từng dòng** | 0 |
| Tồn kho tổng | 10.216 = 10.216 |
| Morph đã trỏ lại | prices, cart_lines, order_lines, discountables |
| Option value mỗi variant | 648/648 có đúng 2 (Color + Size) |

### 6.2 Hai thứ dọn được nhờ đi qua đây

- **12 dòng giá mồ côi** trỏ vào SKU id 1–12 chưa từng tồn tại (bảng SKU bắt đầu
  từ 13). Rác có sẵn từ trước, do `SkuBuilderService` lưu theo kiểu xoá-và-tạo-lại
  nên id đổi mỗi lần lưu sản phẩm. Vô hại khi không ai phân giải chúng — nhưng
  variant tái sử dụng id từ 1, nên nó sắp hết vô hại.
- **66 variant demo** do seeder tạo song song, giờ nằm cùng sản phẩm với variant
  thật. Xoá, kèm giá và tồn kho của chúng; migration từ chối xoá bất cứ dòng nào
  có đơn hàng hay giỏ trỏ tới.

### 6.3 Cái giữ lại có chủ đích

**`images` vẫn là danh sách Asset id**, thêm làm cột trên `lunar_product_variants`
chứ không chuyển thành media của variant. 1.945 tham chiếu trong catalog phân giải
về **162 asset** — biến chúng thành media riêng sẽ nhân bản cùng một file mười hai
lần và vứt bỏ đúng cái thư viện dùng chung mà module Assets sinh ra để làm.

**Vị trí trên trục vẫn là số thứ tự.** Bộ chọn của storefront (SSR lẫn
`enhance/product-variant.js`) định địa chỉ variant theo vị trí: trục 0 giá trị 1 →
`"1-0"`. Trước đây vị trí được LƯU (`products.variables` + `ProductSku.variants`);
nay nó được SUY RA trong `Modules\Catalog\Support\VariantAxes` từ thứ tự option
của sản phẩm. Cùng một hợp đồng, một nguồn sự thật, và **client không phải sửa một
dòng nào** — JS không bao giờ biết có id tồn tại.

### 6.4 Code xoá được

Lunar 2.0 nối sẵn toàn bộ tồn kho vào vòng đời đơn hàng:

```
OrderPlaced / OrderCancelled  → SyncStockForOrder            (commit / release)
FulfilmentCreated             → AllocateStockForFulfilment
chuyển trạng thái fulfilment  → ApplyStockForFulfilmentTransition
                                (`shipped` lấy hàng khỏi kệ, `returned` trả lại)
```

Nên những thứ sau **biến mất khỏi `modules/`**, không phải viết lại:

| Xoá | Bản chính chủ |
|---|---|
| `ProductSku` (325 dòng) | `Lunar\Core\Models\ProductVariant` |
| `SkuBuilderService` (390) | `GenerateProductVariants` |
| `StockLedger` (264) | `RecordStockMovement` + `RecomputeStockRollup` |
| `StockReleaser` (117), `StockSettler` (87) | các listener ở trên |
| `DecrementStock` pipeline (67) | `SyncStockForOrder` |
| `ProductSkuObserver` (53) | `AdjustStock` (+ observer nhỏ cho back-in-stock) |
| `StockMovement` model + `StockMovementType` enum | của Lunar |
| `MigrateVariantsToSkus` command | việc đã xong, và nay ngược chiều |
| `cart-eager-load-overrides.php` | không cần: override đó tồn tại **chỉ vì** `ProductSku` không có quan hệ `values`; `ProductVariant` có |

Ba điểm cải thiện thật, không chỉ là đổi chỗ:

1. `stock_committed` được **suy ra từ sổ đơn hàng** chứ không phải bộ đếm ứng dụng
   tự cộng trừ — không thể lệch. Đúng cái mà cột `stock_before`/`stock_after` của
   sổ cũ sinh ra để phát hiện *sau khi đã lệch*.
2. Huỷ giao và trả hàng đưa tồn kho **quay lại**; sổ cũ chỉ biết đưa đi.
3. Tồn kho **theo địa điểm**, thứ shop trước đây không có cách nào diễn đạt.


### 6.5 Ba bất biến ĐỔI, không phải mất

Hợp nhất không chỉ là đổi chỗ code — ba lời hứa của shop đổi nghĩa. Ghi ra để
không ai coi là hồi quy:

1. **Hoàn tiền không còn tự động nhập kho lại.** Mô hình cũ gộp hai việc: hoàn
   tiền bất kỳ là trả hàng về kệ. 2.0 tách tiền khỏi hàng — tồn kho quay lại khi
   **fulfilment** được đánh dấu `returned`, tức lúc kiện hàng thật sự về. Một đơn
   đã hoàn tiền mà hàng còn ở chỗ khách **không được** thổi phồng tồn kho.
2. **Giữ hàng không ghi movement nào.** Sổ cũ ghi một dòng `sale` lúc tạo đơn với
   `stock_before == stock_after` — một bản ghi *chuyển động* mô tả thứ không hề
   chuyển động. Sổ của Lunar chỉ chứa chuyển động vật lý, nên
   `sum(movements) == on_hand` là bất biến chứ không phải nguyện vọng.
3. **Không còn cái kẹp (clamp) chống nhả hai lần.** `committed` cũ là bộ đếm code
   tự cộng trừ, nhả hai lần sẽ âm và **bịa ra tồn kho**; phải có clamp. 2.0 TÍNH
   LẠI committed từ sổ đơn hàng mỗi lần, nên không có bộ đếm nào để nhả hai lần.
   Kẹp biến mất vì chế độ hỏng biến mất.

Cộng thêm một thứ tự nó hết: **`SkuBuilderService` lưu theo kiểu xoá-và-tạo-lại
nên id đổi mỗi lần lưu sản phẩm** — nguồn gốc của 12 dòng giá mồ côi ở §6.2, và
lý do `getIdentifier()` phải dùng chuỗi `sku` thay vì id. `GenerateProductVariants`
so sánh tổ hợp rồi chỉ thêm/bớt phần chênh, nên id ổn định và cả lớp lỗi đó không
còn.

### 6.6 Test: xoá cái kiểm nội bộ, giữ cái kiểm lời hứa

Ba file test biến mất cùng code chúng phủ (`SkuBuilderTest`, `StockLedgerTest`,
`StockCommitmentIntegrityTest`) — chúng gọi thẳng API của service đã xoá, và kiểm
nội bộ của vendor không phải việc của dự án.

`StockCommitmentTest` thì **giữ, chỉ trỏ sang cơ chế mới**. Uỷ thác một lời hứa
cho dependency không đồng nghĩa với hết cần lời hứa đó: đây là những gì shop hứa
với khách, và đúng là thứ sẽ hỏng im lặng ở lần nâng Lunar kế tiếp. Chúng nay
kiểm **kết quả** đi qua checkout, không kiểm ruột của bên tạo ra kết quả.


---

## 7. Việc còn lại

`lunar_product_skus` (648 dòng) và `sku_variant_map` **vẫn còn trong DB**, dù
không còn dòng code nào đọc chúng. Cố ý: chúng là thứ duy nhất trả lời được
"đơn hàng cũ này trỏ vào SKU nào" nếu có gì đó sai lộ ra muộn. Bảng cũng không
tốn gì.

Drop khi: đã chạy production một thời gian, và không còn ai cần tra ngược. Một
migration nhỏ, chạy sau chứ không phải bây giờ — đây là bước duy nhất không thể
hoàn tác từ dữ liệu còn lại.

**Kiểm chứng lại 2026-09-09** (khi chốt Fase 5): ngoài migration không còn dòng
code nào đọc hai bảng, và ánh xạ còn nguyên vẹn — 648 SKU ↔ 648 map ↔ 648
variant, 0 mồ côi theo cả hai chiều. Lưới an toàn dùng được; điều kiện drop thì
vẫn chưa đạt.

Cài mới thì đã sạch: `migrate:fresh --seed` cho 648 variant và **0 SKU** (bảng
được tạo rồi để rỗng, vì migration tạo bảng vẫn chạy trước migration chuyển đổi).
