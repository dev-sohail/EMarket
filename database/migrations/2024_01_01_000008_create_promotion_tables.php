<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('code', 255)->unique();
            $table->enum('type', ['percentage', 'fixed', 'free_shipping']);
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('minimum_order', 12, 2)->default(0);
            $table->decimal('maximum_discount', 12, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('per_customer_limit')->default(1);
            $table->integer('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('type', 'coupons_type_index');
            $table->index('is_active', 'coupons_is_active_index');
        });

        Schema::create('coupon_categories', function (Blueprint $table) {
            $table->string('coupon_id', 36)->nullable();
            $table->string('category_id', 36)->nullable();
            $table->timestamps();
            $table->index('coupon_id', 'coupon_categories_coupon_id_index');
            $table->index('category_id', 'coupon_categories_category_id_index');
        });

        Schema::create('coupon_products', function (Blueprint $table) {
            $table->string('coupon_id', 36)->nullable();
            $table->string('product_id', 36)->nullable();
            $table->timestamps();
            $table->index('coupon_id', 'coupon_products_coupon_id_index');
            $table->index('product_id', 'coupon_products_product_id_index');
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('coupon_id', 36)->nullable();
            $table->string('customer_id', 36)->nullable();
            $table->string('order_id', 36)->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index('coupon_id', 'coupon_usages_coupon_id_index');
            $table->index('customer_id', 'coupon_usages_customer_id_index');
            $table->index('order_id', 'coupon_usages_order_id_index');
        });

        Schema::create('discount_campaigns', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 12, 2);
            $table->decimal('minimum_order', 12, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('is_active', 'discount_campaigns_is_active_index');
        });

        Schema::create('campaign_categories', function (Blueprint $table) {
            $table->string('campaign_id', 36)->nullable();
            $table->string('category_id', 36)->nullable();
            $table->timestamps();
            $table->index('campaign_id', 'campaign_categories_campaign_id_index');
            $table->index('category_id', 'campaign_categories_category_id_index');
        });

        Schema::create('campaign_products', function (Blueprint $table) {
            $table->string('campaign_id', 36)->nullable();
            $table->string('product_id', 36)->nullable();
            $table->decimal('campaign_price', 12, 2)->nullable();
            $table->timestamps();
            $table->index('campaign_id', 'campaign_products_campaign_id_index');
            $table->index('product_id', 'campaign_products_product_id_index');
        });

        Schema::create('flash_sales', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->decimal('sale_price', 12, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('is_active', 'flash_sales_is_active_index');
        });

        Schema::create('flash_sale_categories', function (Blueprint $table) {
            $table->string('flash_sale_id', 36)->nullable();
            $table->string('category_id', 36)->nullable();
            $table->timestamps();
            $table->index('flash_sale_id', 'flash_sale_categories_flash_sale_id_index');
            $table->index('category_id', 'flash_sale_categories_category_id_index');
        });

        Schema::create('flash_sale_products', function (Blueprint $table) {
            $table->string('flash_sale_id', 36)->nullable();
            $table->string('product_id', 36)->nullable();
            $table->decimal('flash_price', 12, 2)->default(0);
            $table->integer('quantity')->nullable();
            $table->integer('sold')->default(0);
            $table->timestamps();
            $table->index('flash_sale_id', 'flash_sale_products_flash_sale_id_index');
            $table->index('product_id', 'flash_sale_products_product_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_products');
        Schema::dropIfExists('flash_sale_categories');
        Schema::dropIfExists('flash_sales');
        Schema::dropIfExists('campaign_products');
        Schema::dropIfExists('campaign_categories');
        Schema::dropIfExists('discount_campaigns');
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupon_products');
        Schema::dropIfExists('coupon_categories');
        Schema::dropIfExists('coupons');
    }
};
