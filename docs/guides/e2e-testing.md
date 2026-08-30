# Test E2E — khi PHPUnit không nhìn thấy lỗi

> Laravel Dusk lái Chrome thật. Đây là công cụ **chẩn đoán**, không phải tầng test
> chính — 588 test PHPUnit vẫn là lưới an toàn hằng ngày.
> Dựng ra sau đợt truy một bug mất nhiều vòng vì thiếu đúng công cụ này.
> Cập nhật: **2026-08-30**.

---

## 1. Khi nào cần tới nó

Chỉ khi **hành vi sai nằm ở trình duyệt, không ở server**. Dấu hiệu nhận biết:

- Test PHPUnit xanh, request trả 200, dữ liệu đúng — nhưng thao tác trên màn hình
  không có tác dụng.
- Console có lỗi mà PHP không hề ném: `Livewire Entangle Error`,
  `x is not defined`, `Alpine Expression Error`.
- Lỗi chỉ xảy ra **sau một tương tác** (mở modal, bấm nút), không xảy ra lúc tải.

Nếu tái hiện được bằng `Livewire::test()` thì **đừng dùng Dusk** — nó chậm hơn hai
bậc và khó đọc hơn nhiều.

### Bài học đắt nhất

Trong đợt truy bug media picker, **mọi phép đo phía server đều sạch**: khoá
repeater đúng UUID, ổn định qua các vòng Livewire, HTML render luôn khớp state
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
thì phải trỏ sang DB riêng trước**; các test hiện có chỉ đọc và mở modal.

**Không chạy trong CI**: `phpunit.xml` chỉ nạp `tests/Feature`, và runner không có
trình duyệt. Đây là việc chạy tay khi nghi lỗi client.

### ChromeDriver

```bash
php artisan dusk:chrome-driver --detect
```

⚠️ Bản Dusk hiện tại **giải nén sai** cấu trúc zip mới của Chrome for Testing: nó
tạo ra một *thư mục* `vendor/laravel/dusk/bin/chromedriver-mac-arm64` thay vì
*file*. Triệu chứng là `Could not connect to localhost:9515`. Xử lý:

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

## 3. Bốn cái bẫy, cả bốn đều đã sập ít nhất một lần

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

### 3.2 Thuộc tính `wire:` không dùng được trong CSS selector

Dấu hai chấm cần escape, và cách escape đó **không sống sót** khi chuỗi đi từ PHP
sang JS. Đừng `querySelector('[wire\\:snapshot]')` — dùng `hasAttribute`:

```php
$browser->driver->executeScript(<<<'JS'
    const el = [...document.querySelectorAll('*')].find(e => e.hasAttribute('wire:snapshot'));
JS);
```

### 3.3 Đọc snapshot Livewire là đường dẫn tới false green

Tôi từng assert dựa trên `snapshot.data.data[0].skus[0]`. Đường dẫn đoán sai, hàm
trả về chuỗi `"NO SKUS: []"`, và cả ba assert đều **lọt** vì nó không bằng
`null`, `[]` hay `ROW MISSING`. Test xanh, chẳng canh gì cả.

**Assert vào thứ người dùng nhìn thấy.** Ở đây là thumbnail trên dòng, đọc qua
`wire:key`:

```php
// data.skus.<uuid>.images-<assetId>
$keys = $browser->driver->executeScript(<<<'JS'
    const needle = 'skus.' + arguments[0] + '.images-';
    return [...document.querySelectorAll('*')]
        .map(e => e.getAttribute('wire:key'))
        .filter(k => k && k.includes(needle))
        .sort();
JS, [$rowKey]);
```

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

`tests/Browser/VariantMediaPickerTest.php` — 4 test cho trang biến thể sản phẩm:

| Test | Canh cái gì |
|---|---|
| trang tải không lỗi console | Không có entangle/Alpine error lúc tải |
| mở picker giữ console sạch | Lỗi từng nổ đúng lúc modal được chèn vào DOM |
| modal mở được | Không hỏng lặng lẽ |
| chọn ảnh thì gắn vào dòng | **Thêm** ảnh, không **thay** — bug thật đã tìm ra. Ghi vào DB dev rồi khôi phục trong `finally` |
