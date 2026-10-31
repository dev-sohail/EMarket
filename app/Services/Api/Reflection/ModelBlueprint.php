<?php

namespace App\Services\Api\Reflection;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The structured description of a model that reflection derived from it.
 *
 * A plain value object on purpose: the reflector, the definition writer and the
 * runtime handler all pass this around, and nothing in the chain should be able
 * to reach back into the database or re-run reflection. Once it exists, the
 * blueprint is fixed.
 */
class ModelBlueprint
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, array{type: string, rules: list<string>, column: string}>  $filterable
     * @param  list<string>  $sortable
     * @param  list<array{property: string, type: string, related: string, related_model: class-string<Model>|null}>  $relations
     * @param  list<string>  $scopes
     * @param  array<string, array{type: string}>  $columns
     */
    public function __construct(
        public readonly string $model,
        public readonly string $table,
        public readonly string $keyName,
        public readonly ?string $labelColumn,
        public readonly ?string $slugColumn,
        public readonly array $filterable,
        public readonly array $sortable,
        public readonly array $relations,
        public readonly array $scopes,
        public readonly array $columns,
    ) {}

    /**
     * The path segment this resource is exposed under, e.g. `products`.
     */
    public function path(): string
    {
        return Str::kebab(Str::plural(class_basename($this->model)));
    }

    /**
     * The URL prefix for a single record, e.g. `products/{product}`.
     */
    public function detailPath(): string
    {
        return $this->path().'/{'.Str::snake(Str::singular(class_basename($this->model))).'}';
    }

    /**
     * The route parameter name for a single record, e.g. `product`.
     */
    public function detailKeyName(): string
    {
        return Str::snake(Str::singular(class_basename($this->model)));
    }

    /**
     * Human label for the definition, e.g. `Product`.
     */
    public function label(): string
    {
        return Str::singular(class_basename($this->model));
    }

    /**
     * The columns that may appear in an `order by` clause.
     *
     * @return list<string>
     */
    public function sortableColumns(): array
    {
        return $this->sortable;
    }

    /**
     * @return list<string>
     */
    public function filterableNames(): array
    {
        return array_keys($this->filterable);
    }
}
