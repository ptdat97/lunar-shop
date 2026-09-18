<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ cái điểm thưởng — **bút toán, không phải một cột số dư**.
 *
 * Cùng bài học với sổ cái tồn kho: số dư là thứ **dẫn xuất**. Một cột
 * `points_balance` trả lời được "còn bao nhiêu" nhưng không trả lời được "vì
 * sao", và đến lúc nó lệch thì không có gì để đối soát lại. Ở đây:
 *
 *     số dư khả dụng = SUM(points) WHERE available_at IS NULL OR available_at <= now
 *
 * **Vì sao có `lot_id`.** Điểm có hạn dùng, nên không thể trừ vào "số dư" chung
 * chung: phải biết trừ vào LÔ nào mới biết lô nào còn lại bao nhiêu lúc hết hạn.
 * Mỗi bút toán cộng (`earn`, `refund`, `adjust` dương) là một **lô**; mỗi bút
 * toán trừ trỏ về lô nó ăn vào. Phần còn lại của một lô vì thế cũng dẫn xuất:
 *
 *     còn lại của lô = lô.points + SUM(points của các bút toán có lot_id = lô.id)
 *
 * Tiêu điểm ăn theo **FIFO theo hạn**: lô sắp hết hạn trước bị ăn trước, nên
 * điểm của khách không chết oan trong khi lô mới vẫn còn.
 *
 * **`available_at` là cách "thưởng sau hạn đổi/trả" được mã hoá.** Điểm của một
 * đơn vừa trả tiền chưa tiêu được ngay — nó mang `available_at` ở tương lai và
 * nằm ngoài số dư cho tới lúc đó. Khác với giới thiệu bạn (mục §15) vốn cần một
 * lệnh `referrals:release` để PHÁT, ở đây không cần lệnh nào: một cột ngày làm
 * xong việc, và đơn bị trả lại thì lô còn đang chờ, huỷ sạch.
 *
 * Debit (`spend`/`expire`/`revoke`) để `available_at` NULL — chúng có hiệu lực
 * ngay, không có khái niệm "chờ".
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('lunar.database.table_prefix', 'lunar_');

        Schema::create('loyalty_entries', function (Blueprint $table) use ($prefix): void {
            $table->id();

            $table->foreignId('customer_id')->constrained($prefix.'customers')->cascadeOnDelete();

            // Lô mà bút toán này ăn vào. Tự trỏ về bảng này. `cascadeOnDelete`
            // vì một bút toán trừ không có lô là một dòng vô nghĩa.
            $table->foreignId('lot_id')->nullable()->constrained('loyalty_entries')->cascadeOnDelete();

            // Đơn sinh ra điểm (earn) hoặc đơn tiêu điểm (spend). `nullOnDelete`:
            // xoá đơn không được xoá lịch sử điểm của khách.
            $table->foreignId('order_id')->nullable()->constrained($prefix.'orders')->nullOnDelete();

            $table->string('type', 16);

            // CÓ DẤU: cộng là dương, trừ là âm. Một cột, nên tổng luôn là số dư
            // — không có chỗ nào để quên đổi dấu.
            $table->integer('points');

            $table->timestamp('available_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->string('reason', 64)->nullable();
            $table->timestamps();

            // Đường đọc duy nhất của số dư.
            $table->index(['customer_id', 'available_at']);
            // Quét hết hạn: "lô nào đã qua expires_at".
            $table->index(['type', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_entries');
    }
};
