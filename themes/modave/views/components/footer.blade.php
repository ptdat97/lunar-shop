        <!-- Footer -->
        <footer id="footer" class="footer">
            <div class="footer-wrap">
                <div class="footer-body">
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="footer-infor">
                                    <div class="footer-logo">
                                        <a href="{{ route('storefront.home') }}">
                                            <img src="{{ $theme->image('general.logo_footer') }}" alt="{{ config('app.name') }}">
                                        </a>
                                    </div>
                                    <div class="footer-address">
                                        <p>{{ $theme->get('contact.address') }}</p>
                                    </div>
                                    <ul class="footer-info">
                                        @if ($email = $theme->get('contact.email'))
                                            <li>
                                                <i class="icon-mail"></i>
                                                <p><a href="mailto:{{ $email }}">{{ $email }}</a></p>
                                            </li>
                                        @endif
                                        @if ($phone = $theme->get('contact.phone'))
                                            <li>
                                                <i class="icon-phone"></i>
                                                <p><a href="tel:{{ $phone }}">{{ $phone }}</a></p>
                                            </li>
                                        @endif
                                    </ul>
                                    <ul class="tf-social-icon">
                                        @foreach ($theme->get('social', []) as $social)
                                            <li><a href="{{ $social['url'] ?? '#' }}" target="_blank"><i class="icon {{ $social['icon'] ?? '' }}"></i></a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="footer-menu">
                                    {!! app(\Modules\Menu\Services\MenuRenderer::class)->render('footer') !!}
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="footer-col-block">
                                    <div class="footer-heading text-button footer-heading-mobile">
                                        Newletter
                                    </div>
                                    <div class="tf-collapse-content">
                                        <div class="footer-newsletter">
                                            <p class="text-caption-1">{{ $theme->get('newsletter.heading') }}</p>
                                            <form id="subscribe-form" action="#" class="form-newsletter subscribe-form" method="post" accept-charset="utf-8" data-mailchimp="true">
                                                <div id="subscribe-content" class="subscribe-content">
                                                    <fieldset class="email">
                                                        <input id="subscribe-email" type="email" name="email-form" class="subscribe-email" placeholder="Enter your e-mail" tabindex="0" aria-required="true">
                                                    </fieldset>
                                                    <div class="button-submit">
                                                        <button id="subscribe-button" class="subscribe-button" type="button"><i class="icon icon-arrowUpRight"></i></button>
                                                    </div>
                                                </div>
                                                <div id="subscribe-msg" class="subscribe-msg"></div>
                                            </form>
                                            <div class="tf-cart-checkbox">
                                                <div class="tf-checkbox-wrapp">
                                                    <input class="" type="checkbox" id="footer-Form_agree" name="agree_checkbox">
                                                    <div>
                                                        <i class="icon-check"></i>
                                                    </div>
                                                </div>
                                                <label class="text-caption-1" for="footer-Form_agree">
                                                    By clicking subcribe, you agree to the <a class="fw-6 link" href="term-of-use.html">Terms of Service</a> and <a class="fw-6 link" href="#">Privacy Policy</a>.
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="footer-bottom">
                    <div class="container">
                        <div class="row">
                            <div class="col-12">
                                <div class="footer-bottom-wrap">
                                    <div class="left">
                                        <p class="text-caption-1">{{ $theme->get('copyright') }}</p>
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
                                    <div class="tf-payment">
                                        <p class="text-caption-1">Payment:</p>
                                        <ul>
                                            @foreach ($theme->get('payment', []) as $pay)
                                                <li><img src="{{ $theme->url($pay) }}" alt="payment"></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        <!-- /Footer -->
