<?php

namespace Tests;

use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The api/v1 rate limiter (ThrottleApiV1 → ThrottleRequests) is
        // cache-backed. In a single PHPUnit process the array store persists
        // across tests, so limiter hits accumulate and later tests spuriously
        // 429. RefreshDatabase resets the DB but not the cache — and the
        // RateLimiter holds its OWN cache-repository instance (resolved at
        // construction), separate from the Cache facade's default, so
        // Cache::flush() alone does not clear it. Flush the limiter's own store
        // per test so each starts clean. (ApiRateLimitTest exhausts the bucket
        // within a single test, so it is unaffected.)
        Cache::flush();
        $this->clearRateLimiter();

        // Tests must NEVER touch the real public/media store: the lunar_testing
        // DB restarts media ids at 1, so media writes/deletes would land in —
        // and clobber — the dev store's per-id folders (this already destroyed
        // seeded demo originals once). fake() reroutes the disk to a throwaway
        // root wiped per test; the '/media' public URL is kept so conversion-URL
        // assertions and the media.conversion route still see real paths.
        Storage::fake('media', ['url' => '/media']);
    }

    /**
     * Flush the RateLimiter's own cache-repository instance. It resolves and
     * caches a store at construction, so the only reliable reset is to reach
     * that instance and flush it directly.
     */
    private function clearRateLimiter(): void
    {
        $limiter = $this->app->make(RateLimiter::class);

        $property = new \ReflectionProperty($limiter, 'cache');
        $property->setAccessible(true);
        $property->getValue($limiter)->flush();
    }
}
