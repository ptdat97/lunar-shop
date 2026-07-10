<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Modules\Checkout\Services\TokenAwareCartSession;
use Modules\Core\Http\Middleware\VerifyCsrfTokenUnlessStateless;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * CSRF exemption for stateless clients.
 *
 * Cart + checkout inherit CSRF from the `web` group (Lunar's cart needs a
 * session), which 419'd every mobile/POS write. The middleware exempts requests
 * that carry no ambient cookie credential, and only those.
 *
 * Exercised directly rather than over HTTP: Laravel's CSRF middleware
 * short-circuits on `runningUnitTests()`, so an HTTP test can never reach this
 * decision.
 */
class StatelessCsrfTest extends TestCase
{
    use CreatesStorefrontData;

    /** Ask the middleware whether it would skip validation for this request. */
    private function isExempt(Request $request): bool
    {
        $middleware = app(VerifyCsrfTokenUnlessStateless::class);

        $method = new \ReflectionMethod($middleware, 'inExceptArray');
        $method->setAccessible(true);

        return $method->invoke($middleware, $request);
    }

    private function postRequest(array $headers = []): Request
    {
        $request = Request::create('/api/v1/cart', 'POST');

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    public function test_the_cookie_storefront_is_not_exempt(): void
    {
        // No headless header → full CSRF validation, exactly as before.
        $this->assertFalse($this->isExempt($this->postRequest()));
    }

    public function test_a_bearer_token_request_is_exempt(): void
    {
        $this->assertTrue($this->isExempt($this->postRequest(['Authorization' => 'Bearer abc123'])));
    }

    public function test_a_cart_token_request_is_exempt(): void
    {
        $this->assertTrue($this->isExempt($this->postRequest([TokenAwareCartSession::HEADER => 'handle'])));
    }

    public function test_a_first_call_from_the_app_is_exempt(): void
    {
        $this->assertTrue($this->isExempt($this->postRequest([TokenAwareCartSession::CLIENT_HEADER => 'app'])));
    }

    public function test_an_empty_header_does_not_grant_exemption(): void
    {
        // A blank header must not be a CSRF bypass.
        $this->assertFalse($this->isExempt($this->postRequest([TokenAwareCartSession::HEADER => ''])));
        $this->assertFalse($this->isExempt($this->postRequest([TokenAwareCartSession::CLIENT_HEADER => ''])));
    }

    public function test_a_cookie_authenticated_visitor_is_never_exempt(): void
    {
        $user = $this->createUser();

        $request = $this->postRequest([TokenAwareCartSession::CLIENT_HEADER => 'app']);
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn () => $user);

        // Otherwise a cross-site page could opt out of CSRF for a logged-in
        // browser just by adding the header.
        $this->assertFalse($this->isExempt($request));
    }

    public function test_configured_uri_exceptions_still_apply(): void
    {
        // The MoMo IPN callback authenticates by HMAC signature, not CSRF.
        $this->assertTrue($this->isExempt(Request::create('/payment/momo/ipn', 'POST')));
    }
}
