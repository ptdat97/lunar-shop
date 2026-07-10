# Production Deploy Runbook — SME Fashion Ecommerce

> Quy trình đưa **Laravel 12 + LunarPHP** lên production và vận hành. Đọc kèm
> [lunarphp_sme_fashion_plan.md](lunarphp_sme_fashion_plan.md).
> Cập nhật lần cuối: **2026-07-08**.

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

# Payment production endpoints + keys (đọc qua Filament Payment Settings/DB
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
npm ci && npm run build            # hoặc build ở CI, rsync public/build

# 2. Maintenance window (trang 503 branded đã có)
php artisan down --retry=30

# 3. DB + storage
php artisan migrate --force
php artisan storage:link           # lần đầu

# 4. Cache framework (đã verify hoạt động 2026-07-08)
php artisan optimize               # config + route + event + view cache
php artisan filament:cache-components
php artisan icons:cache

# 5. Restart workers (bắt buộc sau khi đổi code — worker giữ code cũ trong RAM)
php artisan horizon:terminate      # supervisor tự khởi động lại

php artisan up
```

Rollback: `git checkout <tag trước> && composer install ... && php artisan
migrate:rollback --step=N` (chỉ khi migration mới gây lỗi) + lại bước 4–5.

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
> đóng tab giữa chừng sẽ **khoá tồn kho vĩnh viễn**. Command chỉ đụng đơn gateway
> (`meta.payment_type`); bank-transfer thu tay nên an toàn.
>
> Chạy thử trước khi bật: `php artisan orders:expire-abandoned --dry-run`.

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
  `staff`) truy cập ở non-local. Đăng nhập admin Filament trước rồi mở /horizon.
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
- **API error envelope**: 500 không leak message nội bộ (bootstrap/app.php).
- **VNPay/MoMo IPN**: idempotent + verify chữ ký (test phủ tamper case).

## 7. Checklist sau deploy (smoke)

1. `https://domain/up` → 200 (health Laravel) và `/api/v1/health` → 200.
2. Trang chủ, trang product, collection render (SSR, đúng ảnh + giá).
3. Add-to-cart → checkout COD end-to-end (order xuất hiện trong admin).
4. VNPay/MoMo sandbox→production: một giao dịch thật giá trị nhỏ + IPN về
   (kiểm tra order → payment-received + email).
5. `/lunar` admin đăng nhập được; `/horizon` mở được bằng staff admin, job
   `mails` chạy khi đặt hàng test.
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
- **Nâng cấp**: `composer outdated` hàng tháng; Lunar theo minor — chạy full
  test suite trước khi lên.

## 9. Chưa làm (chấp nhận được ở quy mô SME, làm khi cần)

- CDN cho `public/` (build assets + media) — todo #4 trong todo.md.
- Error tracker bên thứ ba (Sentry).
- CI pipeline (test + pint chạy tay trước commit theo standards §15).
- Zero-downtime deploy (symlink releases / Deployer) — hiện dùng maintenance
  window ngắn với trang 503 branded.
