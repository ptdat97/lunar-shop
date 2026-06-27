<?php

namespace Modules\Platform\Rule;

/**
 * A single condition: resolve $field from the context, compare with $operator
 * against $value. Pure + serialisable (fromArray/toArray) so workflows/promotions
 * can persist rules as JSON.
 */
class Rule
{
    public function __construct(
        public readonly string $field,
        public readonly Operator $operator,
        public readonly mixed $value,
    ) {}

    /**
     * Build from a serialised definition: ['field' => 'cart.subtotal',
     * 'operator' => '>=', 'value' => 5000].
     *
     * @param  array{field:string, operator:string, value:mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            field: $data['field'],
            operator: Operator::from($data['operator']),
            value: $data['value'] ?? null,
        );
    }

    /** @return array{field:string, operator:string, value:mixed} */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator->value,
            'value' => $this->value,
        ];
    }

    /**
     * Evaluate against a context using the registry to resolve the field.
     *
     * @param  array<string, mixed>  $context
     */
    public function passes(array $context, RuleRegistry $registry): bool
    {
        return $this->operator->compare($registry->resolve($this->field, $context), $this->value);
    }
}
