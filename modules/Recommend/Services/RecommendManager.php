<?php

namespace Modules\Recommend\Services;

use Illuminate\Contracts\Foundation\Application;
use Modules\Recommend\Contracts\RecommendationStrategy;

/**
 * Registry of recommendation strategies. Seeds from config('recommend.strategies')
 * and lets a PLUGIN add more at runtime via extend() — without editing config.
 *
 * Strategies are ordered by priority (lower runs first; config entries keep
 * their declared order via an auto-incrementing default). RecommendationService
 * reads the ordered list lazily per call, so plugins registering at boot are
 * picked up.
 */
class RecommendManager
{
    /** @var array<int, array{priority:int, seq:int, factory:\Closure|string}> */
    protected array $registered = [];

    protected int $seq = 0;

    protected bool $seeded = false;

    public function __construct(
        protected Application $app,
    ) {}

    /**
     * Register a strategy. $strategy is a class-string or a closure returning a
     * RecommendationStrategy. Lower priority runs first (config order preserved).
     *
     * @param  \Closure(Application): RecommendationStrategy|class-string  $strategy
     */
    public function extend(\Closure|string $strategy, int $priority = 100): void
    {
        $this->registered[] = ['priority' => $priority, 'seq' => $this->seq++, 'factory' => $strategy];
    }

    /**
     * The resolved strategies in priority order. Seeds config strategies on first
     * use (priority 0..n in declared order) so they stay ahead of plugin defaults.
     *
     * @return list<RecommendationStrategy>
     */
    public function strategies(): array
    {
        $this->seedFromConfig();

        $sorted = $this->registered;
        usort($sorted, fn ($a, $b) => [$a['priority'], $a['seq']] <=> [$b['priority'], $b['seq']]);

        return array_map(function (array $entry) {
            $factory = $entry['factory'];

            return $factory instanceof \Closure ? $factory($this->app) : $this->app->make($factory);
        }, $sorted);
    }

    /** Seed config-declared strategies once (idempotent). */
    protected function seedFromConfig(): void
    {
        if ($this->seeded) {
            return;
        }

        $this->seeded = true;

        foreach (array_values((array) config('recommend.strategies', [])) as $i => $class) {
            $this->registered[] = ['priority' => $i, 'seq' => $this->seq++, 'factory' => $class];
        }
    }
}
