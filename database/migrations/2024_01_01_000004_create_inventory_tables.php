<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('product_id', 36)->nullable();
            $table->string('variant_id', 36)->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->integer('max_stock')->default(0);
            $table->timestamps();
            $table->index('product_id', 'inventories_product_id_index');
            $table->index('variant_id', 'inventories_variant_id_index');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('inventory_id', 36)->nullable();
            $table->string('performed_by', 255)->nullable();
            $table->string('reference_id', 255)->nullable();
            $table->char('reference_type', 255)->nullable();
            $table->enum('type', ['in', 'out', 'adjustment'])->default('in');
            $table->integer('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('reference_type', 'inventory_movements_reference_type_index');
            $table->index('inventory_id', 'inventory_movements_inventory_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventories');
    }
};
