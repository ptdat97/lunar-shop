<?php

namespace Modules\Core\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * A declared CRUD screen on the Lunar panel.
 *
 * Lunar 2.0's panel covers products, variants, stock, orders, customers,
 * discounts and the whole settings tree first-party. What it cannot know about
 * is this shop's own tables — banners, pages, redirects, lookbooks. Those are
 * plain admin CRUD with no domain logic worth hand-writing a Vue page for, so
 * a subclass declares the model, the columns and the fields, and
 * ResourceController + two shared Vue pages do the rest.
 *
 * Anything with real behaviour (a wizard, a bulk workflow, an editor with live
 * preview) should be a purpose-built section instead — this is deliberately a
 * forms-over-data engine, not a framework.
 */
abstract class PanelResource
{
    /** @return class-string<Model> */
    abstract public function model(): string;

    /** URL segment and route-name fragment, e.g. `banners`. */
    abstract public function key(): string;

    /** Plural, for the index heading and nav. */
    abstract public function label(): string;

    /** Singular, for the "New …" button and edit heading. */
    abstract public function singular(): string;

    /** @return array<int, Field> */
    abstract public function fields(): array;

    /**
     * Which Section owns this resource — the key it registers its routes and
     * navigation under.
     */
    abstract public function section(): string;

    /**
     * Panel permission handle gating both the routes and the nav item, so what
     * a user can see and what they can reach never drift apart.
     */
    public function permission(): string
    {
        return 'content:manage';
    }

    /** Lucide icon name for the navigation item. */
    public function icon(): string
    {
        return 'file-text';
    }

    /**
     * Extra index columns that are not form fields — computed counts, related
     * names. Keyed by column name, each a callback given the model.
     *
     * @return array<string, callable(Model): mixed>
     */
    public function computed(): array
    {
        return [];
    }

    /** Labels for `computed()` columns, keyed the same way. @return array<string, string> */
    public function computedLabels(): array
    {
        return [];
    }

    /** Columns the index search box matches against. @return array<int, string> */
    public function searchable(): array
    {
        return [];
    }

    /** @return array{0: string, 1: string} column, direction */
    public function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    public function perPage(): int
    {
        return 25;
    }

    /** Hook for eager loads or scopes on the index query. */
    public function indexQuery(Builder $query): Builder
    {
        return $query;
    }

    /**
     * Last chance to touch the validated payload before it is written — used by
     * resources whose column shape differs from the form's (JSON fields, for
     * instance, arrive as text and must be decoded).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutate(array $data, ?Model $record): array
    {
        return $data;
    }

    public function routeName(string $action): string
    {
        return "panel.shop.{$this->key()}.{$action}";
    }

    /** @return array<int, Field> */
    public function indexFields(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->showsOnIndex()));
    }

    /**
     * The index table's column descriptors, in order: the fields flagged
     * `onIndex()` followed by the computed ones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        // `key`, not `name`: DataTable.vue's column contract, and the same key
        // its `#cell-{key}` slots are named after. `type` is the cell renderer
        // DataTableCell knows about, which is a different vocabulary from the
        // form field's — a toggle renders as a tick, a select as a badge.
        $cellTypes = ['toggle' => 'boolean', 'select' => 'badge', 'image' => 'image'];

        $columns = array_map(function (Field $f) use ($cellTypes) {
            $column = ['key' => $f->name, 'label' => $f->label];

            if ($cellType = $cellTypes[$f->toArray()['type']] ?? null) {
                $column['type'] = ['name' => $cellType, 'options' => []];
            }

            return $column;
        }, $this->indexFields());

        foreach ($this->computed() as $name => $_) {
            $columns[] = [
                'key' => $name,
                'label' => $this->computedLabels()[$name] ?? $name,
            ];
        }

        return $columns;
    }

    /**
     * The record is passed so a resource can build rules that depend on it —
     * `unique` ignoring the row being edited, above all.
     *
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(?Model $record = null): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            $rules[$field->name] = $field->validationRules();
        }

        return $rules;
    }

    /**
     * A `unique` rule on one of this resource's own columns, ignoring the row
     * being edited. Shared because every resource with a slug or a handle needs
     * exactly this and getting the ignore wrong only shows up on edit.
     */
    protected function unique(string $column, ?Model $record): Unique
    {
        $rule = Rule::unique((new ($this->model()))->getTable(), $column);

        return $record ? $rule->ignore($record->getKey()) : $rule;
    }

    /** The blank record a create form starts from. @return array<string, mixed> */
    public function blank(): array
    {
        $blank = [];

        foreach ($this->fields() as $field) {
            $blank[$field->name] = $field->defaultValue();
        }

        return $blank;
    }

    /**
     * One row as the Vue table and form see it. Only declared fields are
     * exposed, so a column added to the table but not to the schema never
     * leaks to the browser.
     *
     * @return array<string, mixed>
     */
    public function toRow(Model $record): array
    {
        $row = ['id' => $record->getKey()];

        foreach ($this->fields() as $field) {
            $value = $record->getAttribute($field->name);
            $type = $field->toArray()['type'];

            // A JSON column arrives as an array from the cast but the editor is
            // a textarea, so it round-trips as pretty-printed text.
            if ($type === 'json' && is_array($value)) {
                $row[$field->name] = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                continue;
            }

            // A select's value must match its <option value>, and JSON turns
            // every object key into a string — so an int column (status_code
            // 301) would arrive as 301 against "301" and select nothing.
            $row[$field->name] = $type === 'select' && $value !== null ? (string) $value : $value;
        }

        foreach ($this->computed() as $name => $callback) {
            $row[$name] = $callback($record);
        }

        return $row;
    }

    /**
     * One row as the index table sees it. Same data as toRow(), except a select
     * shows its label: the form needs the stored value ('center', 301) but a
     * table cell showing that instead of "Giữa" is just a leaked column value.
     *
     * @return array<string, mixed>
     */
    public function toIndexRow(Model $record): array
    {
        $row = $this->toRow($record);

        foreach ($this->fields() as $field) {
            $definition = $field->toArray();

            if ($definition['type'] !== 'select' || ! $field->showsOnIndex()) {
                continue;
            }

            $value = $row[$field->name] ?? null;
            $row[$field->name] = $definition['options'][$value] ?? $value;
        }

        return $row;
    }
}
