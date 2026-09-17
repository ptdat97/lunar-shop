<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\Models\Product;
use Modules\Catalog\Models\Review;
use Modules\Catalog\Panel\ReviewResource as ReviewPanelResource;
use Modules\Catalog\Services\ReviewService;
use Modules\Core\Support\Settings;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * Đánh giá kèm ảnh + nhãn "đã mua hàng" (docs/roadmap.md §17).
 *
 * Hai thứ được kiểm ở đây đều là **bằng chứng**, không phải hiển thị:
 *
 * - nhãn đã-mua-hàng chỉ mọc từ một đơn ĐÃ THANH TOÁN có chứa đúng sản phẩm
 *   đó. Đăng nhập không mua được nhãn, và client không gửi nó lên được;
 * - ảnh khách gửi **luôn** đi qua hàng đợi duyệt, kể cả khi shop bật tự động
 *   duyệt. Văn bậy thì gỡ được sau khi đọc; ảnh bậy thì người ta đã nhìn thấy
 *   rồi mới gỡ được.
 */
class ReviewPhotoTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    private Product $product;

    private Customer $customer;

    private function makeProduct(): void
    {
        $this->product = $this->createProduct(['name' => 'Linen Shirt']);
        $this->customer = Customer::factory()->create();
    }

    /** Một đơn của $this->customer có chứa biến thể của sản phẩm đang xét. */
    private function buy(string $status = 'payment-received', ?Product $product = null): Order
    {
        $variant = ($product ?? $this->product)->variants->first();

        $order = Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'customer_id' => $this->customer->id,
            ...$this->orderAttributesFor($status),
            'sub_total' => 1000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 1000,
        ]);

        OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => 'product_variant',
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'description' => 'Linen Shirt',
            'quantity' => 1, 'unit_price' => 1000, 'unit_quantity' => 1,
            'sub_total' => 1000, 'discount_total' => 0, 'tax_total' => 0, 'total' => 1000,
        ]);

        return $order;
    }

    /** Một tài khoản đã nối với customer đang xét. */
    private function shopper(): User
    {
        $user = $this->createUser();
        $user->customers()->attach($this->customer->id);

        return $user;
    }

    private function photo(string $name = 'fit.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 900, 1200);
    }

    private function endpoint(): string
    {
        return '/api/v1/products/'.$this->product->id.'/reviews';
    }

    // ---------------------------------------------------------------- verified

    public function test_a_paid_order_with_the_product_earns_the_verified_badge(): void
    {
        $this->seedBaseData();
        $this->makeProduct();
        $order = $this->buy();

        $this->actingAs($this->shopper())
            ->postJson($this->endpoint(), ['author' => 'Mai', 'rating' => 5, 'body' => 'Vừa vặn'])
            ->assertCreated();

        $review = Review::firstOrFail();

        $this->assertSame($order->id, $review->order_id);
        $this->assertTrue($review->isVerified());

        $this->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonPath('data.0.verified', true);

        $this->get('/products/'.$this->product->defaultUrl->slug)
            ->assertOk()
            ->assertSee(__('storefront.product.reviews_verified'));
    }

    public function test_an_order_that_was_never_paid_earns_nothing(): void
    {
        $this->seedBaseData();
        $this->makeProduct();
        $this->buy('awaiting-payment');

        $this->actingAs($this->shopper())
            ->postJson($this->endpoint(), ['author' => 'Mai', 'rating' => 4])
            ->assertCreated();

        $this->assertNull(Review::firstOrFail()->order_id);
        $this->getJson($this->endpoint())->assertJsonPath('data.0.verified', false);
    }

    public function test_buying_a_different_product_does_not_verify_this_one(): void
    {
        $this->seedBaseData();
        $this->makeProduct();
        $this->buy(product: $this->createProduct(['name' => 'Other Tee']));

        $this->actingAs($this->shopper())
            ->postJson($this->endpoint(), ['author' => 'Mai', 'rating' => 4])
            ->assertCreated();

        $this->assertNull(Review::firstOrFail()->order_id);
    }

    public function test_someone_elses_paid_order_does_not_verify_you(): void
    {
        $this->seedBaseData();
        $this->makeProduct();
        $this->buy();

        // Tài khoản KHÔNG nối với customer đã mua.
        $this->actingAs($this->createUser())
            ->postJson($this->endpoint(), ['author' => 'Người lạ', 'rating' => 5])
            ->assertCreated();

        $this->assertNull(Review::firstOrFail()->order_id);
    }

    public function test_a_guest_review_is_never_verified(): void
    {
        $this->seedBaseData();
        $this->makeProduct();
        $this->buy();

        $this->postJson($this->endpoint(), ['author' => 'Khách', 'rating' => 5])
            ->assertCreated();

        $this->assertNull(Review::firstOrFail()->order_id);
        $this->getJson($this->endpoint())->assertJsonPath('data.0.verified', false);
    }

    public function test_the_client_cannot_award_itself_the_badge(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        // Gửi kèm đúng tên cột, phòng trường hợp ai đó nới `fillable` ra rồi
        // quên rằng request đi thẳng vào create().
        $this->postJson($this->endpoint(), [
            'author' => 'Kẻ gian', 'rating' => 5, 'order_id' => 999, 'user_id' => 1,
        ])->assertCreated();

        $review = Review::firstOrFail();

        $this->assertNull($review->order_id);
        $this->assertNull($review->user_id);
    }

    // ------------------------------------------------------------------ photos

    public function test_photos_reach_the_api_and_the_page(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        $this->post($this->endpoint(), [
            'author' => 'Mai', 'rating' => 5, 'body' => 'Ảnh thật',
            'photos' => [$this->photo('a.jpg'), $this->photo('b.jpg')],
        ])->assertCreated();

        $review = Review::firstOrFail();

        $this->assertCount(2, $review->getMedia(Review::PHOTOS));

        // Ảnh chưa duyệt thì chưa ở đâu cả — duyệt xong mới hiện.
        $this->getJson($this->endpoint())->assertJsonCount(0, 'data');

        app(ReviewService::class)->approve($review);

        $this->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonCount(2, 'data.0.photos')
            ->assertJsonPath('data.0.photos.0.id', $review->getMedia(Review::PHOTOS)->first()->id);

        $html = $this->get('/products/'.$this->product->defaultUrl->slug)->assertOk()->getContent();

        $this->assertStringContainsString('review__photo', $html);
        $this->assertStringContainsString('-'.Review::THUMB.'.', $html, 'thẻ img phải dùng bản thumb');
    }

    public function test_a_review_with_photos_waits_for_moderation_even_when_auto_approve_is_on(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        app(Settings::class)->put('review', ['auto_approve' => true]);

        // Không ảnh → đăng ngay (luật cũ, phải giữ nguyên).
        $this->postJson($this->endpoint(), ['author' => 'Mai', 'rating' => 5])
            ->assertCreated()
            ->assertJsonPath('meta.pending', false);

        $this->assertTrue(Review::firstOrFail()->approved);

        // Có ảnh → chờ duyệt, dù công tắc vẫn đang bật.
        $this->post($this->endpoint(), [
            'author' => 'Lan', 'rating' => 5, 'photos' => [$this->photo()],
        ])->assertCreated()->assertJsonPath('meta.pending', true);

        $this->assertFalse(Review::where('author', 'Lan')->firstOrFail()->approved);
    }

    public function test_more_photos_than_the_cap_are_rejected(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        $photos = array_map(fn (int $i) => $this->photo("p{$i}.jpg"), range(1, Review::MAX_PHOTOS + 1));

        $this->post($this->endpoint(), ['author' => 'Mai', 'rating' => 5, 'photos' => $photos])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photos');

        $this->assertSame(0, Review::count());
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        $this->post($this->endpoint(), [
            'author' => 'Mai', 'rating' => 5,
            'photos' => [UploadedFile::fake()->createWithContent('notes.txt', 'khong phai anh')],
        ])->assertStatus(422)->assertJsonValidationErrors('photos.0');

        $this->assertSame(0, Review::count());
    }

    /**
     * Chốt chặn ở model từ chối file thì KHÔNG được để lại đánh giá mồ côi.
     *
     * Đường HTTP đã có `mimetypes` chặn trước, nên ca này chỉ nổ khi hai lớp
     * lệch nhau hoặc khi gọi service thẳng (seeder, lệnh artisan). Nhưng lần
     * đầu viết mục này nó nổ thật: ảnh bị từ chối sau khi dòng đánh giá đã
     * được tạo, để lại một đánh giá không ảnh nằm chờ duyệt vĩnh viễn.
     */
    public function test_a_refused_photo_leaves_no_half_written_review(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        $this->expectException(FileUnacceptableForCollection::class);

        try {
            app(ReviewService::class)->add(
                $this->product->id,
                'Mai',
                5,
                null,
                photos: [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')],
            );
        } finally {
            $this->assertSame(0, Review::count(), 'transaction phải cuốn theo dòng đánh giá');
        }
    }

    public function test_photos_are_refused_outright_when_the_feature_is_off(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        config(['review.photos.enabled' => false]);

        $this->post($this->endpoint(), ['author' => 'Mai', 'rating' => 5, 'photos' => [$this->photo()]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photos');

        // Và ô chọn ảnh cũng biến mất khỏi trang — một công tắc, hai đầu.
        $this->get('/products/'.$this->product->defaultUrl->slug)
            ->assertOk()
            ->assertDontSee('name="photos[]"', false);
    }

    public function test_the_stored_file_name_is_not_the_one_the_customer_sent(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        $this->post($this->endpoint(), [
            'author' => 'Mai', 'rating' => 5, 'photos' => [$this->photo('chan-dung-cua-toi.jpg')],
        ])->assertCreated();

        $media = Review::firstOrFail()->getMedia(Review::PHOTOS)->first();

        // Ảnh nằm trên đĩa công khai, nên tên đoán được = xem được TRƯỚC khi
        // duyệt, đúng thứ mà luật bắt-buộc-duyệt ở trên đang ngăn.
        $this->assertStringNotContainsString('chan-dung-cua-toi', $media->file_name);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.\w+$/', $media->file_name);
    }

    public function test_deleting_a_review_takes_its_photos_with_it(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        $this->post($this->endpoint(), ['author' => 'Mai', 'rating' => 5, 'photos' => [$this->photo()]])
            ->assertCreated();

        $review = Review::firstOrFail();
        $mediaId = $review->getMedia(Review::PHOTOS)->first()->id;

        $review->delete();

        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    }

    // -------------------------------------------------------------- moderation

    public function test_moderation_sees_every_photo(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        $photos = array_map(fn (int $i) => $this->photo("p{$i}.jpg"), range(1, Review::MAX_PHOTOS));

        $this->post($this->endpoint(), ['author' => 'Mai', 'rating' => 5, 'photos' => $photos])
            ->assertCreated();

        $resource = app(ReviewPanelResource::class);

        $columns = array_values(array_filter(
            $resource->columns(),
            fn (array $column) => ($column['type']['name'] ?? null) === 'image',
        ));

        // Trần ảnh và số cột ảnh phải bằng nhau: nâng một mà quên cái kia là
        // ảnh lên storefront mà chưa ai nhìn thấy.
        $this->assertCount(Review::MAX_PHOTOS, $columns);

        $row = $resource->toIndexRow(Review::firstOrFail());

        foreach ($columns as $column) {
            $this->assertNotNull($row[$column['key']] ?? null, $column['key'].' phải có URL ảnh');
        }
    }

    /**
     * Số truy vấn phải **phẳng**, không phải "nhỏ".
     *
     * Đo một ngưỡng tuyệt đối thì một N+1 nhẹ vẫn chui lọt (6 đánh giá kèm ảnh
     * chỉ tốn 9 truy vấn — vừa đủ nằm dưới mọi ngưỡng "hợp lý"). Nên phép thử
     * ở đây là đo hai lần với số đánh giá gấp đôi và bắt hai con số BẰNG NHAU.
     */
    public function test_listing_reviews_costs_a_flat_number_of_queries(): void
    {
        $this->seedBaseData();
        $this->makeProduct();

        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->getJson($this->endpoint())->assertOk();

            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $this->writeReviewsWithPhotos(6);
        $small = $measure();

        $this->writeReviewsWithPhotos(6);
        $large = $measure();

        $this->getJson($this->endpoint())->assertJsonCount(12, 'data');

        $this->assertSame(
            $small,
            $large,
            "6 đánh giá tốn {$small} truy vấn, 12 đánh giá tốn {$large} — có N+1 trên ảnh",
        );
    }

    /** N đánh giá đã duyệt, mỗi cái một ảnh. */
    private function writeReviewsWithPhotos(int $count): void
    {
        $offset = Review::count();

        foreach (range(1, $count) as $i) {
            $this->post($this->endpoint(), [
                'author' => 'Khách '.($offset + $i),
                'rating' => 5,
                'photos' => [$this->photo('p'.($offset + $i).'.jpg')],
            ])->assertCreated();
        }

        Review::query()->update(['approved' => true]);
    }
}
