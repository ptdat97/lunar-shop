@extends('theme::layouts.app')

@php
  use Lunar\Facades\Pricing;

  $name = $product->translateAttribute('name');
  $description = $product->translateAttribute('description');
  $brand = $product->brand?->name;

  // Categories from the product's collections (fallback to brand-only).
$categories = ($product->collections ?? collect())->map(fn($c) => $c->translateAttribute('name'))->filter()->values();

// Gallery: product media (fallback to thumbnail or a placeholder).
$media = $product->media ?? collect();
$images = $media->isNotEmpty()
    ? $media->map(fn($m) => $m->hasGeneratedConversion('large') ? $m->getUrl('large') : $m->getUrl())->all()
    : [$product->thumbnail?->getUrl('large') ?? '/themes/modave/images/products/womens/women-3.jpg'];

// Variants for the picker + pricing.
$variants = $product->variants
    ->map(function ($v) {
        $price = Pricing::for($v)->get()->matched->price;
        return [
            'id' => $v->id,
            'sku' => $v->sku,
            'stock' => $v->stock,
            'price' => $price->decimal(),
            'price_formatted' => (string) $price->formatted(),
        ];
    })
    ->values();

$firstVariant = $variants->first();
$totalStock = $product->variants->sum('stock');
@endphp

@section('title', $name)

@section('content')
  {{-- Breadcrumb --}}
  <div class="tf-breadcrumb">
    <div class="container">
      <div class="tf-breadcrumb-wrap">
        <div class="tf-breadcrumb-list">
          <a href="/" class="text text-caption-1">Homepage</a>
          <i class="icon icon-arrRight"></i>
          @if ($categories->isNotEmpty())
            <a href="/search" class="text text-caption-1">{{ $categories->first() }}</a>
            <i class="icon icon-arrRight"></i>
          @endif
          <span class="text text-caption-1">{{ $name }}</span>
        </div>
      </div>
    </div>
  </div>

  <section class="flat-spacing">
    <div class="tf-main-product section-image-zoom">
      <div class="container">
        <div class="row">
          {{-- Media gallery: vertical thumbs + main swiper + hover/lightbox zoom --}}
          <div class="col-md-6">
            <div class="tf-product-media-wrap sticky-top">
              <div class="thumbs-slider">
                <div dir="ltr" class="swiper tf-product-media-thumbs other-image-zoom" data-direction="vertical">
                  <div class="swiper-wrapper stagger-wrap">
                    @foreach ($images as $img)
                      <div class="swiper-slide stagger-item">
                        <div class="item">
                          <img class="lazyload" data-src="{{ $img }}" src="{{ $img }}"
                            alt="{{ $name }}">
                        </div>
                      </div>
                    @endforeach
                  </div>
                </div>
                <div dir="ltr" class="swiper tf-product-media-main" id="gallery-swiper-started">
                  <div class="swiper-wrapper">
                    @foreach ($images as $img)
                      <div class="swiper-slide">
                        <a href="{{ $img }}" target="_blank" class="item" data-pswp-width="600px"
                          data-pswp-height="800px">
                          <img class="tf-image-zoom lazyload" data-zoom="{{ $img }}"
                            data-src="{{ $img }}" src="{{ $img }}" alt="{{ $name }}">
                        </a>
                      </div>
                    @endforeach
                  </div>
                  <div class="swiper-button-next button-style-arrow thumbs-next"></div>
                  <div class="swiper-button-prev button-style-arrow thumbs-prev"></div>
                </div>
              </div>
            </div>
          </div>

          {{-- Info --}}
          <div class="col-md-6">
            <div class="tf-product-info-wrap position-relative">
              {{-- Drift zoom pane (where the magnified image renders on hover) --}}
              <div class="tf-zoom-main"></div>
              <div class="tf-product-info-list other-image-zoom">
                <div class="tf-product-info-heading">
                  <div class="tf-product-info-name">
                    @if ($brand)
                      <div class="text text-btn-uppercase">{{ $brand }}</div>
                    @endif
                    <h3 class="name">{{ $name }}</h3>
                    <div class="sub">
                      <div class="tf-product-info-rate">
                        <div class="list-star">
                          <i class="icon icon-star"></i>
                          <i class="icon icon-star"></i>
                          <i class="icon icon-star"></i>
                          <i class="icon icon-star"></i>
                          <i class="icon icon-star"></i>
                        </div>
                        <div class="text text-caption-1">(134 reviews)</div>
                      </div>
                      <div class="tf-product-info-sold">
                        <i class="icon icon-lightning"></i>
                        <div class="text text-caption-1">18 sold in last 32 hours</div>
                      </div>
                    </div>
                  </div>
                  <div class="tf-product-info-desc">
                    <div class="tf-product-info-price">
                      <h5 class="price-on-sale font-2" data-vue-price>{{ $firstVariant['price_formatted'] ?? '' }}</h5>
                    </div>
                    @if ($description)
                      <p>{{ $description }}</p>
                    @endif
                    <div class="tf-product-info-liveview">
                      <i class="icon icon-eye"></i>
                      <p class="text-caption-1"><span class="liveview-count">28</span> people are viewing this right now
                      </p>
                    </div>
                  </div>
                </div>
                <div class="tf-product-info-choose-option">
                  {{-- Vue island: variant picker + quantity + add to cart --}}
                  <div data-vue="product-purchase" data-variants='@json($variants)'></div>

                  {{-- Size & Fit (opens popup) --}}
                  <div class="mt_20">
                    <x-theme::size-chart :size-chart="$sizeChart ?? []" :slug="$slug ?? ''" />
                  </div>

                  <div class="tf-product-info-help">
                    <div class="tf-product-info-extra-link">
                      <a href="#delivery_return" data-bs-toggle="modal" class="tf-product-extra-icon">
                        <div class="icon">
                          <i class="icon-shipping"></i>
                        </div>
                        <p class="text-caption-1">Delivery & Return</p>
                      </a>
                      <a href="#ask_question" data-bs-toggle="modal" class="tf-product-extra-icon">
                        <div class="icon">
                          <i class="icon-question"></i>
                        </div>
                        <p class="text-caption-1">Ask A Question</p>
                      </a>
                      <a href="#share_social" data-bs-toggle="modal" class="tf-product-extra-icon">
                        <div class="icon">
                          <i class="icon-share"></i>
                        </div>
                        <p class="text-caption-1">Share</p>
                      </a>
                    </div>
                    <div class="tf-product-info-time">
                      <div class="icon">
                        <i class="icon-timer"></i>
                      </div>
                      <p class="text-caption-1">Estimated Delivery:&nbsp;&nbsp;<span>12-26 days</span> (International),
                        <span>3-6 days</span> (United States)</p>
                    </div>
                    <div class="tf-product-info-return">
                      <div class="icon">
                        <i class="icon-arrowClockwise"></i>
                      </div>
                      <p class="text-caption-1">Return within <span>45 days</span> of purchase. Duties & taxes are
                        non-refundable.</p>
                    </div>
                  </div>

                  <ul class="tf-product-info-sku">
                    @if ($firstVariant['sku'] ?? null)
                      <li>
                        <p class="text-caption-1">SKU:</p>
                        <p class="text-caption-1 text-1">{{ $firstVariant['sku'] }}</p>
                      </li>
                    @endif
                    @if ($brand)
                      <li>
                        <p class="text-caption-1">Vendor:</p>
                        <p class="text-caption-1 text-1">{{ $brand }}</p>
                      </li>
                    @endif
                    <li>
                      <p class="text-caption-1">Available:</p>
                      <p class="text-caption-1 text-1">{{ $totalStock > 0 ? 'In stock' : 'Out of stock' }}</p>
                    </li>
                    @if ($categories->isNotEmpty())
                      <li>
                        <p class="text-caption-1">Categories:</p>
                        <p class="text-caption-1">
                          @foreach ($categories as $category)
                            <a href="/search" class="text-1 link">{{ $category }}</a>
                            @if (!$loop->last)
                              ,
                            @endif
                          @endforeach
                        </p>
                      </li>
                    @endif
                  </ul>

                  <div class="tf-product-info-guranteed">
                    <div class="text-title">
                      Guranteed safe checkout:
                    </div>
                    <div class="tf-payment">
                      @foreach (range(1, 6) as $i)
                        <a href="#">
                          <img src="/themes/modave/images/payment/img-{{ $i }}.png" alt="payment">
                        </a>
                      @endforeach
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- Description tabs --}}
  @if ($description)
    <section class="">
      <div class="container">
        <div class="row">
          <div class="col-12">
            <div class="widget-tabs style-1">
              <ul class="widget-menu-tab">
                <li class="item-title active">
                  <span class="inner">Description</span>
                </li>
              </ul>
              <div class="widget-content-tab">
                <div class="widget-content-inner active">
                  <div class="tab-description">
                    <div class="right">
                      <div class="letter-1 text-btn-uppercase mb_12">{{ $name }}</div>
                      <p class="text-secondary">{{ $description }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  @endif

  {{-- Related products --}}
  @if (($related ?? collect())->isNotEmpty())
    <section class="flat-spacing">
      <div class="container flat-animate-tab">
        <ul class="tab-product justify-content-sm-center wow fadeInUp" data-wow-delay="0s" role="tablist">
          <li class="nav-tab-item" role="presentation">
            <a href="#relatedProducts" class="active" data-bs-toggle="tab">Related Products</a>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane active show" id="relatedProducts" role="tabpanel">
            <div dir="ltr" class="swiper tf-sw-latest" data-preview="4" data-tablet="3" data-mobile-sm="2"
              data-mobile="1" data-space-lg="30" data-space="15" data-pagination="1" data-pagination-md="1"
              data-pagination-lg="1">
              <div class="swiper-wrapper">
                @foreach ($related as $item)
                  <div class="swiper-slide">
                    <x-theme::product-card :product="$item" />
                  </div>
                @endforeach
              </div>
              <div class="sw-dots style-2 sw-pagination-latest justify-content-center"></div>
            </div>
          </div>
        </div>
      </div>
    </section>
  @endif

  {{-- Product modals (linked from the info-help block above) --}}
  <div class="modal modalCentered fade tf-product-modal modal-part-content" id="ask_question">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="header">
          <div class="demo-title">Ask a question</div>
          <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
        </div>
        <div class="overflow-y-auto">
          <form>
            <fieldset>
              <label>Name *</label>
              <input type="text" name="name" tabindex="2" value="" aria-required="true" required>
            </fieldset>
            <fieldset>
              <label>Email *</label>
              <input type="email" name="email" tabindex="2" value="" aria-required="true" required>
            </fieldset>
            <fieldset>
              <label>Phone number</label>
              <input type="tel" name="phone" tabindex="2" value="">
            </fieldset>
            <fieldset>
              <label>Message</label>
              <textarea name="message" rows="4" tabindex="2" aria-required="true" required></textarea>
            </fieldset>
            <button type="submit" class="btn-style-2 w-100"><span class="text">Send</span></button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal modalCentered fade tf-product-modal modal-part-content" id="delivery_return">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="header">
          <div class="demo-title">Shipping & Delivery</div>
          <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
        </div>
        <div class="overflow-y-auto">
          <div class="tf-product-popup-delivery">
            <div class="title">Delivery</div>
            <p class="text-paragraph">All orders shipped with UPS Express.</p>
            <p class="text-paragraph">Always free shipping for orders over US $250.</p>
            <p class="text-paragraph">All orders are shipped with a UPS tracking number.</p>
          </div>
          <div class="tf-product-popup-delivery">
            <div class="title">Returns</div>
            <p class="text-paragraph">Items returned within 14 days of their original shipment date in same as new
              condition will be eligible for a full refund or store credit.</p>
            <p class="text-paragraph">Refunds will be charged back to the original form of payment used for purchase.</p>
            <p class="text-paragraph">Customer is responsible for shipping charges when making returns and
              shipping/handling fees of original purchase is non-refundable.</p>
            <p class="text-paragraph">All sale items are final purchases.</p>
          </div>
          <div class="tf-product-popup-delivery">
            <div class="title">Help</div>
            <p class="text-paragraph">Give us a shout if you have any other questions and/or concerns.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal modalCentered fade tf-product-modal modal-part-content" id="share_social">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="header">
          <div class="demo-title">Share</div>
          <span class="icon-close icon-close-popup" data-bs-dismiss="modal"></span>
        </div>
        <div class="overflow-y-auto">
          <ul class="tf-social-icon d-flex gap-10">
            <li><a href="#" class="box-icon social-facebook bg_line"><i class="icon icon-fb"></i></a></li>
            <li><a href="#" class="box-icon social-twiter bg_line"><i class="icon icon-x"></i></a></li>
            <li><a href="#" class="box-icon social-instagram bg_line"><i class="icon icon-instagram"></i></a>
            </li>
            <li><a href="#" class="box-icon social-tiktok bg_line"><i class="icon icon-tiktok"></i></a></li>
            <li><a href="#" class="box-icon social-pinterest bg_line"><i class="icon icon-pinterest"></i></a>
            </li>
          </ul>
          <form class="form-share" method="post" accept-charset="utf-8">
            <fieldset>
              <input type="text" value="{{ url()->current() }}" tabindex="0" aria-required="true" readonly>
            </fieldset>
            <div class="button-submit">
              <button class="tf-btn radius-4 btn-fill" type="button"><span class="text">Copy</span></button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('head')
  <link rel="stylesheet" href="/themes/modave/css/drift-basic.min.css">
  <link rel="stylesheet" href="/themes/modave/css/photoswipe.css">
@endpush

@push('scripts')
  <script src="/themes/modave/js/drift.min.js"></script>
  <script type="module" src="/themes/modave/js/zoom.js"></script>
@endpush
