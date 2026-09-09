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
use Modules\Core\Panel\ResourceRegistry;

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
            ->paginate($resource->perPage())
            ->withQueryString();

        return Inertia::render('shop/resource/Index', [
            'resource' => $this->descriptor($resource),
            'columns' => $resource->columns(),
            // `_actions` is the panel's own row-action contract: RowActions.vue
            // renders an action only when the row carries a URL under its key,
            // so per-row permissions and route names both stay in PHP.
            'rows' => collect($paginator->items())->map(fn ($record) => [
                ...$resource->toIndexRow($record),
                '_actions' => [
                    'edit' => route($resource->routeName('edit'), $record->getKey()),
                    'destroy' => route($resource->routeName('destroy'), $record->getKey()),
                ],
            ])->all(),
            'actions' => [
                ['key' => 'edit', 'label' => __('panel.edit'), 'icon' => 'edit', 'method' => 'get', 'primary' => true],
                [
                    'key' => 'destroy',
                    'label' => __('panel.delete'),
                    'icon' => 'trash',
                    'method' => 'delete',
                    'primary' => false,
                    'confirmation' => __('panel.confirm_delete'),
                ],
            ],
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

        $data = $this->validated($request, $resource, null);

        $record = $resource->model()::create($data);

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
            'fields' => array_map(fn (Field $f) => $f->toArray(), $resource->fields()),
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

        $model->update($this->validated($request, $resource, $model));

        return back()->with('success', __('panel.saved', ['name' => $resource->singular()]));
    }

    public function destroy(Request $request, int $record): RedirectResponse
    {
        $resource = $this->resource($request);

        $resource->model()::findOrFail($record)->delete();

        return redirect()
            ->route($resource->routeName('index'))
            ->with('success', __('panel.deleted', ['name' => $resource->singular()]));
    }

    /**
     * Validate against the declared rules, then hand the payload to the
     * resource for any shape fixing (JSON text → array, and so on).
     *
     * @return array<string, mixed>
     */
    protected function validated(Request $request, PanelResource $resource, ?Model $record): array
    {
        $data = $request->validate($resource->validationRules($record));

        foreach ($resource->fields() as $field) {
            if ($field->toArray()['type'] !== 'json') {
                continue;
            }

            $raw = $data[$field->name] ?? null;

            if (blank($raw)) {
                $data[$field->name] = null;

                continue;
            }

            $decoded = json_decode((string) $raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    $field->name => __('panel.invalid_json'),
                ]);
            }

            $data[$field->name] = $decoded;
        }

        return $resource->mutate($data, $record);
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
        ];
    }
}
