<?php

namespace App\Services\Api;

use App\Models\ApiEndpoint;
use App\Services\Api\Reflection\ModelResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The runtime half of a reflection-sourced definition.
 *
 * Reflection decides *what* an API exposes; this class is the only code that
 * has to exist for every reflected model. It reads the endpoint's own declared
 * parameters — which ModelReflector generated from the schema — and applies
 * them. That is the trick that makes reflection worth using: the generic
 * handler never hard-codes a column name, so a new column on `products`
 * becomes a filter after one re-run of the reflector and no code change at all.
 *
 * Adding `App\Services\Api\ReflectedCatalogService::index` to a model therefore
 * means: reflect it, register the two endpoints, activate. Nothing else.
 *
 * A record is serialised with `$record->toArray()` and nothing more. The rows
 * are deliberately shallow — no relation is walked, even though the reflector
 * found them. A generic handler cannot know that `variants` is bounded and
 * `reviews` is not, and eagerly loading whatever relations a model happens to
 * declare is exactly how a reflected listing ends up issuing fifty queries per
 * row. Which relations to expose, and at what depth, is the one thing that
 * genuinely still needs a hand-written handler. What `$hidden` withholds is
 * respected for free, because that is the model's own decision.
 *
 * Conventions this relies on, both produced by the reflector:
 *  - the endpoint path segment names the table (`products`)
 *  - filters arrive as `filter_<column>` query parameters
 */
class ReflectedCatalogService
{
    /**
     * Hard ceiling on page size, matching the hand-written catalogue.
     */
    protected const MAX_PER_PAGE = 60;

    public function __construct(protected ModelResolver $resolver) {}

    /**
     * List records of the reflected model.
     *
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, table: string}
     */
    public function index(Request $request): array
    {
        [$model, $query] = $this->resolve($request);

        $validated = $this->validated($request);

        $this->applyFilters($query, $model, $validated);

        $perPage = $this->perPage($validated['per_page'] ?? null);

        $paginator = $query
            ->orderBy($this->sortColumn($model, $validated['sort'] ?? null), $this->sortDirection($validated['direction'] ?? null))
            ->paginate($perPage);

        return [
            'items' => array_map(
                fn (Model $record) => $record->toArray(),
                $paginator->items()
            ),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
            'table' => (new $model)->getTable(),
        ];
    }

    /**
     * Show one record, addressed by its slug when the model has a slug column
     * and by its primary key otherwise.
     *
     * @return array{record: array<string, mixed>, table: string}
     */
    public function show(Request $request): array
    {
        [$model, $query] = $this->resolve($request);

        $validated = $this->validated($request);

        $parameter = $this->identifierParameter($request);

        // An endpoint with no declared path parameter (or none supplied) has
        // nothing to look a record up by; that is a miss, not an undefined-key
        // error surfacing as a 500.
        if ($parameter === '' || ! isset($validated[$parameter]) || ! is_scalar($validated[$parameter])) {
            throw new NotFoundHttpException(class_basename($model).' not found.');
        }

        $identifier = (string) $validated[$parameter];
        $instance = new $model;
        $slugColumn = $this->slugColumn($model);

        $record = $query
            ->where(function (Builder $sub) use ($instance, $identifier, $slugColumn) {
                $sub->where($instance->getKeyName(), $identifier);

                if ($slugColumn !== null) {
                    $sub->orWhere($slugColumn, $identifier);
                }
            })
            ->first();

        if ($record === null) {
            throw new NotFoundHttpException(class_basename($model).' not found.');
        }

        return [
            'record' => $record->toArray(),
            'table' => $instance->getTable(),
        ];
    }

    /**
     * The name of the endpoint's path parameter.
     *
     * Read from the endpoint's own declaration rather than hard-coded as
     * `product` or `record`, because the reflector derives it from the model
     * name (`Product` -> `product`, `Category` -> `category`). Hard-coding it
     * would make every reflected model except the one it was copied from fail
     * to resolve its own detail route.
     */
    protected function identifierParameter(Request $request): string
    {
        $endpoint = $request->attributes->get('api_endpoint');

        $pathParameter = $endpoint instanceof ApiEndpoint
            ? $endpoint->parameters->firstWhere('location', 'path')
            : null;

        return (string) ($pathParameter?->name ?? '');
    }

    /**
     * Work out which model the endpoint belongs to and start a query for it.
     *
     * The model comes from the *path*, which the reflector derived from the
     * model's own table name — the same convention the slug is stored under, so
     * `products` round-trips back to `App\Models\Product`.
     *
     * @return array{0: class-string<Model>, 1: Builder<Model>}
     */
    protected function resolve(Request $request): array
    {
        $endpoint = $request->attributes->get('api_endpoint');

        if (! $endpoint instanceof ApiEndpoint) {
            throw new \LogicException('Reflected endpoints require the ApiEndpoint on the request.');
        }

        $segment = Str::before($endpoint->path, '/');

        $model = $this->resolver->forTable($segment);

        if ($model === null) {
            throw new \RuntimeException("No model reflects table [{$segment}].");
        }

        return [$model, $model::query()];
    }

    /**
     * Apply every `filter_<column>` parameter the endpoint declares.
     *
     * The column name is taken from the parameter's *declared* metadata rather
     * than guessed from the value, so a caller cannot invent a filter that was
     * never reflected.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function applyFilters(Builder $query, string $model, array $validated): void
    {
        $instance = new $model;

        foreach ($validated as $name => $value) {
            if (! is_string($name) || ! str_starts_with($name, 'filter_') || $value === null || $value === '') {
                continue;
            }

            $column = Str::after($name, 'filter_');

            // Guard against a parameter that slipped past validation naming a
            // column the model does not have.
            if (! $this->isRealColumn($instance, $column)) {
                continue;
            }

            $type = $instance->getCasts()[$column] ?? null;

            $this->applyFilter($query, $instance, $column, $value, is_string($type) ? $type : null);
        }
    }

    /**
     * Apply one filter with sensible semantics for its column type.
     */
    protected function applyFilter(Builder $query, Model $instance, string $column, mixed $value, ?string $cast): void
    {
        $type = $this->castType($cast) ?? $this->columnType($instance, $column);

        match ($type) {
            'boolean' => $query->whereIn($column, $this->truthy($value)),
            // A number reads as a threshold, not a match: `base_price=99` means
            // "99 or more", which is the only reading that makes a price filter
            // useful.
            'integer', 'number' => is_numeric($value)
                ? $query->where($column, ((float) $value >= 0) ? '>=' : '<', abs((float) $value))
                : $query->where($column, 'like', '%'.$value.'%'),
            'string' => $this->applyStringFilter($query, $column, (string) $value),
            default => $query->where($column, (string) $value),
        };
    }

    /**
     * A string filter supports three shapes from one query parameter:
     *
     *   filter_name=wool      -> exact match
     *   filter_name=wool~     -> substring
     *   filter_name=wool^     -> starts with
     *
     * The operator is a suffix on the *value*, so every shape stays a single
     * `filter_<column>` parameter instead of tripling the reflected parameter
     * count. Anything else is an exact match.
     *
     * Note that the operator is part of the value: `filter_name~wool` is read
     * by the query parser as a parameter *named* `filter_name~wool` and is
     * ignored, because no such parameter was declared.
     */
    protected function applyStringFilter(Builder $query, string $column, string $value): void
    {
        match (true) {
            str_ends_with($value, '~') => $query->where($column, 'like', '%'.Str::beforeLast($value, '~').'%'),
            str_ends_with($value, '^') => $query->where($column, 'like', Str::beforeLast($value, '^').'%'),
            default => $query->where($column, $value),
        };
    }

    /**
     * Expand a boolean filter into the column values that mean true.
     *
     * The parameter is validated as a `boolean` before it reaches here, so only
     * the spellings Laravel's boolean rule accepts can arrive: `1`/`true` and
     * `0`/`false`.
     *
     * @return list<int|string>
     */
    protected function truthy(mixed $value): array
    {
        $enabled = in_array(
            is_bool($value) ? $value : strtolower((string) $value),
            [true, '1', 'true'],
            true
        );

        return $enabled ? [1, true, '1'] : [0, false, '0'];
    }

    /**
     * Whether a column exists on the model's table.
     *
     * Checked through the query grammar's column listing rather than a schema
     * call per filter, so applying twenty filters costs one listing.
     */
    protected function isRealColumn(Model $instance, string $column): bool
    {
        // Cache the column *listing* per table, not the answer for one column:
        // caching the boolean made the first filter checked decide every other
        // filter on the same table, so a real column could be silently ignored
        // and a non-existent one passed straight into the query.
        static $listings = [];

        $table = $instance->getTable();

        $listings[$table] ??= $instance->getConnection()->getSchemaBuilder()->getColumnListing($table);

        return in_array($column, $listings[$table], true);
    }

    /**
     * The declared cast for a column, reduced to a broad type.
     */
    protected function castType(?string $cast): ?string
    {
        if ($cast === null) {
            return null;
        }

        return match (true) {
            str_contains($cast, 'bool') => 'boolean',
            str_contains($cast, 'int') => 'integer',
            str_contains($cast, 'float'), str_contains($cast, 'decimal') => 'number',
            default => 'string',
        };
    }

    /**
     * Fall back to the database column type when there is no cast.
     */
    protected function columnType(Model $instance, string $column): string
    {
        foreach (Schema::getColumns($instance->getTable()) as $meta) {
            if ($meta['name'] !== $column) {
                continue;
            }

            $type = strtolower((string) ($meta['type_name'] ?? 'string'));

            return match (true) {
                $type === 'tinyint' && (int) ($meta['length'] ?? 0) === 1 => 'boolean',
                str_contains($type, 'int') => 'integer',
                str_contains($type, 'decimal'), str_contains($type, 'float'), str_contains($type, 'double') => 'number',
                default => 'string',
            };
        }

        return 'string';
    }

    /**
     * The slug column, when the model has one.
     *
     * @param  class-string<Model>  $model
     */
    protected function slugColumn(string $model): ?string
    {
        foreach (['slug', 'sku', 'code'] as $candidate) {
            if (in_array($candidate, (new $model)->getConnection()->getSchemaBuilder()->getColumnListing((new $model)->getTable()), true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Resolve the sort column, falling back to the primary key.
     *
     * @param  class-string<Model>  $model
     */
    protected function sortColumn(string $model, mixed $requested): string
    {
        $instance = new $model;
        $columns = $instance->getConnection()->getSchemaBuilder()->getColumnListing($instance->getTable());

        $requested = is_string($requested) ? $requested : '';

        if ($requested !== '' && in_array($requested, $columns, true)) {
            return $requested;
        }

        // Descending by default: a catalogue ordered by an arbitrary primary
        // key reads as newest-first for the prefixed, time-ordered ids this
        // project uses.
        return $instance->getKeyName();
    }

    protected function sortDirection(mixed $requested): string
    {
        return strtolower((string) $requested) === 'asc' ? 'asc' : 'desc';
    }

    protected function perPage(mixed $requested): int
    {
        return max(1, min((int) ($requested ?: 25), self::MAX_PER_PAGE));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return (array) $request->attributes->get('api_validated', []);
    }
}
