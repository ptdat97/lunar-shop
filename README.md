# Lunar Shop

Ecommerce fashion cho SME single-store — Laravel 12 + [Lunar](https://lunarphp.io/)
commerce core, admin Filament 3, storefront Blade SSR.

## Yêu cầu

- PHP 8.2+ (`bcmath`, `curl`, `exif`, `fileinfo`, `iconv`, `intl`, `json`, `openssl`, `pdo`, `simplexml`, `zip`)
- MySQL 8+
- Node 18+ · Composer 2

## Cài đặt

```bash
composer setup          # install · .env · key · migrate · npm install · build
php artisan db:seed     # dữ liệu demo: catalog, SKU, review, tồn kho, CMS
php artisan lunar:create-admin
composer dev            # server + queue + logs + vite
```

Storefront ở `/`, admin ở `/lunar`.

## Kiểm thử

```bash
php artisan test                    # 432 test
./vendor/bin/pint --dirty           # code style, chỉ file đã sửa
```

## Tài liệu

Toàn bộ tài liệu kỹ thuật nằm trong **[docs/](docs/README.md)**.

| | |
|---|---|
| [Kiến trúc tổng thể](docs/architecture/overview.md) | Hệ thống có gì, hoạt động ra sao |
| [Quy tắc viết code](docs/guides/coding-standards.md) | Bắt buộc đọc trước khi commit |
| [Theme `fashion`](docs/architecture/theme.md) | Cấu trúc storefront |
| [Lệnh thường dùng](docs/guides/commands.md) | artisan cheatsheet |
| [Deploy & vận hành](docs/guides/deployment.md) | Đưa lên production |
| [Việc còn tồn đọng](docs/roadmap.md) | Roadmap |

## Cấu trúc

```
app/            # Laravel skeleton (mỏng — logic nằm trong modules/)
modules/        # 13 module nghiệp vụ (nwidart/laravel-modules v13)
themes/fashion/ # Blade + JS + CSS storefront
docs/           # tài liệu kỹ thuật
```

Lunar là composer package, **không fork vào repo** — mở rộng qua điểm mở rộng
chính chủ, xem [docs/architecture/overview.md](docs/architecture/overview.md).
