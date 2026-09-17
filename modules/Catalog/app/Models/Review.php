<?php

namespace Modules\Catalog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\Product;
use Modules\Assets\Services\MediaUrl;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $product_id
 * @property ?int $user_id
 * @property ?int $order_id
 * @property string $author
 * @property int $rating
 * @property ?string $body
 * @property bool $approved
 */
class Review extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** Ảnh khách gửi kèm. Tách khỏi thư viện ảnh của admin — xem registerMediaCollections(). */
    public const PHOTOS = 'photos';

    /**
     * Trần số ảnh một đánh giá được mang.
     *
     * Con số này **không tuỳ ý**: hàng đợi duyệt trong panel hiện mỗi ảnh một
     * cột (panel chạy asset biên dịch sẵn của vendor, không tự thêm được cell
     * nhiều ảnh), nên nâng trần mà quên thêm cột = ảnh thứ tư lên storefront
     * mà chưa ai nhìn thấy. `ReviewPhotoTest::moderation_sees_every_photo`
     * khoá hai con số đó lại với nhau.
     */
    public const MAX_PHOTOS = 3;

    /** Bản nhỏ cho lưới + hàng đợi duyệt. */
    public const THUMB = 'thumb';

    /** Bản lớn cho lightbox / mở ảnh. */
    public const FULL = 'full';

    /**
     * Định dạng ảnh được nhận — **một danh sách, hai chỗ dùng**.
     *
     * Luật của request (`mimetypes:` trong ReviewController) và chốt chặn ở
     * model (`acceptsMimeTypes` bên dưới) phải nói cùng một thứ. Chép ra hai
     * nơi thì đến lúc nới một bên, bên kia biến thành 500 giữa luồng gửi đánh
     * giá của khách — đúng cái đã xảy ra khi viết mục này.
     *
     * @var array<int, string>
     */
    public const PHOTO_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    protected $table = 'product_reviews';

    protected $fillable = ['product_id', 'user_id', 'order_id', 'author', 'rating', 'body', 'approved'];

    protected $casts = [
        'rating' => 'int',
        'approved' => 'bool',
    ];

    /**
     * The product being reviewed.
     *
     * The column was always here; the relation was not, because nothing but the
     * storefront's own product-scoped queries ever needed it. The moderation
     * queue does: it lists reviews across every product and has to name each
     * one without a query per row.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** Tài khoản đã viết, nếu có. Khách vãng lai vẫn đánh giá được → null. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Đơn đã thanh toán có chứa sản phẩm này — bằng chứng cho nhãn "đã mua hàng".
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Đánh giá này có phải của người đã mua thật hay không.
     *
     * Đọc `order_id` chứ không đọc `user_id`: đăng nhập chỉ chứng minh là một
     * tài khoản, không chứng minh đã mua. Đây là toàn bộ giá trị của nhãn —
     * nới lỏng nó ra thành "có tài khoản" là biến nhãn thành đồ trang trí.
     */
    public function isVerified(): bool
    {
        return $this->order_id !== null;
    }

    /**
     * Ảnh khách gửi, dưới dạng hợp đồng dùng chung cho API lẫn Blade SSR.
     *
     * @return array<int, array{id:int, thumb:?string, full:?string}>
     */
    public function photoUrls(): array
    {
        $urls = app(MediaUrl::class);

        return $this->getMedia(self::PHOTOS)
            ->map(fn (Media $media) => [
                'id' => (int) $media->id,
                'thumb' => $urls->conversion($media, self::THUMB),
                'full' => $urls->conversion($media, self::FULL),
            ])
            // Chuyển đổi hỏng (file nguồn không đọc được) trả null — bỏ hẳn ảnh
            // đó đi còn hơn để một ô ảnh vỡ nằm giữa đánh giá.
            ->filter(fn (array $photo) => $photo['thumb'] !== null)
            ->values()
            ->all();
    }

    /**
     * Ba accessor dưới đây tồn tại **chỉ cho hàng đợi duyệt trong panel**:
     * `Field::image()` đọc một giá trị vô hướng qua `data_get()`, nên mỗi ảnh
     * cần một khoá riêng. Chúng đi kèm MAX_PHOTOS — xem ghi chú ở hằng số đó.
     */
    protected function photo1(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photoThumb(0));
    }

    protected function photo2(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photoThumb(1));
    }

    protected function photo3(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photoThumb(2));
    }

    protected function photoThumb(int $index): ?string
    {
        $media = $this->getMedia(self::PHOTOS)->get($index);

        return $media ? app(MediaUrl::class)->conversion($media, self::THUMB) : null;
    }

    /**
     * Ảnh khách **không** đi vào thư viện ảnh của admin.
     *
     * Thư viện admin liệt kê `Lunar\Core\Models\Asset` (MediaLibraryService::browse),
     * còn ảnh ở đây treo thẳng vào chính đánh giá — nên picker ảnh sản phẩm
     * không bao giờ nhặt phải ảnh selfie của khách, và ngược lại xoá một đánh
     * giá không đụng tới ảnh nào của shop.
     *
     * `acceptsMimeTypes` là lớp chặn cuối ở tầng model: request đã validate rồi,
     * nhưng một seeder hay một lệnh artisan thì không đi qua request nào.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTOS)
            ->acceptsMimeTypes(self::PHOTO_MIMES);
    }

    /**
     * Hai cỡ, và **không cỡ nào crop bản lớn**.
     *
     * Ảnh sản phẩm của shop luôn 2:3 nên crop được; ảnh khách chụp thì tỉ lệ
     * gì cũng có, mà cắt mất cái áo trong ảnh khách gửi là hỏng đúng thứ khiến
     * ảnh đó đáng giá. Bản nhỏ vẫn crop vuông vì nó nằm trong lưới.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::THUMB)
            ->fit(Fit::Crop, 320, 320)
            ->keepOriginalImageFormat()
            ->performOnCollections(self::PHOTOS);

        $this->addMediaConversion(self::FULL)
            ->fit(Fit::Contain, 1400, 1400)
            ->keepOriginalImageFormat()
            ->performOnCollections(self::PHOTOS);
    }
}
