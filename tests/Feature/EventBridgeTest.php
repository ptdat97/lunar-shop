<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Event;
use Modules\Platform\Events\EventBridge;
use Modules\Platform\Facades\Hook;
use Tests\TestCase;

/**
 * P3 — the Platform EventBridge: the generic mechanism that re-broadcasts a
 * framework/domain event onto the shared hook plane. Modules declare the
 * mapping; the bridge owns the listen→doAction plumbing.
 */
class EventBridgeTest extends TestCase
{
    public function test_it_fires_a_hook_with_args_derived_from_the_event(): void
    {
        $captured = null;
        Hook::addAction('demo.bridged', function ($value) use (&$captured) {
            $captured = $value;
        });

        app(EventBridge::class)->bridge(
            BridgeFixtureEvent::class,
            'demo.bridged',
            fn (BridgeFixtureEvent $e) => [$e->payload],
        );

        Event::dispatch(new BridgeFixtureEvent('hello'));

        $this->assertSame('hello', $captured);
    }

    public function test_it_is_a_shared_singleton(): void
    {
        $this->assertSame(app(EventBridge::class), app(EventBridge::class));
    }
}

class BridgeFixtureEvent
{
    public function __construct(public string $payload) {}
}
