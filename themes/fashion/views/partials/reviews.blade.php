{{-- Đánh giá sản phẩm. SSR trước (§8): danh sách và tóm tắt render sẵn từ
     server, JS chỉ lo gửi form. Không có JS thì khách vẫn ĐỌC được đánh giá —
     phần quan trọng nhất của mục này là để đọc, không phải để viết.

     `id="danh-gia"` là neo mà email xin đánh giá trỏ tới; đổi tên là link trong
     những email đã gửi đi thành vô nghĩa. --}}
<section id="danh-gia" class="mt-5">
    <h2 class="h4 mb-3">{{ __('storefront.product.reviews_title') }}</h2>

    @if(($reviewSummary['count'] ?? 0) > 0)
        <p class="mb-3">
            <span class="fw-semibold">{{ number_format($reviewSummary['average'] ?? 0, 1) }}/5</span>
            <span class="text-muted">
                · {{ __('storefront.product.reviews_count', ['count' => $reviewSummary['count']]) }}
            </span>
        </p>
    @endif

    @forelse($reviews as $review)
        <article class="border-top py-3">
            <div class="d-flex justify-content-between align-items-baseline gap-2">
                <strong>{{ $review->author }}</strong>
                <span class="text-warning" aria-label="{{ __('storefront.product.reviews_rating', ['rating' => $review->rating]) }}">
                    {{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}
                </span>
            </div>
            @if($review->body)
                <p class="mb-0 mt-2">{{ $review->body }}</p>
            @endif
            <small class="text-muted">{{ $review->created_at?->translatedFormat('d/m/Y') }}</small>
        </article>
    @empty
        <p class="text-muted">{{ __('storefront.product.reviews_empty') }}</p>
    @endforelse

    {{-- Form gửi đánh giá. `action` trỏ thẳng endpoint API đã có, nên không cần
         route mới; enhance/review-form.js chặn submit và gửi bằng fetch để khách
         không rời trang. --}}
    <form class="mt-4 row g-2 align-items-end"
          data-review-form
          action="{{ url('/api/v1/products/'.$slug.'/reviews') }}"
          method="post">
        <div class="col-12 col-sm-4">
            <label class="form-label" for="review-author">{{ __('storefront.product.reviews_your_name') }}</label>
            <input class="form-control" id="review-author" name="author" required maxlength="255">
        </div>
        <div class="col-6 col-sm-3">
            <label class="form-label" for="review-rating">{{ __('storefront.product.reviews_rating_label') }}</label>
            <select class="form-select" id="review-rating" name="rating" required>
                @foreach(range(5, 1) as $star)
                    <option value="{{ $star }}">{{ $star }} ★</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="review-body">{{ __('storefront.product.reviews_body') }}</label>
            <textarea class="form-control" id="review-body" name="body" rows="3" maxlength="2000"></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-dark" type="submit">{{ __('storefront.product.reviews_submit') }}</button>
            <span class="ms-2 small" data-review-status role="status" aria-live="polite"></span>
        </div>
    </form>
</section>
