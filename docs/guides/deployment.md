# Production Deploy Runbook — SME Fashion Ecommerce

> Quy trình đưa **Laravel 12 + LunarPHP** lên production và vận hành. Đọc kèm
> [../architecture/overview.md](../architecture/overview.md).
> Cập nhật lần cuối: **2026-08-27** (sửa §3: Lunar là composer package, bản fork
> trong repo đã gỡ từ 2026-07-20).

---

## 0. ⚠️ Việc bắt buộc trước lần deploy đầu tiên

1. **Xoay vòng (rotate) toàn bộ secrets.** File `.env` từng bị commit vào git
   (đã gỡ khỏi index 2026-07-08, nhưng **vẫn nằm trong git history**). Trước khi
   repo được push/chia sẻ rộng hơn:
   - Đổi: DB password, `APP_KEY` không cần đổi nếu chưa có dữ liệu mã hoá thật,
     VNPay hash secret, MoMo access/secret key, SMTP credential, Redis password.
   - Nếu repo đã từng public/push remote: cân nhắc rewrite history
     (`git filter-repo --path .env --invert-paths`) và force-push, hoặc coi mọi
     secret trong history là đã lộ.
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
                                   # kéo lunarphp/lunar về vendor/ và dựng lại autoload
                                   # PSR-4 cho 13 module (wikimedia/composer-merge-plugin);
                                   # post-autoload-dump publish lại asset panel
npm ci && npm run build            # hoặc build ở CI, rsync public/build

# 2. Maintenance window (trang 503 branded đã có)
php artisan down --retry=30

# 3. DB + storage
php artisan migrate --force
php artisan storage:link           # lần đầu

# 4. Cache framework (đã verify hoạt động 2026-07-08)
php artisan optimize               # config + route + event + view cache
php artisan icons:cache

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
**orders:expire-abandoned 10'**):

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
    từ app/POS. Request stateless **không mang credential ngầm** (browser không tự gắn
    `Authorization` cross-site; header tuỳ biến phải qua CORS preflight, mà
    `cors.supports_credentials=false`), nên không có gì để forge. Khách **đã đăng nhập
    bằng cookie thì không bao giờ được miễn trừ**.
- **Token API** (app/POS): `expires_at` 60 ngày (`API_TOKEN_TTL_DAYS`), ability
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
- **Log**: `storage/logs/laravel-*.log` (daily, 14 ngày). Cân nhắc gắn error
  tracker (Sentry/Flare) khi có ngân sách — chưa wired.
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
- Error tracker bên thứ ba (Sentry). **Khoảng trống lớn nhất còn lại.** Hôm nay
  lỗi production chỉ nằm trong `storage/logs` — không ai được báo, và không có
  gì gom nhóm hay đếm tần suất. Riêng vi phạm CSP thì còn tệ hơn: chúng chỉ tồn
  tại trong trình duyệt khách cho tới khi `CSP_REPORT_URI` có chỗ nhận.
- Zero-downtime deploy (symlink releases / Deployer) — hiện dùng maintenance
  window ngắn với trang 503 branded.

### Đã làm xong, không còn nằm ở mục này

- ~~CI pipeline~~ ✅ `.github/workflows/ci.yml` chạy trên mọi push và PR vào
  `main`, bốn job song song:

  | Job | Canh cái gì |
  | --- | --- |
  | **PHPUnit** | Toàn bộ suite trên MySQL 8 thật (không phải SQLite — app dùng JSON function cho attribute + facet) |
  | **Dusk** | Smoke trình duyệt thật. Đỏ thì upload ảnh chụp + console log, vì lỗi trình duyệt không để lại dấu vết phía server |
  | **Bảo mật dependency** | `composer audit` + `npm audit --omit=dev`. `composer.lock` ghim version nên CVE mới không tự xuất hiện |
  | **Pint** | Format |

  ⚠️ Cả job PHPUnit lẫn Dusk đều **bắt buộc** chạy `npm run build`: `public/build`
  và bundle add-on của panel đều gitignore, thiếu là mọi trang `@vite` trả 500 và
  trang panel render rỗng mà không có lỗi phía server nào để lần.
