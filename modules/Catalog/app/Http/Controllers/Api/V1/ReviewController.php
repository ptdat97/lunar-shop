<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Lunar\Core\Models\Product;
use Modules\Catalog\Http\Resources\ReviewResource;
use Modules\Catalog\Models\Review;
use Modules\Catalog\Services\ReviewService;
use Modules\Core\Support\ApiPagination;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviews,
    ) {}

    /**
     * GET /api/v1/products/{product}/reviews
     *
     * `meta` carries the pagination counters used everywhere else in the API;
     * the rating roll-up moved to `summary` when this endpoint was paginated.
     */
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $reviews = $this->reviews->forProduct(
            $product->id,
            perPage: ApiPagination::perPage($request, default: 20, max: 50),
            page: ApiPagination::page($request),
        );

        return ReviewResource::collection($reviews->getCollection())
            ->additional([
                'summary' => $this->reviews->summaryFor($product->id),
                'meta' => ApiPagination::meta($reviews),
            ]);
    }

    /**
     * POST /api/v1/products/{product}/reviews
     *
     * Nhận cả `application/json` (không ảnh) lẫn `multipart/form-data` (có ảnh)
     * — cùng một endpoint, vì đó vẫn là một hành động.
     *
     * Không có trường nào cho "đã mua hàng" và cũng không có trường nào cho
     * "duyệt luôn": cả hai do {@see ReviewService::add()} quyết định. Client chỉ
     * gửi được thứ khách gõ.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'author' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
            ...$this->photoRules(),
        ]);

        $review = $this->reviews->add(
            $product->id,
            $data['author'],
            $data['rating'],
            $data['body'] ?? null,
            user: $request->user('sanctum'),
            photos: $request->file('photos') ?? [],
        );

        return response()->json([
            'data' => $this->reviews->summaryFor($product->id),
            // Đánh giá có ảnh luôn phải qua duyệt. Trả lời "cảm ơn, đã đăng"
            // trong khi nó đang nằm trong hàng đợi là nói dối khách — cùng lý
            // do mà form cố ý không chèn đánh giá vừa gửi vào danh sách.
            'meta' => ['pending' => ! $review->approved],
        ], 201);
    }

    /**
     * Luật cho ảnh gửi kèm.
     *
     * Tắt tính năng thì `prohibited` — từ chối thẳng và nói rõ, thay vì lặng lẽ
     * bỏ qua file khách vừa chờ tải lên xong.
     *
     * `mimetypes` chứ không phải `mimes`: kiểm theo nội dung file, không theo
     * đuôi khách đặt. Danh sách đọc từ `Review::PHOTO_MIMES` — cùng hằng số mà
     * media collection của model dùng, nên hai lớp không thể lệch nhau. Trần số
     * ảnh đọc từ `Review::MAX_PHOTOS`, cùng hằng số mà service cắt và panel
     * dựng cột.
     *
     * @return array<string, array<int, string>>
     */
    protected function photoRules(): array
    {
        if (! config('review.photos.enabled', true)) {
            return ['photos' => ['prohibited']];
        }

        return [
            'photos' => ['sometimes', 'array', 'max:'.Review::MAX_PHOTOS],
            'photos.*' => [
                'file',
                'mimetypes:'.implode(',', Review::PHOTO_MIMES),
                'max:'.(int) config('review.photos.max_size_kb', 8192),
            ],
        ];
    }
}
