<?php

namespace Tests\Feature;

use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\Api\V1\HealthController;
use RuntimeException;
use Tests\TestCase;

/**
 * The health probe must actually verify its dependencies.
 *
 * It previously returned a hardcoded `{"status":"ok"}` with zero DB/cache/queue
 * calls, so a load balancer would keep routing traffic to a node with a dead
 * database. A probe that cannot fail is worse than no probe.
 *
 * Failures are injected at the driver level (a broken cache store, a bogus DB /
 * queue connection) rather than by mocking the facades: `Cache::shouldReceive()`
 * replaces the whole CacheManager, which the throttle middleware and the theme
 * settings store also depend on.
 */
class HealthCheckTest extends TestCase
{
    /** Point the default cache store at a driver that throws on write. */
    private function breakCache(): void
    {
        Cache::extend('broken', fn () => Cache::repository(new class extends ArrayStore
        {
            public function put($key, $value, $seconds): bool
            {
                throw new RuntimeException('redis unreachable');
            }
        }));

        config(['cache.stores.broken' => ['driver' => 'broken'], 'cache.default' => 'broken']);
    }

    /**
     * Probe the health controller with a database that refuses connections,
     * then restore the real manager.
     *
     * The binding is swapped rather than `database.default` repointed: the test
     * case's RefreshDatabase teardown resolves the default connection, so a
     * config change would blow up after the assertions instead of during them.
     *
     * @return array<string, mixed> the `data` payload
     */
    private function healthWithBrokenDatabase(string $message): array
    {
        $real = app('db');

        app()->instance('db', new class($message)
        {
            public function __construct(private string $message) {}

            public function connection($name = null): never
            {
                throw new \PDOException($this->message);
            }
        });

        // The DB facade caches its resolved instance, so the swap above is
        // invisible to `DB::connection()` until the cache is cleared.
        DB::clearResolvedInstances();

        try {
            $response = app(HealthController::class)();
            $this->lastStatus = $response->status();
            $this->lastBody = $response->content();

            return $response->getData(true)['data'];
        } finally {
            app()->instance('db', $real);
            DB::clearResolvedInstances();
        }
    }

    private int $lastStatus = 0;

    private string $lastBody = '';

    /** A cache that accepts writes but never returns them (eviction / read-only). */
    private function breakCacheSilently(): void
    {
        Cache::extend('amnesiac', fn () => Cache::repository(new class extends ArrayStore
        {
            public function get($key): mixed
            {
                return null;
            }
        }));

        config(['cache.stores.amnesiac' => ['driver' => 'amnesiac'], 'cache.default' => 'amnesiac']);
    }

    public function test_the_probe_carries_no_cache_backed_middleware(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/health');

        // Both the rate limiter and the storefront-locale middleware read the
        // cache. If either wrapped this route, a Redis outage would 500 the
        // probe instead of letting it report `cache: not ok`.
        $this->assertSame([], $route->gatherMiddleware());
    }

    public function test_the_probe_is_exempt_from_rate_limiting(): void
    {
        // Well past the 120/min `api` limiter — a probe must never be throttled.
        for ($i = 0; $i < 130; $i++) {
            $this->getJson('/api/v1/health')->assertOk();
        }
    }

    public function test_reports_ok_when_every_dependency_responds(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.database.ok', true)
            ->assertJsonPath('data.checks.cache.ok', true)
            ->assertJsonPath('data.checks.queue.ok', true);
    }

    public function test_returns_503_when_the_database_is_down(): void
    {
        $data = $this->healthWithBrokenDatabase('connection refused');

        $this->assertSame(503, $this->lastStatus);
        $this->assertSame('degraded', $data['status']);
        $this->assertFalse($data['checks']['database']['ok']);
        $this->assertSame('PDOException', $data['checks']['database']['error']);
    }

    public function test_returns_503_when_the_cache_is_down(): void
    {
        $this->breakCache();

        // Invoke the controller directly: a dead cache also takes down the
        // locale/throttle middleware that runs ahead of it, which would mask
        // the very behaviour under test.
        $response = app(HealthController::class)();

        $this->assertSame(503, $response->status());
        $this->assertFalse($response->getData(true)['data']['checks']['cache']['ok']);
    }

    public function test_a_broken_cache_that_does_not_throw_is_still_unhealthy(): void
    {
        $this->breakCacheSilently();

        $response = app(HealthController::class)();

        $this->assertSame(503, $response->status());
        $this->assertFalse($response->getData(true)['data']['checks']['cache']['ok']);
    }

    public function test_returns_503_when_the_queue_is_down(): void
    {
        config([
            'queue.connections.dead' => ['driver' => 'redis', 'connection' => 'nonexistent', 'queue' => 'default'],
            'queue.default' => 'dead',
        ]);

        $response = app(HealthController::class)();

        $this->assertSame(503, $response->status());
        $this->assertFalse($response->getData(true)['data']['checks']['queue']['ok']);
    }

    public function test_failure_detail_does_not_leak_credentials(): void
    {
        // A real PDO failure message carries the DSN, user and password.
        $data = $this->healthWithBrokenDatabase(
            'SQLSTATE[HY000] [1045] Access denied for user=root password=hunter2',
        );

        // Only the exception class name is exposed.
        $this->assertStringNotContainsString('hunter2', $this->lastBody);
        $this->assertStringNotContainsString('SQLSTATE', $this->lastBody);
        $this->assertSame('PDOException', $data['checks']['database']['error']);
        $this->assertArrayNotHasKey('message', $data['checks']['database']);
    }

    public function test_one_broken_dependency_does_not_mask_the_others(): void
    {
        $this->breakCache();

        $checks = app(HealthController::class)()->getData(true)['data']['checks'];

        // Database and queue are still probed and reported healthy.
        $this->assertFalse($checks['cache']['ok']);
        $this->assertTrue($checks['database']['ok']);
        $this->assertTrue($checks['queue']['ok']);
    }
}
