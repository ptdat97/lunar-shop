@extends('theme::layouts.app')

@section('title', $page->meta_title ?: $page->title)

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-12">
        <article>
            <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ $page->title }}</h1>

            @if($page->content)
                <div class="prose prose-gray max-w-none">
                    {!! $page->content !!}
                </div>
            @endif
        </article>
    </div>
@endsection