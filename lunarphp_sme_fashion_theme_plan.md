# Theme `fashion` — Build Plan (SSR-first)

> Kế hoạch dựng theme storefront `fashion` từ đầu. `themes/fashion/` hiện **rỗng**
> (theme `modave` đã bị xoá ở commit `966c84e`). Theme này là **lớp trình bày thuần**:
> chỉ Blade + JS + CSS, **không** query DB / model Lunar / business logic. Mọi data
> đến từ Storefront controller (gọi service module) hoặc Vue/vanilla island fetch
> `/api/v1/*`.
>
> Tham chiếu kiến trúc: [lunarphp_sme_fashion_plan.md](lunarphp_sme_fashion_plan.md)
> (mục "Storefront", "Quy ước JS", "Nguyên tắc SSR-first", "CMS & Sections").

---

## 0. Nguyên tắc bất biến

1. **SSR-first.** Mọi nội dung công khai (home, product, collection, search, CMS page)
   render **HTML thật** ở server. Crawlable, không flash trắng, chạy khi tắt JS.
2. **3 lớp:** *SSR shell (Blade)* → *JSON hydration payload (`$state`, cùng shape
   với `/api/v1`)* → *JS enhancement (đọc payload, chỉ fetch khi tương tác)*.
3. **Một contract dữ liệu duy nhất.** SSR và island dùng **cùng API Resource shape**
   (`ProductResource`, …). Không nhân đôi shape, không lệch dữ liệu.
4. **Vue tối thiểu (3 island), vanilla là mặc định.** Vue CHỈ cho `product-purchase`,
   `checkout-page`, `quick-view`. Mọi thứ khác: Blade + `enhance/*.js`.
5. **No-JS fallback thật.** Filter/sort/search/pagination là `GET` form/link thật.
6. **Cấm CSR trá hình** cho nội dung SEO (`onMounted(fetch)` rồi để SSR trong `<noscript>`).

> Hợp đồng giữa codebase ↔ theme đã được wire sẵn:
> - `ThemeServiceProvider` đăng ký namespace `theme::` → `themes/<active>/views`,
>   và view composer biến `$theme` (ThemeSettings) khả dụng trong mọi view `theme::*`.
> - `config/theme.php` → `active = env('THEME','fashion')`.
> - Storefront controller đã `return view('theme::pages.*', [...])` với data + `$state`.
> Theme chỉ cần **cung cấp các view + JS/CSS** mà các controller này gọi.

---

## 1. Cấu trúc thư mục theme

```text
themes/fashion/
 ├── theme.json                 # manifest: name, version, author
 ├── views/
 │    ├── layouts/
 │    │    └── app.blade.php     # <html> shell: head/meta/seo, header, @yield, footer, @stack
 │    ├── partials/
 │    │    ├── header.blade.php          # logo, mega-menu (CMS menu), search, cart count, account
 │    │    ├── footer.blade.php
 │    │    ├── cart-drawer.blade.php     # markup #shoppingCart (vanilla, fetch /api/v1/cart)
 │    │    ├── search-modal.blade.php    # vanilla suggest
 │    │    └── flash.blade.php
 │    ├── pages/
 │    │    ├── home.blade.php            # render sections (SectionBuilder)
 │    │    ├── product.blade.php         # detail + variant island + size + related
 │    │    ├── collection.blade.php      # SSR grid + facets + $state
 │    │    ├── search.blade.php          # SSR results + $state
 │    │    ├── cart.blade.php            # trang giỏ (vanilla)
 │    │    ├── checkout.blade.php        # checkout island (Vue)
 │    │    ├── wishlist.blade.php
 │    │    ├── page.blade.php            # CMS page (sections)
 │    │    └── auth/{login,register,account}.blade.php
 │    ├── sections/              # SectionBuilder: 1 type = 1 partial
 │    │    ├── hero.blade.php
 │    │    ├── product-grid.blade.php
 │    │    ├── banner.blade.php
 │    │    ├── slider.blade.php
 │    │    ├── collection.blade.php
 │    │    ├── rich-text.blade.php
 │    │    └── video.blade.php
 │    └── components/            # Blade components UI tái dùng
 │         ├── product-card.blade.php    # markup chuẩn — _card.js render khớp 1:1
 │         ├── price.blade.php
 │         ├── pagination.blade.php
 │         ├── facet.blade.php
 │         ├── breadcrumb.blade.php
 │         └── rating.blade.php
 ├── js/
 │    ├── app.js                 # bootstrap: VUE_ISLANDS allow-list + auto-load enhance/*
 │    ├── api.js                 # axios instance (baseURL /api/v1, CSRF/Sanctum cookie)
 │    ├── events.js              # cart:updated / cart:refreshed bus
 │    ├── islands/               # Vue islands (CHỈ 3)
 │    │    ├── ProductPurchase.vue
 │    │    ├── QuickView.vue
 │    │    └── CheckoutPage.vue
 │    └── enhance/               # vanilla — mỗi file export default fn(root=document)
 │         ├── _card.js          # render product card khớp product-card.blade.php
 │         ├── cart.js           # mini-cart drawer + header count
 │         ├── cart-page.js
 │         ├── collection-shop.js # đọc $state, fetch khi đổi filter/sort/page
 │         ├── search-results.js
 │         ├── search-modal.js
 │         ├── wishlist.js
 │         └── auth.js
 ├── css/
 │    └── app.css                # Tailwind 4 (@import "tailwindcss")
 └── assets/                     # ảnh tĩnh, fonts
```

`theme.json`:
```json
{ "name": "Fashion", "version": "1.0.0", "author": "SME", "supports": { "vite": true } }
```

---

## 2. Bản đồ trang ↔ controller ↔ endpoint (đã tồn tại trong codebase)

Các route/endpoint dưới đây **đã có**; theme chỉ dựng view + JS khớp với chúng.

| Trang | Web route → view | Data SSR (controller) | API island/enhance dùng |
|---|---|---|---|
| Home | `storefront.home` → `pages.home` | `HomeController` (sections) | — (tĩnh) |
| Product | `storefront.product` → `pages.product` | `ProductService::findBySlug`, `sizeChart`, `related` | `GET /api/v1/cart`, `POST /api/v1/cart`, `GET products/{slug}/size-chart`, `POST products/{slug}/recommend-size`, `GET products/{slug}/recommendations` |
| Collection | `storefront.collection` → `pages.collection` | `SearchEngine` + `$state` (ProductResource) | `GET /api/v1/search?scope=…` |
| Search | `storefront.search` → `pages.search` | `SearchEngine` + `$state` | `GET /api/v1/search`, `GET /api/v1/search/suggest` |
| Cart | `storefront.cart` → `pages.cart` | (SSR shell) | `GET/POST /api/v1/cart`, `PATCH/DELETE cart/lines/{line}`, `POST/DELETE cart/coupon`, `GET cart/coupons` |
| Checkout | `storefront.checkout` → `pages.checkout` | `CheckoutController::index` | `GET checkout/shipping-options`, `POST checkout/addresses`, `POST checkout/shipping`, `POST checkout` |
| Confirmation | `storefront.checkout.confirmation` | `CheckoutController::confirmation` | — |
| Wishlist | `storefront.wishlist` → `pages.wishlist` | `WishlistController` | `GET /api/v1/wishlist`, `POST /api/v1/wishlist` (toggle) |
| Auth | `storefront.{login,register,account}` | `AuthPageController` | `POST auth/{register,login,logout}`, `GET customer`, `GET customer/orders` |
| CMS page | `storefront.page` → `pages.page` | `CMS PageController` (sections) | `GET /api/v1/pages/{slug}` (nếu cần) |

> **Hợp đồng hydration:** với collection/search, controller serialize **cùng shape**
> `GET /api/v1/search` (`{ data, facets, meta }`) và nhúng:
> ```blade
> <script type="application/json" data-island-state>@json($state)</script>
> ```
> `enhance/collection-shop.js` & `search-results.js` đọc payload này làm state đầu,
> **không fetch lần đầu**, chỉ gọi API khi đổi filter/sort/page/term.

---

## 3. Lớp JS

### `app.js` — bootstrap
- Định nghĩa `VUE_ISLANDS = { 'product-purchase': ProductPurchase, 'quick-view': QuickView, 'checkout-page': CheckoutPage }`. Quét `[data-vue]`, **chỉ** mount nếu nằm trong allow-list — ngoài danh sách thì bỏ qua (không phải cứ có `data-vue` là mount).
- Auto-load mọi `enhance/*.js` (trừ `_card.js`), gọi `default(document)` khi DOM ready; mỗi enhancer tự target qua `data-*`, idempotent để chạy lại trên fragment mới.

### `api.js` — axios
- `axios.create({ baseURL: '/api/v1', withCredentials: true, headers: { 'X-Requested-With': 'XMLHttpRequest' } })`; CSRF + Sanctum cookie tự đính kèm (cùng domain). Interceptor map lỗi 422 → field errors, 401 → điều hướng login.

### `events.js` — đồng bộ giỏ
- `cart:updated` (ai đó đổi giỏ: add-to-cart card, variant island, cart-page) → `enhance/cart.js` refresh từ `/api/v1/cart` → bắn `cart:refreshed` → `cart-page.js` re-render. Refresh **không** bắn `cart:updated` (tránh vòng lặp). Checkout (Vue) nghe cùng event qua store riêng.

### Vanilla enhancers (mẫu `enhance/_card.js`)
- Render card sản phẩm từ JSON `ProductResource` **khớp 1:1** `components/product-card.blade.php` → SSR grid và grid re-render sau filter trông y hệt.

### Vue islands (3)
- `ProductPurchase.vue`: variant matrix (size/color), tồn kho, giá theo variant, add-to-cart → `POST /api/v1/cart` → `dispatchEvent('cart:updated')`. Hydrate từ `data-island-state` của product page (không fetch on mount).
- `QuickView.vue`: mở từ card (delegated), fetch `GET products/{slug}` (nội dung session/tương tác, được phép fetch-on-open), chứa variant + add-to-cart.
- `CheckoutPage.vue`: flow address → shipping → place, dùng 4 endpoint checkout. State giỏ là server-side (Lunar cart).

---

## 4. SSR shell — quy ước Blade

- `layouts/app.blade.php`: `<head>` đổ SEO/meta từ data (product/collection/page có `seo`); `@stack('head')`, `@yield('content')`, `@stack('scripts')`; `@vite` trỏ entry theme.
- **Catalog grid**: render `@foreach($products as $p) <x-theme::product-card :product="$p"/>` (HTML thật) + nhúng `$state` cho enhancer. Facet sidebar + pagination là `GET` link thật.
- **Sections**: `pages.home`/`pages.page` lặp `page_sections` → `@include('theme::sections.'.$section->type, ['settings' => $section->settings])`. Render server-side (tốt SEO), không drag-drop editor.
- `$theme` (ThemeSettings) dùng cho logo/màu/typography/social — đã được composer inject.

---

## 5. Build / Vite

- `@vitejs/plugin-vue` + `@tailwindcss/vite` đã cài; `vite.config.js` chọn entry theo `process.env.THEME` (default `fashion`) — trỏ `themes/fashion/js/app.js` + `css/app.css`.
- `css/app.css`: `@import "tailwindcss";` + design tokens (CSS vars cho brand color/font lấy từ ThemeSettings, có thể inject qua `<style>` inline ở layout).
- Dev: `THEME=fashion npm run dev`; prod: `npm run build`.
- **Cleanup nhỏ:** comment trong `vite.config.js` còn nhắc "modave uses vendor CSS" → cập nhật/bỏ cho khớp theme `fashion`.

---

## 6. Lộ trình thực hiện

**Bước 1 — Khung chạy được (SSR shell)**
- `theme.json`, `css/app.css`, `js/app.js` (bootstrap rỗng), `js/api.js`, `js/events.js`.
- `layouts/app.blade.php` + `partials/{header,footer,flash}` + `components/{product-card,price,pagination,breadcrumb}`.
- `pages/home.blade.php` render sections → xác nhận `/` lên hình, `npm run dev` OK.

**Bước 2 — Catalog SSR-first**
- `pages/product.blade.php` (SSR đầy đủ, chưa cần island) + `pages/collection.blade.php` + `pages/search.blade.php` với SSR grid + facet + nhúng `$state`.
- `enhance/_card.js` khớp `product-card.blade.php`; `collection-shop.js` + `search-results.js` đọc `$state`, fetch khi tương tác, `history.replaceState` đồng bộ URL.
- Kiểm thử **no-JS**: filter/sort/page/search vẫn chạy bằng `GET`.

**Bước 3 — Giỏ & wishlist (vanilla)**
- `partials/cart-drawer.blade.php` (`#shoppingCart`) + `enhance/cart.js` + `pages/cart.blade.php` + `enhance/cart-page.js`; per-card add-to-cart (delegated) + event bus.
- `pages/wishlist.blade.php` + `enhance/wishlist.js` (toggle, count, load membership 1 lần).

**Bước 4 — Vue islands**
- `ProductPurchase.vue` gắn vào product page (`data-vue="product-purchase"` + `data-island-state`); size chart + recommend-size; `related`/recommendations.
- `QuickView.vue` mở từ card; `search-modal.js` (suggest).

**Bước 5 — Checkout & account**
- `pages/checkout.blade.php` + `CheckoutPage.vue` (4 endpoint) + confirmation page.
- `pages/auth/*` + `enhance/auth.js` (login/register/logout, customer + orders).

**Bước 6 — Hoàn thiện**
- SEO/meta đầy đủ mọi trang, OpenGraph, breadcrumb JSON-LD; responsive; lazyload ảnh (jQuery plugin nếu cần); a11y; gỡ comment modave trong vite.

---

## 7. Definition of Done

- [ ] Mọi trang catalog render HTML thật, pass kiểm thử tắt JS (filter/sort/search/paginate qua `GET`).
- [ ] SSR grid và grid sau-tương-tác khớp pixel (cùng `product-card` markup).
- [ ] SSR `$state` ↔ `/api/v1/search` cùng một shape, không lệch.
- [ ] Chỉ đúng 3 Vue island được mount; `data-vue` ngoài allow-list bị bỏ qua.
- [ ] Giỏ đồng bộ qua `cart:updated`/`cart:refreshed`, không vòng lặp.
- [ ] Checkout đặt hàng end-to-end (COD/bank qua driver hiện có).
- [ ] SEO/meta + OpenGraph đầy đủ; Lighthouse SEO ≥ 95, không layout shift do hydrate.
- [ ] Đổi brand = copy `themes/fashion` → `themes/<brand>`, đổi `THEME` env, không đụng `app/`.
