# 🌙 Lunar Shop — Lệnh thường dùng

> Tổng hợp lệnh thực tế của dự án (đã kiểm tra qua `php artisan list`).

## 🌙 Lunar

| Lệnh | Mô tả |
| --- | --- |
| `php artisan lunar:install` | Cài đặt Lunar (chạy 1 lần khi setup) |
| `php artisan lunar:create-admin` | Tạo tài khoản admin Lunar |
| `php artisan lunar:search:index` | Đánh index dữ liệu tìm kiếm (qua Laravel Scout) |
| `php artisan lunar:prune:carts` | Dọn giỏ hàng cũ trong bảng carts |
| `php artisan lunar:import:address-data` | Import dữ liệu địa chỉ (quốc gia/tỉnh thành) |
| `php artisan lunar:addons:discover` | Rebuild manifest addon của Lunar |
| `php artisan lunar:orders:sync-new` | Backfill cờ "khách mới" cho đơn hàng cũ |

## 🏬 Lệnh riêng của dự án

| Lệnh | Mô tả |
| --- | --- |
| `php artisan orders:expire-abandoned` | Hủy đơn gateway chưa thanh toán quá hạn, trả tồn kho về (scheduler tự chạy mỗi 10 phút) |
| `php artisan membership:backfill` | Đồng bộ lại hạng thành viên theo tổng chi tiêu (thêm `--dry-run` để xem trước, không ghi) |

## 🌱 Seed dữ liệu

```bash
# Seed mặc định (DatabaseSeeder)
php artisan db:seed

# Seed 1 class cụ thể (class trong modules cần namespace đầy đủ)
php artisan db:seed --class="Modules\Content\Database\Seeders\HomeSectionsSeeder"
```

Seeder hay dùng (đều nằm dưới `Modules\<Module>\Database\Seeders\`):

| Class | Mô tả |
| --- | --- |
| `Catalog...\BaseDataSeeder` | Dữ liệu nền (channel, currency, tax...) |
| `Catalog...\DemoCatalogSeeder` | Danh mục + sản phẩm demo |
| `Content...\HomeSectionsSeeder` | Layout section trang chủ (idempotent, ghi đè settings đã sửa trong admin — cân nhắc trước khi chạy lại) |
| `Content...\HeaderMenuSeeder` / `FooterMenuSeeder` | Menu header / footer |
| `Content...\CmsDemoSeeder` | Trang CMS demo |
| `Promotion...\DemoPromotionSeeder` | Khuyến mãi demo (có flash sale) |
| `Promotion...\DemoCouponSeeder` | Mã giảm giá demo |
| `Customer...\VnLocationSeeder` | Tỉnh thành / quận huyện Việt Nam |

## ⏱️ Queue & Scheduler

```bash
php artisan horizon          # Queue supervisor (dùng thay queue:work khi có Redis)
php artisan queue:work       # Worker đơn giản (không Horizon)
php artisan schedule:work    # Chạy scheduler ở local (dev)
```

| Lệnh | Mô tả |
| --- | --- |
| `horizon:status` / `horizon:terminate` | Trạng thái / restart Horizon (sau khi deploy) |
| `queue:failed` / `queue:retry <id>` / `queue:retry all` | Xem / chạy lại job lỗi |
| `schedule:list` | Xem các task đã lên lịch |
| `schedule:test` | Chạy thử 1 task theo lịch |

Task đang lên lịch trong dự án:

| Lịch | Lệnh |
| --- | --- |
| Mỗi 5 phút | `horizon:snapshot` |
| Mỗi 10 phút | `orders:expire-abandoned` |
| Hằng ngày 0h | `sanctum:prune-expired --hours=24` |
| Chủ nhật 0h | `queue:prune-failed --hours=168` |

## 🗄️ Database

| Lệnh | Mô tả |
| --- | --- |
| `migrate` | Chạy migration |
| `migrate:status` | Xem trạng thái migration |
| `migrate:rollback` | Rollback batch migration cuối |
| `migrate:fresh --seed` | ⚠️ Xóa toàn bộ DB, migrate + seed lại |
| `db:show` / `db:table <table>` | Xem thông tin DB / bảng |

## ⚡ Cache & Optimize

```bash
# Dev: xóa toàn bộ cache (config, route, view, event, bootstrap)
php artisan optimize:clear

# Production: cache toàn bộ sau khi deploy
php artisan optimize
php artisan filament:optimize   # cache component + icon cho admin
```

| Lệnh | Mô tả |
| --- | --- |
| `cache:clear` | Xóa cache ứng dụng (bao gồm cache section/config trang) |
| `config:clear` / `route:clear` / `view:clear` | Xóa từng loại cache riêng |
| `filament:optimize-clear` | Xóa cache Filament |

## 🔍 Search (Scout)

| Lệnh | Mô tả |
| --- | --- |
| `scout:import "Lunar\Models\Product"` | Import model vào search index |
| `scout:flush "Lunar\Models\Product"` | Xóa model khỏi index |
| `scout:sync-index-settings` | Đồng bộ cấu hình index (Meilisearch) |

## 🖼️ Media

| Lệnh | Mô tả |
| --- | --- |
| `media-library:regenerate` | Tạo lại ảnh conversion (thumbnail, medium...) |
| `media-library:clean` | Dọn conversion/file mồ côi |

## 🧰 Khác

| Lệnh | Mô tả |
| --- | --- |
| `storage:link` | Tạo symlink storage → public |
| `auth:clear-resets` | Xóa token reset password hết hạn |
| `activitylog:clean` | Dọn activity log cũ |
| `permission:cache-reset` | Reset cache phân quyền |

## 🧠 Debug & Dev

| Lệnh | Mô tả |
| --- | --- |
| `tinker` | Laravel REPL |
| `test` / `test --filter=TenTest` | Chạy toàn bộ / 1 nhóm test |
| `pail` | Tail log realtime |
| `route:list` | Xem toàn bộ route |
| `about` | Thông tin ứng dụng, driver, version |
| `debugbar:clear` | Xóa storage của Debugbar |

## 🎨 Frontend (Vite)

```bash
npm run dev     # Dev server (HMR)
npm run build   # Build production (bắt buộc sau khi sửa SCSS/JS theme)
```

## ⚙️ Composer

```bash
# Windows thiếu ext-pcntl/ext-posix nên cần ignore
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
composer update --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

## 🌿 Git

### Trạng thái & Lịch sử

| Lệnh | Mô tả |
| --- | --- |
| `git status` | Xem trạng thái file thay đổi |
| `git log --oneline` | Lịch sử commit ngắn gọn |
| `git log --oneline --graph` | Lịch sử dạng cây nhánh |
| `git diff` | Thay đổi chưa stage |
| `git diff --staged` | Thay đổi đã stage |

### Commit

| Lệnh | Mô tả |
| --- | --- |
| `git add .` / `git add <file>` | Stage toàn bộ / 1 file |
| `git commit -m "message"` | Tạo commit |
| `git commit --amend` | Sửa commit cuối (chưa push) |

### Nhánh

| Lệnh | Mô tả |
| --- | --- |
| `git branch` | Danh sách branch |
| `git checkout -b <branch>` | Tạo và chuyển sang branch mới |
| `git checkout <branch>` | Chuyển branch |
| `git merge <branch>` | Merge branch vào branch hiện tại |
| `git branch -d <branch>` | Xóa branch đã merge (`-D` = xóa bắt buộc) |

### Remote

| Lệnh | Mô tả |
| --- | --- |
| `git pull origin main` | Kéo code từ remote |
| `git push origin <branch>` | Đẩy branch lên remote |
| `git fetch` | Lấy thông tin remote, không merge |
| `git remote -v` | Danh sách remote |

### Undo & Reset

| Lệnh | Mô tả |
| --- | --- |
| `git restore <file>` | Hoàn tác thay đổi chưa stage |
| `git restore --staged <file>` | Bỏ stage file |
| `git reset HEAD~1` | Bỏ commit cuối, giữ thay đổi |
| `git reset --hard HEAD~1` | ⚠️ Bỏ commit cuối, xóa thay đổi |
| `git reset --hard <commit>` | ⚠️ Quay về commit cụ thể, xóa thay đổi sau đó |
| `git revert <commit>` | Tạo commit đảo ngược (an toàn khi đã push) |
| `git push origin HEAD --force` | ⚠️ Push đè lịch sử remote |

### Stash & Tag

| Lệnh | Mô tả |
| --- | --- |
| `git stash` / `git stash pop` | Lưu tạm / khôi phục thay đổi |
| `git stash list` / `git stash drop` | Xem / xóa stash |
| `git tag v1.0.0` | Tạo tag |
| `git push origin --tags` | Đẩy tất cả tag lên remote |
