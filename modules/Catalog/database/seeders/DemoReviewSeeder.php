<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Product;
use Modules\Catalog\Models\Review;

/**
 * Seeds customer reviews across the demo catalog.
 *
 * Exercises the whole review surface: the rating summary on product cards and
 * the product page (ReviewService::summaryFor averages only APPROVED rows), the
 * paginated review list, and the admin moderation queue — which is empty and
 * looks broken unless some rows are left unapproved.
 *
 * Ratings are deliberately uneven so averages are not all 5.0, and a couple of
 * products are left with no reviews at all so the "no reviews yet" empty state
 * is reachable.
 *
 * Idempotent: keyed on (product, author).
 */
class DemoReviewSeeder extends Seeder
{
    /**
     * Review bodies paired with their rating, in Vietnamese (the shop's primary
     * locale). Index order doubles as the rotation across products.
     *
     * @var list<array{author: string, rating: int, body: string, approved: bool}>
     */
    protected const REVIEWS = [
        ['author' => 'Nguyễn Minh Anh', 'rating' => 5, 'approved' => true,
            'body' => 'Vải mát, form chuẩn như mô tả. Mình cao 1m65 nặng 55kg mặc size M vừa đẹp.'],
        ['author' => 'Trần Quốc Bảo', 'rating' => 4, 'approved' => true,
            'body' => 'Chất lượng tốt so với giá. Giao hàng nhanh, đóng gói cẩn thận. Trừ 1 sao vì màu nhạt hơn ảnh một chút.'],
        ['author' => 'Lê Thu Hà', 'rating' => 5, 'approved' => true,
            'body' => 'Mua lần thứ hai rồi, giặt máy nhiều lần vẫn không xù lông. Rất đáng tiền.'],
        ['author' => 'Phạm Đức Duy', 'rating' => 3, 'approved' => true,
            'body' => 'Sản phẩm ổn nhưng size hơi nhỏ so với bảng size, nên chọn lớn hơn một size.'],
        ['author' => 'Vũ Khánh Linh', 'rating' => 5, 'approved' => true,
            'body' => 'Đường may chắc chắn, không có chỉ thừa. Mặc đi làm rất hợp.'],
        ['author' => 'Đặng Hoài Nam', 'rating' => 2, 'approved' => true,
            'body' => 'Hàng đúng mẫu nhưng vải mỏng hơn mình nghĩ. Mặc mùa hè thì hợp.'],
        // Left unapproved on purpose: gives the admin moderation queue real work
        // and keeps the public average from counting it.
        ['author' => 'Khách vãng lai', 'rating' => 1, 'approved' => false,
            'body' => 'Chưa nhận được hàng, shop kiểm tra giúp mình đơn hàng nhé.'],
        ['author' => 'Bùi Thanh Tùng', 'rating' => 4, 'approved' => false,
            'body' => 'Áo đẹp, sẽ ủng hộ tiếp lần sau.'],
    ];

    public function run(): void
    {
        $products = Product::query()->where('status', 'published')->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No published products found — run the catalog seeders first.');

            return;
        }

        $created = 0;

        foreach ($products as $index => $product) {
            // Leave every 7th product without reviews so the empty state shows up.
            if ($index % 7 === 6) {
                continue;
            }

            // 2–5 reviews per product, rotating through the pool so ratings and
            // approval states vary product to product.
            $count = 2 + ($index % 4);

            for ($i = 0; $i < $count; $i++) {
                $review = self::REVIEWS[($index + $i) % count(self::REVIEWS)];

                $exists = Review::where('product_id', $product->id)
                    ->where('author', $review['author'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                Review::create([
                    'product_id' => $product->id,
                    'author' => $review['author'],
                    'rating' => $review['rating'],
                    'body' => $review['body'],
                    'approved' => $review['approved'],
                ]);

                $created++;
            }
        }

        $pending = Review::where('approved', false)->count();
        $this->command?->info("Seeded {$created} reviews ({$pending} awaiting moderation).");
    }
}
