<?php

namespace Modules\Core\Http\Controllers\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;
use Modules\Core\Panel\RowAction;
use Modules\Core\Panel\ResourceRegistry;
use Throwable;

/**
 * The one controller behind every declared PanelResource.
 *
 * It never names a model or a column: the schema on the resource decides what
 * is listed, what is validated and what is written. Two Vue pages
 * (`shop::resource/index` and `shop::resource/form`) render whatever it sends.
 */
class ResourceController extends Controller
{
    public function __construct(protected ResourceRegistry $registry) {}

    public function index(Request $request): Response
    {
        $resource = $this->resource($request);

        $query = $resource->indexQuery($resource->model()::query());

        if ($term = trim((string) $request->query('q', ''))) {
            $columns = $resource->searchable();

            $query->where(function ($q) use ($columns, $term): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$term}%");
                }
            });
        }

        [$sortColumn, $sortDirection] = $resource->defaultSort();

        $paginator = $query
            ->orderBy($sortColumn, $sortDirection)
            // Tie-break on the key. Every default sort here is on a column that
            // repeats — created_at, sort, priority — and a paginated query with
            // an unstable order can show the same row on two pages and never
            // show another at all.
            ->orderBy($resource->model()::make()->getQualifiedKeyName(), $sortDirection)
            ->paginate($resource->perPage())
            ->withQueryString();

        return Inertia::render('shop/resource/Index', [
            'resource' => $this->descriptor($resource),
            'columns' => $resource->columns(),
            // `_actions` is the panel's own row-action contract: RowActions.vue
            // renders an action only when the row carries a URL under its key,
            // so per-row permissions and route names both stay in PHP.
            'rows' => collect($paginator->items())
                ->map(fn ($record) => [...$resource->toIndexRow($record), '_actions' => $this->rowUrls($resource, $record)])
                ->all(),
            'actions' => $this->actionDescriptors($resource),
            // Pagination.vue reads Laravel's own paginator meta keys, so send
            // them verbatim rather than inventing a second shape.
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'filters' => ['q' => $term],
        ]);
    }

    public function create(Request $request): Response
    {
        $resource = $this->resource($request);

        abort_unless($resource->canCreate(), 404);

        return Inertia::render('shop/resource/Form', [
            'resource' => $this->descriptor($resource),
            'fields' => array_map(fn (Field $f) => $f->toArray(), $resource->fields()),
            'record' => $resource->blank(),
            'isNew' => true,
            'actions' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $resource = $this->resource($request);

        abort_unless($resource->canCreate(), 404);

        $data = $this->validated($request, $resource, null);
        $payload = $data;

        // hasMany rows are not columns, so they are held back and written once
        // the parent exists and can own them.
        $relations = $this->extractRelations($data, $resource);

        $record = $resource->model()::create($data);

        $this->syncRelations($record, $resource, $relations);
        $resource->saved($record, $payload);

        return redirect()
            ->route($resource->routeName('edit'), $record->getKey())
            ->with('success', __('panel.saved', ['name' => $resource->singular()]));
    }

    public function edit(Request $request, int $record): Response
    {
        $resource = $this->resource($request);
        $model = $resource->model()::findOrFail($record);

        return Inertia::render('shop/resource/Form', [
            'resource' => $this->descriptor($resource),
            // The record is passed down so a picker can be scoped to it — a
            // lookbook item's pin image comes from that lookbook's own photos.
            'fields' => array_map(fn (Field $f) => $f->toArray($model), $resource->fields()),
            'record' => $resource->toRow($model),
            'isNew' => false,
            'actions' => [
                'update' => route($resource->routeName('update'), $model->getKey()),
                'destroy' => route($resource->routeName('destroy'), $model->getKey()),
            ],
        ]);
    }

    public function update(Request $request, int $record): RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $resource->model()::findOrFail($record);

        abort_unless($resource->canEdit(), 404);

        $data = $this->validated($request, $resource, $model);
        $payload = $data;
        $relations = $this->extractRelations($data, $resource);

        $model->update($data);

        $this->syncRelations($model, $resource, $relations);
        $resource->saved($model, $payload);

        return back()->with('success', __('panel.saved', ['name' => $resource->singular()]));
    }

    public function destroy(Request $request, int $record): RedirectResponse
    {
        $resource = $this->resource($request);

        abort_unless($resource->canDelete(), 404);

        $resource->model()::findOrFail($record)->delete();

        return redirect()
            ->route($resource->routeName('index'))
            ->with('success', __('panel.deleted', ['name' => $resource->singular()]));
    }

    /**
     * Run a declared row operation. The action decides for itself whether it
     * applies to this row, so a stale button in a browser tab left open cannot
     * refund a return twice.
     */
    public function action(Request $request, int $record, string $action): RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $resource->model()::findOrFail($record);

        $rowAction = collect($resource->rowActions())->firstWhere('key', $action);

        if (! $rowAction || ! $rowAction->availableFor($model)) {
            abort(404);
        }

        if ($rules = $rowAction->validationRules()) {
            $request->validate($rules);
        }

        try {
            $rowAction->handle($model, $request);
        } catch (Throwable $e) {
            // The service layer refuses for real reasons — a gateway declining
            // a refund, above all. Surfacing the message beats a 500 on a
            // screen whose whole job is handling money going back out.
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('panel.action_done', ['name' => $rowAction->label]));
    }

    /**
     * The URLs one row offers. RowActions.vue draws an action only when its key
     * is present here, so availability is decided per row in PHP.
     *
     * @return array<string, string>
     */
    protected function rowUrls(PanelResource $resource, Model $record): array
    {
        $urls = ['edit' => route($resource->routeName('edit'), $record->getKey())];

        if ($resource->canDelete()) {
            $urls['destroy'] = route($resource->routeName('destroy'), $record->getKey());
        }

        foreach ($resource->rowActions() as $action) {
            if ($action->availableFor($record)) {
                $urls[$action->key] = route($resource->routeName('action'), [$record->getKey(), $action->key]);
            }
        }

        return $urls;
    }

    /** @return array<int, array<string, mixed>> */
    protected function actionDescriptors(PanelResource $resource): array
    {
        return [
            ['key' => 'edit', 'label' => __('panel.edit'), 'icon' => 'edit', 'method' => 'get', 'primary' => true],
            ...array_map(fn (RowAction $action) => $action->toArray(), $resource->rowActions()),
            [
                'key' => 'destroy',
                'label' => __('panel.delete'),
                'icon' => 'trash',
                'method' => 'delete',
                'primary' => false,
                'confirmation' => __('panel.confirm_delete'),
            ],
        ];
    }

    /**
     * Validate against the declared rules, then hand the payload to the
     * resource for any shape fixing (JSON text → array, and so on).
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request, PanelResource $resource, ?Model $record): array
    {
        // The request's own state selects which conditional fields apply, so a
        // page section is validated against the branch it actually submitted.
        $data = $request->validate($resource->validationRules($record, $request->all()));

        foreach ($resource->fieldsFor($request->all()) as $field) {
            $type = $field->toArray()['type'];

            if ($type === 'tags') {
                $raw = (string) (data_get($data, $field->name) ?? '');

                data_set($data, $field->name, array_values(array_filter(
                    array_map('trim', explode(',', $raw)),
                    fn (string $tag) => $tag !== '',
                )));

                continue;
            }

            if ($type !== 'json') {
                continue;
            }

            // data_get/data_set, not array access: a field name may be a path
            // into a JSON column rather than a key of its own.
            $raw = data_get($data, $field->name);

            if (blank($raw)) {
                data_set($data, $field->name, null);

                continue;
            }

            $decoded = json_decode((string) $raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    $field->name => __('panel.invalid_json'),
                ]);
            }

            data_set($data, $field->name, $decoded);
        }

        return $resource->mutate($data, $record);
    }

    /**
     * Pull the hasMany payloads out of the validated data — they would be
     * rejected as unknown columns by a mass assignment.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function extractRelations(array &$data, PanelResource $resource): array
    {
        $relations = [];

        foreach ($resource->relationFields() as $field) {
            $relations[$field->name] = array_values((array) ($data[$field->name] ?? []));
            unset($data[$field->name]);
        }

        // Virtual fields are not columns either: the resource's saved() hook
        // owns them, and leaving one in would break the mass assignment.
        foreach ($resource->virtualFields() as $field) {
            unset($data[$field->name]);
        }

        return $relations;
    }

    /**
     * Bring each hasMany relation in line with what was submitted: update the
     * rows that came back with an id, create the new ones, delete the rest.
     * Order is written from each row's position, so the repeater's arrows are
     * the only place the admin thinks about ordering.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $relations
     */
    protected function syncRelations(Model $record, PanelResource $resource, array $relations): void
    {
        foreach ($resource->relationFields() as $field) {
            $rows = $relations[$field->name] ?? [];
            $names = array_map(fn (Field $child) => $child->name, $field->children());

            // Loaded once into models rather than queried per row. A relation's
            // query builder is a single mutable object: calling find() on it
            // leaves a `where id = ?` behind that would then silently scope the
            // delete below to that one row.
            $existing = $record->{$field->name}()->get()->keyBy(fn (Model $child) => $child->getKey());

            // `sort` is written from the row's position when the child model
            // accepts it, so ordering is the repeater's arrows and nothing else.
            $ordered = in_array('sort', $record->{$field->name}()->getRelated()->getFillable(), true);

            $kept = [];

            foreach ($rows as $index => $row) {
                $attributes = array_intersect_key($row, array_flip($names));

                if ($ordered) {
                    $attributes['sort'] = $index;
                }

                $child = ! empty($row['id']) ? $existing->get($row['id']) : null;

                if ($child) {
                    $child->update($attributes);
                    $kept[] = $child->getKey();

                    continue;
                }

                $kept[] = $record->{$field->name}()->create($attributes)->getKey();
            }

            $existing
                ->reject(fn (Model $child) => in_array($child->getKey(), $kept, true))
                ->each->delete();
        }
    }

    protected function resource(Request $request): PanelResource
    {
        $key = (string) $request->route()->defaults['resourceKey'];

        return $this->registry->get($key) ?? abort(404);
    }

    /** @return array<string, mixed> */
    protected function descriptor(PanelResource $resource): array
    {
        // The Vue pages take their chrome text from here rather than from the
        // panel's own i18n bundle: these strings live in lang/{locale}/panel.php
        // alongside the field labels the schemas already use, so there is one
        // place to translate an admin screen, not two.
        return [
            'key' => $resource->key(),
            'label' => $resource->label(),
            'singular' => $resource->singular(),
            'icon' => $resource->icon(),
            'newLabel' => __('panel.new', ['name' => $resource->singular()]),
            'saveLabel' => __('panel.save'),
            'backLabel' => __('panel.back'),
            'deleteLabel' => __('panel.delete'),
            'emptyLabel' => __('panel.empty', ['name' => $resource->label()]),
            'searchPlaceholder' => __('panel.search', ['name' => $resource->label()]),
            'routes' => [
                'index' => route($resource->routeName('index')),
                'create' => route($resource->routeName('create')),
                'store' => route($resource->routeName('store')),
            ],
            'searchable' => (bool) $resource->searchable(),
            'canCreate' => $resource->canCreate(),
            'canEdit' => $resource->canEdit(),
            'canDelete' => $resource->canDelete(),
        ];
    }
}
