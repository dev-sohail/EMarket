<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('customer_id', 36)->nullable();
            $table->string('session_id', 255)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->timestamps();
            $table->index('customer_id', 'carts_customer_id_index');
            $table->index('session_id', 'carts_session_id_index');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('cart_id', 36)->nullable();
            $table->string('product_id', 36)->nullable();
            $table->string('variant_id', 36)->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();
            $table->index('cart_id', 'cart_items_cart_id_index');
            $table->index('product_id', 'cart_items_product_id_index');
            $table->index('variant_id', 'cart_items_variant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
