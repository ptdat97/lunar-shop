<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Health / readiness probe for the `/api/v1` layer.
 *
 * This used to return a hardcoded `"ok"`, so a load balancer kept sending
 * traffic to a node whose database was down — worse than having no probe at all.
 * It now exercises each dependency and answers 503 when any of them fails, so an
 * orchestrator can pull the node out of rotation.
 */
class HealthController extends Controller
{
    /**
     * GET /api/v1/health
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::connection()->getPdo()),
            'cache' => $this->check(function (): void {
                // Round-trip a value: a store that is reachable but broken
                // (evicting everything, read-only) still has to fail here.
                $key = 'health:'.bin2hex(random_bytes(4));
                Cache::put($key, 'ok', 5);

                if (Cache::pull($key) !== 'ok') {
                    throw new \RuntimeException('cache did not return the stored value');
                }
            }),
            'queue' => $this->check(fn () => Queue::connection()->size()),
        ];

        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'data' => [
                'status' => $healthy ? 'ok' : 'degraded',
                'api' => 'v1',
                'checks' => $checks,
            ],
        ], $healthy ? 200 : 503);
    }

    /**
     * Run one dependency probe. Never throws: an endpoint that 500s tells the
     * orchestrator nothing about *which* dependency broke, and each probe must
     * run even if an earlier one failed.
     *
     * @param  callable():mixed  $probe
     * @return array{ok: bool, error?: string}
     */
    protected function check(callable $probe): array
    {
        try {
            $probe();

            return ['ok' => true];
        } catch (Throwable $e) {
            report($e);

            // Class name only: exception messages carry connection strings and
            // credentials, and this endpoint is public.
            return ['ok' => false, 'error' => class_basename($e)];
        }
    }
}
