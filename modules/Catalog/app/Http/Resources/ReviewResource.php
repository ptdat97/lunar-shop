<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Review;

/**
 * Stable JSON contract for a product review.
 *
 * The shape used to be built inline in the controller (coding standards §6 asks
 * every endpoint to serialise through a JsonResource), so nothing stopped the
 * API and any future consumer from drifting apart.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => $this->author,
            'rating' => $this->rating,
            'body' => $this->body,
            // Đã mua hàng thật hay chưa. Suy ra từ `order_id` phía server —
            // xem Review::isVerified(). Luôn có mặt (kể cả false) để hợp đồng
            // không đổi hình theo từng dòng.
            'verified' => $this->isVerified(),
            // Ảnh khách gửi: [{id, thumb, full}]. Mảng rỗng khi không có, chứ
            // không phải null — người đọc JSON không phải phân biệt hai ca.
            'photos' => $this->photoUrls(),
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
