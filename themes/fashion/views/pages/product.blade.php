@extends('theme::layouts.app')

@php
    $name = $product->translateAttribute('name');
    $variantsJson = $product->variants->map(fn ($v) => [
        'id' => $v->id,
        'sku' => $v->sku,
        'stock' => $v->stock,
        'price' => (string) \Lunar\Facades\Pricing::for($v)->get()->matched->price->formatted(),
    ])->values()->toJson();
@endphp

@section('title', $name)

@section('content')
    <article class="product-detail">
        <div class="product-detail__media">
            <x-theme::responsive-image
                :media="$product->thumbnail"
                :alt="$name"
                conversion="large"
                loading="eager"
            />
        </div>

        <div class="product-detail__info">
            <h1>{{ $name }}</h1>

            @if ($description = $product->translateAttribute('description'))
                <div class="product-detail__description">{{ $description }}</div>
            @endif

            {{-- Vue island: pick a variant + add to cart via /api/v1/cart --}}
            <div data-vue="variant-picker" data-variants='{{ $variantsJson }}'></div>
        </div>
    </article>
@endsection
