<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\PrefixedModel;
use App\Models\Product;
use App\Models\ShippingRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class PrefixedModelRelationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PrefixedModel declares the primary key as the string column "_id", so
     * Eloquent's own foreign key derivation (Str::snake($name).'_'.$keyName)
     * produces "<name>__id", a column that does not exist. PrefixedModel
     * therefore overrides belongsTo/hasOne/hasMany/belongsToMany to derive the
     * real single-underscore key. This test guards that convention: any relation
     * added without a key, or a model renamed, must not reintroduce "__id".
     */
    public function test_every_derived_foreign_key_is_a_real_column(): void
    {
        $failures = [];

        foreach ($this->prefixedModelClasses() as $class) {
            $model = new $class;

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic() || str_starts_with($method->getName(), '__')) {
                    continue;
                }

                $relation = $this->resolveRelation($model, $method->getName());

                if (! $relation instanceof BelongsTo && ! $relation instanceof HasOneOrMany) {
                    continue;
                }

                $foreignKey = Str::afterLast($relation->getQualifiedForeignKeyName(), '.');

                if (str_contains($foreignKey, '__')) {
                    $failures[] = "{$class}::{$method->getName()}() derived double-underscore key {$foreignKey}";

                    continue;
                }

                // The column holding the key is the table the relation points
                // FROM, not the table it points at. belongsTo() puts it on the
                // declaring model (cart_items.product_id); hasOne()/hasMany() put
                // it on the related table (customer.user_id). Checking
                // getRelated() for both reported every one of them as missing.
                $table = $relation instanceof BelongsTo
                    ? $model->getTable()
                    : $relation->getRelated()->getTable();

                if (! Schema::hasTable($table)) {
                    $failures[] = "{$class}::{$method->getName()}() targets unknown table {$table}";

                    continue;
                }

                if (! Schema::hasColumn($table, $foreignKey)) {
                    $failures[] = "{$class}::{$method->getName()}() derived missing key {$table}.{$foreignKey}";
                }
            }
        }

        $this->assertSame([], $failures, "Relations deriving a non-existent foreign key:\n".implode("\n", $failures));
    }

    /**
     * belongsTo() derives the key from the relation name (the child column),
     * while hasOne()/hasMany() derive it from the declaring class. The two are
     * deliberately asymmetric, so both directions are asserted explicitly.
     */
    public function test_belongs_to_and_has_one_derive_from_different_names(): void
    {
        $cartItem = new CartItem;
        $order = new Order;

        $this->assertSame('product_id', $this->foreignKeyFor($cartItem, 'product'));
        $this->assertSame('order_id', $this->foreignKeyFor($order, 'items'));
        $this->assertSame('order_id', $this->foreignKeyFor($order, 'shippingAddress'));
        $this->assertSame('customer_id', $this->foreignKeyFor($order, 'customer'));
    }

    public function test_shipping_rule_method_resolves_its_non_conventional_column(): void
    {
        $relation = (new ShippingRule)->method();

        $this->assertSame('shipping_method_id', $relation->getForeignKeyName());
    }

    public function test_a_belongs_to_actually_resolves_a_record(): void
    {
        $product = Product::factory()->create();
        $item = CartItem::factory()->create([
            'product_id' => $product->_id,
            'quantity' => 1,
        ]);

        $this->assertInstanceOf(Product::class, $item->product);
        $this->assertSame($product->_id, $item->product->_id);
    }

    private function foreignKeyFor(Model $model, string $method): string
    {
        $relation = $this->resolveRelation($model, $method);

        $this->assertInstanceOf(Relation::class, $relation, $model::class.'::'.$method.'() is not a key-bearing relation.');

        return $relation->getForeignKeyName();
    }

    private function resolveRelation(Model $model, string $method): mixed
    {
        if (! method_exists($model, $method)) {
            return null;
        }

        $reflection = new ReflectionClass($model);

        if (! $reflection->hasMethod($method)) {
            return null;
        }

        $declaring = $reflection->getMethod($method)->getDeclaringClass()->getName();

        if ($declaring === Model::class || $declaring === PrefixedModel::class) {
            return null;
        }

        $returnType = $reflection->getMethod($method)->getReturnType();

        if ($returnType instanceof \ReflectionNamedType
            && ! is_a($returnType->getName(), Model::class, true)
            && ! is_a($returnType->getName(), Relation::class, true)) {
            return null;
        }

        try {
            $result = $model->{$method}();
        } catch (\Throwable) {
            return null;
        }

        return $result instanceof BelongsTo || $result instanceof HasOneOrMany ? $result : null;
    }

    /**
     * @return array<int, class-string<Model>>
     */
    private function prefixedModelClasses(): array
    {
        $classes = [];

        foreach (File::allFiles(app_path('Models')) as $file) {
            $class = 'App\\Models\\'.$file->getFilenameWithoutExtension();

            if (! class_exists($class) || ! is_subclass_of($class, PrefixedModel::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }
}
