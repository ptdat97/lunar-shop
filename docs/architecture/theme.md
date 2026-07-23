# Theme `fashion` — kiến trúc storefront

> Theme storefront đang chạy (theme active duy nhất). **Lớp trình bày thuần**: chỉ
> Blade + SCSS + vanilla JS — **không** query DB, **không** gọi model Lunar, **không**
> business logic. Mọi data đến từ Storefront controller (gọi service module) hoặc từ
> `/api/v1/*` khi người dùng tương tác.
>
> Stack: **Blade SSR + Bootstrap 5 (SCSS) + vanilla JS**. **Không có Vue** — xem
> § [Vì sao không Vue](#vì-sao-không-vue).
>
> Tham chiếu kiến trúc: [overview.md](overview.md) · quy tắc code:
> [../guides/coding-standards.md](../guides/coding-standards.md) §7–§9.

---

## 0. Nguyên tắc bất biến

1. **SSR-first.** Mọi nội dung công khai (home, product, collection, search, CMS page)
   render **HTML thật** ở server. Crawlable, không flash trắng, chạy khi tắt JS.
2. **3 lớp:** *SSR shell (Blade)* → *JSON hydration payload (cùng shape `/api/v1`)* →
   *JS enhancement (đọc payload, chỉ fetch khi tương tác)*.
3. **Một contract dữ liệu duy nhất.** SSR và JS dùng **cùng API Resource shape**
   (`ProductResource`…). Không nhân đôi shape, không lệch dữ liệu.
4. **Vanilla JS là mặc định.** Mỗi enhancer là một file `enhance/*.js`.
5. **No-JS fallback thật.** Filter/sort/search/pagination là `GET` form/link thật.
6. **Cấm CSR trá hình** cho nội dung SEO (fetch-on-mount rồi để SSR trong `<noscript>`).

> Hợp đồng codebase ↔ theme:
> - `ThemeServiceProvider` đăng ký namespace `theme::` → `themes/<active>/views`, và
>   view composer đưa `$theme` (ThemeSettings) vào mọi view `theme::*`.
> - `config/theme.php` → `active = env('THEME','fashion')`.
> - Storefront controller `return view('theme::pages.*', [...])` kèm data + `$state`.

### Vì sao không Vue

Storefront **từng** có 3 Vue island (variant picker, quick-view, checkout). Chúng đã
được thay bằng vanilla enhancer và **Vue gỡ hoàn toàn** khỏi `package.json`. Dòng đầu
`enhance/product-variant.js` còn ghi lại điều này.

Thêm Vue/React trở lại **cần phê duyệt kiến trúc riêng** — xem
[coding-standards §9](../guides/coding-standards.md#9-javascript-theme). Lý do: trang
sản phẩm là nội dung SEO bắt buộc SSR, component framework rất dễ trượt sang
render client-side.

---

## 1. Cấu trúc thư mục

```text
themes/fashion/
 ├── theme.json                 # manifest: name, version, author
 ├── views/
 │    ├── layouts/app.blade.php # <html> shell: head/meta/SEO, header, @yield, footer, @stack
 │    ├── partials/  (14)       # header, footer, cart-drawer, search-panel, size-chart,
 │    │                         # promo-bar, recently-viewed, product-jsonld, pixels…
 │    ├── pages/     (16)       # home, product, collection, search, cart, checkout(+confirmation),
 │    │                         # wishlist, account, login, register, page, lookbook(s), promotion(s)
 │    ├── sections/  (8)        # SectionBuilder: 1 type = 1 partial (hero-slider, collection-grid,
 │    │                         # flash-sale, lookbook, product-tabs, promotion-slider, iconbox…)
 │    ├── components/ (3)       # product-card · price · picture (responsive <picture>)
 │    └── menus/                # menu render theo cấu trúc CMS Menu
 ├── js/
 │    ├── app.js                # bootstrap: import Bootstrap JS + auto-load enhance/*
 │    ├── api.js                # axios instance (baseURL /api/v1, CSRF/Sanctum cookie)
 │    ├── events.js             # cart:updated / cart:refreshed bus
 │    ├── config/               # hằng số chia sẻ giữa Blade và JS (vd grid.js)
 │    └── enhance/   (25)       # vanilla — mỗi file export default fn(root=document)
 └── css/
      ├── app.scss              # entry Vite
      ├── features/             # _header, _product-card, _mini-cart, _search-panel…
      └── pages/                # style riêng từng trang
```

**Quy ước `enhance/`:** file bắt đầu bằng `_` (`_card.js`, `_gallery.js`, `_shop.js`) là
**helper**, được import chứ không auto-run. Còn lại auto-load qua glob trong `app.js`,
gọi `default(document)` khi DOM ready; mỗi enhancer tự target qua `data-*` và phải
**idempotent** để chạy lại được trên fragment mới.

---

## 2. Bản đồ trang ↔ controller ↔ endpoint

| Trang | Web route → view | Data SSR (controller) | API mà JS gọi |
|---|---|---|---|
| Home | `storefront.home` → `pages.home` | `HomeController` (sections) | — (tĩnh) |
| Product | `storefront.product` → `pages.product` | `ProductService::findBySlug`, `sizeChart`, `related` | `GET/POST /api/v1/cart`, `products/{slug}/size-chart`, `POST products/{slug}/recommend-size`, `products/{slug}/recommendations` |
| Collection | `storefront.collection` → `pages.collection` | `SearchEngine` + `$state` | `GET /api/v1/search?scope=…` |
| Search | `storefront.search` → `pages.search` | `SearchEngine` + `$state` | `GET /api/v1/search`, `search/suggest` |
| Cart | `storefront.cart` → `pages.cart` | (SSR shell) | `GET/POST /api/v1/cart`, `PATCH/DELETE cart/lines/{line}`, `POST/DELETE cart/coupon` |
| Checkout | `storefront.checkout` → `pages.checkout` | `CheckoutController::index` | `checkout/shipping-options`, `POST checkout/addresses`, `POST checkout/shipping`, `POST checkout` |
| Confirmation | `storefront.checkout.confirmation` | `CheckoutController::confirmation` | — |
| Wishlist | `storefront.wishlist` → `pages.wishlist` | `WishlistController` | `GET/POST /api/v1/wishlist` |
| Account/Auth | `storefront.{login,register,account}` | `AuthPageController` | `auth/{register,login,logout}`, `customer`, `customer/orders` |
| CMS page | `storefront.page` → `pages.page` | `PageController` (sections) | — |
| Lookbook | `storefront.lookbook(s)` | `LookbookController` | — (pin toạ độ render SSR) |
| Promotion | `storefront.promotion(s)` | `PromotionController` | — |

**Hợp đồng hydration** — hai payload nhúng, cùng shape với `/api/v1`:

```blade
<script type="application/json" data-product-state>@json($state)</script>  {{-- product --}}
<script type="application/json" data-island-state>@json($state)</script>   {{-- collection/search --}}
```

JS đọc payload này làm state đầu, **không fetch lần đầu**, chỉ gọi API khi đổi
filter/sort/page/term và đồng bộ URL qua `history.replaceState`.

---

## 3. Lớp JS

### `app.js` — bootstrap
Import Bootstrap 5 JS và gán `window.bootstrap` (enhancer cần gọi Offcanvas/Modal bằng
tay — chỉ import side-effect thì `window.bootstrap` undefined và các lệnh đó **im lặng
không chạy**). Sau đó auto-load mọi `enhance/*.js` không bắt đầu bằng `_`.

### `api.js` — axios
`baseURL: '/api/v1'`, `withCredentials: true`; CSRF + Sanctum cookie tự đính kèm (cùng
domain).

### `events.js` — đồng bộ giỏ
`cart:updated` (add-to-cart, cart-page, checkout) → `enhance/cart.js` refresh từ
`/api/v1/cart` → bắn `cart:refreshed` → consumer re-render. Refresh **không** bắn lại
`cart:updated` (tránh vòng lặp).

### Helper (không auto-run)
- **`_card.js`** — render product card từ JSON `ProductResource` **khớp 1:1**
  `components/product-card.blade.php`, để grid SSR và grid sau filter trông y hệt.
- **`_gallery.js`** — dựng Swiper + PhotoSwipe cho gallery; `MediaUrlGallery()` re-render
  khi đổi bộ ảnh.
- **`_shop.js`** — logic dùng chung cho collection/search grid.

### Enhancer đáng chú ý
- **`product-variant.js`** — đọc `data-product-state`, chọn SKU theo option, cập nhật
  giá/tồn/nút add-to-cart, đồng bộ URL (`?màu-sắc=Đen`), và **đổi gallery theo màu**.
  Key theo **tập ảnh** chứ không theo variant id: mọi size cùng màu dùng chung bộ ảnh
  nên đổi size **không** rebuild gallery (giữ nguyên vị trí slide đang xem).
- **`add-to-cart.js`**, **`cart.js`**, **`cart-page.js`** — giỏ + mini-cart drawer.
- **`collection-shop.js`**, **`search-results.js`**, **`search-panel.js`** — filter/sort/
  phân trang + suggest.
- **`size-finder.js`** — "tìm size của tôi" → bắn event `size:recommended`, variant
  picker nghe và chọn size tương ứng.
- **`notify-me.js`**, **`wishlist.js`**, **`membership.js`**, **`flash-sale.js`**,
  **`lookbook.js`**, **`recently-viewed.js`**, **`pixels.js`**.

---

## 4. SSR shell — quy ước Blade

- `layouts/app.blade.php`: `<head>` đổ SEO/meta từ data; `@stack('head')`,
  `@yield('content')`, `@stack('scripts')`; `@vite` trỏ entry theme.
- **Catalog grid**: `@foreach` render `product-card` (HTML thật) + nhúng `$state` cho
  enhancer. Facet sidebar + pagination là `GET` link thật.
- **Sections**: `pages.home`/`pages.page` lặp `page_sections` →
  `@include('theme::sections.'.$section->type, [...])`. Render server-side.
- `$theme` (ThemeSettings) cho logo/màu/typography/social — composer đã inject.
- **Blade chỉ format.** Giá, ảnh, menu… do view composer inject (coding-standards §7);
  Blade không resolve service.

---

## 5. Build / Vite

- `vite.config.js` chọn entry theo `process.env.THEME` (default `fashion`):
  `themes/<theme>/css/app.scss` + `themes/<theme>/js/app.js`.
- CSS là **SCSS + Bootstrap 5**, chia `features/` và `pages/`; app.scss là entry duy nhất.
- Dev: `npm run dev` (hoặc `composer dev` chạy kèm server/queue/logs).
  Prod: `npm run build`.

---

## 6. Definition of Done (cho thay đổi theme)

- [ ] Trang catalog render HTML thật, **pass kiểm thử tắt JS** (filter/sort/search/
      paginate qua `GET`).
- [ ] Grid SSR và grid sau-tương-tác khớp nhau (cùng markup `product-card`).
- [ ] `$state` nhúng ↔ `/api/v1/*` cùng một shape, không lệch.
- [ ] Giỏ đồng bộ qua `cart:updated`/`cart:refreshed`, không vòng lặp.
- [ ] SEO/meta + OpenGraph đầy đủ; không layout shift khi JS chạy.
- [ ] Không thêm Vue/React nếu chưa có phê duyệt kiến trúc.
