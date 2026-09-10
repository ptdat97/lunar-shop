# Test E2E — khi PHPUnit không nhìn thấy lỗi

> Laravel Dusk lái Chrome thật. Đây là công cụ **chẩn đoán**, không phải tầng test
> chính — 722 test PHPUnit vẫn là lưới an toàn hằng ngày.
> Dựng ra sau đợt truy một bug mất nhiều vòng vì thiếu đúng công cụ này.
> Cập nhật: **2026-09-10**.
>
> **Từ 2026-09-10 nó cũng chạy trong CI** (job `dusk`, xem
> [deployment.md](deployment.md) §9). Điều đó KHÔNG đổi vị trí của nó: vẫn là
> chẩn đoán, vẫn chỉ có test smoke, PHPUnit vẫn là tầng chính. Lý do cắm vào CI
> là một công cụ chẩn đoán phải nhớ gõ tay mới chạy thì sẽ mục — đã có quãng
> 8/8 test Dusk đỏ mà không ai biết. Job đỏ sẽ upload ảnh chụp + console log,
> vì đó là toàn bộ bằng chứng còn lại khi lỗi nằm ở trình duyệt.

---

## 1. Khi nào cần tới nó

Chỉ khi **hành vi sai nằm ở trình duyệt, không ở server**. Dấu hiệu nhận biết:

- Test PHPUnit xanh, request trả 200, dữ liệu đúng — nhưng thao tác trên màn hình
  không có tác dụng.
- Console có lỗi mà PHP không hề ném. Với panel Inertia/Vue thường là
  `Panel page not found: …` (bundle add-on chưa build, xem
  [panel-addon.md](../architecture/panel-addon.md)),
  `[LunarPanel] Extension component "…" is not registered`, hoặc một lỗi render
  của Vue. Storefront là JS thuần nên thường là `x is not defined`.
- Lỗi chỉ xảy ra **sau một tương tác** (mở modal, bấm nút), không xảy ra lúc tải.

Nếu tái hiện được bằng test feature (`assertInertia()` cho panel, request test cho
storefront) thì **đừng dùng Dusk** — nó chậm hơn hai bậc và khó đọc hơn nhiều.

### Nguyên tắc quyết định

Hỏi đúng một câu: **bằng chứng cần thu nằm ở đâu?**

| Bằng chứng nằm ở | Dùng | Vì sao |
| --- | --- | --- |
| Trạng thái DB sau một request | Test feature | Dusk không thấy gì thêm, chỉ chậm hơn |
| HTML server trả về | Test feature | `assertSee`, regex trên `getContent()` |
| JSON API trả về | Test feature | Hợp đồng ở tầng server |
| **DOM sau khi JS chạy** | **E2E** | Server không biết JS làm gì với HTML nó gửi đi |
| **Console trình duyệt** | **E2E** | PHP không ném gì cả — lỗi ở phía kia |
| **Sau một tương tác** (bấm, mở modal, đổi tab) | **E2E** | Không có request nào để mà test |

Ba quy tắc rút ra từ chính những lần đã sập trong dự án này:

**1. Đừng viết E2E cho thứ test feature chứng minh được.** Đắt hơn, giòn hơn, và
khi đỏ thì nói ít hơn. Cả `tests/Browser` chỉ nên có vài test.

**2. Nhưng E2E là thứ DUY NHẤT chứng minh được vài loại việc.** Nếu bỏ nó thì
không có lưới nào cả. Ví dụ có thật, 2026-09-10: sau khi việt hoá ~26 chuỗi JS,
mọi test server đều xanh — payload có trong HTML, mọi khoá resolve đúng. Không
cái nào chứng minh được **enhancer thật sự đọc payload đó**. `t()` chạy trong
trình duyệt, trên một khối JSON do trình duyệt parse, ở thời điểm PHPUnit không
nhìn thấy. Mà đó chính là chỗ lỗi cũ đã sống suốt: shop hiện tiếng Anh cho khách
Việt đúng lúc gần trả tiền nhất, và không test server nào bắt được.

**3. Test phải kiểm THỨ ĐÚNG, không phải thứ dễ kiểm.** Bẫy này không riêng gì
E2E nhưng E2E hay dính nhất, vì "trang có render không" luôn dễ viết hơn "thao
tác có chạy không".

> **Ca đắt nhất, 2026-09-10.** Form đánh giá mới dựng có test khẳng định
> `data-review-form` xuất hiện trong HTML. Test xanh. Nhưng `action` của form
> dựng từ **slug**, trong khi route `products/{product}` bind theo **id** — nên
> **mọi lượt gửi đánh giá đều 404**. Tính năng hỏng ở đúng chỗ quan trọng nhất
> và test vẫn xanh, vì nó kiểm *form có mặt* chứ không kiểm *form gửi được*.
>
> Bản sửa: lấy `action` **ra khỏi trang đã render** rồi POST vào chính nó. Đó
> mới là phiên bản duy nhất của test này có thể bắt được lỗi đó.

### Ba trường hợp E2E đáng viết trong shop này

Không phải danh sách mong muốn — là ba loại việc mà bỏ E2E thì không còn gì canh:

**a. Bundle add-on của panel có nạp và render không.** Panel là Inertia/Vue; một
màn hình khai báo bị hỏng biểu hiện là `Panel page not found: …` **chỉ trong
console**, còn server vẫn trả 200 với payload Inertia hợp lệ. Xem
`PanelFormsTest`.

**b. Chuỗi đã dịch có tới được DOM không.** Xem nguyên tắc 2 ở trên và
`StorefrontI18nSmokeTest`.

**c. Console sạch trên các trang chính.** Rẻ, và bắt được cả một lớp lỗi: bundle
chưa build, biến chưa định nghĩa, thư viện nạp sai thứ tự. `StorefrontSmokeTest`
ghé trang chủ, collection, tìm kiếm, sản phẩm, giỏ, wishlist, đăng nhập/đăng ký.

### Khi test E2E đỏ, nghi theo thứ tự này

1. **Chưa build asset.** `public/build` và bundle panel đều gitignore. Đây là
   nguyên nhân phổ biến nhất và triệu chứng trông giống hệt lỗi code.
2. **ChromeDriver lệch Chrome** — xem §2. Lệch một major thì thường vẫn chạy;
   lệch nhiều mới chết.
3. **Dữ liệu DB dev đã đổi.** E2E chạy trên DB dev thật, không phải
   `RefreshDatabase`. Một sản phẩm bị đổi tên là một test đỏ.
4. Rồi mới tới code.

### Bài học đắt nhất

Trong đợt truy bug media picker (thời admin còn chạy Filament/Livewire), **mọi
phép đo phía server đều sạch**: khoá repeater đúng UUID, ổn định qua các vòng
Livewire, HTML render luôn khớp state
(12 dòng / 12 uuid). Ba lần tôi tưởng đã sửa xong dựa trên suy luận, ba lần vẫn
lỗi.

Chỉ khi lái trình duyệt thật và **ghi log ngay trong action** mới lộ ra nguyên
nhân: `$get('.')` trong một action đăng ký trên field trả về **cả dòng repeater**
chứ không phải field, nên `$set('.')` xoá trắng dòng — và Select bị mất state
ngay dưới chân nó chính là lỗi entangle kia.

> Server đúng ≠ ứng dụng đúng. Test PHPUnit chứng minh server đúng, thế thôi.

---

## 2. Chạy

```bash
php artisan dusk                       # cần Chrome + site sống ở APP_URL
php artisan dusk --filter=<TestName>
```

Chạy trên **APP_URL + database dev** — đúng dữ liệu bạn nhìn thấy trong admin, đó
là điểm mạnh: tái hiện được đúng bản ghi đang lỗi. Đổi lại, **test có ghi dữ liệu
phải tự khôi phục** — xem §3.4.

**Chạy trong CI từ 2026-09-10** (job `dusk`), và vẫn chạy tay khi nghi lỗi client.
Hai lý do ghi ở đây trước kia — "runner không có trình duyệt" và "`phpunit.xml`
chỉ nạp `tests/Feature`" — lý do đầu đã lạc hậu (ubuntu-latest có sẵn Chrome),
lý do sau không áp dụng vì `artisan dusk` dùng cấu hình riêng chứ không đọc
`phpunit.xml`.

### ChromeDriver

```bash
php artisan dusk:chrome-driver --detect
```

⚠️ Bản Dusk hiện tại **giải nén sai** cấu trúc zip mới của Chrome for Testing: nó
tạo ra một *thư mục* `vendor/laravel/dusk/bin/chromedriver-mac-arm64` thay vì
*file*. Triệu chứng là `Could not connect to localhost:9515`, hoặc lệnh chết
thẳng với `ZipArchive::extractTo(...): Failed to open stream`.

**Lỗi này cũng đánh vào CI.** Job `dusk` trong `.github/workflows/ci.yml` vì vậy
KHÔNG gọi `dusk:chrome-driver` mà tải thẳng từ endpoint của Google, rồi
`install` vào đúng tên Dusk tìm (`chromedriver-linux`, xem
`Chrome/ChromeProcess.php`) — chính chỗ lệch giữa tên đó và thư mục
`chromedriver-linux64/` trong zip là nguyên nhân.

📌 Trên macOS, Dusk dùng file tên `chromedriver-mac-arm`, **không phải**
`chromedriver-mac-arm64` (cả hai cùng nằm trong `bin/`, dễ thay nhầm file rồi
tưởng đã sửa xong). Và ChromeDriver lệch một major so với Chrome vẫn chạy được
trong thực tế: đã đo 8/8 test xanh với driver 152 trên Chrome 153.

Xử lý ở local:

```bash
V=$(/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --version | awk '{print $3}')
curl -sL -o /tmp/cd.zip \
  "https://storage.googleapis.com/chrome-for-testing-public/$V/mac-arm64/chromedriver-mac-arm64.zip"
unzip -q -o /tmp/cd.zip -d /tmp/cdx
rmdir vendor/laravel/dusk/bin/chromedriver-mac-arm64 2>/dev/null
cp /tmp/cdx/chromedriver-mac-arm64/chromedriver vendor/laravel/dusk/bin/chromedriver-mac-arm64
chmod +x vendor/laravel/dusk/bin/chromedriver-mac-arm64
xattr -d com.apple.quarantine vendor/laravel/dusk/bin/chromedriver-mac-arm64 2>/dev/null
```

---

## 3. Năm cái bẫy, cả năm đều đã sập ít nhất một lần

### 3.1 Debugbar chặn click

```
ElementClickInterceptedException: Other element would receive the click:
<span class="phpdebugbar-text">
```

Debugbar nổi ở đáy trang và ăn mất click vào các dòng dưới. Đừng `->click()`, hãy
cuộn tới rồi click bằng JS:

```php
$browser->driver->executeScript(
    'arguments[0].scrollIntoView({block: "center"}); arguments[0].click();',
    [$element],
);
```

### 3.2 Selector cho panel admin: dùng `data-*`, không có `wire:` nữa

Admin từ Lunar 2.0 là Inertia + Vue, không còn Livewire — nên **mọi lời khuyên
cũ về `wire:snapshot` / `wire:key` đã hết hiệu lực**. Nếu bạn thấy chúng ở đâu
đó, đó là tàn dư.

Bề mặt selector ổn định của panel, theo thứ tự ưu tiên:

| Thuộc tính | Ai đặt | Dùng cho |
| --- | --- | --- |
| `data-screen-label` | panel first-party | khẳng định đang ở đúng màn hình |
| `data-testid` | panel first-party | vài chỗ cụ thể (dòng fulfilment…) |
| `data-widget` | panel first-party | thẻ trên dashboard |
| `data-field="{tên}"` | component của shop | ô nhập trên form khai báo |
| `data-error="{tên}"` | component của shop | thông báo lỗi của đúng ô đó |
| `data-media-picker` | component của shop | nút mở thư viện ảnh |
| `data-repeater-add` / `-remove` / `-up` / `-down` / `-row` | component của shop | thao tác trên repeater |

`data-field` khớp **tên trường trong schema PHP**, kể cả tên dạng dot
(`settings.slides`) — nghĩa là selector Dusk và schema không thể lệch nhau mà
không ai biết.

Đừng bám vào class Tailwind: CSS của panel là bản biên dịch sẵn và class có thể
đổi bất cứ lúc nào mà không phải một thay đổi có chủ đích nào cả.

### 3.3 Đọc state nội bộ của framework là đường dẫn tới false green

Bài học từ thời Livewire, và nó **không** mất đi cùng Livewire — chỉ đổi chỗ.

Tôi từng assert dựa trên `snapshot.data.data[0].skus[0]`. Đường dẫn đoán sai, hàm
trả về chuỗi `"NO SKUS: []"`, và cả ba assert đều **lọt** vì nó không bằng
`null`, `[]` hay `ROW MISSING`. Test xanh, chẳng canh gì cả.

Với Inertia cái bẫy y hệt nằm ở `<div id="app" data-page="...">`: cả state của
trang là một khối JSON ngay trong DOM, rất mời gọi để đọc thẳng. Đừng. Một
đường dẫn sai trong đó cũng "không null" y như cũ.

**Assert vào thứ người dùng nhìn thấy** — text đã render, hoặc `data-field` của
đúng ô đó:

```php
$browser->assertSeeIn('[data-screen-label="New product"]', 'Lưu')
        ->assertInputValue('[data-field="title"]', 'Áo khoác');
```

Muốn kiểm payload phía server thì đã có `assertInertia()` trong test feature —
nhanh hơn Dusk hai bậc và không đoán đường dẫn (xem `PanelContentResourceTest`).

### 3.4 Test chạy trên DB dev thì phải tự dọn

Test lần đầu bấm `$tiles[0]` — ô đó tình cờ **đang được chọn**, nên nó *bỏ chọn*
chứ không thêm. Lần chạy sau lại bắt đầu từ trạng thái lần trước để lại, và kết
quả đảo chiều.

Hai việc phải làm cùng lúc:

```php
// 1. Chọn ô CHƯA được chọn, đừng bấm mù
$target = collect($tiles)->first(
    fn ($tile) => ! str_contains((string) $tile->getAttribute('class'), 'ring-2')
);

// 2. Trả dữ liệu về như cũ
$original = ProductSku::query()->where('product_id', 1)->pluck('images', 'id');
try { /* … */ } finally {
    foreach ($original as $id => $images) {
        ProductSku::query()->whereKey($id)->update(['images' => json_encode($images)]);
    }
}
```

### 3.5 Console rỗng không tự nhiên có

Phải chủ động đọc và **lọc nhiễu**, nếu không mọi assert đều đỏ vì favicon hay
cảnh báo preload:

```php
$browser->driver->manage()->getLog('browser');
```

Xem `consoleErrors()` trong `tests/Browser/VariantMediaPickerTest.php`. Nhớ **xả
log** sau bước tải trang, để assert sau đó chỉ nói về thao tác đang xét.

---

## 4. Khuôn một test đáng tin

```php
$browser->loginAs(Staff::findOrFail(1), 'staff')   // route _dusk/login, không cần mật khẩu
    ->visit('/lunar/products/1/variants')
    ->waitForText('SKU', 15)
    ->pause(1500);                                  // để x-load init xong

$before = $this->rowImageKeys($browser, $rowKey);   // trạng thái NHÌN THẤY, trước
// … thao tác …
$after = $this->rowImageKeys($browser, $rowKey);    // và sau

$this->assertSame($before, array_values(array_intersect($after, $before)));
$this->assertCount(count($before) + 1, $after);
```

Ba điểm khiến nó đáng tin:

1. **So trước/sau** trên cùng một đối tượng — không phụ thuộc dữ liệu seed.
2. **Assert cụ thể**: "thêm đúng một, giữ nguyên cái cũ", chứ không phải "có thay
   đổi gì đó". Bản đầu của tôi chỉ assert `before !== after` và vẫn xanh trong khi
   picker đang **xoá sạch** ảnh cũ.
3. **Không đọc state nội bộ** — chỉ đọc DOM.

---

## 5. Khi bí, hãy log phía server

Cách nhanh nhất để nối triệu chứng ở trình duyệt với nguyên nhân trong PHP: nhét
tạm một `Log::info()` vào đúng closure đang nghi, chạy Dusk, rồi đọc log.

```php
Log::info('PICKER data', ['keys' => array_keys($data), 'browser' => $data['browser'] ?? 'MISSING']);
```

Một dòng đó kết thúc cuộc truy tìm kéo dài nhiều vòng — nó cho thấy `$get('.')`
trả về cả dòng SKU chứ không phải field. Nhớ gỡ ra sau khi xong.

---

## 6. Đang có gì

| File | Test | Canh cái gì |
| --- | --- | --- |
| `PanelFormsTest` | 5 | Bundle add-on nạp và render; đổi loại section thì nhánh hiển thị đổi theo; repeater thêm/xoá dòng; slug bám theo tiêu đề; trường ảnh mở được thư viện |
| `StorefrontSmokeTest` | 3 | 12 trang chính tải được **và console sạch** — trang chủ, collection, tìm kiếm, sản phẩm, nội dung, lookbook, khuyến mãi, giỏ, wishlist, đăng nhập, đăng ký |
| `StorefrontI18nSmokeTest` | 2 | Khối i18n tới được trình duyệt và parse được; lời nhắc freeship ghép đúng từ mẫu đã dịch + thay `:amount` |

Chạy trong CI từ 2026-09-10 (job `dusk`), đỏ thì upload ảnh chụp + console log —
đó là toàn bộ bằng chứng còn lại khi lỗi nằm ở trình duyệt.
