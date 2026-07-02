@extends('theme::layouts.app')
@section('body_class', 'page-cms')

@php
    $title = $page->meta_title ?: $page->title;
    $description = $page->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $page->content), 155);
    // featured_image is a MediaPicker value; $fileUrl resolver injected by the
    // Assets view composer (coding standards §7).
    $ogImage = $page->featured_image ? $fileUrl($page->featured_image, 'large') : null;
@endphp

@section('title', $title . ' — ' . config('app.name'))
@section('meta_description', $description)
@section('og_type', 'article')
@if ($ogImage)
    @section('og_image', $ogImage)
@endif

@section('content')
  <div class="container py-5">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}"
            class="text-decoration-none">{{ __('storefront.nav.home') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $page->title }}</li>
      </ol>
    </nav>

    <article class="row justify-content-center">
      <div class="col-12 col-lg-8">
        @if ($ogImage)
          <img src="{{ $ogImage }}" alt="{{ $page->title }}" class="img-fluid rounded mb-4 w-100">
        @endif
        <h1 class="h2 mb-4">{{ $page->title }}</h1>
        <div class="cms-content">{!! $page->content !!}</div>
      </div>
    </article>
  </div>
@endsection

@push('head')
  @php
    $webPageLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $page->title,
        'description' => $description,
        'url' => url()->current(),
        'image' => $ogImage,
        'dateModified' => $page->updated_at?->toIso8601String(),
    ]);
    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.static.home_breadcrumb'), 'item' => route('storefront.home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page->title, 'item' => url()->current()],
        ],
    ];
  @endphp
  <script type="application/ld+json">@json($webPageLd, JSON_UNESCAPED_SLASHES)</script>
  <script type="application/ld+json">@json($breadcrumbLd, JSON_UNESCAPED_SLASHES)</script>
@endpush
