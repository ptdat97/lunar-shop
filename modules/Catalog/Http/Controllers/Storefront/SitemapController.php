<?php

namespace Modules\Catalog\Http\Controllers\Storefront;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Catalog\Services\SitemapService;

/**
 * GET /sitemap.xml — storefront sitemap. Data assembled by SitemapService; this
 * controller only serialises it to the sitemaps.org XML schema and caches the
 * response briefly so crawlers don't hammer the catalog queries.
 */
class SitemapController extends Controller
{
    public function __construct(
        protected SitemapService $sitemap,
    ) {}

    public function __invoke(): Response
    {
        $xml = cache()->remember('sitemap.xml', now()->addHour(), function (): string {
            $out = '<?xml version="1.0" encoding="UTF-8"?>'
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            foreach ($this->sitemap->urls() as $entry) {
                $out .= '<url>';
                $out .= '<loc>' . htmlspecialchars($entry['loc'], ENT_XML1) . '</loc>';
                if (! empty($entry['lastmod'])) {
                    $out .= '<lastmod>' . $entry['lastmod'] . '</lastmod>';
                }
                $out .= '<changefreq>' . $entry['changefreq'] . '</changefreq>';
                $out .= '<priority>' . $entry['priority'] . '</priority>';
                $out .= '</url>';
            }

            return $out . '</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
