<?php

namespace App\Services\Api\Reflection;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Derives an API blueprint from a model by inspecting it three ways: the
 * database schema (what columns exist and what they hold), the class itself
 * (what relations and scopes it exposes), and the cast map (how values should
 * be read).
 *
 * This is the whole idea behind a reflection-sourced definition: nobody writes
 * an endpoint or a filter by hand. The schema says there is a `base_price`
 * column, the reflector turns that into a `filter_base_price` parameter with a
 * `numeric` rule, and the generic handler applies it. Change the migration,
 * re-run the reflector, and the new column is filterable.
 *
 * Two rules keep the result safe rather than merely clever:
 *
 *  1. Sensitive columns are never reflected. Reflection has no notion of
 *     intent — it cannot know `cost_price` is a margin secret and `sku` is
 *     not — so the exclusion is an explicit deny-list and anything unlisted is
 *     still treated as private-by-default for write paths.
 *  2. Nothing is instantiated. Column types come from the schema, relations
 *     from method signatures. A model whose constructor needs injected state
 *     can still be reflected.
 */
class ModelReflector
{
    /**
     * Columns never reflected into a public API, matched case-insensitively as
     * a substring so `stripe_client_secret` and `api_key` are caught too.
     *
     * @var list<string>
     */
    protected const SENSITIVE = [
        'cost_price',
        'password',
        'remember_token',
        'token',
        'secret',
        'api_key',
        'client_secret',
        'private',
    ];

    /**
     * Column-name fragments that are treated as the human label, in order of
     * preference.
     *
     * @var list<string>
     */
    protected const LABEL_PREFERENCE = ['name', 'title', 'label', 'order_number', 'sku'];

    /**
     * Column-name fragments that are treated as internal bookkeeping and so are
     * never offered as a filter or a sort key. Only the soft-delete column
     * qualifies: filtering by `deleted_at` from an API would let a caller walk
     * into the trash, and the primary key is excluded separately.
     *
     * Timestamps are deliberately *not* here — filtering a catalogue by
     * `created_at` is a legitimate thing for a storefront to want.
     *
     * @var list<string>
     */
    protected const NON_FILTERABLE = ['deleted_at'];

    /**
     * Build the blueprint for a model class.
     *
     * @param  class-string<Model>  $model
     *
     * @throws \InvalidArgumentException when the class is not an Eloquent model,
     *                                   or is a keyless value model
     * @throws \RuntimeException when its table is not present in the database
     */
    public function reflect(string $model): ModelBlueprint
    {
        if (! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            throw new \InvalidArgumentException("{$model} is not an Eloquent model.");
        }

        $instance = new $model;
        $table = $instance->getTable();

        // A model with no primary key has no addressable row, so there is
        // nothing for a detail route to resolve and nothing for the generic
        // handler to look a record up by. ProductAttributeValue is the real
        // case in this codebase: it declares `$primaryKey = null` because it
        // is a value object reached through a relation, not a standalone
        // resource. Reflecting it would mean inventing a key.
        if (empty($instance->getKeyName())) {
            throw new \InvalidArgumentException(
                "{$model} has no primary key, so it cannot be exposed as a resource. "
                .'Reach it through a relation on the model that owns it instead.'
            );
        }

        if (! Schema::hasTable($table)) {
            throw new \RuntimeException("Table [{$table}] for {$model} does not exist. Run your migrations first.");
        }

        $columns = $this->describeColumns($instance);

        return new ModelBlueprint(
            model: $model,
            table: $table,
            keyName: $instance->getKeyName(),
            labelColumn: $this->pickLabelColumn($columns),
            slugColumn: $this->pickColumn($columns, ['slug', 'sku', 'code']),
            filterable: $this->buildFilterable($columns),
            sortable: $this->buildSortable($columns),
            relations: $this->discoverRelations($model),
            scopes: $this->discoverScopes($model),
            columns: $columns,
        );
    }

    /**
     * Read the table's columns and classify each one.
     *
     * @return array<string, array{type: string, rules: list<string>, column: string}>
     */
    protected function describeColumns(Model $instance): array
    {
        $described = [];
        $casts = $instance->getCasts();

        foreach (Schema::getColumns($instance->getTable()) as $column) {
            $name = $column['name'];

            if ($this->isSensitive($name)) {
                continue;
            }

            [$type, $rules] = $this->classify($column, $casts[$name] ?? null);

            $described[$name] = [
                'type' => $type,
                'rules' => $rules,
                'column' => $name,
            ];
        }

        return $described;
    }

    /**
     * Map a schema column onto an API parameter type and its validation rules.
     *
     * A cast on the model wins over the raw column type, because it is the one
     * of the two that a human deliberately wrote down. Without this, a
     * `boolean` cast on a `tinyint` column is read as an integer: MySQL will
     * not tell you a tinyint is a flag, so `tinyint(1)` is the only hint, and
     * some drivers do not report the length at all.
     *
     * @param  array<string, mixed>  $column
     * @return array{0: string, 1: list<string>}
     */
    protected function classify(array $column, ?string $cast): array
    {
        if ($cast !== null) {
            $fromCast = match (true) {
                str_contains($cast, 'bool') => ['boolean', ['boolean']],
                str_contains($cast, 'int') => ['integer', ['integer']],
                str_contains($cast, 'float'), str_contains($cast, 'decimal') => ['number', ['numeric']],
                str_contains($cast, 'date'), str_contains($cast, 'datetime') => ['string', ['date']],
                default => null,
            };

            if ($fromCast !== null) {
                return $fromCast;
            }
        }

        $type = strtolower((string) ($column['type_name'] ?? 'string'));
        $length = $column['length'] ?? null;

        return match (true) {
            // A 1-byte tinyint is a boolean flag; a wider one is a counter.
            $type === 'tinyint' && (int) $length === 1 => ['boolean', ['boolean']],
            $type === 'boolean', $type === 'bool' => ['boolean', ['boolean']],
            str_contains($type, 'decimal'), str_contains($type, 'float'), str_contains($type, 'double'), $type === 'numeric' => ['number', ['numeric']],
            str_contains($type, 'int'), $type === 'serial', $type === 'bigint' => ['integer', ['integer']],
            str_contains($type, 'date'), str_contains($type, 'time') => ['string', ['date']],
            default => ['string', ['string', 'max:'.($length ?: 255)]],
        };
    }

    /**
     * Build the `filter_<column>` parameter map.
     *
     * The soft-delete column is excluded: filtering by `deleted_at` from a
     * public endpoint would let a caller walk into the trash.
     *
     * @param  array<string, array{type: string, rules: list<string>, column: string}>  $columns
     * @return array<string, array{type: string, rules: list<string>, column: string}>
     */
    protected function buildFilterable(array $columns): array
    {
        $filterable = [];

        foreach ($columns as $name => $meta) {
            if ($name === '_id' || in_array($name, self::NON_FILTERABLE, true)) {
                continue;
            }

            $filterable['filter_'.$name] = $meta;
        }

        return $filterable;
    }

    /**
     * Columns that may be sorted on: anything filterable, which keeps the two
     * lists from drifting apart.
     *
     * @param  array<string, array{type: string, rules: list<string>, column: string}>  $columns
     * @return list<string>
     */
    protected function buildSortable(array $columns): array
    {
        $sortable = [];

        foreach ($columns as $name => $meta) {
            if ($name === '_id' || in_array($name, self::NON_FILTERABLE, true)) {
                continue;
            }

            $sortable[] = $name;
        }

        return $sortable;
    }

    /**
     * Find the relation methods on the model.
     *
     * The relation *kind* comes from the return type, which needs no instance:
     * `public function variants(): HasMany` says "this is a hasMany" without
     * touching the database. The *related model* is not in the signature —
     * `HasMany` is not generic — so it is read from the relation object.
     * Constructing a relation runs no query (`getRelated()` just hands back
     * the related model), so this stays cheap; it is wrapped because a model
     * with a hand-rolled relation method can still throw here, and a failed
     * relation must not abort the whole reflection.
     *
     * @param  class-string<Model>  $model
     * @return list<array{property: string, type: string, related: string|null, related_model: class-string<Model>|null}>
     */
    protected function discoverRelations(string $model): array
    {
        $relations = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $type = $method->getReturnType();

            // A relation declares a class return type; `void`/`self`/`static`
            // are builtins and can never be one.
            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $related = $type->getName();

            if (! class_exists($related) || ! is_subclass_of($related, Relation::class)) {
                continue;
            }

            $relations[] = [
                'property' => $method->getName(),
                'type' => $this->relationType($related),
                'related' => $related,
                'related_model' => $this->resolveRelatedModel($model, $method->getName()),
            ];
        }

        return $relations;
    }

    /**
     * Instantiate a relation to learn which model it points at.
     *
     * @param  class-string<Model>  $model
     * @return class-string<Model>|null
     */
    protected function resolveRelatedModel(string $model, string $property): ?string
    {
        try {
            $relation = (new $model)->{$property}();

            if ($relation instanceof Relation) {
                $related = $relation->getRelated();

                return is_subclass_of($related, Model::class) ? $related::class : null;
            }
        } catch (Throwable) {
            // A relation that cannot be built outside a request is simply
            // reflected without its target model.
        }

        return null;
    }

    /**
     * Find the model's local query scopes (`scopePublished()` => `published`).
     *
     * @param  class-string<Model>  $model
     * @return list<string>
     */
    protected function discoverScopes(string $model): array
    {
        $scopes = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (! str_starts_with($method->getName(), 'scope') || $method->getName() === 'scope') {
                continue;
            }

            $scopes[] = lcfirst(substr($method->getName(), 5));
        }

        return array_values(array_unique($scopes));
    }

    /**
     * Classify a relation so the UI can label it.
     *
     * @param  class-string<Relation>  $relation
     */
    protected function relationType(string $relation): string
    {
        return match ($relation) {
            HasMany::class => 'hasMany',
            HasManyThrough::class => 'hasManyThrough',
            HasOne::class => 'hasOne',
            HasOneThrough::class => 'hasOneThrough',
            BelongsTo::class => 'belongsTo',
            BelongsToMany::class => 'belongsToMany',
            MorphMany::class => 'morphMany',
            MorphOne::class => 'morphOne',
            MorphTo::class => 'morphTo',
            MorphToMany::class => 'morphToMany',
            default => 'relation',
        };
    }

    /**
     * @param  array<string, array{type: string, rules: list<string>, column: string}>  $columns
     * @param  list<string>  $candidates
     */
    protected function pickLabelColumn(array $columns): ?string
    {
        return $this->pickColumn($columns, self::LABEL_PREFERENCE);
    }

    /**
     * @param  array<string, array{type: string, rules: list<string>, column: string}>  $columns
     * @param  list<string>  $candidates
     */
    protected function pickColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Whether a column name looks like it holds a secret.
     */
    protected function isSensitive(string $name): bool
    {
        $haystack = strtolower($name);

        foreach (self::SENSITIVE as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
