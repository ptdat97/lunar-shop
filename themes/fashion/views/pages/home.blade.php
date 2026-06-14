@extends('theme::layouts.app')

@section('title', 'Home')

@section('content')
    <section class="hero">
        <h1>{{ $heading ?? 'Welcome' }}</h1>
        <p>{{ $subheading ?? '' }}</p>
    </section>

    <section class="product-grid">
        <h2 class="section-title">Featured</h2>

        @if ($products->isEmpty())
            <p class="empty">No products yet.</p>
        @else
            <div class="grid">
                @foreach ($products as $product)
                    <x-theme::product-card :product="$product" />
                @endforeach
            </div>
        @endif
    </section>
@endsection
