<?php

namespace Modules\Platform\Rule;

/**
 * A set of rules combined with ALL (AND) or ANY (OR). Pure + serialisable, so a
 * workflow's "conditions" or a promotion's eligibility can be stored as JSON and
 * evaluated against a context. An empty set passes (no conditions = always).
 */
class RuleSet
{
    public const ALL = 'all';
    public const ANY = 'any';

    /**
     * @param  list<Rule>  $rules
     */
    public function __construct(
        protected array $rules = [],
        protected string $match = self::ALL,
    ) {}

    /**
     * Build from a serialised definition:
     *   ['match' => 'all', 'rules' => [ ['field'=>…,'operator'=>…,'value'=>…], … ]]
     *
     * @param  array{match?:string, rules?:array<int, array>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rules: array_map(fn (array $r) => Rule::fromArray($r), $data['rules'] ?? []),
            match: $data['match'] ?? self::ALL,
        );
    }

    /** @return array{match:string, rules:list<array>} */
    public function toArray(): array
    {
        return [
            'match' => $this->match,
            'rules' => array_map(fn (Rule $r) => $r->toArray(), $this->rules),
        ];
    }

    /**
     * Whether the context satisfies the set.
     *
     * @param  array<string, mixed>  $context
     */
    public function passes(array $context, RuleRegistry $registry): bool
    {
        if ($this->rules === []) {
            return true;
        }

        $results = array_map(fn (Rule $r) => $r->passes($context, $registry), $this->rules);

        return $this->match === self::ANY
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);
    }
}
