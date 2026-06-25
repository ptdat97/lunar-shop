@extends('theme::layouts.app')

@section('title', __('storefront.promotion.title').' — '.config('app.name'))

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}" class="text-decoration-none">{{ __('storefront.nav.home') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('storefront.promotion.title') }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-4"><i class="bi bi-tags-fill text-danger"></i> {{ __('storefront.promotion.title') }}</h1>

    @forelse($promotions as $promo)
        @php($discount = $promo['discount'])
        <section class="mb-5">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                <div>
                    <h2 class="h5 mb-1">
                        @if($promo['is_flash_sale'])<i class="bi bi-lightning-charge-fill text-danger"></i>@endif
                        {{ $discount->name }}
                    </h2>
                    <p class="text-muted mb-0">{{ $promo['description'] }}</p>
                    @if($discount->ends_at)
                        <p class="small text-danger mb-0">{{ __('storefront.promotion.ends', ['date' => $discount->ends_at->format('M j, Y H:i')]) }}</p>
                    @endif
                </div>
                <a href="{{ route('storefront.promotion', $discount->handle) }}" class="btn btn-dark btn-sm flex-shrink-0">
                    {{ __('storefront.common.view_all') }} <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            @if($promo['products']->isNotEmpty())
                <div class="row g-3">
                    @foreach($promo['products'] as $product)
                        <div class="col-6 col-md-4 col-lg-3">
                            @include('theme::components.product-card', ['product' => $product])
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-muted">{{ __('storefront.promotion.no_products') }}</p>
            @endif
        </section>
    @empty
        <p class="text-muted py-5 text-center">{{ __('storefront.promotion.none_active') }}</p>
    @endforelse
</div>
@endsection
