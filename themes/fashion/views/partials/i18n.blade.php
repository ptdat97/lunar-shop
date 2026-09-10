{{-- Chuỗi cho JS của theme, theo ngôn ngữ khách đang xem.

     Cùng cơ chế trang sản phẩm đã dùng từ trước (một khối
     `<script type="application/json">` để JS đọc), nhưng dùng chung cho mọi
     trang thay vì mỗi trang một khối. `$storefrontI18n` do view composer của
     ThemeServiceProvider đẩy vào — Blade không tự resolve service (§7).

     `type="application/json"` nên trình duyệt KHÔNG thực thi nó, và CSP
     `script-src 'self'` cũng không chặn: khối dữ liệu, không phải mã.

     JSON_UNESCAPED_UNICODE để tiếng Việt giữ nguyên chữ có dấu thay vì thành
     chuỗi \uXXXX dài gấp mấy lần. --}}
<script type="application/json" data-storefront-i18n>@json($storefrontI18n ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
