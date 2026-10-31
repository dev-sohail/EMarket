<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give attribute values a stable, admin-controlled order.
 *
 * The admin form submits an attribute's values in the order shown, but the
 * table had no column to record that order in, so values could only ever come
 * back in insertion order and reordering them in the form was impossible. The
 * index and the product form both render `$attribute->values`, so the order the
 * operator chose was silently discarded.
 *
 * Existing rows are backfilled from their id so the order they already display
 * is preserved rather than reshuffled. The index is added for the same reason
 * `attribute_values_attribute_id_index` exists: the storefront has to load the
 * ordered value list for every filterable attribute on every catalogue request.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attribute_values') || Schema::hasColumn('attribute_values', 'sort_order')) {
            return;
        }

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->integer('sort_order')->default(0)->after('value');
            $table->index('sort_order', 'attribute_values_sort_order_index');
        });

        // Preserve the order the values are currently displayed in.
        DB::table('attribute_values')->orderBy('_id')->get()->each(function ($row, $index) {
            DB::table('attribute_values')->where('_id', $row->_id)->update(['sort_order' => $index]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attribute_values') || ! Schema::hasColumn('attribute_values', 'sort_order')) {
            return;
        }

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropIndex('attribute_values_sort_order_index');
            $table->dropColumn('sort_order');
        });
    }
};
