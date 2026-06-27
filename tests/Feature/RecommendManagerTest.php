<?php

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Lunar\Models\Product;
use Modules\Recommend\Contracts\RecommendationStrategy;
use Modules\Recommend\Services\RecommendManager;
use Tests\TestCase;

/**
 * E.3 — the RecommendManager strategy registry. Seeds from config in declared
 * order (priority 0..n, so curated stays first) and lets a plugin add a strategy
 * at runtime via extend(), ordered by priority — no config edit.
 */
class RecommendManagerTest extends TestCase
{
    public function test_it_seeds_config_strategies_in_declared_order(): void
    {
        config(['recommend.strategies' => [StratA::class, StratB::class]]);

        $strategies = (new RecommendManager($this->app))->strategies();

        $this->assertInstanceOf(StratA::class, $strategies[0]);
        $this->assertInstanceOf(StratB::class, $strategies[1]);
    }

    public function test_a_plugin_strategy_runs_after_config_by_default(): void
    {
        config(['recommend.strategies' => [StratA::class]]);

        $manager = new RecommendManager($this->app);
        $manager->extend(StratB::class);   // default priority 100 → after config (0..n)

        $strategies = $manager->strategies();

        $this->assertInstanceOf(StratA::class, $strategies[0]);
        $this->assertInstanceOf(StratB::class, $strategies[1]);
    }

    public function test_priority_orders_strategies(): void
    {
        config(['recommend.strategies' => [StratA::class]]);   // seeded at priority 0

        $manager = new RecommendManager($this->app);
        $manager->extend(StratB::class, priority: -10);        // jumps ahead of config

        $strategies = $manager->strategies();

        $this->assertInstanceOf(StratB::class, $strategies[0]);
        $this->assertInstanceOf(StratA::class, $strategies[1]);
    }

    public function test_extend_accepts_a_closure(): void
    {
        config(['recommend.strategies' => []]);

        $manager = new RecommendManager($this->app);
        $manager->extend(fn () => new StratB);

        $this->assertInstanceOf(StratB::class, $manager->strategies()[0]);
    }
}

class StratA implements RecommendationStrategy
{
    public function for(Product $product, int $limit = 8): Collection
    {
        return collect();
    }
}

class StratB implements RecommendationStrategy
{
    public function for(Product $product, int $limit = 8): Collection
    {
        return collect();
    }
}
