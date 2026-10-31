<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('customer_id', 36)->nullable();
            $table->string('product_id', 36)->nullable();
            $table->string('order_id', 36)->nullable();
            $table->unsignedInteger('rating');
            $table->char('title', 255)->nullable();
            $table->text('comment')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_verified_purchase')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('customer_id', 'reviews_customer_id_index');
            $table->index('product_id', 'reviews_product_id_index');
            $table->index('order_id', 'reviews_order_id_index');
            $table->index('status', 'reviews_status_index');
            $table->index('rating', 'reviews_rating_index');
        });

        Schema::create('review_images', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('review_id', 36)->nullable();
            $table->char('path', 255);
            $table->timestamps();
            $table->index('review_id', 'review_images_review_id_index');
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('customer_id', 36)->unique();
            $table->timestamps();
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('wishlist_id', 36)->nullable();
            $table->string('product_id', 36)->nullable();
            $table->timestamps();
            $table->index('product_id', 'wishlist_items_product_id_index');
            $table->index('wishlist_id', 'wishlist_items_wishlist_id_index');
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('is_active', 'shipping_zones_is_active_index');
            $table->index('sort_order', 'shipping_zones_sort_order_index');
        });

        Schema::create('shipping_zone_cities', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('shipping_zone_id', 36)->nullable();
            $table->char('city', 255);
            $table->timestamps();
            $table->index('shipping_zone_id', 'shipping_zone_cities_shipping_zone_id_index');
            $table->index('city', 'shipping_zone_cities_city_index');
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->text('description')->nullable();
            $table->decimal('base_cost', 12, 2)->default(0);
            $table->decimal('per_kg_cost', 12, 2)->nullable();
            $table->integer('estimated_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('is_active', 'shipping_methods_is_active_index');
            $table->index('sort_order', 'shipping_methods_sort_order_index');
        });

        Schema::create('shipping_rules', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('shipping_zone_id', 36)->nullable();
            $table->string('shipping_method_id', 36)->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('free_shipping_threshold', 12, 2)->nullable();
            $table->decimal('min_weight', 8, 2)->nullable();
            $table->decimal('max_weight', 8, 2)->nullable();
            $table->decimal('min_order_value', 12, 2)->nullable();
            $table->decimal('max_order_value', 12, 2)->nullable();
            $table->integer('min_quantity')->nullable();
            $table->integer('max_quantity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('shipping_zone_id', 'shipping_rules_shipping_zone_id_index');
            $table->index('shipping_method_id', 'shipping_rules_shipping_method_id_index');
            $table->index('is_active', 'shipping_rules_is_active_index');
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->char('code', 255)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_cod')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('stripe_enabled')->default(false);
            $table->boolean('stripe_sandbox')->default(false);
            $table->char('stripe_publishable_key', 255)->nullable();
            $table->char('stripe_secret_key', 255)->nullable();
            $table->timestamps();
            $table->index('is_active', 'payment_methods_is_active_index');
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->decimal('rate', 5, 2);
            $table->enum('type', ['percentage', 'fixed']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active', 'tax_rules_is_active_index');
        });

        Schema::create('tax_rule_targets', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('tax_rule_id', 36)->nullable();
            $table->enum('target_type', ['product', 'category', 'global']);
            $table->string('target_id', 36)->nullable();
            $table->timestamps();
            $table->index('tax_rule_id', 'tax_rule_targets_tax_rule_id_index');
            $table->index('target_type', 'tax_rule_targets_target_type_index');
            $table->index('target_id', 'tax_rule_targets_target_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rule_targets');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('shipping_rules');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zone_cities');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('review_images');
        Schema::dropIfExists('reviews');
    }
};
