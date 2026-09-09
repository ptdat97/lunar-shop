<?php

namespace Modules\Core\Panel;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * A named operation a row offers — approve a return, refund it, reject it.
 *
 * This is the seam between forms-over-data and workflow. A resource whose rows
 * only need editing declares no actions; one whose rows move through states
 * declares them here rather than growing a bespoke screen.
 *
 * Availability is per row, which lines up exactly with how the panel renders
 * them: RowActions.vue only draws an action the row carries a URL for, so a
 * return that has already been refunded simply has no refund button.
 */
class RowAction
{
    /** @var (Closure(Model): bool)|null */
    protected ?Closure $availability = null;

    /** @var Closure(Model, Request): mixed */
    protected Closure $handler;

    protected ?string $icon = null;

    protected bool $primary = false;

    protected ?string $confirmation = null;

    /** @var array<string, array<int, string>> */
    protected array $rules = [];

    final protected function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {
        $this->handler = fn () => null;
    }

    public static function make(string $key, string $label): static
    {
        return new static($key, $label);
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /** Show this action in the row itself rather than the overflow menu. */
    public function primary(): static
    {
        $this->primary = true;

        return $this;
    }

    public function confirm(string $message): static
    {
        $this->confirmation = $message;

        return $this;
    }

    /** Only offer the action on rows this returns true for. */
    public function when(Closure $availability): static
    {
        $this->availability = $availability;

        return $this;
    }

    /**
     * Validation for whatever the action posts alongside the row.
     *
     * @param  array<string, array<int, string>>  $rules
     */
    public function accepts(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /** @param  Closure(Model, Request): mixed  $handler */
    public function run(Closure $handler): static
    {
        $this->handler = $handler;

        return $this;
    }

    public function availableFor(Model $record): bool
    {
        return $this->availability === null || ($this->availability)($record);
    }

    /** @return array<string, array<int, string>> */
    public function validationRules(): array
    {
        return $this->rules;
    }

    public function handle(Model $record, Request $request): mixed
    {
        return ($this->handler)($record, $request);
    }

    /** The descriptor RowActions.vue renders. @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'method' => 'post',
            'primary' => $this->primary,
            'confirmation' => $this->confirmation,
        ];
    }
}
