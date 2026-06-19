@extends('theme::layouts.app')

@section('title', 'My account — '.config('app.name'))

@section('content')
<div class="container py-4" data-account>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My account</h1>
        <button class="btn btn-outline-dark btn-sm" data-logout>Sign out</button>
    </div>

    <div class="row g-4">
        {{-- Profile (SSR — already authenticated) --}}
        <div class="col-12 col-lg-4">
            <div class="border rounded p-3">
                <h2 class="h6 text-uppercase">Profile</h2>
                <dl class="row small mb-0">
                    <dt class="col-4 text-muted fw-normal">Name</dt><dd class="col-8">{{ $user->name }}</dd>
                    <dt class="col-4 text-muted fw-normal">Email</dt><dd class="col-8">{{ $user->email }}</dd>
                </dl>
            </div>
            <a href="{{ route('storefront.wishlist') }}" class="btn btn-link px-0 mt-2">View wishlist →</a>
        </div>

        {{-- Order history (fetched — personalised, not SEO content) --}}
        <div class="col-12 col-lg-8">
            <div class="border rounded p-3">
                <h2 class="h6 text-uppercase">Order history</h2>
                <div class="text-muted small" data-orders-loading>Loading orders…</div>
                <div class="text-muted small" data-orders-empty hidden>You have no orders yet.</div>
                <div class="table-responsive" data-orders-wrap hidden>
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr class="small text-uppercase text-muted">
                                <th>Reference</th><th>Date</th><th>Status</th><th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody data-orders-body></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
