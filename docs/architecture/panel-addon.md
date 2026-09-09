# Màn hình admin riêng trên Lunar panel

Lunar 2.0 mang theo panel chính chủ (`lunarphp/panel`, Inertia + Vue 3) lo sẵn
sản phẩm, biến thể, tồn kho, đơn hàng, khách hàng, khuyến mãi và toàn bộ cây
settings. Tài liệu này nói về phần còn lại: những bảng của riêng shop mà panel
không thể biết — banner, trang tĩnh, redirect, lookbook, RMA…

Nguyên tắc xuyên suốt: **không fork panel**. Mọi thứ dưới đây đi qua điểm mở
rộng chính thức `Lunar\Panel\Facades\Panel`.

## Ba lớp

```
modules/Core/app/Panel/Field.php            ← DSL khai báo một trường
modules/Core/app/Panel/PanelResource.php    ← khai báo một màn hình CRUD
modules/Core/app/Panel/ResourceRegistry.php ← gom resource → sinh route
modules/Core/app/Panel/ShopSection.php      ← Section duy nhất của shop
modules/Core/app/Http/Controllers/Panel/ResourceController.php
resources/js/pages/shop/resource/{Index,Form}.vue
```

Thêm một màn hình admin tốn **một class schema**, không tốn một trang Vue:

```php
class BannerResource extends PanelResource
{
    public function model(): string { return Banner::class; }
    public function section(): string { return 'shop'; }
    public function key(): string { return 'banners'; }
    public function label(): string { return __('admin.banner.plural'); }
    public function singular(): string { return __('admin.banner.label'); }

    public function fields(): array
    {
        return [
            Field::text('title', __('admin.common.title'))->required()->onIndex(),
            Field::toggle('active', __('admin.common.active'))->default(true)->onIndex(),
            Field::image('image', __('admin.banner.image')),
        ];
    }
}
```

rồi đăng ký trong provider của module sở hữu nó:

```php
$this->app->make(ResourceRegistry::class)->add(new BannerResource);
```

`ShopSection` tự sinh 6 route (`index/create/store/edit/update/destroy`) dưới
`/panel/shop/{key}` và một mục điều hướng, đều gác bằng `permission()` của
resource.

Đây cố tình là **forms-over-data**, không phải framework. Màn hình có hành vi
thật (wizard, thao tác hàng loạt, editor có xem trước) nên viết Section riêng.

## Bộ trường

| Kiểu | Dùng cho |
| --- | --- |
| `text` `textarea` `html` `number` `toggle` `image` | cột thường |
| `slug(from:)` | slug tự suy từ trường khác, khớp hook `creating` của model |
| `select(options)` | danh sách cố định |
| `relation(fn (?Model $record) => …)` | option lấy từ DB, resolve theo từng request; nhận bản ghi đang sửa nên picker scope được (ảnh ghim của lookbook chỉ lấy ảnh của chính lookbook đó) |
| `json` | cột JSON hình dạng tự do — chốt chặn cuối |
| `repeater(fields)` | danh sách lồng trong một cột JSON, lồng được nhiều tầng |
| `hasMany(fields)` | danh sách là **bảng con thật**; giữ nguyên id của hàng qua mỗi lần lưu |

Modifier: `required()` `nullable()` `default()` `help()` `placeholder()`
`width(1..12)` `onIndex()` `multiple()` `itemLabel()` `addLabel()`
`visibleWhen($field, ...$values)` `virtual()`.

### Ba modifier đáng nói riêng

**`visibleWhen`** không chỉ là chuyện hiển thị — trường ẩn thì **không render,
không submit, và không validate**. Đó là thứ cho phép hai nhánh loại trừ nhau
dùng chung một tên: `hero-slider` và `lookbook` cùng ghi `settings.slides` với
sub-field khác hẳn nhau. Validate cả hai một lúc thì rule đè nhau và một nhánh
im lặng thắng.

Trong một repeater, điều kiện đọc **hàng của chính nó**, không phải cả form:
một mục menu chỉ hiện danh sách link con khi bản thân nó là dropdown.

**`hasMany` giữ id.** Hàng con round-trip kèm `id` để lần lưu sau *cập nhật* chứ
không xoá-tạo-lại. Không có nó thì mọi thứ trỏ tới hàng con theo id sẽ đứt —
lookbook ghim sản phẩm lên ảnh theo `image_id`, xoá-tạo-lại là mất sạch pin mà
vẫn báo "đã lưu". Thứ tự ghi từ vị trí hàng, nên admin sắp xếp bằng mũi tên chứ
không gõ số.

**`virtual`** đánh dấu trường không phải cột: engine bỏ qua nó khi đọc và khi
mass-assign, resource tự lo qua `toRow()` + `saved()`. Cây menu là ví dụ — một
trường trên form, một bảng riêng bên dưới, `MenuTree` dịch hai chiều.

## Những ràng buộc đã phải tuân theo

Bốn điều dưới đây là hợp đồng của panel, không phải lựa chọn — làm sai thì trang
hỏng im lặng, không có lỗi phía server nào để lần.

**1. Trang add-on không được tự bọc `<PanelLayout>`.** `runtime/pageResolver.ts`
tự gán layout mặc định cho trang đến từ registry (`addonPage.layout ??=`). Bọc
thêm một lớp nữa là sidebar lồng trong sidebar.

**2. CSS của panel là bản biên dịch sẵn, chỉ chứa utility mà chính nó dùng** —
1156 class. `grid-cols-12` và `col-span-*` **không** có trong đó. Nên lưới của
form đặt bằng inline style (`grid-template-columns`, `gridColumn`), còn lại chỉ
dùng đúng những class trang first-party đã dùng. Đừng thêm class Tailwind mới rồi
tưởng nó sẽ có.

Bù lại, panel định nghĩa lại toàn bộ biến màu trong `.dark`, nên CSS thuần dùng
`var(--color-ink-500)`, `var(--color-line)`… tự có dark mode.

**3. Hợp đồng bảng/hàng là của panel, không phải của mình.**

| Thành phần | Panel đòi | Nơi sinh ra |
| --- | --- | --- |
| Cột `DataTable` | `{key, label, type?: {name, options}}` | `PanelResource::columns()` |
| URL thao tác từng dòng | `row._actions[{key}]` | `ResourceController::index()` |
| Thao tác dòng | `{key, label, icon, method, primary, confirmation}` | như trên |
| Meta phân trang | key nguyên bản của paginator Laravel | như trên |

`RowActions.vue` chỉ vẽ thao tác nào mà dòng có URL tương ứng — nghĩa là phân
quyền theo từng dòng nằm hẳn ở PHP.

**4. Tên component Inertia = đường dẫn file thật** dưới `resources/js/pages`
(`shop/resource/Index`). Không bắt buộc về mặt chạy — registry là một map phẳng —
nhưng `ensure_pages_exist` của Inertia lúc test tra theo đường dẫn, nên quy ước
này biến việc đổi tên file thành test đỏ thay vì trang trắng.

## Đã chuyển được gì

| Màn hình | Cách khai báo |
| --- | --- |
| Banner, Trang tĩnh, Redirect | trường phẳng |
| Section trang chủ | 8 nhánh `visibleWhen` + repeater trong cột JSON |
| Lookbook | hai `hasMany` (ảnh, sản phẩm) + picker ghim scope theo bản ghi |
| Menu | `virtual` + repeater lồng 3 tầng, `MenuTree` dịch cây ↔ bảng |

## Build

Bundle add-on build bằng **config Vite thứ hai**, `vite.panel.config.js`. Không
gộp được với config storefront: storefront là Blade + JS thuần qua manifest của
`laravel-vite-plugin`, còn cái này là IIFE externalise `vue`,
`@inertiajs/vue3`, `vue-i18n`, `@lunarphp/panel` sang các global mà `app.ts` của
panel publish. Dùng chung một instance Vue/Inertia chính là thứ làm `usePage()`,
`<Link>`, `useI18n()` chạy được trong trang add-on.

```sh
npm run build:panel     # chỉ bundle panel
npm run build           # storefront + panel (CI chạy cái này)
```

Output đi thẳng vào `public/vendor/lunar-panel/shop/build` — đúng
`buildDirectory` mà `ShopSection::vite()` khai, nên không cần
`lunar:panel:link`, cũng không có bước publish nào để quên.

Thư mục này nằm trong `.gitignore` (giống `public/build` của storefront). Quên
build thì trang panel của shop render rỗng — nên có test canh đúng chuyện đó:
`PanelContentResourceTest::test_the_panel_html_loads_the_addon_bundle`.

**Bẫy đã dính:** `publicDir` mặc định của Vite là `public/`, mà `outDir` lại nằm
trong `public/` → Vite copy đệ quy cả cây `public` vào chính nó cho tới khi vỡ
độ dài đường dẫn. `vite.panel.config.js` đặt `publicDir: false`.

## Phân quyền

`Gate::after` của panel chỉ cấp một ability khi manifest access-control biết đến
nó, mà manifest dựng từ **bảng `permissions`**. Vậy nên quyền mới phải có hàng
trong bảng, không chỉ là một chuỗi trong Section — thiếu hàng đó thì
`can:content:manage` chặn tất cả, kể cả admin.

Tạo bằng migration chứ không phải seeder: bản cài mới phải có đúng những quyền
mà bản nâng cấp có. Xem
`modules/Content/database/migrations/2026_09_09_140000_add_content_manage_permission.php`.

## Dịch

Nhãn trường/cột vẫn lấy từ `lang/{locale}/admin.php` — nguyên vẹn từ thời
Filament, không phải dịch lại. Phần khung mà engine vẽ (nút, xác nhận, trạng
thái rỗng) nằm ở `lang/{locale}/panel.php` và được gửi xuống trong prop
`resource`, nên một màn hình admin chỉ có một chỗ để dịch chứ không phải hai.
