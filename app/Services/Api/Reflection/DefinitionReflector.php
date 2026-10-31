<?php

namespace App\Services\Api\Reflection;

use App\Models\ApiDefinition;
use App\Models\ApiEndpoint;
use App\Models\ApiEndpointParameter;
use App\Services\Api\ReflectedCatalogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes a reflected blueprint into the database as a real, callable API
 * definition.
 *
 * The output is not special-cased anywhere else in the module: once written, a
 * reflected endpoint is an ordinary ApiEndpoint row with ordinary parameters,
 * registered by the same ApiRouteRegistrar and dispatched by the same
 * ApiDynamicController as a hand-built one. That is the point — reflection is a
 * *code generator*, not a second runtime. You can hand-edit anything it
 * produces, and the only thing you lose is the ability to regenerate it.
 *
 * Re-running is safe and is the normal workflow: change a migration, run the
 * reflector again, and the definition picks up the new columns. Parameters are
 * replaced wholesale rather than merged, because a parameter set is a
 * declaration of what the endpoint accepts — keeping a filter for a column that
 * no longer exists would keep validating an input the API can no longer use.
 */
class DefinitionReflector
{
    /**
     * Reflection-sourced definitions are written inactive by default.
     *
     * Generating a URL that the public internet can reach is a decision, not a
     * side effect of running a command. Activate once you have read what was
     * exposed.
     */
    protected const ACTIVATE_BY_DEFAULT = false;

    /**
     * Reflect a model and persist the definition, endpoints and parameters.
     *
     * @param  class-string<Model>  $model
     * @param  array{activate?: bool, force?: bool}  $options
     *
     * @throws PathConflictException when a generated URL is already owned by
     *                               another definition and `force` is not set.
     */
    public function write(string $model, array $options = []): ApiDefinition
    {
        $blueprint = app(ModelReflector::class)->reflect($model);

        $activate = $options['activate'] ?? self::ACTIVATE_BY_DEFAULT;

        // The conflict check runs *before* anything is written, and needs to
        // know which definition is "ours" so its own endpoints are not treated
        // as foreign. That id is looked up rather than created first, because
        // upserting first would leave a half-built definition behind every time
        // a collision aborted the write.
        $own = ApiDefinition::withTrashed()->where('slug', $blueprint->path())->first();

        if (! ($options['force'] ?? false)) {
            $this->assertPathsAvailable($own?->getKey(), [
                ['method' => 'GET', 'path' => $blueprint->path()],
                ['method' => 'GET', 'path' => $blueprint->detailPath()],
            ]);
        }

        $definition = $this->upsertDefinition($blueprint, $activate);

        $this->writeIndexEndpoint($definition, $blueprint, $activate);
        $this->writeShowEndpoint($definition, $blueprint, $activate);

        return $definition->load('endpoints.parameters');
    }

    /**
     * Refuse to shadow another definition's public URL.
     *
     * Two endpoints cannot share a route: `ApiRouteRegistrar` registers one
     * Laravel route per row, and the router matches the first one whose URI
     * pattern fits. So the check compares *patterns*, not literal strings —
     * `products/{id}` and `products/{product}` are the same URL to the router,
     * and the one registered first silently wins. Comparing the raw paths would
     * call that a conflict-free setup and produce a detail endpoint that always
     * 401s or 404s behind the earlier route.
     *
     * `routable()` is the same scope the registrar uses, so the two cannot
     * disagree about what is published. It matters here because an endpoint
     * belonging to a retired definition is not routed and so cannot shadow
     * anything, while an ungrouped endpoint with no definition at all is
     * routed and therefore does count as a conflict.
     *
     * @param  list<array{method: string, path: string}>  $proposed
     * @param  string|null  $ownDefinitionId  the definition being regenerated,
     *                                        whose own endpoints are exempt
     *
     * @throws PathConflictException
     */
    protected function assertPathsAvailable(?string $ownDefinitionId, array $proposed): void
    {
        $conflicts = [];

        foreach ($proposed as $candidate) {
            $pattern = $this->routePattern($candidate['path']);

            $query = ApiEndpoint::query()
                ->routable()
                ->where('method', $candidate['method'])
                ->with('definition');

            if ($ownDefinitionId !== null) {
                $query->where('api_definition_id', '!=', $ownDefinitionId);
            }

            $owners = $query->get()
                ->filter(fn (ApiEndpoint $endpoint) => $this->routePattern($endpoint->path) === $pattern)
                ->map(fn (ApiEndpoint $endpoint) => [
                    'id' => $endpoint->_id,
                    'definition' => $endpoint->definition?->name ?? 'an ungrouped endpoint',
                    'path' => $endpoint->path,
                ])
                ->values()
                ->all();

            if ($owners !== []) {
                $conflicts[] = $candidate + ['conflicts' => $owners];
            }
        }

        if ($conflicts !== []) {
            throw new PathConflictException($conflicts);
        }
    }

    /**
     * Reduce a path to the pattern the router actually matches on.
     *
     * Placeholder *names* are irrelevant to matching — `{id}`, `{product}` and
     * `{anything}` are the same segment to Laravel — so every `{...}` collapses
     * to a single token.
     */
    protected function routePattern(string $path): string
    {
        return preg_replace('/\{[^}]*\}/', '{}', trim($path, '/'));
    }

    /**
     * A short, honest summary of what reflection produced, for the UI and for
     * the command output.
     *
     * @param  class-string<Model>  $model
     * @return array<string, mixed>
     */
    public function preview(string $model): array
    {
        $blueprint = app(ModelReflector::class)->reflect($model);

        return [
            'model' => $model,
            'table' => $blueprint->table,
            'paths' => [$blueprint->path(), $blueprint->detailPath()],
            'filters' => $blueprint->filterableNames(),
            'relations' => array_map(
                fn (array $relation) => $relation['property'].' ('.$relation['type'].')',
                $blueprint->relations
            ),
            'scopes' => $blueprint->scopes,
        ];
    }

    /**
     * Create or update the definition row for a blueprint.
     *
     * `is_active` is deliberately *not* reset on an existing definition. Re-running
     * the reflector after a migration is the normal maintenance workflow, and
     * reflection writes definitions inactive; letting a routine regeneration take a
     * published API offline would make the command destructive by default. The
     * flag only ever moves a definition forward, and only when explicitly asked.
     */
    protected function upsertDefinition(ModelBlueprint $blueprint, bool $activate): ApiDefinition
    {
        $attributes = [
            'name' => Str::plural($blueprint->label()),
            'slug' => $blueprint->path(),
            'description' => $this->describe($blueprint),
            'source' => 'reflection',
        ];

        // withTrashed(): a deleted definition still holds its slug in the unique
        // index, so a plain updateOrCreate would try to insert and crash.
        $existing = ApiDefinition::withTrashed()->where('slug', $blueprint->path())->first();

        if ($existing !== null) {
            $existing->restore();
            $existing->fill($attributes)->save();

            return $activate ? tap($existing)->update(['is_active' => true]) : $existing;
        }

        return ApiDefinition::create($attributes + ['is_active' => $activate]);
    }

    protected function describe(ModelBlueprint $blueprint): string
    {
        return sprintf(
            'Reflected from %s. Exposes %s as %s and %s, with %d filterable column(s), %d relation(s) '
            .'and the local scope(s): %s. Regenerate with `php artisan api:reflect %s` after a schema change.',
            class_basename($blueprint->model),
            $blueprint->table,
            $blueprint->path(),
            $blueprint->detailPath(),
            count($blueprint->filterable),
            count($blueprint->relations),
            $blueprint->scopes === [] ? 'none' : implode(', ', $blueprint->scopes),
            class_basename($blueprint->model)
        );
    }

    protected function writeIndexEndpoint(ApiDefinition $definition, ModelBlueprint $blueprint, bool $activate): ApiEndpoint
    {
        $endpoint = $this->upsertEndpoint($definition, [
            'method' => 'GET',
            'path' => $blueprint->path(),
            'action' => ReflectedCatalogService::class.'::index',
            'handler_type' => 'service',
            'description' => sprintf(
                'Reflected listing of %s. Filter with any `filter_<column>` parameter, sort with `sort` and `direction`.',
                $blueprint->table
            ),
            'requires_auth' => false,
            'sort_order' => 10,
        ], $activate);

        $parameters = [];

        foreach ($blueprint->filterable as $name => $meta) {
            $parameters[] = [
                'name' => $name,
                'location' => 'query',
                'type' => $meta['type'],
                'required' => false,
                'description' => sprintf('Filter on the `%s` column.', $meta['column']),
                'rules' => $meta['rules'],
            ];
        }

        $parameters[] = [
            'name' => 'sort',
            'location' => 'query',
            'type' => 'string',
            'required' => false,
            'description' => sprintf('Column to sort by. One of: %s.', implode(', ', $blueprint->sortableColumns())),
            // Allow-listed so `sort` can never name an arbitrary column.
            'rules' => ['string', 'in:'.implode(',', $blueprint->sortableColumns())],
        ];

        $parameters[] = [
            'name' => 'direction',
            'location' => 'query',
            'type' => 'string',
            'required' => false,
            'description' => 'Sort direction. Defaults to desc.',
            'rules' => ['string', 'in:asc,desc'],
        ];

        $parameters[] = [
            'name' => 'per_page',
            'location' => 'query',
            'type' => 'integer',
            'required' => false,
            'description' => 'Page size, capped at 60.',
            'rules' => ['integer', 'min:1', 'max:60'],
        ];

        $parameters[] = [
            'name' => 'page',
            'location' => 'query',
            'type' => 'integer',
            'required' => false,
            'rules' => ['integer', 'min:1'],
        ];

        $this->syncParameters($endpoint, $parameters);

        return $endpoint;
    }

    protected function writeShowEndpoint(ApiDefinition $definition, ModelBlueprint $blueprint, bool $activate): ApiEndpoint
    {
        $endpoint = $this->upsertEndpoint($definition, [
            'method' => 'GET',
            'path' => $blueprint->detailPath(),
            'action' => ReflectedCatalogService::class.'::show',
            'handler_type' => 'service',
            'description' => sprintf('Reflected detail view of one %s record.', $blueprint->label()),
            'requires_auth' => false,
            'sort_order' => 20,
        ], $activate);

        $identifierHelp = 'Primary key';

        if ($blueprint->slugColumn !== null) {
            $identifierHelp .= sprintf(' or %s', $blueprint->slugColumn);
        }

        $this->syncParameters($endpoint, [[
            'name' => $blueprint->detailKeyName(),
            'location' => 'path',
            'type' => 'string',
            'required' => true,
            'description' => $identifierHelp.'.',
            'rules' => ['string', 'max:255'],
        ]]);

        return $endpoint;
    }

    /**
     * Create or update one endpoint of a reflected definition.
     *
     * Like the definition, an existing endpoint keeps its own `is_active`: an
     * endpoint may have been retired by hand, and regenerating the declaration
     * is not a reason to publish it again.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertEndpoint(ApiDefinition $definition, array $attributes, bool $activate): ApiEndpoint
    {
        $existing = ApiEndpoint::withTrashed()
            ->where('api_definition_id', $definition->getKey())
            ->where('method', $attributes['method'])
            ->where('path', $attributes['path'])
            ->first();

        if ($existing !== null) {
            $existing->restore();
            $existing->fill($attributes)->save();

            return $activate ? tap($existing)->update(['is_active' => true]) : $existing;
        }

        return ApiEndpoint::create($attributes + [
            'api_definition_id' => $definition->getKey(),
            'is_active' => $activate,
        ]);
    }

    /**
     * Replace an endpoint's parameters with the reflected declaration.
     *
     * @param  list<array<string, mixed>>  $parameters
     */
    protected function syncParameters(ApiEndpoint $endpoint, array $parameters): void
    {
        // Parameters are hard-deleted, not soft-deleted, so a rerun leaves no
        // stale row behind for the registrar to still validate against.
        ApiEndpointParameter::where('api_endpoint_id', $endpoint->getKey())->delete();

        foreach ($parameters as $sortOrder => $parameter) {
            ApiEndpointParameter::create([
                'api_endpoint_id' => $endpoint->getKey(),
                'name' => $parameter['name'],
                'location' => $parameter['location'],
                'type' => $parameter['type'],
                'required' => (bool) ($parameter['required'] ?? false),
                'description' => $parameter['description'] ?? null,
                // Cast to array on the model, so pass the array — encoding here
                // would store a JSON *string* inside the JSON column.
                'rules' => array_values($parameter['rules'] ?? []),
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
