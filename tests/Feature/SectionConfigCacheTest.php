<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Content\Models\PageSection;
use Modules\Content\Services\SectionRenderer;
use Tests\TestCase;

class SectionConfigCacheTest extends TestCase
{
    protected function makeSection(array $overrides = []): PageSection
    {
        return PageSection::create(array_merge([
            'page_handle' => 'home',
            'type' => 'iconbox',
            'sort' => 1,
            'enabled' => true,
            'settings' => ['items' => [['heading' => 'Free ship']]],
        ], $overrides));
    }

    public function test_render_reads_section_config_from_cache(): void
    {
        $this->makeSection();

        app(SectionRenderer::class)->render('home');
        $this->assertTrue(Cache::has(PageSection::cacheKey('home')));

        // Second render must not query page_sections (config comes from cache).
        $sectionQueries = 0;
        DB::listen(function ($q) use (&$sectionQueries): void {
            if (str_contains($q->sql, 'page_sections')) {
                $sectionQueries++;
            }
        });

        app(SectionRenderer::class)->render('home');
        $this->assertSame(0, $sectionQueries);
    }

    public function test_saving_a_section_busts_the_config_cache(): void
    {
        $section = $this->makeSection();
        app(SectionRenderer::class)->render('home');
        $this->assertTrue(Cache::has(PageSection::cacheKey('home')));

        $section->update(['sort' => 5]);

        $this->assertFalse(Cache::has(PageSection::cacheKey('home')));
    }

    public function test_moving_a_section_busts_both_pages_caches(): void
    {
        $section = $this->makeSection();
        app(SectionRenderer::class)->render('home');
        app(SectionRenderer::class)->render('sale');

        $section->update(['page_handle' => 'sale']);

        $this->assertFalse(Cache::has(PageSection::cacheKey('home')));
        $this->assertFalse(Cache::has(PageSection::cacheKey('sale')));
    }

    public function test_deleting_a_section_busts_the_config_cache(): void
    {
        $section = $this->makeSection();
        app(SectionRenderer::class)->render('home');

        $section->delete();

        $this->assertFalse(Cache::has(PageSection::cacheKey('home')));
    }

    public function test_cached_config_rehydrates_with_casts(): void
    {
        $this->makeSection();

        // Prime, then re-render from cache and check the settings cast survives
        // the raw-attribute round trip (JSON string → array on access).
        app(SectionRenderer::class)->render('home');
        $html = (string) app(SectionRenderer::class)->render('home');

        $this->assertStringContainsString('Free ship', $html);
    }
}
