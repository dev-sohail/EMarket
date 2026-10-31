<?php

namespace App\Console\Commands;

use App\Models\ApiDefinition;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Scan the application's model directories to discover existing feature sets,
 * then report which ones could be exposed as API endpoints.
 *
 * This is a discovery / reporting tool: it does not create ApiDefinition rows
 * directly. Instead it prints a concise summary that an administrator can use
 * to seed the API definitions table, either by hand or via a follow-up
 * import command.
 */
class DiscoverApiDefinitionsCommand extends Command
{
    protected $signature = 'api:discover {--write : Write discovered definitions to the database}';

    protected $description = 'Discover existing system features suitable for dynamic API exposure';

    public function handle(Filesystem $files): int
    {
        $models = $this->discoverModels($files);

        if ($models->isEmpty()) {
            $this->info('No models found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Model', 'Table'],
            $models->map(function (string $class) {
                return [
                    'model' => $class,
                    'table' => $this->tableName($class),
                ];
            })
        );

        if ($this->option('write')) {
            $created = 0;

            foreach ($models as $model) {
                $tableName = $this->tableName($model);

                $definition = ApiDefinition::firstOrNew(
                    ['slug' => $tableName],
                    [
                        'name' => Str::title(Str::singular($tableName)),
                        'description' => 'Dynamic API endpoint for '.$tableName.'.',
                        'source' => 'reflection',
                        'is_active' => false,
                    ]
                );

                if ($definition->exists) {
                    continue;
                }

                $definition->save();
                $created++;

                $this->line("  Created definition: {$definition->name} ({$definition->slug})");
            }

            $this->info("{$created} API definition(s) written to the database.");
            $this->info('Remember to review and activate each one before use.');
        } else {
            $this->info('');
            $this->info('Run with --write to persist these as ApiDefinition rows.');
        }

        return self::SUCCESS;
    }

    /**
     * Walk the configured model paths and return fully-qualified class names
     * for classes that extend Illuminate\Database\Eloquent\Model.
     */
    protected function discoverModels(Filesystem $files): Collection
    {
        $paths = config('api_management.discovery.model_paths', [app_path('Models')]);
        $excluded = config('api_management.discovery.exclude_models', []);

        $results = collect();

        foreach ($paths as $path) {
            if (! $files->isDirectory($path)) {
                continue;
            }

            foreach ($files->allFiles($path) as $file) {
                $class = $this->classFromFile($file->getRealPath());

                if ($class === null) {
                    continue;
                }

                if (in_array($class, $excluded, true)) {
                    continue;
                }

                try {
                    if (! is_subclass_of($class, Model::class)) {
                        continue;
                    }
                } catch (\Throwable) {
                    continue;
                }

                $results->push($class);
            }
        }

        return $results;
    }

    /**
     * Resolve a model's table name without instantiating it.
     *
     * `new $model` is not always safe here: abstract bases such as
     * PrefixedModel cannot be constructed, and some models build their table
     * name from injected state. Reflection keeps discovery side-effect free.
     */
    protected function tableName(string $class): string
    {
        try {
            if ((new ReflectionClass($class))->isInstantiable()) {
                return (new $class)->getTable();
            }
        } catch (\Throwable) {
            // Fall through to reflection below.
        }

        try {
            $reflection = new ReflectionClass($class);

            if ($reflection->hasProperty('table')) {
                $property = $reflection->getProperty('table');
                $property->setAccessible(true);
                $value = $property->getDefaultValue();

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        } catch (\Throwable) {
            // Fall through to the derived name below.
        }

        return Str::snake(Str::plural(class_basename($class)));
    }

    /**
     * Convert a file path to a PSR-4 class name using the application namespace.
     */
    protected function classFromFile(string $path): ?string
    {
        $basePath = base_path();
        $relative = str_replace($basePath.DIRECTORY_SEPARATOR, '', $path);

        // Replace directory separators with backslashes, remove .php extension.
        $class = str_replace(['/', '.php'], ['\\', ''], $relative);

        // Replace app\ with the application namespace.
        $class = preg_replace('/^app[\\\\\/]/i', $this->laravel->getNamespace(), $class);

        // Ensure the class exists and is a model.
        if (! class_exists($class)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($class);

            if ($reflection->isInstantiable() || $reflection->isSubclassOf(Model::class)) {
                return $class;
            }
        } catch (\Throwable) {
            // ignore classes that fail reflection.
        }

        return null;
    }
}
