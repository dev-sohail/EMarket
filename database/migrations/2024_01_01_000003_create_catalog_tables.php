<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('parent_id', 36)->nullable();
            $table->char('name', 255);
            $table->char('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->char('image', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->char('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('parent_id', 'categories_parent_id_index');
            $table->index('is_active', 'categories_is_active_index');
            $table->index('sort_order', 'categories_sort_order_index');
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->char('slug', 255)->unique();
            $table->char('logo', 255)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->char('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('is_active', 'brands_is_active_index');
            $table->index('sort_order', 'brands_sort_order_index');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->char('slug', 255)->unique();
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->char('slug', 255)->unique();
            $table->enum('type', ['text', 'number', 'select', 'multiselect', 'color']);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_variant')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('slug', 'attributes_slug_index');
            $table->index('is_filterable', 'attributes_is_filterable_index');
            $table->index('is_variant', 'attributes_is_variant_index');
            $table->index('sort_order', 'attributes_sort_order_index');
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('attribute_id', 36)->nullable();
            $table->char('value', 255);
            $table->timestamps();
            $table->index('attribute_id', 'attribute_values_attribute_id_index');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->char('slug', 255)->unique();
            $table->char('sku', 255)->unique();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->text('specifications')->nullable();
            $table->decimal('base_price', 12, 2);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->char('discount_type', 255)->default('none');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'on_backorder'])->default('in_stock');
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('return_allowed')->default(true);
            $table->char('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('sku', 'products_sku_index');
            $table->index('is_active', 'products_is_active_index');
            $table->index('is_featured', 'products_is_featured_index');
            $table->index('is_new', 'products_is_new_index');
            $table->index('published_at', 'products_published_at_index');
            $table->index('base_price', 'products_base_price_index');
            $table->index('stock_status', 'products_stock_status_index');
            $table->index('slug', 'products_slug_index');
            $table->index('return_allowed', 'products_return_allowed_index');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('product_id', 36)->nullable();
            $table->char('sku', 255)->unique();
            $table->char('name', 255)->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->integer('price_add_to_main')->default(false);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'on_backorder'])->default('in_stock');
            $table->decimal('weight', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('image', 255)->nullable();
            $table->char('combination_key', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('product_id', 'product_variants_product_id_index');
            $table->index('sku', 'product_variants_sku_index');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('product_id', 36)->nullable();
            $table->string('variant_id', 36)->nullable();
            $table->char('path', 255);
            $table->char('alt_text', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index('product_id', 'product_images_product_id_index');
            $table->index('variant_id', 'product_images_variant_id_index');
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->string('product_id', 36);
            $table->string('category_id', 36);
            $table->timestamps();
            $table->index('product_id', 'product_categories_product_id_index');
            $table->index('category_id', 'product_categories_category_id_index');
        });

        Schema::create('product_brands', function (Blueprint $table) {
            $table->string('product_id', 36);
            $table->string('brand_id', 36);
            $table->timestamps();
            $table->index('product_id', 'product_brands_product_id_index');
            $table->index('brand_id', 'product_brands_brand_id_index');
        });

        Schema::create('product_tags', function (Blueprint $table) {
            $table->string('product_id', 36);
            $table->string('tag_id', 36);
            $table->timestamps();
            $table->index('product_id', 'product_tags_product_id_index');
            $table->index('tag_id', 'product_tags_tag_id_index');
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->string('product_id', 36);
            $table->string('attribute_id', 36);
            $table->string('attribute_value_id', 36);
            $table->timestamps();
            $table->index('product_id', 'product_attribute_values_product_id_index');
            $table->index('attribute_id', 'product_attribute_values_attribute_id_index');
            $table->index('attribute_value_id', 'product_attribute_values_attribute_value_id_index');
        });

        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->string('product_variant_id', 36);
            $table->string('attribute_id', 36);
            $table->string('attribute_value_id', 36);
            $table->timestamps();
            $table->index('product_variant_id', 'variant_attribute_values_product_variant_id_index');
            $table->index('attribute_id', 'variant_attribute_values_attribute_id_index');
            $table->index('attribute_value_id', 'variant_attribute_values_attribute_value_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_brands');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
