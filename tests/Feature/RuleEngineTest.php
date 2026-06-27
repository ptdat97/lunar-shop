<?php

namespace Tests\Feature;

use Modules\Platform\Rule\Operator;
use Modules\Platform\Rule\Rule;
use Modules\Platform\Rule\RuleRegistry;
use Modules\Platform\Rule\RuleSet;
use Tests\TestCase;

/**
 * W.1 — the Platform Rule engine: pure, side-effect-free condition evaluation.
 * Modules/plugins register fields (resolvers from context); Core only knows how
 * to resolve a key + compare with an operator + combine with all/any. Rules are
 * serialisable (JSON) for storage in workflows/promotions.
 */
class RuleEngineTest extends TestCase
{
    protected function registry(): RuleRegistry
    {
        $registry = new RuleRegistry;
        $registry->field('cart.subtotal', fn (array $ctx) => $ctx['subtotal'] ?? 0);
        $registry->field('customer.group', fn (array $ctx) => $ctx['group'] ?? null);
        $registry->field('cart.skus', fn (array $ctx) => $ctx['skus'] ?? []);

        return $registry;
    }

    public function test_operators_compare_correctly(): void
    {
        $this->assertTrue(Operator::Gte->compare(5000, 5000));
        $this->assertFalse(Operator::Gt->compare(5000, 5000));
        $this->assertTrue(Operator::In->compare('gold', ['silver', 'gold']));
        $this->assertTrue(Operator::Contains->compare(['A', 'B'], 'B'));
        $this->assertFalse(Operator::Eq->compare('a', 'b'));
    }

    public function test_a_single_rule_resolves_a_field_and_compares(): void
    {
        $rule = new Rule('cart.subtotal', Operator::Gte, 5000);

        $this->assertTrue($rule->passes(['subtotal' => 6000], $this->registry()));
        $this->assertFalse($rule->passes(['subtotal' => 4000], $this->registry()));
    }

    public function test_unknown_field_fails_closed(): void
    {
        $rule = new Rule('nope.field', Operator::Gte, 1);

        $this->assertFalse($rule->passes([], $this->registry()));
    }

    public function test_rule_set_all_requires_every_rule(): void
    {
        $set = new RuleSet([
            new Rule('cart.subtotal', Operator::Gte, 5000),
            new Rule('customer.group', Operator::Eq, 'gold'),
        ], RuleSet::ALL);

        $this->assertTrue($set->passes(['subtotal' => 6000, 'group' => 'gold'], $this->registry()));
        $this->assertFalse($set->passes(['subtotal' => 6000, 'group' => 'silver'], $this->registry()));
    }

    public function test_rule_set_any_requires_one_rule(): void
    {
        $set = new RuleSet([
            new Rule('cart.subtotal', Operator::Gte, 100000),
            new Rule('customer.group', Operator::Eq, 'gold'),
        ], RuleSet::ANY);

        $this->assertTrue($set->passes(['subtotal' => 100, 'group' => 'gold'], $this->registry()));
        $this->assertFalse($set->passes(['subtotal' => 100, 'group' => 'silver'], $this->registry()));
    }

    public function test_empty_rule_set_passes(): void
    {
        $this->assertTrue((new RuleSet)->passes([], $this->registry()));
    }

    public function test_rule_set_round_trips_through_json(): void
    {
        $definition = [
            'match' => 'all',
            'rules' => [
                ['field' => 'cart.subtotal', 'operator' => '>=', 'value' => 5000],
                ['field' => 'cart.skus', 'operator' => 'contains', 'value' => 'SKU-1'],
            ],
        ];

        $set = RuleSet::fromArray(json_decode(json_encode($definition), true));

        $this->assertSame($definition, $set->toArray());
        $this->assertTrue($set->passes(['subtotal' => 5000, 'skus' => ['SKU-1', 'SKU-2']], $this->registry()));
        $this->assertFalse($set->passes(['subtotal' => 5000, 'skus' => ['SKU-9']], $this->registry()));
    }

    public function test_registry_is_a_shared_singleton(): void
    {
        $this->assertSame(app(RuleRegistry::class), app(RuleRegistry::class));
    }
}
