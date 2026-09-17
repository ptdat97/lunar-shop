<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ai viết đánh giá, và đánh giá đó có gắn với một đơn đã mua thật hay không.
 *
 * Trước đây `product_reviews` chỉ có `author` — một chuỗi khách tự gõ vào form.
 * Nghĩa là không có cách nào phân biệt người đã mua với người chưa từng mua, và
 * với thời trang thì đó đúng là thứ khách đọc đánh giá muốn biết nhất.
 *
 * `order_id` là **bằng chứng**, không phải thông tin thêm: nhãn "đã mua hàng"
 * chỉ được phép hiện khi cột này có giá trị, và nó được điền bằng cách tra đơn
 * ĐÃ THANH TOÁN của chính khách có chứa sản phẩm — không phải bằng lời khai.
 *
 * Cả hai cột đều nullable + `nullOnDelete`:
 *
 * - đánh giá cũ không có, khách vãng lai không có — nullable là trạng thái
 *   bình thường chứ không phải dữ liệu hỏng;
 * - xoá tài khoản hoặc xoá đơn **không** được kéo theo đánh giá. Nội dung khách
 *   viết thuộc về sản phẩm; mất nó là mất luôn phần tóm tắt sao của sản phẩm đó.
 *   Hệ quả có ý thức: đánh giá mất `order_id` thì mất luôn nhãn đã-mua-hàng.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orders = config('lunar.database.table_prefix', 'lunar_').'orders';

        Schema::table('product_reviews', function (Blueprint $table) use ($orders): void {
            $table->foreignId('user_id')->nullable()->after('product_id')
                ->constrained('users')->nullOnDelete();

            $table->foreignId('order_id')->nullable()->after('user_id')
                ->constrained($orders)->nullOnDelete();

            // Tra "người này đã đánh giá sản phẩm này chưa" — đường đọc duy nhất
            // đi qua cặp cột này, nên index theo đúng cặp đó.
            $table->index(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'product_id']);
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
