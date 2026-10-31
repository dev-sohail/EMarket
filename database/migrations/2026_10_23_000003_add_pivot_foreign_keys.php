<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $pivots = [
        'product_tags' => [['product_id', 'products', '_id'], ['tag_id', 'tags', '_id']],
        'role_permission' => [['role_id', 'roles', '_id'], ['permission_id', 'permissions', '_id']],
        'user_role' => [['user_id', 'users', '_id'], ['role_id', 'roles', '_id']],
        'campaign_products' => [['campaign_id', 'discount_campaigns', '_id'], ['product_id', 'products', '_id']],
        'campaign_categories' => [['campaign_id', 'discount_campaigns', '_id'], ['category_id', 'categories', '_id']],
        'flash_sale_products' => [['flash_sale_id', 'flash_sales', '_id'], ['product_id', 'products', '_id']],
        'flash_sale_categories' => [['flash_sale_id', 'flash_sales', '_id'], ['category_id', 'categories', '_id']],
        'product_categories' => [['product_id', 'products', '_id'], ['category_id', 'categories', '_id']],
        'product_brands' => [['product_id', 'products', '_id'], ['brand_id', 'brands', '_id']],
        'variant_attribute_values' => [['product_variant_id', 'product_variants', '_id'], ['attribute_value_id', 'attribute_values', '_id']],
        'product_attribute_values' => [['product_id', 'products', '_id'], ['attribute_value_id', 'attribute_values', '_id']],
    ];

    public function up(): void
    {
        foreach ($this->pivots as $table => $fks) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($fks) {
                foreach ($fks as [$col, $refTable, $refCol]) {
                    if (Schema::hasColumn($t->getTable(), $col) && Schema::hasTable($refTable)) {
                        $t->foreign($col)->references($refCol)->on($refTable)->cascadeOnDelete();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->pivots) as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                foreach ($t->getForeignKeys() as $fk) {
                    $t->dropForeign($fk->getColumns());
                }
            });
        }
    }
};
