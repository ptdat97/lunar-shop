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

    /**
     * Named operations the rows offer beyond edit and delete — the seam for a
     * resource whose records move through states rather than just being edited.
     *
     * @return array<int, RowAction>
     */
    public function rowActions(): array
    {
        return [];
    }

    /**
     * Whether rows are created and deleted from this screen at all. A returns
     * queue is opened by customers and worked by staff; an admin "New return"
     * button would only ever create nonsense.
     */
    public function canCreate(): bool
    {
        return true;
    }

    public function canDelete(): bool
    {
        return true;
    }

    /** Whether the form saves at all, or only shows the record. */
    public function canEdit(): bool
    {
        return true;
    }

    /** Lucide icon name for the navigation item. */
    public function icon(): string
    {
        return 'fileText';
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

    /**
     * Called after the record and its hasMany rows are written. The seam for a
     * resource whose form holds something the engine cannot store on its own —
     * a menu's item tree, which is a table of its own shape.
     *
     * @param  array<string, mixed>  $data  the validated payload, relations included
     */
    public function saved(Model $record, array $data): void {}

    public function routeName(string $action): string
    {
        return "panel.shop.{$this->key()}.{$action}";
    }

    /**
     * The fields that apply to a given form state — everything unconditional
     * plus whichever conditional branch that state selects.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, Field>
     */
    public function fieldsFor(array $input): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->appliesTo($input)));
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
    public function validationRules(?Model $record = null, array $input = []): array
    {
        $rules = [];

        foreach ($this->fieldsFor($input) as $field) {
            $rules[$field->name] = $field->validationRules();

            $rules += $this->childRules($field, $field->name, $input);
        }

        return $rules;
    }

    /**
     * Rules for a repeater's rows, recursing into nested repeaters — a menu is
     * three levels deep (items → columns → links). Laravel's own wildcard
     * syntax does the addressing, so an invalid row reports against
     * `tree.0.children.2.label` and the form can point straight at it.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, mixed>>
     */
    protected function childRules(Field $field, string $prefix, array $input): array
    {
        $rules = [];

        foreach ($field->children() as $child) {
            $path = $prefix.'.*.'.$child->name;
            $rules[$path] = $child->validationRules();
            $rules += $this->childRules($child, $path, $input);
        }

        // A hasMany row carries the child's id so a save updates it rather than
        // recreating it; undeclared keys are stripped by validate(), so the id
        // needs a rule of its own to survive.
        if ($field->isRelation()) {
            $rules[$prefix.'.*.id'] = ['nullable', 'integer'];
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

        // Only unconditional fields: a create form opens with no branch chosen,
        // and seeding every branch's defaults would let one branch's value win
        // by declaration order (two of them share `settings.limit` with
        // different limits). The form seeds a branch when it becomes visible.
        foreach ($this->fieldsFor([]) as $field) {
            // Dot names address a path inside a JSON column
            // (`settings.slides`), so the blank record has to be nested the
            // same way the saved one is.
            data_set($blank, $field->name, $field->defaultValue());
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
            // data_get, not getAttribute: a field may address a path inside a
            // JSON column ('settings.slides') rather than a column of its own.
            $value = data_get($record, $field->name);
            $type = $field->toArray()['type'];

            // A JSON column arrives as an array from the cast but the editor is
            // a textarea, so it round-trips as pretty-printed text.
            if ($type === 'json' && is_array($value)) {
                data_set($row, $field->name, json_encode(
                    $value,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ));

                continue;
            }

            if ($field->isVirtual()) {
                continue;
            }

            if ($type === 'tags') {
                data_set($row, $field->name, implode(', ', (array) ($value ?? [])));

                continue;
            }

            if ($type === 'repeater') {
                data_set($row, $field->name, $field->isRelation()
                    ? $this->relationRows($record, $field)
                    : array_values((array) ($value ?? [])));

                continue;
            }

            // A select's value must match its <option value>, and JSON turns
            // every object key into a string — so an int column (status_code
            // 301) would arrive as 301 against "301" and select nothing.
            data_set($row, $field->name, $type === 'select' && $value !== null && ! is_array($value)
                ? (string) $value
                : $value);
        }

        foreach ($this->computed() as $name => $callback) {
            $row[$name] = $callback($record);
        }

        return $row;
    }

    /**
     * A hasMany field's rows: the declared sub-fields plus the child's id, so a
     * save updates the existing rows instead of deleting and recreating them —
     * anything referencing a child by id keeps pointing at it.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function relationRows(Model $record, Field $field): array
    {
        $names = array_map(fn (Field $child) => $child->name, $field->children());

        return $record->{$field->name}
            ->map(function (Model $child) use ($names): array {
                $row = ['id' => $child->getKey()];

                foreach ($names as $name) {
                    $value = $child->getAttribute($name);
                    $row[$name] = $value === null ? null : (is_scalar($value) ? (string) $value : $value);
                }

                return $row;
            })
            ->values()
            ->all();
    }

    /** @return array<int, Field> */
    public function relationFields(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->isRelation()));
    }

    /** @return array<int, Field> */
    public function virtualFields(): array
    {
        return array_values(array_filter($this->fields(), fn (Field $f) => $f->isVirtual()));
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

            $value = data_get($row, $field->name);
            data_set($row, $field->name, $definition['options'][$value] ?? $value);
        }

        return $row;
    }
}
