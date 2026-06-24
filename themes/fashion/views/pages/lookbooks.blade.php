@extends('theme::layouts.app')

@section('title', 'Lookbooks — '.config('app.name'))
@section('meta_description', 'Browse our curated lookbooks — shop the looks you love.')

@section('content')
    <div class="container py-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Lookbooks</li>
            </ol>
        </nav>

        <h1 class="h3 mb-4">Lookbooks</h1>

        @if(empty($lookbooks))
            <p class="text-muted">No lookbooks published yet.</p>
        @else
            <div class="row g-4">
                @foreach($lookbooks as $lookbook)
                    @php
                        // $fileUrl resolver injected by the FileManager composer (§7).
                        $cover = $fileUrl($lookbook->cover_image, 'large');
                    @endphp
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a href="{{ route('storefront.lookbook', $lookbook->slug) }}"
                           class="d-block text-decoration-none text-dark lookbook-card">
                            <div class="ratio ratio-4x3 rounded overflow-hidden bg-light mb-2">
                                @if($cover)
                                    <img src="{{ $cover }}" alt="{{ $lookbook->title }}"
                                         class="w-100 h-100" style="object-fit:cover" loading="lazy">
                                @endif
                            </div>
                            <h2 class="h6 mb-1">{{ $lookbook->title }}</h2>
                            @if($lookbook->description)
                                <p class="small text-muted mb-0">{{ \Illuminate\Support\Str::limit($lookbook->description, 90) }}</p>
                            @endif
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
