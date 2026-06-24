@extends('theme::layouts.app')

{{-- Home is fully SSR: the controller passes $sections already rendered by
     SectionRenderer (DB-driven section list → theme::sections.{type}). --}}
@section('content')
    @include('theme::partials.promotions-strip')
    {!! $sections !!}
@endsection
