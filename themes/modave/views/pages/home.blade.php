@extends('theme::layouts.app')

@section('title', 'Home')

@section('content')
    {{-- Sections are rendered server-side from the DB (SectionBuilder). --}}
    {!! $sections !!}
@endsection
