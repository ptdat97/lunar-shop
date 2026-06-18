        <!-- Top Bar-->
        <div class="tf-topbar style-2 line-bt">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-xl-3 d-none d-xl-block">
                        <ul class="tf-social-icon style-fill">
                            @foreach ($theme->get('social', []) as $social)
                                <li><a href="{{ $social['url'] ?? '#' }}" target="_blank"><i class="icon {{ $social['icon'] ?? '' }}"></i></a></li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-xl-6 col-12 text-center">
                        <div dir="ltr" class="swiper tf-sw-top_bar" data-preview="1" data-space="0" data-loop="true" data-speed="1000" data-auto-play="true" data-delay="2000">
                            <div class="swiper-wrapper">
                                @foreach ($theme->get('topbar', []) as $slide)
                                    <div class="swiper-slide">
                                        <p class="top-bar-text text-line-clamp-1 text-btn-uppercase fw-semibold letter-1">{{ $slide['text'] ?? '' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 d-none d-xl-block">
                        <div class="tf-cur justify-content-end">
                            <div class="tf-currencies">
                                <select class="image-select center style-default type-currencies">
                                    <option selected data-thumbnail="/themes/modave/images/country/us.svg">USD</option>
                                    <option data-thumbnail="/themes/modave/images/country/vn.svg">VND</option>
                                </select>
                            </div>
                            <div class="tf-languages">
                                <select class="image-select center style-default type-languages">
                                    <option>English</option>
                                    <option>Vietnam</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Top Bar -->
        <!-- Header -->
        <header id="header" class="header-default">
            <div class="container">
                <div class="row wrapper-header align-items-center">
                    <div class="col-md-4 col-3 d-xl-none">
                        <a href="#mobileMenu" class="mobile-menu" data-bs-toggle="offcanvas" aria-controls="mobileMenu">
                            <i class="icon icon-categories"></i>
                        </a>
                    </div>
                    <div class="col-xl-3 col-md-4 col-6">
                        <a href="{{ route('storefront.home') }}" class="logo-header">
                            <img src="{{ $theme->image('general.logo') }}" alt="{{ config('app.name') }}" class="logo">
                        </a>
                    </div>
                    <div class="col-xl-6 d-none d-xl-block">
                        <nav class="box-navigation text-center">
                            <ul class="box-nav-ul d-flex align-items-center justify-content-center">
                                {!! app(\Modules\Menu\Services\MenuRenderer::class)->render('header') !!}
                            </ul>
                        </nav>
                    </div>
                    <div class="col-xl-3 col-md-4 col-3">
                        <ul class="nav-icon d-flex justify-content-end align-items-center">
                            <li class="nav-search"><a href="#search" data-bs-toggle="modal" class="nav-icon-item">
                                <svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11 19C15.4183 19 19 15.4183 19 11C19 6.58172 15.4183 3 11 3C6.58172 3 3 6.58172 3 11C3 15.4183 6.58172 19 11 19Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M21.35 21.0004L17 16.6504" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>    
                            </a></li>
                            <li class="nav-account">
                                <a href="#" class="nav-icon-item">
                                    <svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </a>
                                <div class="dropdown-account dropdown-login">
                                    @auth
                                        <div class="sub-top">
                                            <p class="text-center mb_8">Hi, {{ auth()->user()->name }}</p>
                                            <a href="{{ route('storefront.account') }}" class="tf-btn btn-reset">My Account</a>
                                            <p class="text-center mt_8"><a href="{{ route('storefront.wishlist') }}">Wishlist</a></p>
                                        </div>
                                    @else
                                        <div class="sub-top">
                                            <a href="{{ route('storefront.login') }}" class="tf-btn btn-reset">Login</a>
                                            <p class="text-center text-secondary-2">Don’t have an account? <a href="{{ route('storefront.register') }}">Register</a></p>
                                        </div>
                                    @endauth
                                    <div class="sub-bot">
                                        <span class="body-text-">Support</span>
                                    </div>
                                </div>
                            </li>
                            <li class="nav-wishlist"><a href="/wishlist" class="nav-icon-item">
                                <svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20.8401 4.60987C20.3294 4.09888 19.7229 3.69352 19.0555 3.41696C18.388 3.14039 17.6726 2.99805 16.9501 2.99805C16.2276 2.99805 15.5122 3.14039 14.8448 3.41696C14.1773 3.69352 13.5709 4.09888 13.0601 4.60987L12.0001 5.66987L10.9401 4.60987C9.90843 3.57818 8.50915 2.99858 7.05012 2.99858C5.59109 2.99858 4.19181 3.57818 3.16012 4.60987C2.12843 5.64156 1.54883 7.04084 1.54883 8.49987C1.54883 9.95891 2.12843 11.3582 3.16012 12.3899L4.22012 13.4499L12.0001 21.2299L19.7801 13.4499L20.8401 12.3899C21.3511 11.8791 21.7565 11.2727 22.033 10.6052C22.3096 9.93777 22.4519 9.22236 22.4519 8.49987C22.4519 7.77738 22.3096 7.06198 22.033 6.39452C21.7565 5.72706 21.3511 5.12063 20.8401 4.60987V4.60987Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="count-box" data-wishlist-count style="display:none"></span></a>
                            </li>
                            <li class="nav-cart"><a href="#shoppingCart" data-bs-toggle="modal" class="nav-icon-item">
                                <svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M16.5078 10.8734V6.36686C16.5078 5.17166 16.033 4.02541 15.1879 3.18028C14.3428 2.33514 13.1965 1.86035 12.0013 1.86035C10.8061 1.86035 9.65985 2.33514 8.81472 3.18028C7.96958 4.02541 7.49479 5.17166 7.49479 6.36686V10.8734M4.11491 8.62012H19.8877L21.0143 22.1396H2.98828L4.11491 8.62012Z" stroke="#181818" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span data-vue="cart-count"></span></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>
        <!-- /Header -->
