<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response security headers, driven by `config/security.php`.
 *
 * The shop shipped with none of these. The ones here are the set that costs
 * nothing to be right about (nosniff, frame options, referrer policy,
 * permissions policy) plus two that need care and get it: HSTS, which is
 * production-and-https only because sending it over http locks a browser onto
 * https for the whole domain with no server-side undo; and CSP, which defaults
 * to report-only because a wrong policy kills the site's JavaScript in the
 * customer's browser and leaves no server-side trace at all.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        foreach ((array) config('security.headers', []) as $header => $value) {
            $response->headers->set($header, $value);
        }

        $this->applyHsts($request, $response);
        $this->applyCsp($request, $response);

        return $response;
    }

    private function applyHsts(Request $request, Response $response): void
    {
        if (! config('security.hsts.enabled', true)) {
            return;
        }

        // Both conditions matter. Not production → a developer's browser gets
        // pinned to https on localhost. Not secure → the header is meaningless
        // on this request anyway and signals a misconfigured proxy.
        if (! app()->environment('production') || ! $request->secure()) {
            return;
        }

        $value = 'max-age='.(int) config('security.hsts.max_age', 31536000);

        if (config('security.hsts.include_subdomains', true)) {
            $value .= '; includeSubDomains';
        }

        if (config('security.hsts.preload', false)) {
            $value .= '; preload';
        }

        $response->headers->set('Strict-Transport-Security', $value);
    }

    private function applyCsp(Request $request, Response $response): void
    {
        $mode = (string) config('security.csp.mode', 'report');

        if ($mode === 'off') {
            return;
        }

        $policy = $this->policy();

        if ($policy === '') {
            return;
        }

        // The panel is Lunar's own Inertia/Vue bundle. We do not control what it
        // inlines or evaluates, so it never gets an enforcing policy — a CSP
        // that breaks the admin locks staff out of their own shop. Report-only
        // still surfaces violations, which is what would tell us it is safe to
        // enforce there one day.
        $enforce = $mode === 'enforce' && ! $this->isPanel($request);

        $response->headers->set(
            $enforce ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
            $policy,
        );
    }

    private function policy(): string
    {
        $directives = (array) config('security.csp.directives', []);

        $parts = [];

        foreach ($directives as $directive => $sources) {
            $sources = array_filter((array) $sources);

            $parts[] = $sources === []
                ? $directive
                : $directive.' '.implode(' ', $sources);
        }

        if ($reportUri = config('security.csp.report_uri')) {
            $parts[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $parts);
    }

    private function isPanel(Request $request): bool
    {
        $prefix = trim((string) config('lunar.panel.path', 'panel'), '/');

        return $prefix !== '' && $request->is($prefix, $prefix.'/*');
    }
}
