# Production Deploy Runbook — SME Fashion Ecommerce

> Quy trình đưa **Laravel 12 + LunarPHP** lên production và vận hành. Đọc kèm
> [../architecture/overview.md](../architecture/overview.md).
> Cập nhật lần cuối: **2026-09-22** (§10 rotate khoá: `.env.example` đã trống,
> nhưng khoá hiện tại vẫn nằm trong `compromised_app_keys` → deploy production vẫn
> bị chặn; xem §10).

---

## 0. ⚠️ Việc bắt buộc trước lần deploy đầu tiên

1. **Rotate `APP_KEY`.** File `.env` từng bị commit (đã gỡ khỏi index
   2026-07-08, nhưng **vẫn nằm trong git history**). Quy trình 4 bước bắt buộc ở
   [§10](#10-rotate-app_key). `shop:preflight` **chặn deploy production** cho tới
   khi làm xong.

   > **Mục này trước đây ghi sai, đã sửa sau khi rà lại history (2026-09-18).**
   > Bản cũ dặn đổi DB password, VNPay hash secret, MoMo key, SMTP, Redis — và
   > nói *"`APP_KEY` không cần đổi nếu chưa có dữ liệu mã hoá thật"*. Cả hai vế
   > đều ngược:
   >
   > - Quét toàn bộ **314 commit**: bí mật **duy nhất** từng vào repo là
   >   `APP_KEY` (9 commit). `DB_PASSWORD`, `MAIL_PASSWORD`, `REDIS_PASSWORD`,
   >   AWS đều **rỗng hoặc `null`** ở mọi bản. Key VNPay/MoMo **chưa từng** nằm
   >   trong `.env` — chúng đọc qua `Settings` (Cài đặt → Thanh toán), không qua
   >   env. Rotate chúng vẫn nên làm nếu đã dùng thật, nhưng **không phải vì
   >   repo**.
   > - Và `APP_KEY` thì **có** dữ liệu mã hoá thật: 2FA của staff
   >   (`lunar_staff.app_authentication_*`). Đổi khoá mà không mã hoá lại là
   >   khoá mọi staff bật 2FA ra khỏi panel.
   > - **Nặng hơn cả history:** `.env.example` — file đang tracked — mang đúng
   >   khoá đó, nên ai clone repo cũng có mà không cần đào history. Đã thay bằng
   >   chỗ trống. Lượt rà đầu chỉ quét `.env` và bỏ sót nó; nay
   >   `KeyRotationTest` quét mọi file tracked.

2. **`.env` production tạo tay trên server** từ `.env.example` — không copy từ
   máy dev, không commit.

## 1. Yêu cầu server

| Thành phần | Yêu cầu |
|---|---|
| PHP | 8.4 + extensions: `bcmath, ctype, curl, dom, fileinfo, gd` (hoặc imagick — media conversions), `intl, mbstring, mysqli/pdo_mysql, opcache, redis, xml, zip` |
| MySQL | 8.x (app dùng JSON functions — bắt buộc MySQL, không SQLite) |
| Redis | cache + session + queue (Horizon) |
| Node | chỉ cần lúc build (Vite 7) — có thể build ở CI rồi rsync `public/build` |
| Web server | nginx (root = `public/`) |
| Supervisor | chạy `php artisan horizon` |
| Cron | 1 dòng `schedule:run` (xem §4) |

## 2. `.env` production — các key quyết định

```dotenv
APP_NAME="<Tên shop>"            # đang mặc định "Laravel" — hiện ở title/mail/error page
APP_ENV=production
APP_DEBUG=false                  # BẮT BUỘC — debug=true lộ secrets qua trang lỗi
APP_URL=https://your-domain.com

LOG_CHANNEL=daily                # + LOG_LEVEL=warning (hoặc error)
LOG_DAILY_DAYS=14

DB_*                             # MySQL production, user riêng least-privilege

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true       # cookie chỉ đi qua HTTPS
QUEUE_CONNECTION=redis
REDIS_*                          # host/password

MAIL_MAILER=smtp                 # + credential SMTP thật (mail đang là `log`)

SANCTUM_STATEFUL_DOMAINS=your-domain.com
SESSION_DOMAIN=your-domain.com

# Payment production endpoints + keys (đọc qua Settings/DB
# hoặc env fallback): VNPAY_*, MOMO_* — dùng URL production, không sandbox.
```

Đứng sau proxy/load-balancer (Cloudflare…)? Thêm trusted proxies trong
`bootstrap/app.php`: `$middleware->trustProxies(at: '*')` (hoặc dải IP LB) để
`APP_URL`/secure cookie/`url()` nhận đúng scheme https.

## 3. Quy trình deploy (mỗi release)

```bash
# 1. Code + dependencies
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader
                                   # kéo lunarphp/core + lunarphp/panel về vendor/ và
                                   # dựng lại autoload PSR-4 cho 13 module
                                   # (wikimedia/composer-merge-plugin);
                                   # post-autoload-dump publish lại asset panel
npm ci && npm run build            # hoặc build ở CI, rsync public/build

# 2. Maintenance window (trang 503 branded đã có)
php artisan down --retry=30

# 3. DB + storage
php artisan migrate --force
php artisan storage:link           # lần đầu

# 4. Cache framework (đã verify hoạt động 2026-07-08)
php artisan optimize               # config + route + event + view cache

# 5. Restart workers (bắt buộc sau khi đổi code — worker giữ code cũ trong RAM)
php artisan horizon:terminate      # supervisor tự khởi động lại

# 6. CỔNG PHÁT HÀNH — chạy TRƯỚC `up`, khi site còn đóng
php artisan shop:preflight         # exit 1 = đừng mở traffic

php artisan up
```

> **`shop:preflight` là cổng, không phải báo cáo.** Nó thoát khác 0 khi cấu hình
> sẽ làm mất tiền hoặc lộ dữ liệu: `APP_DEBUG` bật ở production, endpoint thanh
> toán còn trỏ sandbox, có mã merchant mà thiếu khoá ký, queue còn `sync`,
> session driver còn `file`, hoặc tài khoản seeder demo còn tồn tại. Cảnh báo
> (exit 0 nhưng có in) là những thứ nên sửa mà không đáng chặn phát hành.
>
> Đặt nó **sau `migrate`** vì có phép kiểm cần đọc DB, và **trước `up`** vì cả
> mục đích là chặn traffic chứ không phải ghi nhận sau khi khách đã vào. Trên
> host mới chưa migrate thì `--env-only` bỏ qua các phép kiểm cần DB.
>
> Chỗ đau nhất nó canh: `VNPAY_PAYMENT_URL` / `MOMO_ENDPOINT` còn trỏ sandbox.
> Không có gì trên site báo điều đó — khách bấm thanh toán, thấy màn hình cổng
> quen thuộc, "trả tiền" xong, đơn về `payment-received`, và tiền thì không bao
> giờ tồn tại.

Rollback: `git checkout <tag trước> && composer install ... && php artisan
migrate:rollback --step=N` (chỉ khi migration mới gây lỗi) + lại bước 4–5.

> ⚠️ **Lần deploy đầu sau bản siết payment callback:** migration
> `unique_gateway_capture_per_order` tạo unique index trên `lunar_transactions`. Trước
> đây **không có gì** ngăn hai capture trùng (return-URL + IPN cùng lúc), nên production
> có thể đã có sẵn bản ghi trùng → migration sẽ **fail giữa chừng**. Kiểm tra trước khi
> `migrate --force`:
>
> ```sql
> SELECT order_id, driver, reference, COUNT(*) c
> FROM lunar_transactions WHERE type = 'capture'
> GROUP BY order_id, driver, reference HAVING c > 1;
> ```
>
> Có kết quả → đối soát tay với sao kê gateway, giữ **một** dòng mỗi nhóm rồi mới migrate.
> Đừng xoá bừa: mỗi dòng là một lần tiền thật đã chuyển.

## 4. Supervisor + Cron

Horizon (đã cấu hình 2 supervisor trong `config/horizon.php`: `supervisor-app`
cho mails/notifications/default, `supervisor-media` cho ảnh):

```ini
[program:horizon]
process_name=%(program_name)s
command=php /var/www/lunar-shop/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/horizon.log
stopwaitsecs=3600
```

Cron (chạy schedule trong `routes/console.php`: horizon:snapshot 5' /
sanctum:prune-expired daily / queue:prune-failed weekly /
lunar:stock:reconcile 03:30 / **orders:expire-abandoned 10'** / carts:remind-abandoned 10' /
orders:request-reviews 09:00 / referrals:release 09:30 / loyalty:expire 09:45 /
schedule:heartbeat hourly):

```cron
* * * * * cd /var/www/lunar-shop && php artisan schedule:run >> /dev/null 2>&1
```

> ⚠️ **`orders:expire-abandoned` là bắt buộc, không phải dọn dẹp cho đẹp.** Đơn thanh toán
> qua gateway giữ tồn kho **trước khi** khách trả tiền (Lunar tạo order ở
> `authorize()`, rồi mới redirect sang VNPay/MoMo). Không chạy cron này thì mỗi khách
> đóng tab giữa chừng sẽ **khoá tồn kho vĩnh viễn**. Bank-transfer thu tay nên an toàn
> (nó có `placed_at`).
>
> Command cũng dọn **đơn mồ côi** (`placed_at IS NULL`) — order đã tạo và trừ kho nhưng
> process chết trước khi driver kịp ghi `placed_at`/`meta`.
>
> ⚠️ **Lần chạy đầu sau bản vá này** có thể huỷ một loạt đơn mồ côi tồn đọng từ trước
> (trước đây **không có gì** dọn chúng). Đếm trước:
>
> ```sql
> SELECT COUNT(*) FROM lunar_orders WHERE placed_at IS NULL AND stock_released_at IS NULL;
> ```
>
> Số lớn bất thường → xem lại vài đơn trước khi để cron chạy; chúng đáng lẽ không tồn tại.
>
> **Thời gian giữ hàng** (mặc định 60') giờ nằm ở **Admin → Cấu hình → Kho**
> (`inventory.hold_minutes`, clamp 10–10080). Scheduler **không** truyền `--minutes`
> nữa — truyền vào sẽ âm thầm ghi đè lựa chọn của chủ shop. Cờ chỉ để quét thủ công
> một lần (`--minutes=5` sau khi vừa sửa gateway).
>
> Chạy thử trước khi bật: `php artisan orders:expire-abandoned --dry-run`.

### 4.1 Dây bảo hiểm cho scheduler

Cron ngừng chạy thì **im lặng**, mà im lặng trông y hệt "không có gì để làm" — đó
chính là lý do cảnh báo ở trên nguy hiểm. Nay mỗi lần scheduled task chạy đều được
ghi vào bảng `scheduled_runs`, và:

```bash
php artisan schedule:heartbeat      # exit 0 = mọi task đúng hạn, exit 1 = có task lặng
```

Ngưỡng đọc từ **chính cron expression của từng task** (quá hạn = lỡ hai lượt liên
tiếp), nên thêm command mới vào `routes/console.php` là tự được canh — không có
danh sách ngưỡng nào để trôi khỏi thực tế.

Lệnh này tự chạy mỗi giờ và ghi `Log::error` khi phát hiện task lặng.

> ⚠️ **Giới hạn phải biết:** nếu **cả** scheduler chết thì lệnh này cũng không chạy.
> Nó chỉ bắt được trường hợp một task lặng trong khi cron vẫn sống. Muốn phủ nốt
> trường hợp còn lại, cho uptime check bên ngoài (cron riêng, Healthchecks.io,
> UptimeRobot…) gọi `php artisan schedule:heartbeat` và cảnh báo theo **exit code**.

## 5. nginx (điểm chính)

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/lunar-shop/public;
    index index.php;

    client_max_body_size 25m;                 # upload media admin

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ { fastcgi_pass unix:/run/php/php8.4-fpm.sock; include fastcgi_params;
                        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; }

    # Static + media: cache dài hạn (build assets có hash trong tên file)
    location ~* \.(css|js|woff2?|jpg|jpeg|png|webp|svg|ico)$ {
        expires 30d; add_header Cache-Control "public, immutable";
        try_files $uri /index.php?$query_string;   # media on-demand conversion cần fallback PHP
    }

    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header Referrer-Policy strict-origin-when-cross-origin;
}
# + server block 80 → redirect 301 https
```

Lưu ý: **giữ `try_files … /index.php`** cho media — ảnh conversion sinh
on-demand qua PHP lần đầu, các lần sau nginx serve file tĩnh.

## 6. Bảo mật đã wired trong code (2026-07-08)

- **Rate limiting** (sửa 2026-07-09): `Modules\Core\Http\Middleware\ThrottleApiV1`
  (prepend global, guard theo URI `api/v1/*`) phủ **mọi** route `api/v1` — 120
  req/phút/user-hoặc-IP. Trước đó dùng `throttleApi()` vốn chỉ áp cho nhóm middleware
  `api`, nên **48/52 route không có limiter** (cart/checkout/orders/customer chạy nhóm
  `web`/`storefront` vì cần session) — kể cả `POST /api/v1/checkout`.
  - `throttle:checkout` **10 req/phút** cho `POST /api/v1/checkout` (write đắt: tạo
    order, giữ kho, gọi gateway).
  - `throttle:auth` 10 req/phút/IP trên `auth/login`, `auth/register`, `auth/token`,
    `auth/token/register` (chống brute-force).
  - `GET /api/v1/health` **miễn trừ** (limiter dùng cache; probe phải sống khi cache chết).
- **Health probe**: `GET /api/v1/health` kiểm tra thật DB + cache + queue, trả **503
  `degraded`** khi bất kỳ cái nào hỏng (trước đây luôn trả `"ok"` → load balancer giữ
  node chết trong rotation). Chạy **không middleware**. Dùng cho readiness probe.
- **Horizon dashboard** (`/horizon`): chỉ **Lunar staff có cờ admin** (guard
  `staff`) truy cập ở non-local. Đăng nhập panel trước rồi mở /horizon.
- **Error pages** 404/500/503/403/419: tự chứa (không phụ thuộc DB/theme),
  song ngữ EN/VI, `noindex` — không lộ stack trace khi `APP_DEBUG=false`.
- **CSRF** (cập nhật 2026-07-10): bật toàn bộ, trừ
  - `payment/momo/ipn` (xác thực bằng HMAC chữ ký), và
  - **request stateless** — mang `Authorization: Bearer`, `X-Cart-Token`, hoặc
    `X-Client` (`Modules\Core\Http\Middleware\VerifyCsrfTokenUnlessStateless`).
    Cart/checkout nằm group `web` (Lunar cart cần session) nên trước đây 419 mọi ghi
    từ app di động. Request stateless **không mang credential ngầm** (browser không tự gắn
    `Authorization` cross-site; header tuỳ biến phải qua CORS preflight, mà
    `cors.supports_credentials=false`), nên không có gì để forge. Khách **đã đăng nhập
    bằng cookie thì không bao giờ được miễn trừ**.
- **Token API** (app di động): `expires_at` 60 ngày (`API_TOKEN_TTL_DAYS`), ability
  `customer:*`, xoay qua `POST /api/v1/auth/token/refresh` (thu hồi token cũ).
  ⚠️ **Không bật `sanctum.expiration`** — nó tính từ `created_at` nên sẽ vô hiệu hoá
  **mọi token đã phát hành**.
- ⚠️ **Chạy test:** luôn `php artisan optimize:clear` trước. `config:cache` che các
  `<env>` trong `phpunit.xml`, khiến `runningUnitTests()` = false → CSRF chạy thật →
  test checkout đỏ 419. (Hành vi Laravel, có từ trước; tái hiện được trên code cũ.)
- **Header bảo mật** (thêm 2026-09-10): `Modules\Core\Http\Middleware\SecurityHeaders`
  prepend global nên phủ cả storefront, API lẫn panel. Gửi `X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` ở mọi môi trường;
  `Strict-Transport-Security` **chỉ** ở production trên https (gửi nhầm trên http
  sẽ khoá trình duyệt vào https cho cả domain, không rút lại được từ server).
  - **CSP mặc định `report`**, không phải `enforce`. Một CSP sai làm chết JS trên
    toàn site và **không để lại dòng log nào phía server** — triệu chứng chỉ nằm
    trong trình duyệt khách. Chạy report vài ngày, đọc vi phạm, rồi `CSP_MODE=enforce`.
  - Storefront **đủ điều kiện enforce ngay**: không có một inline script thực thi
    nào (mọi `<script>` đều có `src=`, hoặc là `application/json` — dữ liệu, không
    chạy), nguồn ngoài duy nhất là Google Fonts. `style-src` buộc phải có
    `'unsafe-inline'` vì theme có 11 thuộc tính style động (vị trí hotspot
    lookbook, ảnh nền slider) — inline style không thực thi mã nên rủi ro thấp
    hơn hẳn; `script-src 'self'` mới là phần đáng giá và nó sạch.
  - **Panel LUÔN report-only** bất kể `CSP_MODE`: đó là bundle Inertia/Vue của
    Lunar, ta không kiểm soát nó inline gì, và một policy làm hỏng panel là khoá
    nhân viên khỏi chính cửa hàng của họ.
- **Cổng thanh toán chưa cấu hình thì KHÔNG được chào** (sửa 2026-09-10). Trước
  đây trang checkout ẩn đúng, nhưng whitelist validation của API lại là danh sách
  riêng không lọc — `payment_type: vnpay` được nhận trên shop không có credential
  VNPay, đơn đặt xong không có bước thanh toán nào (`paymentRedirectUrl()` trả
  null), khách về thẳng trang cảm ơn mà chưa trả đồng nào và kho bị giữ tới lượt
  quét đơn bỏ quên.
- **API error envelope**: 500 không leak message nội bộ (bootstrap/app.php).
- **VNPay/MoMo IPN**: idempotent + verify chữ ký (test phủ tamper case).

## 7. Checklist sau deploy (smoke)

1. `https://domain/up` → 200 (health Laravel) và `/api/v1/health` → 200.
2. Trang chủ, trang product, collection render (SSR, đúng ảnh + giá).
3. Add-to-cart → checkout COD end-to-end (order xuất hiện trong admin).
4. VNPay/MoMo sandbox→production: một giao dịch thật giá trị nhỏ + IPN về
   (kiểm tra order → payment-received + email).
5. `/panel` đăng nhập được (đường cũ `/lunar` là thời Filament, đã bỏ);
   `/horizon` mở được bằng staff admin, job `mails` chạy khi đặt hàng test.
   Mở một đơn bất kỳ trong panel: phải thấy thẻ fulfilment — đơn không có
   fulfilment nghĩa là chuỗi `OrderPlaced` của Lunar không chạy.
6. Trang bất kỳ không tồn tại → 404 branded; `php artisan down` → 503 branded.
7. `php artisan about` trên server: Environment=production, Debug=OFF, mọi
   cache CACHED, storage LINKED.

## 8. Vận hành định kỳ

- **Backup**: mysqldump hằng ngày (giữ ≥ 14 bản) + `storage/app` & `public/media`
  (ảnh sản phẩm) — script/cron ngoài repo. Test restore mỗi quý.
- **Log**: `storage/logs/laravel-*.log` (daily, 14 ngày). **Error tracker: Sentry đã
  cắm** (2026-09-10) nhưng **tắt mặc định** — chưa có `SENTRY_LARAVEL_DSN` thì không
  gửi gì. Việc còn lại khi lên production: tạo project Sentry + đặt DSN, xem §9.
- **Monitor**: uptime check `/up`; Horizon dashboard cho queue lag; disk cho
  `public/media` (conversion tăng dần).
- **Nâng cấp**: `composer outdated` hàng tháng (Laravel, Lunar, Spatie…) — chạy full
  test suite trước khi lên.
  Lunar là composer package (`lunarphp/core` + `lunarphp/panel`) nên **nằm trong
  `composer outdated` bình thường** và nhận security patch như mọi dependency khác.
  Dự án **không còn composer patch nào** (2026-08-27), nên `composer update` không
  còn điểm fail cứng vì vendor bị sửa. Xem [../upstream/README.md](../upstream/README.md).
  ⚠️ **Nâng minor Lunar không phải việc thường lệ.** Bản 1.5 kéo theo Filament v4;
  bản 2.0 thay cả tầng admin và bỏ `orders.status`. Mỗi bản có runbook riêng:
  [upgrade-lunar-1.5.md](upgrade-lunar-1.5.md), [upgrade-lunar-2.0.md](upgrade-lunar-2.0.md).

## 9. Chưa làm (chấp nhận được ở quy mô SME, làm khi cần)

- CDN cho `public/` (build assets + media) — todo #4 trong ../roadmap.md.
- Zero-downtime deploy (symlink releases / Deployer) — hiện dùng maintenance
  window ngắn với trang 503 branded.

### Đã làm xong, không còn nằm ở mục này

- ~~Error tracker bên thứ ba~~ ✅ Sentry đã cắm (2026-09-10), **tắt mặc định**.
  Không có `SENTRY_LARAVEL_DSN` thì SDK không gửi gì và không mở kết nối nào —
  bật một đường truyền dữ liệu ra bên thứ ba phải là quyết định có người bấm nút.

  ⚠️ **Việc phải làm khi lên production:** tạo project trên Sentry, đặt
  `SENTRY_LARAVEL_DSN`, và đặt `SENTRY_RELEASE=$(git rev-parse --short HEAD)`
  trong quy trình deploy để biết bản nào gây lỗi. `shop:preflight` cảnh báo
  (không chặn) nếu thiếu, và **chặn** nếu `send_default_pii` bị bật.

  `Modules\Core\Support\SentryScrubber` là lưới lọc cuối trước khi một sự kiện
  rời máy chủ. `send_default_pii => false` chặn phần lớn nhưng KHÔNG chặn những
  thứ mang dữ liệu khách theo đường vòng, và với shop thì đó mới là chỗ nguy
  hiểm: lỗi ở `/payment/vnpay/return?vnp_SecureHash=…` mang nguyên chữ ký thanh
  toán trong URL. Scrubber xoá query string, header xác thực, email/điện
  thoại/địa chỉ, ràng buộc SQL — giữ lại đúng id người dùng, vì không có định
  danh nào thì không khớp báo lỗi với người báo được.

  Còn thiếu `CSP_REPORT_URI`: CSP đang chạy `report` nhưng chưa có chỗ nhận, nên
  vi phạm chỉ tồn tại trong DevTools của người đang mở trang. Sentry có endpoint
  nhận CSP report — đặt cùng lúc với DSN.

- ~~CI pipeline~~ ✅ `.github/workflows/ci.yml` chạy trên mọi push và PR vào
  `main`, bốn job song song:

  | Job | Canh cái gì |
  | --- | --- |
  | **PHPUnit** | Toàn bộ suite trên MySQL 8 thật (không phải SQLite — app dùng JSON function cho attribute + facet) |
  | **Dusk** | Smoke trình duyệt thật. Đỏ thì upload ảnh chụp + console log, vì lỗi trình duyệt không để lại dấu vết phía server |
  | **Bảo mật dependency** | `composer audit` + `npm audit --omit=dev --audit-level=high`. `composer.lock` ghim version nên CVE mới không tự xuất hiện |
  | **Pint** | Format, chạy `vendor/bin/pint --test` trên **toàn repo** (local thì dùng `vendor/bin/pint --dirty` cho file vừa sửa) |
  ⚠️ Cả job PHPUnit lẫn Dusk đều **bắt buộc** chạy `npm run build`: `public/build`
  và bundle add-on của panel đều gitignore, thiếu là mọi trang `@vite` trả 500 và
  trang panel render rỗng mà không có lỗi phía server nào để lần.

---

## 10. Rotate `APP_KEY`

**Trạng thái: CHƯA LÀM — khoá đang chạy vẫn là khoá trong git history.** `shop:preflight`
chặn deploy production cho tới khi xong. `.env.example` đã được trống khoá (2026-09-18),
không còn file tracked nào mang khoá đó — nhưng khoá hiện tại vẫn là khoá đã từng leak,
vẫn nằm trong `config/security.php` → `compromised_app_keys`, nên deploy sản phẩm vẫn bị
chặn bởi `shop:preflight`.

### Vì sao không chỉ là `php artisan key:generate`

`APP_KEY` mã hoá hai thứ, và chỉ một thứ là vô hại khi đổi:

| | Đổi khoá thì sao |
| --- | --- |
| Session cookie | Khách bị đăng xuất. Phiền, hết. |
| Cột `encrypted` trong DB | **Không giải được nữa.** Mất dữ liệu. |

Shop này có đúng hai cột loại thứ hai — `lunar_staff.app_authentication_secret`
và `…_recovery_codes`, tức **2FA của staff**. Và chúng không hỏng lịch sự: secret
giải ra rác thì không phải base32, Google2FA ném `InvalidCharactersException`,
nên màn hình 2FA trả **500** chứ không báo "mã sai". Dự án đã trả giá đúng lớp
lỗi này một lần ở đợt nâng Lunar 1.5.

Danh sách cột nằm ở `Modules\Core\Support\EncryptedColumns`, và
`KeyRotationTest` quét source bắt nó phải phủ mọi cast `encrypted` — thêm model
mới có cột mã hoá mà quên khai báo là suite đỏ, không phải là một sự cố
production.

### Bốn bước

Chạy trên server production, **trong maintenance window**, sau khi đã backup DB.

```bash
# 1. Lấy khoá hiện tại ra trước khi ghi đè — đây là thứ duy nhất đọc được dữ liệu cũ
grep '^APP_KEY=' .env          # chép lại giá trị này

php artisan key:generate --force

# 2. Đặt khoá CŨ vào APP_PREVIOUS_KEYS để Laravel còn đọc được dữ liệu cũ
#    (thêm dòng này vào .env, giá trị là khoá vừa chép ở bước 1)
#    APP_PREVIOUS_KEYS=base64:...khoá-cũ...

php artisan config:clear

# 3. Chuyển dữ liệu sang khoá mới. Xem trước bằng --dry-run.
php artisan shop:reencrypt --dry-run
php artisan shop:reencrypt

# 4. Chỉ khi bước 3 trả về thành công: bỏ APP_PREVIOUS_KEYS khỏi .env
php artisan config:clear
php artisan shop:preflight        # phải xanh dòng "APP_KEY đã rotate"
```

> ⚠️ **Bước 3 là bước duy nhất không ai nghĩ tới, và bỏ nó KHÔNG có triệu
> chứng.** `APP_PREVIOUS_KEYS` khiến mọi thứ vẫn giải mã được, nên site trông
> như bình thường. Rồi tới lúc ai đó dọn biến môi trường thừa — có thể vài tháng
> sau — 2FA của toàn bộ staff chết cùng một lúc, và không còn ai nối được hai sự
> kiện với nhau.

> `shop:reencrypt` **chạy lại được** (giá trị đã ở khoá mới thì bỏ qua) và
> **không bao giờ ghi đè** giá trị nó không giải được — nó báo lỗi và giữ
> nguyên. Bị ngắt giữa chừng thì chỉ cần chạy lại.

### Nếu bước 3 báo có giá trị không giải được

Nghĩa là khoá cũ không mở được chúng — thường là dữ liệu còn sót từ một lần đổi
khoá trước đó. **Đừng bỏ `APP_PREVIOUS_KEYS`.** Cách xử lý: xoá 2FA của đúng
những staff đó trong panel và bắt họ bật lại. Giữ nguyên còn hơn ghi đè bằng rác.

### Còn git history thì sao

Rotate xong thì khoá cũ trở nên vô dụng, nên **không bắt buộc** phải viết lại
history. Nếu vẫn muốn dọn:

```bash
git filter-repo --path .env --invert-paths    # rồi force-push
```

Cái giá: mọi SHA đổi, mọi clone/fork hiện có gãy, và mọi link tới commit cũ chết.
Với repo một người thì chấp nhận được; đổi lại chỉ là việc "cho sạch", vì khoá
đã cháy thì đã cháy.

**Không xoá dòng digest trong `config/security.php`.** Khoá cũ vẫn nằm trong mọi
bản clone đã tồn tại, nên nó cháy vĩnh viễn — danh sách chỉ dài thêm, không ngắn
lại.
