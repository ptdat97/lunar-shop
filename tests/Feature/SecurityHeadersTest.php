<?php

namespace Tests\Feature;

use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The shop shipped with no security response headers at all.
 *
 * Two of these need judgement rather than a checklist, and that is what most of
 * this file pins down: HSTS must never leave production (sending it over http
 * pins a browser to https for the whole domain with no server-side undo), and
 * CSP must never enforce on the panel (it is Lunar's bundle, and a policy that
 * breaks the admin locks staff out of their own shop).
 */
class SecurityHeadersTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_the_safe_headers_are_always_sent(): void
    {
        $this->seedBaseData();

        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }

    /** The API is a separate middleware group — it must not be left bare. */
    public function test_the_json_api_gets_them_too(): void
    {
        $this->seedBaseData();

        $this->getJson('/api/v1/products')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_csp_is_report_only_by_default(): void
    {
        $this->seedBaseData();

        $response = $this->get('/');

        $this->assertNull(
            $response->headers->get('Content-Security-Policy'),
            'CSP mặc định phải là report-only — bật enforce ngay là cách nhanh nhất làm chết JS trên production.',
        );
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_enforcing_mode_sends_a_blocking_policy(): void
    {
        $this->seedBaseData();
        config(['security.csp.mode' => 'enforce']);

        $policy = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($policy);

        // The whole point of the policy. An exception added here would quietly
        // give back most of the XSS protection it exists for.
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
    }

    public function test_the_panel_is_never_enforced(): void
    {
        config(['security.csp.mode' => 'enforce']);

        $response = $this->get('/panel');

        $this->assertNull(
            $response->headers->get('Content-Security-Policy'),
            'Panel là bundle của Lunar — enforce ở đó là khoá nhân viên khỏi chính cửa hàng của họ.',
        );
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_csp_can_be_turned_off_entirely(): void
    {
        $this->seedBaseData();
        config(['security.csp.mode' => 'off']);

        $response = $this->get('/');

        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    /**
     * The policy has to permit what the pages actually load.
     *
     * Asserting the header's text proves the policy is what we wrote; it does
     * not prove the shop still works under it. This walks the rendered HTML of
     * the real pages, collects every external script, stylesheet and font
     * origin, and checks the policy allows each one — so the day someone drops
     * a CDN <script> into a Blade file, this fails instead of the storefront
     * silently losing its JavaScript in customers' browsers.
     */
    public function test_the_policy_permits_every_origin_the_pages_load(): void
    {
        $this->seedBaseData();
        config(['security.csp.mode' => 'enforce']);

        $allowed = collect(config('security.csp.directives'))
            ->flatten()
            ->filter(fn ($source) => str_starts_with((string) $source, 'https://'))
            ->map(fn ($source) => parse_url($source, PHP_URL_HOST))
            ->push(parse_url(config('app.url'), PHP_URL_HOST))
            ->filter()
            ->unique()
            ->all();

        foreach (['/', '/search?q='] as $uri) {
            $html = $this->get($uri)->getContent();

            preg_match_all('/(?:src|href)="(https?:\/\/[^"]+)"/i', (string) $html, $matches);

            foreach (array_unique($matches[1] ?? []) as $url) {
                $host = parse_url($url, PHP_URL_HOST);

                $this->assertContains(
                    $host,
                    $allowed,
                    "Trang [{$uri}] tải {$url} nhưng CSP không cho phép host [{$host}] — trình duyệt sẽ chặn.",
                );
            }
        }
    }

    /** HSTS over http would pin a browser to https with no way back. */
    public function test_hsts_is_not_sent_outside_production(): void
    {
        $this->seedBaseData();

        $this->assertNull($this->get('/')->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_is_sent_on_secure_production_requests(): void
    {
        $this->seedBaseData();
        app()->detectEnvironment(fn () => 'production');

        $header = $this->get('https://localhost/')->headers->get('Strict-Transport-Security');

        $this->assertNotNull($header, 'Production trên https phải có HSTS.');
        $this->assertStringContainsString('max-age=', $header);
        $this->assertStringNotContainsString('preload', $header, 'preload phải tắt mặc định — nộp vào danh sách Chrome gần như một chiều.');
    }

    public function test_hsts_is_withheld_on_a_plain_http_production_request(): void
    {
        $this->seedBaseData();
        app()->detectEnvironment(fn () => 'production');

        $this->assertNull($this->get('http://localhost/')->headers->get('Strict-Transport-Security'));
    }
}
