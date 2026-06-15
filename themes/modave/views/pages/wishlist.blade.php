@extends('theme::layouts.app')

@section('title', 'Wishlist')

@section('content')
    <div class="page-title">
        <div class="container-full">
            <div class="row"><div class="col-12">
                <h3 class="heading text-center">Wishlist</h3>
            </div></div>
        </div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            <div data-vue="wishlist-page"></div>
        </div>
    </section>
@endsection
