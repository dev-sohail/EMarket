<?php

namespace App\Console\Commands;

use App\Services\Api\Reflection\DefinitionReflector;
use App\Services\Api\Reflection\ModelReflector;
use App\Services\Api\Reflection\ModelResolver;
use App\Services\Api\Reflection\PathConflictException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

/**
 * Generate a working API definition from an Eloquent model.
 *
 * `api:discover` answers "what could be exposed?". This command answers "expose
 * it" — it runs the model through ModelReflector and writes the result out as an
 * ordinary API definition, endpoints and parameters, ready to activate.
 *
 * That is the whole demonstration: once a model is added to a config path, it
 * gains a working listing and detail endpoint with no handler, resource,
 * serializer or route file written by hand. Re-running after a migration is the
 * maintenance workflow, not a rebuild.
 *
 * Examples:
 *   php artisan api:reflect --list
 *   php artisan api:reflect Product
 *   php artisan api:reflect Product Brand Category --write
 */
class ReflectApiDefinitionCommand extends Command
{
    protected $signature = 'api:reflect
                            {model?* : Model class names, e.g. Product Brand}
                            {--write : Persist the generated definitions, endpoints and parameters}
                            {--activate : With --write, activate the definitions immediately}
                            {--force : With --write, accept URLs that another definition already owns}
                            {--list : List the reflectable models and exit}';

    protected $description = 'Reflect an Eloquent model into a working API definition, endpoints and parameters';

    public function handle(Filesystem $files, DefinitionReflector $reflector, ModelReflector $models): int
    {
        if ($this->option('list')) {
            return $this->listReflectable($files, $models);
        }

        $names = (array) $this->argument('model');

        if ($names === []) {
            $this->components->error('Name at least one model, or pass --list to see the options.');

            return self::FAILURE;
        }

        $resolved = [];

        foreach ($names as $name) {
            $class = $this->resolveModel($name);

            if ($class === null) {
                return self::FAILURE;
            }

            $resolved[] = $class;
        }

        $failures = 0;

        foreach ($resolved as $class) {
            $failures += $this->handleModel($class, $reflector) === self::FAILURE ? 1 : 0;
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Reflect one model and report or persist it.
     */
    protected function handleModel(string $class, DefinitionReflector $reflector): int
    {
        try {
            $preview = $reflector->preview($class);
        } catch (Throwable $e) {
            $this->components->error("Could not reflect {$class}: ".$e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Model', $class);
        $this->components->twoColumnDetail('Table', $preview['table']);
        $this->components->twoColumnDetail('Endpoints', implode(', ', array_map(
            fn (string $path) => 'GET '.$path,
            $preview['paths']
        )));
        $this->components->twoColumnDetail('Filterable columns', (string) count($preview['filters']));
        $this->components->twoColumnDetail('Relations', $preview['relations'] === []
            ? 'none'
            : implode(', ', $preview['relations']));
        $this->components->twoColumnDetail('Model scopes', $preview['scopes'] === []
            ? 'none'
            : implode(', ', $preview['scopes']));

        if (! $this->option('write')) {
            $this->newLine();
            $this->components->twoColumnDetail('Next', 'add --write to persist');

            return self::SUCCESS;
        }

        try {
            $definition = $reflector->write($class, [
                'activate' => (bool) $this->option('activate'),
                'force' => (bool) $this->option('force'),
            ]);
        } catch (PathConflictException $e) {
            $this->newLine();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%s "%s" (%s) with %d endpoint(s) and %d parameter(s).',
            $definition->wasRecentlyCreated ? 'Created' : 'Updated',
            $definition->name,
            $definition->slug,
            $definition->endpoints->count(),
            $definition->endpoints->sum(fn ($endpoint) => $endpoint->parameters->count()),
        ));

        if (! $definition->is_active) {
            $this->components->warn('Left inactive. Review the endpoints, then activate it in Admin > API Management.');
        }

        return self::SUCCESS;
    }

    /**
     * Show every model that can be reflected.
     */
    protected function listReflectable(Filesystem $files, ModelReflector $models): int
    {
        $resolver = app(ModelResolver::class);
        $resolver->flush();

        $rows = [];

        foreach ($resolver->tableMap() as $table => $class) {
            try {
                $blueprint = $models->reflect($class);
            } catch (Throwable $e) {
                // A model whose table has not been migrated yet is listed as
                // unavailable rather than crashing the whole listing.
                $rows[] = [$class, $table, 'unavailable', $e->getMessage()];

                continue;
            }

            $rows[] = [
                $class,
                $table,
                (string) count($blueprint->filterableNames()),
                implode(', ', array_map(
                    fn (array $relation) => $relation['property'],
                    $blueprint->relations
                )),
            ];
        }

        sort($rows);

        $this->table(['Model', 'Table', 'Filters', 'Relations'], $rows);

        $this->components->info('Reflect one with: php artisan api:reflect Model --write');

        return self::SUCCESS;
    }

    /**
     * Accept a full class name or a short name, with or without the `Model`
     * suffix, and confirm it is really a model.
     *
     * @return class-string<Model>|null
     */
    protected function resolveModel(string $name): ?string
    {
        $candidates = [$name];

        if (! str_contains($name, '\\')) {
            $candidates[] = 'App\\Models\\'.$name;
            $candidates[] = 'App\\Models\\'.Str::studly($name).'Model';
        }

        foreach ($candidates as $candidate) {
            if (! class_exists($candidate)) {
                continue;
            }

            if (! is_subclass_of($candidate, Model::class)) {
                $this->components->error("{$candidate} is not an Eloquent model.");

                return null;
            }

            if (! (new ReflectionClass($candidate))->isInstantiable()) {
                $this->components->error("{$candidate} cannot be instantiated.");

                return null;
            }

            // Same exclusion list ModelResolver enforces for the admin UI, so
            // the CLI cannot publish a model the module keeps out of reflection.
            $excluded = array_map(
                fn ($class) => ltrim((string) $class, '\\'),
                (array) config('api_management.discovery.exclude_models', [])
            );

            if (in_array(ltrim($candidate, '\\'), $excluded, true)) {
                $this->components->error("{$candidate} cannot be exposed through a reflected API definition.");

                return null;
            }

            return $candidate;
        }

        $this->components->error("Could not find model [{$name}]. Try the full class name, or pass --list.");

        return null;
    }
}
