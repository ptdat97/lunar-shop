<?php

namespace Modules\Platform\Events;

use Illuminate\Support\Facades\Event;
use Modules\Platform\Services\HookManager;

/**
 * Re-broadcasts framework / library / domain events onto the shared hook plane
 * (Hooks::*) so modules and plugins react via one seam without coupling to the
 * producing event class.
 *
 * Core owns the MECHANISM (the Event::listen → doAction plumbing); each module
 * owns the DECLARATION of which of its events maps to which hook (that mapping
 * is business knowledge, so it's stated in the module's provider via bridge()).
 *
 * Resolved as a singleton so the wiring is registered once.
 */
class EventBridge
{
    public function __construct(
        protected HookManager $hooks,
    ) {}

    /**
     * Listen for $eventClass and fire $hook with args derived from the event.
     *
     * @param  class-string  $eventClass  the framework/library/domain event
     * @param  string  $hook  a Hooks::* action name
     * @param  callable(object): array<int, mixed>  $argsUsing  maps the event to doAction args
     */
    public function bridge(string $eventClass, string $hook, callable $argsUsing): void
    {
        Event::listen($eventClass, function (object $event) use ($hook, $argsUsing): void {
            $this->hooks->doAction($hook, $argsUsing($event));
        });
    }
}
