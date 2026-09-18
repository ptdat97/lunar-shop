{{-- Đánh giá sản phẩm. SSR trước (§8): danh sách và tóm tắt render sẵn từ
     server, JS chỉ lo gửi form. Không có JS thì khách vẫn ĐỌC được đánh giá —
     phần quan trọng nhất của mục này là để đọc, không phải để viết. Ảnh khách
     gửi kèm cũng vậy: chúng là thẻ <img> thật, mở bằng <a> thật.

     `id="danh-gia"` là neo mà email xin đánh giá trỏ tới; đổi tên là link trong
     những email đã gửi đi thành vô nghĩa. --}}
<section id="danh-gia" class="mt-5">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h2 class="h4 mb-0">{{ __('storefront.product.reviews_title') }}</h2>

        {{-- Nút mở popup viết đánh giá. Form nằm trong modal ngay dưới, không
             nằm giữa danh sách: khách vào mục này để ĐỌC trước, và sáu ô nhập
             chen vào giữa các đánh giá làm loãng đúng thứ họ đang tìm.

             `data-bs-toggle` chứ không phải href neo: popup do Bootstrap JS
             mở, còn khách không có JS được phục vụ bằng khối <noscript> bên
             dưới modal (form trở về dạng tĩnh). --}}
        <button class="btn btn-outline-dark btn-sm" type="button"
                data-bs-toggle="modal" data-bs-target="#reviewForm">
            <i class="bi bi-pencil"></i> {{ __('storefront.product.reviews_write') }}
        </button>
    </div>

    @if(($reviewSummary['count'] ?? 0) > 0)
        <p class="mb-3">
            <span class="fw-semibold">{{ number_format($reviewSummary['average'] ?? 0, 1) }}/5</span>
            <span class="text-muted">
                · {{ __('storefront.product.reviews_count', ['count' => $reviewSummary['count']]) }}
            </span>
        </p>
    @endif

    @forelse($reviews as $review)
        <article class="border-top py-3 review">
            <div class="d-flex justify-content-between align-items-baseline gap-2">
                <strong>
                    {{ $review->author }}
                    {{-- Nhãn chỉ hiện khi có đơn đã thanh toán chứa sản phẩm này.
                         Xem Review::isVerified() — nó đọc order_id, không đọc
                         user_id, nên "có tài khoản" không mua được nhãn này. --}}
                    @if($review->isVerified())
                        <span class="badge text-bg-success-subtle text-success-emphasis fw-normal review__verified"
                              title="{{ __('storefront.product.reviews_verified_hint') }}">
                            ✓ {{ __('storefront.product.reviews_verified') }}
                        </span>
                    @endif
                </strong>
                <span class="text-warning" aria-label="{{ __('storefront.product.reviews_rating', ['rating' => $review->rating]) }}">
                    {{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}
                </span>
            </div>
            @if($review->body)
                <p class="mb-0 mt-2">{{ $review->body }}</p>
            @endif

            @php($photos = $review->photoUrls())
            @if($photos)
                {{-- Mở ảnh bằng <a target="_blank">, không phải lightbox JS: một
                     tấm ảnh phải xem được kể cả khi bundle chưa tải xong. --}}
                <ul class="list-unstyled d-flex flex-wrap gap-2 mt-2 mb-0 review__photos">
                    @foreach($photos as $photo)
                        <li>
                            <a href="{{ $photo['full'] ?? $photo['thumb'] }}" target="_blank" rel="noopener">
                                <img src="{{ $photo['thumb'] }}"
                                     alt="{{ __('storefront.product.reviews_photo_of', ['author' => $review->author]) }}"
                                     width="96" height="96" loading="lazy" class="review__photo rounded">
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            <small class="text-muted">{{ $review->created_at?->translatedFormat('d/m/Y') }}</small>
        </article>
    @empty
        <p class="text-muted">{{ __('storefront.product.reviews_empty') }}</p>
    @endforelse

    {{-- Form gửi đánh giá, đặt trong popup #reviewForm. `action` trỏ thẳng endpoint
         API đã có, nên không cần route mới; enhance/review-form.js chặn submit và
         gửi bằng fetch để khách không rời trang.

         `enctype` là multipart vì form có thể mang ảnh — và phải đúng ngay cả ở
         đường không-JS, nơi trình duyệt tự submit. --}}
    <div class="modal fade" id="reviewForm" tabindex="-1" aria-labelledby="reviewFormLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewFormLabel">
                        {{ __('storefront.product.reviews_write') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <form class="row g-2"
                          data-review-form
                          {{-- ID, KHÔNG phải slug: route `products/{product}` bind theo khoá route
                               của model, và Product khoá theo `id`. Dùng slug thì mọi lượt gửi
                               đánh giá đều 404 — và một form chỉ được kiểm "có mặt trong HTML"
                               sẽ không phát hiện ra điều đó. --}}
                          action="{{ url('/api/v1/products/'.$product->id.'/reviews') }}"
                          method="post"
                          enctype="multipart/form-data">
                        <div class="col-12 col-sm-7">
                            <label class="form-label" for="review-author">{{ __('storefront.product.reviews_your_name') }}</label>
                            <input class="form-control" id="review-author" name="author" required maxlength="255">
                        </div>
                        <div class="col-12 col-sm-5">
                            <label class="form-label" for="review-rating">{{ __('storefront.product.reviews_rating_label') }}</label>
                            <select class="form-select" id="review-rating" name="rating" required>
                                @foreach(range(5, 1) as $star)
                                    <option value="{{ $star }}">{{ $star }} ★</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="review-body">{{ __('storefront.product.reviews_body') }}</label>
                            <textarea class="form-control" id="review-body" name="body" rows="4" maxlength="2000"></textarea>
                        </div>
                        @if($reviewPhotos ?? false)
                            <div class="col-12">
                                <label class="form-label" for="review-photos">
                                    {{ __('storefront.product.reviews_photos_label', ['max' => $maxReviewPhotos]) }}
                                </label>
                                {{-- `data-max-photos` là nguồn con số cho cả HTML lẫn JS: theme
                                     không đọc hằng số của Catalog, controller truyền xuống. --}}
                                <input class="form-control" type="file" id="review-photos" name="photos[]"
                                       accept="image/jpeg,image/png,image/webp" multiple
                                       data-max-photos="{{ $maxReviewPhotos }}">
                                <div class="form-text">{{ __('storefront.product.reviews_photos_help') }}</div>
                            </div>
                        @endif
                        <div class="col-12">
                            <button class="btn btn-dark" type="submit">{{ __('storefront.product.reviews_submit') }}</button>
                            <span class="ms-2 small" data-review-status role="status" aria-live="polite"></span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Khách tắt JS: Bootstrap không gỡ được `display:none` của .modal, nên popup
         trên là một form vô hình và mục này mất luôn đường GỬI đánh giá. Đây là
         chỗ duy nhất trong partial mà việc VIẾT phụ thuộc JS — đổi lại phần đọc
         gọn hơn hẳn. Vài dòng dưới trả form về dạng tĩnh để khách không JS vẫn
         submit được như một form thường. --}}
    <noscript>
        <style>
            #reviewForm { display: block; position: static; width: auto; height: auto; opacity: 1; }
            #reviewForm .modal-dialog { max-width: 32rem; margin: 1.5rem auto; }
            #reviewForm .btn-close { display: none; }
        </style>
    </noscript>
</section>
