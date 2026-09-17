<?php

namespace Modules\Catalog\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\Core\Models\Order;
use Modules\Catalog\Models\Review;
use Modules\Core\Support\Settings;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Support\OrderStatus;

/**
 * Single source of review logic — used by the API controller AND the
 * product.resource enrichment, so the payload and the endpoint never drift.
 */
class ReviewService
{
    public function __construct(
        protected CustomerResolver $customers,
    ) {}

    /**
     * Per-request memo of summaries, keyed by product id.
     *
     * The service is bound `scoped`, so this lives for one request only
     * (Octane-safe) — long enough to serve a whole product grid, short enough
     * that a review added mid-request is never served stale.
     *
     * @var array<int, array{count:int, average:float}>
     */
    protected array $summaries = [];

    /** @return array{count:int, average:float} */
    public function summaryFor(int $productId): array
    {
        if (! isset($this->summaries[$productId])) {
            $this->loadSummaries([$productId]);
        }

        return $this->summaries[$productId];
    }

    /**
     * Warm the memo for many products in ONE query.
     *
     * ProductResource embeds a review summary per product, so a 24-card grid
     * used to fire 48 queries (a count + an avg each). Call this before
     * serialising a collection and the whole grid costs one aggregate query.
     *
     * @param  array<int, int>  $productIds
     */
    public function loadSummaries(array $productIds): void
    {
        $missing = array_values(array_diff(
            array_unique(array_filter($productIds)),
            array_keys($this->summaries),
        ));

        if (! $missing) {
            return;
        }

        $rows = Review::query()
            ->selectRaw('product_id, COUNT(*) as review_count, AVG(rating) as review_average')
            ->whereIn('product_id', $missing)
            ->where('approved', true)
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // Products with no approved reviews get a zero row, so they are memoised
        // too and never fall back to a per-product query.
        foreach ($missing as $id) {
            $row = $rows->get($id);

            $this->summaries[$id] = [
                'count' => (int) ($row->review_count ?? 0),
                'average' => round((float) ($row->review_average ?? 0), 2),
            ];
        }
    }

    /** Drop a product's memo after its reviews change. */
    public function forgetSummary(int $productId): void
    {
        unset($this->summaries[$productId]);
    }

    /**
     * Approved reviews, newest first. Paginated: a popular product accumulates
     * reviews without bound, and the endpoint used to return every one of them.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function forProduct(int $productId, int $perPage = 20, int $page = 1)
    {
        return Review::where('product_id', $productId)
            ->where('approved', true)
            // Ảnh gửi kèm: một truy vấn cho cả trang, không phải một cho mỗi
            // đánh giá. Danh sách này render 10 dòng một lần.
            ->with('media')
            ->latest()
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Ghi một đánh giá mới.
     *
     * Hai thứ được quyết định ở đây chứ không ở controller, vì chúng là luật
     * của đánh giá chứ không phải của một endpoint:
     *
     * 1. **Nhãn "đã mua hàng" do server tự tra**, từ đơn đã thanh toán của
     *    chính khách ({@see self::purchasedOrderFor()}). Không có trường nào
     *    trong request chạm tới được — nhãn mà client gửi lên được thì nó
     *    không còn là bằng chứng.
     * 2. **Có ảnh là phải qua duyệt**, kể cả khi shop bật tự động duyệt. Một
     *    câu văn bậy còn đọc rồi gỡ; một tấm ảnh bậy thì người xem đã nhìn
     *    thấy rồi mới gỡ được. Hai rủi ro khác hạng, nên không dùng chung một
     *    công tắc.
     *
     * @param  array<int, UploadedFile>  $photos
     */
    public function add(
        int $productId,
        string $author,
        int $rating,
        ?string $body,
        ?User $user = null,
        array $photos = [],
    ): Review {
        $photos = array_slice(array_values($photos), 0, Review::MAX_PHOTOS);

        // Trong một transaction vì ảnh được gắn SAU khi dòng đánh giá đã có id.
        // Chốt chặn `acceptsMimeTypes` của model ném giữa chừng thì không có
        // transaction sẽ để lại một đánh giá không ảnh, đang chờ duyệt, mà
        // khách thì nhận 500 và tưởng chưa gửi được gì.
        $review = DB::transaction(function () use ($productId, $author, $rating, $body, $user, $photos): Review {
            $review = Review::create([
                'product_id' => $productId,
                'user_id' => $user?->id,
                'order_id' => $user ? $this->purchasedOrderFor($user, $productId)?->id : null,
                'author' => $author,
                'rating' => max(1, min(5, $rating)),
                'body' => $body,
                'approved' => $photos === []
                    && (bool) app(Settings::class)->get('review.auto_approve', true),
            ]);

            foreach ($photos as $photo) {
                $this->attachPhoto($review, $photo);
            }

            return $review;
        });

        // ReviewController::store adds then immediately reads the summary in the
        // same request — without this it would answer with the pre-review memo.
        $this->forgetSummary($productId);

        return $review;
    }

    /**
     * Đơn ĐÃ THANH TOÁN gần nhất của khách có chứa sản phẩm này, hoặc null.
     *
     * "Đã thanh toán" đọc từ {@see OrderStatus} — cùng một định nghĩa mà doanh
     * thu, hạng thành viên và lịch sử vừa vặn đang dùng, nên đơn COD
     * (`payment-offline`) cũng tính, đúng như khách hiểu.
     *
     * Dòng đơn trỏ tới **biến thể**, nên phải bắc qua `product_variants` mới
     * về được sản phẩm; `whereExists` thay vì join để một đơn có ba biến thể
     * của cùng sản phẩm không nhân thành ba dòng.
     */
    public function purchasedOrderFor(User $user, int $productId): ?Order
    {
        $customer = $this->customers->existingForUser($user);

        if (! $customer) {
            return null;
        }

        return OrderStatus::scopePaid(Order::query())
            ->where('customer_id', $customer->id)
            ->whereExists(fn ($query) => $query
                ->select(DB::raw(1))
                ->from('lunar_order_lines as ol')
                ->join('lunar_product_variants as pv', function ($join) {
                    $join->on('pv.id', '=', 'ol.purchasable_id')
                        ->where('ol.purchasable_type', '=', 'product_variant');
                })
                ->whereColumn('ol.order_id', 'lunar_orders.id')
                ->where('pv.product_id', $productId))
            ->latest('id')
            ->first();
    }

    /**
     * Gắn một ảnh vào đánh giá.
     *
     * Tên file được đặt lại ngẫu nhiên, không giữ tên khách gửi lên. Ảnh nằm
     * trên đĩa công khai (`/media/{id}/{tên}`) như mọi ảnh khác, nên tên đoán
     * được đồng nghĩa với xem được ảnh **trước khi ai đó duyệt** — mà duyệt
     * ảnh là toàn bộ lý do luật ở `add()` tồn tại. Đuôi file lấy từ nội dung
     * thật (`extension()` đoán theo mime), không lấy từ tên khách gửi.
     */
    protected function attachPhoto(Review $review, UploadedFile $photo): void
    {
        $review->addMedia($photo)
            ->usingFileName(Str::random(40).'.'.($photo->extension() ?: 'jpg'))
            ->toMediaCollection(Review::PHOTOS);
    }

    /**
     * Publish a review.
     *
     * Moderation lives here rather than in the panel controller because this
     * service owns what a review's approval means to the rest of the app — the
     * summary counts a product's rating from approved rows only, and its memo
     * has to drop in the same request or a staff member who approves and then
     * looks at the product sees the old average.
     */
    public function approve(Review $review): Review
    {
        return $this->setApproval($review, true);
    }

    /** Take a review back off the storefront without deleting what was written. */
    public function unapprove(Review $review): Review
    {
        return $this->setApproval($review, false);
    }

    protected function setApproval(Review $review, bool $approved): Review
    {
        if ($review->approved === $approved) {
            return $review;
        }

        $review->update(['approved' => $approved]);

        $this->forgetSummary($review->product_id);

        return $review->refresh();
    }

    /** How many reviews are waiting on a decision. */
    public function pendingCount(): int
    {
        return Review::where('approved', false)->count();
    }
}
