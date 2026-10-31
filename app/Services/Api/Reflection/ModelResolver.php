<?php

namespace App\Services\Api\Reflection;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

/**
 * Resolves a reflected definition back to the model it was generated from.
 *
 * A definition stores a slug (`products`) but not the model class. This rebuilds
 * the `table => model` map by reflecting over app/Models, so the generic
 * handler can turn `products` back into `App\Models\Product` at request time.
 *
 * The map is cached because it costs a directory scan plus a class load per
 * model. It is rebuilt whenever the model set is likely to have changed — a
 * definition reflected by hand is always re-resolvable, because the slug was
 * generated from the model's own table name in the first place.
 */
class ModelResolver
{
    /**
     * How long the scan is cached. Short enough that a newly added model shows
     * up without a deploy, long enough that a request does not re-scan.
     */
    protected const TTL_SECONDS = 300;

    public function __construct(protected Filesystem $files) {}

    /**
     * Find the model whose table matches a definition slug or path segment.
     *
     * @return class-string<Model>|null
     */
    public function forTable(string $table): ?string
    {
        $needle = Str::snake($table);

        foreach ($this->tableMap() as $mappedTable => $model) {
            if ($mappedTable === $needle || Str::snake(class_basename($model)) === $needle) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Every model in the configured paths, keyed by table name.
     *
     * @return Collection<string, class-string<Model>>
     */
    public function tableMap(): Collection
    {
        return Cache::remember('api.reflection.models', self::TTL_SECONDS, function () {
            $models = collect();

            $excluded = $this->excludedModels();

            foreach ((array) config('api_management.discovery.model_paths', [app_path('Models')]) as $path) {
                if (! $this->files->isDirectory($path)) {
                    continue;
                }

                foreach ($this->files->allFiles($path) as $file) {
                    $class = $this->classFromFile($file->getRealPath());

                    if ($class === null || in_array($class, $excluded, true)) {
                        continue;
                    }

                    try {
                        $table = (new $class)->getTable();
                    } catch (Throwable) {
                        // An abstract base or a model needing injected state is
                        // simply not resolvable; skip it.
                        continue;
                    }

                    $models->put($table, $class);
                }
            }

            return $models;
        });
    }

    /**
     * Resolve a user-supplied model name to a concrete, reflectable model class.
     *
     * Accepts what an admin would actually type — `Product`, `product`, `ProductModel`
     * or the full `App\Models\Product` — because the reflect screen offers a
     * picker and the CLI takes a typed argument, and the two must agree on what a
     * name means or a definition reflected in the UI could be unreachable from the
     * command and vice versa.
     *
     *
     * @return class-string<Model>
     *
     * @throws InvalidArgumentException with a message naming the specific problem,
     *                                  so the caller can show it as-is instead of
     *                                  guessing which of the four failures applied.
     */
    public function resolveClass(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('No model was given.');
        }

        $candidates = [$name];

        if (! str_contains($name, '\\')) {
            $candidates[] = 'App\\Models\\'.$name;
            $candidates[] = 'App\\Models\\'.Str::studly($name).'Model';
        }

        $found = null;

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                $found = $candidate;
                break;
            }
        }

        if ($found === null) {
            throw new InvalidArgumentException("Could not find model [{$name}]. Try the full class name, e.g. App\\Models\\Product.");
        }

        if (! is_subclass_of($found, Model::class)) {
            throw new InvalidArgumentException("{$found} exists but is not an Eloquent model.");
        }

        // Reflected endpoints are generated public (`requires_auth` false), so a
        // model the module itself keeps out of discovery — API tokens, request
        // logs, roles and permissions — must not be reachable through the
        // typed-name / picker route either.
        if (in_array(ltrim($found, '\\'), $this->excludedModels(), true)) {
            throw new InvalidArgumentException("{$found} cannot be exposed through a reflected API definition.");
        }

        $reflection = new ReflectionClass($found);

        if (! $reflection->isInstantiable()) {
            throw new InvalidArgumentException("{$found} cannot be instantiated, so it cannot be reflected.");
        }

        return $found;
    }

    /**
     * Model classes that must never be reflected into an API definition.
     *
     * @return list<class-string>
     */
    protected function excludedModels(): array
    {
        return array_values(array_map(
            fn ($class) => ltrim((string) $class, '\\'),
            (array) config('api_management.discovery.exclude_models', [])
        ));
    }

    /**
     * Forget the cached scan.
     */
    public function flush(): void
    {
        Cache::forget('api.reflection.models');
    }

    /**
     * Convert a file path to a PSR-4 class name, confirming it is a model.
     *
     * @return class-string<Model>|null
     */
    protected function classFromFile(string $path): ?string
    {
        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
        $class = preg_replace(
            '/^app[\\\\\/]/i',
            $this->namespace(),
            str_replace(['/', '.php'], ['\\', ''], $relative)
        );

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        try {
            if (! is_subclass_of($class, Model::class) || ! (new ReflectionClass($class))->isInstantiable()) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return $class;
    }

    /**
     * The application namespace, with its trailing separator.
     *
     * Kept with the trailing backslash so it can be used directly as the
     * `preg_replace` replacement when turning `app/Models/Product.php` into a
     * class name — trimming it would yield `AppModels\Product`.
     */
    protected function namespace(): string
    {
        return app()->getNamespace();
    }
}
