<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Every model must describe the table that actually exists.
 *
 * This project migrated to prefixed `_id` keys and trimmed several tables, but
 * a number of models kept naming columns from the pre-migration shape. Eloquent
 * does not complain about any of it: an unknown column in `$fillable` is
 * silently dropped on insert, and an unknown attribute read returns null. So the
 * drift did not fail loudly — it failed quietly, one discarded field at a time:
 *
 *   - InventoryMovement wrote `note` while the column is `notes`, so every
 *     inventory movement note was silently discarded.
 *   - ReportService compared `$inventory->low_stock_threshold` while the column
 *     is `reorder_level`, so `quantity <= null` was always false and the
 *     low-stock report was permanently empty.
 *   - The address form posted `label`/`full_name` against NOT NULL
 *     `first_name`/`last_name`/`email`, so creating an address could not work.
 *
 * A missing column is therefore silent in production and invisible in review, so
 * it is pinned here instead: this test fails the moment a model names a column
 * the schema does not have.
 */
class ModelSchemaAlignmentTest extends TestCase
{
    // The check is against the migrated schema, so the tables have to exist.
    // The data provider itself stays container-free because providers run before
    // the application boots.
    use RefreshDatabase;

    /**
     * Columns that are never written through mass assignment.
     *
     * @var array<int, string>
     */
    private const IGNORED_COLUMNS = [
        'password',
        'remember_token',
    ];

    /**
     * Keys that are not columns: the primary key, and accessors/relations.
     *
     * @var array<int, string>
     */
    private const IGNORED_KEYS = [
        'id',
        '_id',
    ];

    /**
     * Data providers run before the application is booted, so `app_path()` is
     * not available here — the path is derived from this file instead.
     *
     * @return iterable<string, array{class-string<Model>}>
     */
    public static function modelProvider(): iterable
    {
        $files = glob(dirname(__DIR__, 2).'/app/Models/*.php') ?: [];

        foreach ($files as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            yield $class => [$class];
        }
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[DataProvider('modelProvider')]
    public function test_every_declared_attribute_is_a_real_column(string $class): void
    {
        // Instantiated normally, not with newInstanceWithoutConstructor(): the
        // Eloquent constructor is what populates the model's cast list, so
        // skipping it makes getCasts() return [] and silently checks nothing.
        /** @var Model $model */
        $model = new $class;
        $table = $model->getTable();

        $this->assertTrue(
            Schema::hasTable($table),
            sprintf('%s points at table "%s", which does not exist.', class_basename($class), $table),
        );

        $columns = Schema::getColumnListing($table);

        $declared = array_unique(array_merge(
            $model->getFillable(),
            array_keys($model->getCasts()),
            $model->getDates(),
        ));

        $phantom = array_values(array_filter(
            $declared,
            fn (string $key) => ! in_array($key, self::IGNORED_COLUMNS, true)
                && ! in_array($key, self::IGNORED_KEYS, true)
                && ! in_array($key, $columns, true),
        ));

        $this->assertSame(
            [],
            $phantom,
            sprintf(
                '%s declares column(s) that table "%s" does not have: %s. Eloquent drops them silently on insert and '
                .'returns null when read, so either drop them from the model or add them to the schema.',
                class_basename($class),
                $table,
                implode(', ', $phantom),
            ),
        );
    }

    /**
     * Every cast must name a cast type the framework actually implements.
     *
     * An unrecognised cast is not a no-op: Eloquent throws "Call to undefined
     * cast [x]" the first time the attribute is read, so a single wrong string
     * makes the whole model unusable. `Setting` declared a `mixed` cast, which
     * this framework version does not have, and the `setting()` helper caught
     * the resulting exception and returned its default — the same catch that
     * hid the missing table.
     *
     * @param  class-string<Model>  $class
     */
    #[DataProvider('modelProvider')]
    public function test_every_cast_names_an_implemented_cast_type(string $class): void
    {
        /** @var Model $model */
        $model = new $class;

        // The list is a protected static on the trait, so it is read reflectively
        // rather than duplicated here — a copy would drift as casts are added.
        $supported = (new ReflectionClass(HasAttributes::class))
            ->getStaticPropertyValue('primitiveCastTypes');

        $invalid = [];

        foreach ($model->getCasts() as $attribute => $castType) {
            // Encrypted and custom casts are class names or prefixed variants;
            // only a bare word has to appear in the primitive list.
            $baseType = Str::before($castType, ':');

            if (! in_array($baseType, $supported, true) && ! class_exists($baseType)) {
                $invalid[] = sprintf('%s => %s', $attribute, $castType);
            }
        }

        $this->assertSame(
            [],
            $invalid,
            sprintf(
                '%s declares cast(s) this framework version does not implement: %s. '
                .'Reading the attribute throws, so the model is unusable until the cast is corrected.',
                class_basename($class),
                implode(', ', $invalid),
            ),
        );
    }
}
