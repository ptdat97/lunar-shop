@extends('theme::layouts.app')
@section('body_class', 'page-account')

@section('title', __('storefront.account.my_account').' — '.config('app.name'))
@section('robots', 'noindex, follow')

@section('content')
<div class="container py-4" data-account>
    {{-- Invoice download + return request: URL templates (:id replaced
         client-side) + labels, so account.js stays free of hardcoded routes/strings.
         Built in a @php block (not inline @json([...])) because Blade's @json
         directive mis-parses nested arrays / route(..., [...]) calls. --}}
    @php
        $accountState = [
            'countries' => $countries,
            'invoiceUrl' => route('storefront.orders.invoice', ['order' => '__ID__']),
            'returnUrl' => route('storefront.orders.returns.store', ['order' => '__ID__']),
            // Size reasons carry a direction: they feed FitHistoryService, which
            // recommends a size (and warns "between two sizes") on later visits.
            'returnReasons' => [
                'too-small' => __('storefront.account.return_reason_too_small'),
                'too-large' => __('storefront.account.return_reason_too_large'),
                'defect' => __('storefront.account.return_reason_defect'),
                'not-as-described' => __('storefront.account.return_reason_described'),
                'changed-mind' => __('storefront.account.return_reason_mind'),
            ],
            'i18n' => [
                'downloadInvoice' => __('storefront.account.download_invoice'),
                'requestReturn' => __('storefront.account.request_return'),
                'submitReturn' => __('storefront.account.submit_return'),
                'returnReason' => __('storefront.account.return_reason'),
                'returnQty' => __('storefront.account.return_qty'),
                'returnSubmitted' => __('storefront.account.return_submitted'),
                'returnError' => __('storefront.account.return_error'),
                'view' => __('storefront.account.view'),
                'edit' => __('storefront.account.edit'),
                'delete' => __('storefront.account.delete'),
                'default' => __('storefront.account.default'),
                'shippingTo' => __('storefront.account.shipping_to'),
                'subtotal' => __('storefront.account.subtotal'),
                'shipping' => __('storefront.account.shipping'),
                'total' => __('storefront.account.total'),
                'loading' => __('storefront.account.loading'),
                'selectWard' => __('storefront.account.select_ward'),
                'editAddress' => __('storefront.account.edit_address'),
                'addAddress' => __('storefront.account.add_address'),
                'deleteAddressConfirm' => __('storefront.account.delete_address_confirm'),
                'ordersError' => __('storefront.account.orders_error'),
                'orderError' => __('storefront.account.order_error'),
                'addressesError' => __('storefront.account.addresses_error'),
                'saveError' => __('storefront.account.save_error'),
                'addressSaveError' => __('storefront.account.address_save_error'),
                'selectItem' => __('storefront.account.select_item'),
            ],
        ];
    @endphp
    <script type="application/json" data-account-state>@json($accountState)</script>
    <h1 class="h3 mb-4">{{ __('storefront.account.my_account') }}</h1>

    <div class="row g-4">
        {{-- Sidebar nav --}}
        <div class="col-12 col-lg-3">
            <div class="list-group" role="tablist">
                <button class="list-group-item list-group-item-action active" data-tab-btn="dashboard">{{ __('storefront.account.dashboard') }}</button>
                <button class="list-group-item list-group-item-action" data-tab-btn="orders">{{ __('storefront.account.orders') }}</button>
                <button class="list-group-item list-group-item-action" data-tab-btn="addresses">{{ __('storefront.account.addresses') }}</button>
                <button class="list-group-item list-group-item-action" data-tab-btn="profile">{{ __('storefront.account.profile') }}</button>
                <a class="list-group-item list-group-item-action" href="{{ route('storefront.wishlist') }}">{{ __('storefront.nav.wishlist') }}</a>
                <button class="list-group-item list-group-item-action text-danger" data-logout>{{ __('storefront.auth.sign_out') }}</button>
            </div>
        </div>

        <div class="col-12 col-lg-9">
            {{-- Dashboard --}}
            <section data-tab-panel="dashboard">
                <div class="border rounded p-3 mb-3">
                    <h2 class="h6 text-uppercase">{{ __('storefront.static.hello') }}, {{ $user->name }}</h2>
                    <p class="text-muted mb-0">{{ $user->email }}</p>
                </div>

                {{-- Membership tier card — populated by enhance/membership.js
                     from /api/v1/promotions/membership. Hidden until loaded. --}}
                <div class="border rounded p-3 mb-3 d-none" data-membership>
                    <div class="d-flex align-items-center justify-content-between">
                        <h2 class="h6 text-uppercase mb-0">{{ __('storefront.account.membership') }}</h2>
                        <span class="badge bg-dark" data-membership-tier>—</span>
                    </div>
                    <p class="text-muted small mb-2" data-membership-perk hidden></p>
                    <div class="progress" style="height:6px;" data-membership-progress-wrap hidden>
                        <div class="progress-bar bg-warning" role="progressbar" data-membership-progress style="width:0%"></div>
                    </div>
                    <p class="text-muted small mt-2 mb-0" data-membership-next hidden></p>
                </div>
                {{-- Điểm thưởng — SSR, cùng khuôn với khối giới thiệu bạn ngay
                     dưới: tính năng tắt (hoặc khách chưa có hồ sơ) thì $loyalty
                     là null và cả khối biến mất. Blade không biết gì về cờ. --}}
                @if(! empty($loyalty))
                    <div class="border rounded p-3 mb-3" data-loyalty>
                        <h2 class="h6 text-uppercase">{{ __('storefront.loyalty.title') }}</h2>

                        @if($loyalty['balance'] > 0)
                            <p class="mb-1">
                                <span class="h4">{{ __('storefront.loyalty.balance', ['points' => number_format($loyalty['balance'])]) }}</span>
                                <span class="text-muted ms-1">{{ __('storefront.loyalty.worth', ['value' => $loyalty['value']]) }}</span>
                            </p>
                        @else
                            <p class="text-muted mb-1">{{ __('storefront.loyalty.empty') }}</p>
                        @endif

                        @if($loyalty['pending'] > 0)
                            <p class="text-muted small mb-1">
                                {{ __('storefront.loyalty.pending', ['points' => number_format($loyalty['pending'])]) }}
                            </p>
                        @endif

                        {{-- Điểm sắp chết là thứ duy nhất ở khối này khách cần
                             hành động, nên nó được nói to hơn phần còn lại. --}}
                        @if($loyalty['expiring'])
                            <p class="text-warning-emphasis small mb-1">
                                {{ __('storefront.loyalty.expiring', [
                                    'points' => number_format($loyalty['expiring']['points']),
                                    'date' => $loyalty['expiring']['at'],
                                ]) }}
                            </p>
                        @endif

                        <p class="text-muted small mb-0">
                            {{ __('storefront.loyalty.rate_hint', [
                                'value' => $loyalty['point_value'],
                                'min' => $loyalty['min_redeem'],
                            ]) }}
                        </p>
                    </div>
                @endif

                {{-- Giới thiệu bạn — SSR y như khối đánh giá: mã và link nằm sẵn
                     trong HTML, JS chỉ thêm nút copy. Tính năng tắt thì biến
                     $referral là null và cả khối này không render — Blade không
                     phải biết gì về cờ bật/tắt. --}}
                @if(! empty($referral))
                    <div class="border rounded p-3 mb-3" data-referral>
                        <h2 class="h6 text-uppercase">{{ __('storefront.referral.title') }}</h2>
                        <p class="text-muted small mb-2">
                            {{ __('storefront.referral.intro', [
                                'welcome' => $referral['welcome_percentage'],
                                'reward' => $referral['reward_percentage'],
                                'days' => $referral['reward_delay_days'],
                            ]) }}
                        </p>

                        <div class="input-group input-group-sm mb-2">
                            <input class="form-control" type="text" readonly
                                   value="{{ $referral['link'] }}" data-referral-link
                                   aria-label="{{ __('storefront.referral.link_label') }}">
                            <button class="btn btn-outline-dark" type="button" data-referral-copy>
                                {{ __('storefront.referral.copy') }}
                            </button>
                        </div>
                        <p class="mb-3">
                            <span class="badge bg-dark">{{ $referral['code'] }}</span>
                            <span class="text-muted small ms-1">{{ __('storefront.referral.code_hint') }}</span>
                        </p>

                        <div class="row g-2 mb-3">
                            @foreach(['invited' => 'stat_invited', 'awaiting' => 'stat_awaiting', 'rewarded' => 'stat_rewarded'] as $countKey => $labelKey)
                                <div class="col-4">
                                    <div class="border rounded p-2 text-center">
                                        <div class="h6 mb-0">{{ $referral[$countKey] }}</div>
                                        <div class="small text-muted">{{ __('storefront.referral.'.$labelKey) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Người đang xem được giới thiệu bởi người khác: đây là mã
                             của chính họ, không phải mã họ đi mời. --}}
                        @if($referral['welcome'])
                            <div class="alert alert-success py-2 mb-3">
                                <div class="fw-semibold">{{ __('storefront.referral.welcome_title') }}</div>
                                <div class="small">
                                    {{ __('storefront.referral.welcome_body', ['percent' => $referral['welcome']['percentage']]) }}
                                </div>
                                <div class="mt-1">
                                    <span class="badge bg-dark">{{ $referral['welcome']['code'] }}</span>
                                    @if($referral['welcome']['used'])
                                        <span class="small ms-1">{{ __('storefront.referral.welcome_used') }}</span>
                                    @elseif($referral['welcome']['expired'])
                                        <span class="small ms-1">{{ __('storefront.referral.welcome_expired') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($referral['friends'])
                            <ul class="list-unstyled small mb-0">
                                @foreach($referral['friends'] as $friend)
                                    <li class="d-flex justify-content-between border-top py-1">
                                        <span>{{ $friend['name'] }} <span class="text-muted">· {{ $friend['date'] }}</span></span>
                                        <span>
                                            <span class="text-muted">{{ __('storefront.referral.status_'.$friend['status']) }}</span>
                                            @if($friend['reward_code'])
                                                <span class="badge bg-light text-dark">{{ $friend['reward_code'] }}</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-6 col-md-4">
                        <button class="border rounded p-3 w-100 text-start bg-white" data-tab-btn="orders">
                            <div class="h4 mb-0" data-stat-orders>—</div>
                            <div class="small text-muted">{{ __('storefront.account.orders') }}</div>
                        </button>
                    </div>
                    <div class="col-6 col-md-4">
                        <button class="border rounded p-3 w-100 text-start bg-white" data-tab-btn="addresses">
                            <div class="h4 mb-0" data-stat-addresses>—</div>
                            <div class="small text-muted">{{ __('storefront.account.saved_addresses') }}</div>
                        </button>
                    </div>
                </div>
            </section>

            {{-- Orders --}}
            <section data-tab-panel="orders" hidden>
                <div class="border rounded p-3">
                    <h2 class="h6 text-uppercase">{{ __('storefront.account.order_history') }}</h2>
                    <div class="text-muted small" data-orders-loading>…</div>
                    <div class="text-muted small" data-orders-empty hidden>{{ __('storefront.account.no_orders') }}</div>
                    <div class="table-responsive" data-orders-wrap hidden>
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-uppercase text-muted">
                                    <th>{{ __('storefront.account.reference') }}</th><th>{{ __('storefront.common.date') }}</th><th>{{ __('storefront.common.status') }}</th><th class="text-end">{{ __('storefront.cart.total') }}</th><th></th>
                                </tr>
                            </thead>
                            <tbody data-orders-body></tbody>
                        </table>
                    </div>
                </div>

                {{-- Order detail (shown when a row is opened) --}}
                <div class="border rounded p-3 mt-3" data-order-detail hidden>
                    <button class="btn btn-link p-0 mb-2" data-order-back>← {{ __('storefront.account.order_history') }}</button>
                    <div data-order-detail-body></div>
                </div>
            </section>

            {{-- Addresses --}}
            <section data-tab-panel="addresses" hidden>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 text-uppercase mb-0">{{ __('storefront.account.address_book') }}</h2>
                    <button class="btn btn-dark btn-sm" data-address-new>{{ __('storefront.account.add_address') }}</button>
                </div>
                <div class="text-muted small" data-addresses-loading>…</div>
                <div class="text-muted small" data-addresses-empty hidden>{{ __('storefront.account.no_addresses') }}</div>
                <div class="row g-3" data-addresses-list></div>

                {{-- Address form (create/edit) --}}
                <div class="border rounded p-3 mt-3" data-address-form-wrap hidden>
                    <h3 class="h6" data-address-form-title>{{ __('storefront.account.add_address') }}</h3>
                    <form data-address-form>
                        <input type="hidden" name="id">
                        <div class="row g-2">
                            <div class="col-6"><input class="form-control" name="first_name" placeholder="Họ" required></div>
                            <div class="col-6"><input class="form-control" name="last_name" placeholder="Tên" required></div>
                            <div class="col-6">
                                <select class="form-select" name="state" required data-province-select>
                                    <option value="" disabled selected>Tỉnh/Thành phố</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <select class="form-select" name="city" required data-ward-select disabled>
                                    <option value="" disabled selected>Phường/Xã</option>
                                </select>
                            </div>
                            <div class="col-12"><input class="form-control" name="line_one" placeholder="Số nhà, tên đường" required></div>
                            <div class="col-12"><input class="form-control" name="line_two" placeholder="Toà nhà, ghi chú (tuỳ chọn)"></div>
                            <div class="col-12">
                                <select class="form-select" name="country_id" required data-country-select></select>
                            </div>
                            <div class="col-6"><input class="form-control" name="contact_phone" placeholder="Số điện thoại"></div>
                            <div class="col-12 form-check ms-1">
                                <input class="form-check-input" type="checkbox" name="shipping_default" id="addr-ship-default">
                                <label class="form-check-label" for="addr-ship-default">{{ __('storefront.account.use_as_default_shipping') }}</label>
                            </div>
                        </div>
                        <div class="alert alert-danger mt-2" data-address-error hidden></div>
                        <div class="mt-3 d-flex gap-2">
                            <button class="btn btn-dark" type="submit">{{ __('storefront.common.save') }}</button>
                            <button class="btn btn-outline-secondary" type="button" data-address-cancel>{{ __('storefront.common.cancel') }}</button>
                        </div>
                    </form>
                </div>
            </section>

            {{-- Profile --}}
            <section data-tab-panel="profile" hidden>
                <div class="border rounded p-3 mb-3">
                    <h2 class="h6 text-uppercase">{{ __('storefront.account.profile') }}</h2>
                    <form data-profile-form>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('storefront.common.name') }}</label>
                            <input class="form-control" name="name" value="{{ $user->name }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('storefront.common.email') }}</label>
                            <input class="form-control" type="email" name="email" value="{{ $user->email }}" required>
                        </div>
                        <div class="alert alert-danger" data-profile-error hidden></div>
                        <div class="alert alert-success" data-profile-ok hidden>{{ __('storefront.account.profile_updated') }}</div>
                        <button class="btn btn-dark" type="submit">{{ __('storefront.common.save_changes') }}</button>
                    </form>
                </div>

                <div class="border rounded p-3">
                    <h2 class="h6 text-uppercase">{{ __('storefront.account.change_password') }}</h2>
                    <form data-password-form>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('storefront.account.current_password') }}</label>
                            <input class="form-control" type="password" name="current_password" autocomplete="current-password" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('storefront.account.new_password') }}</label>
                            <input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">{{ __('storefront.account.confirm_new_password') }}</label>
                            <input class="form-control" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                        </div>
                        <div class="alert alert-danger" data-password-error hidden></div>
                        <div class="alert alert-success" data-password-ok hidden>{{ __('storefront.account.password_changed') }}</div>
                        <button class="btn btn-dark" type="submit">{{ __('storefront.account.update_password') }}</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
