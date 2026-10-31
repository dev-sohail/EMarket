<?php

namespace App\Models;

use App\Traits\PrefixedId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class ApiEndpoint extends PrefixedModel
{
    use HasFactory, PrefixedId, SoftDeletes;

    protected $table = 'api_endpoints';

    protected $fillable = [
        'api_definition_id',
        'method',
        'path',
        'action',
        'handler_type',
        'description',
        'requires_auth',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_auth' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'aep';
    }

    public function definition(): HasOne
    {
        return $this->hasOne(ApiDefinition::class, '_id', 'api_definition_id');
    }

    /**
     * Endpoints that should be published as routes.
     *
     * An endpoint is routable when it is active *and* is not owned by a
     * soft-deleted definition. `is_active` alone is not enough: retiring a
     * definition soft-deletes the definition without touching the endpoint
     * rows, so the endpoints of a retired definition would otherwise keep
     * being published. That is not hypothetical — a retired `storefront`
     * definition left its `products/{id}` endpoint live, where it shadowed the
     * reflected `products/{product}` route and made it permanently
     * unreachable.
     *
     * The four cases, because only one of them should be excluded:
     *
     *   no definition id at all      -> routable; `api_definition_id` is
     *                                    nullable and a standalone endpoint
     *                                    that was never grouped is legitimate
     *   id pointing at no row        -> routable; the parent is simply gone
     *   id pointing at a live row    -> routable
     *   id pointing at a deleted row -> NOT routable
     *
     * `whereHas('definition')` alone would get the last case right but would
     * also drop the first two, silently unpublishing every ungrouped endpoint.
     * A plain `whereHas` is therefore not enough, and neither is `withTrashed`
     * in the sub-query.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRoutable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $query) {
                $query
                    ->whereNull('api_definition_id')
                    ->orWhereNotIn('api_definition_id', ApiDefinition::withTrashed()->select('_id'))
                    // Applies ApiDefinition's soft-delete scope, so this
                    // matches live parents only.
                    ->orWhereHas('definition');
            });
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(ApiEndpointParameter::class, 'api_endpoint_id', '_id')
            ->orderBy('sort_order');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(ApiEndpointPermission::class, 'api_endpoint_id', '_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApiRouteLog::class, 'api_endpoint_id', '_id');
    }

    /**
     * Resolve the concrete handler callable for this endpoint.
     *
     * handler_type is one of: controller, service. The action is the
     * fully-qualified `Class@method` (controller) or `Class::method` (service)
     * string. Returns null when the handler cannot be resolved, so callers
     * can fall back to a 500 rather than a confusing reflection error.
     *
     * The action is only ever resolved if it appears in
     * `config('api_management.allowed_handlers')`. This is a security boundary,
     * not a tidy-up: `action` is user-supplied (it is a plain validated string
     * on StoreApiEndpointRequest), and resolving it means `app($target)` on an
     * arbitrary class followed by a call to an arbitrary public method on it.
     * Without the allow-list, anyone who can create an endpoint gets arbitrary
     * method invocation inside the container — `App\Models\User::delete` or
     * `Illuminate\Support\Facades\Artisan::call` included — which is remote
     * code execution behind an admin login.
     *
     * The check is on the whole action string exactly as stored, so an allowed
     * class cannot be paired with a method that was not itself reviewed.
     */
    public function resolveHandler(): ?object
    {
        $action = $this->action;

        if (empty($action)) {
            return null;
        }

        if (! $this->handlerIsAllowed($action)) {
            Log::warning('Rejected dynamic API handler outside the allow-list.', [
                'endpoint' => $this->_id,
                'action' => $action,
                'handler_type' => $this->handler_type,
            ]);

            return null;
        }

        [$target, $method] = match ($this->handler_type) {
            'service' => $this->splitOn($action, '::'),
            default => $this->splitOn($action, '@'),
        };

        if ($target === null || $method === null) {
            return null;
        }

        if (! class_exists($target)) {
            return null;
        }

        $instance = app($target);

        if (! method_exists($instance, $method)) {
            return null;
        }

        return $instance;
    }

    /**
     * Whether this endpoint's action is on the configured allow-list.
     *
     * A missing `allowed_handlers` key resolves to an empty allow-list, which
     * denies everything. Failing closed matters here: a typo in config, or a
     * cached config from an older deploy, must not silently restore arbitrary
     * dispatch.
     */
    protected function handlerIsAllowed(string $action): bool
    {
        $allowed = config('api_management.allowed_handlers');

        // Cast as well as default: config() returns whatever the cached config
        // holds, and a key explicitly set to null would otherwise reach
        // in_array() as a TypeError rather than a clean denial.
        return is_array($allowed) && in_array($action, $allowed, true);
    }

    /**
     * Split a handler string into a class and method, returning nulls when the
     * separator is absent so the caller can short-circuit cleanly.
     */
    protected function splitOn(string $action, string $separator): array
    {
        if (! str_contains($action, $separator)) {
            return [null, null];
        }

        [$target, $method] = explode($separator, $action, 2);

        return [$target, $method];
    }
}
