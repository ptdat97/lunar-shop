@extends('theme::layouts.app')
@section('body_class', 'page-home')

{{-- Home is fully SSR: the controller passes $sections already rendered by
     SectionRenderer (DB-driven section list → theme::sections.{type}). --}}
@section('content')
    @include('theme::partials.promotions-strip')
    {!! $sections !!}
@endsection
