# 🌙 Lunar Shop - Artisan Commands

## Cài đặt Lunar

```bash
php artisan lunar:install
php artisan vendor:publish --tag=lunar
```

## Queue work

```bash
php artisan queue:work



## Quản lý dữ liệu

```bash
# Cập nhật tỷ giá tiền tệ
php artisan fetch:currency-rate

# Tự động hủy đơn hàng quá hạn
php artisan process:order
```

## Tối ưu & Cache

```bash
# Xóa toàn bộ cache hệ thống
php artisan optimize:clear
```

## Database

```bash
# Chạy migration
php artisan migrate

# Seed dữ liệu
php artisan db:seed
```

## Composer

```bash
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
composer update --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix