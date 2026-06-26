<?php

namespace Tests\Feature;

use Modules\Platform\Support\Decorator;
use Tests\TestCase;

/**
 * P4 — the Platform Decorator helper: standardised, lazy, stackable wrapping of
 * a container binding (the seam the D-series will use to layer behaviour onto
 * core services without editing them). Mechanism only; nothing decorated yet.
 */
class DecoratorTest extends TestCase
{
    public function test_it_wraps_a_binding_with_a_decorator(): void
    {
        $this->app->singleton(GreeterContract::class, fn () => new BaseGreeter);

        Decorator::wrap(GreeterContract::class, ShoutDecorator::class, $this->app);

        $this->assertSame('HI', $this->app->make(GreeterContract::class)->greet());
    }

    public function test_decorators_stack_last_registered_outermost(): void
    {
        $this->app->singleton(GreeterContract::class, fn () => new BaseGreeter);

        Decorator::wrap(GreeterContract::class, ShoutDecorator::class, $this->app);   // -> HI
        Decorator::wrap(GreeterContract::class, BangDecorator::class, $this->app);    // -> HI!

        // Bang wraps Shout wraps Base: strtoupper('hi') then append '!'.
        $this->assertSame('HI!', $this->app->make(GreeterContract::class)->greet());
    }

    public function test_wrap_using_supports_a_closure_decorator(): void
    {
        $this->app->singleton(GreeterContract::class, fn () => new BaseGreeter);

        Decorator::wrapUsing(
            GreeterContract::class,
            fn ($inner) => new BangDecorator($inner),
            $this->app,
        );

        $this->assertSame('hi!', $this->app->make(GreeterContract::class)->greet());
    }

    public function test_decorator_resolves_its_extra_dependencies_from_the_container(): void
    {
        $this->app->singleton(GreeterContract::class, fn () => new BaseGreeter);

        Decorator::wrap(GreeterContract::class, SuffixDecorator::class, $this->app);

        // SuffixDecorator pulls Suffix (auto-resolved) and appends it.
        $this->assertSame('hi'.app(Suffix::class)->value, $this->app->make(GreeterContract::class)->greet());
    }
}

interface GreeterContract
{
    public function greet(): string;
}

class BaseGreeter implements GreeterContract
{
    public function greet(): string
    {
        return 'hi';
    }
}

class ShoutDecorator implements GreeterContract
{
    public function __construct(protected GreeterContract $inner) {}

    public function greet(): string
    {
        return strtoupper($this->inner->greet());
    }
}

class BangDecorator implements GreeterContract
{
    public function __construct(protected GreeterContract $inner) {}

    public function greet(): string
    {
        return $this->inner->greet().'!';
    }
}

class Suffix
{
    public string $value = '?';
}

class SuffixDecorator implements GreeterContract
{
    public function __construct(protected GreeterContract $inner, protected Suffix $suffix) {}

    public function greet(): string
    {
        return $this->inner->greet().$this->suffix->value;
    }
}
