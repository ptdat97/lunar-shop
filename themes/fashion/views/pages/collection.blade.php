@extends('theme::layouts.app')

@php $name = $collection->translateAttribute('name'); @endphp

@section('title', $name)

@section('content')
    <section class="collection">
        <header class="collection__header">
            <h1>{{ $name }}</h1>
            @if ($description = $collection->translateAttribute('description'))
                <p>{{ $description }}</p>
            @endif
        </header>

        <div class="collection__body">
            {{-- Vue island: client-side filters (size/color/price) over /api/v1/search?scope= --}}
            <aside data-vue="collection-filters" data-scope="{{ $collection->defaultUrl?->slug }}"></aside>

            <div class="collection__products">
                @if ($products->isEmpty())
                    <p class="empty">No products in this collection.</p>
                @else
                    <div class="grid">
                        @foreach ($products as $product)
                            <x-theme::product-card :product="$product" />
                        @endforeach
                    </div>

                    {{ $products->links() }}
                @endif
            </div>
        </div>
    </section>
@endsection
