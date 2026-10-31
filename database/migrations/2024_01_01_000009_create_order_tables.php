<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('order_id', 36)->nullable();
            $table->string('product_id', 36)->nullable();
            $table->string('variant_id', 36)->nullable();
            $table->char('product_name', 255);
            $table->char('sku', 255);
            $table->char('variant_name', 255)->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
            $table->index('order_id', 'order_items_order_id_index');
            $table->index('product_id', 'order_items_product_id_index');
            $table->index('variant_id', 'order_items_variant_id_index');
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('order_id', 36)->nullable();
            $table->enum('type', ['shipping', 'billing']);
            $table->char('full_name', 255);
            $table->char('phone', 255);
            $table->char('address_line_1', 255);
            $table->char('address_line_2', 255)->nullable();
            $table->char('city', 255);
            $table->char('state', 255);
            $table->char('postal_code', 255)->nullable();
            $table->timestamps();
            $table->index('order_id', 'order_addresses_order_id_index');
        });

        Schema::create('order_charges', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('order_id', 36)->nullable();
            $table->char('charge_type', 255);
            $table->char('name', 255);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->index('order_id', 'order_charges_order_id_index');
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('order_id', 36)->nullable();
            $table->string('from_status', 255)->nullable();
            $table->string('to_status', 255);
            $table->string('note', 255)->nullable();
            $table->string('changed_by', 36)->nullable();
            $table->timestamps();
            $table->index('changed_by', 'order_status_histories_changed_by_index');
            $table->index('order_id', 'order_status_histories_order_id_index');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('invoice_number', 255)->unique();
            $table->string('order_id', 36)->nullable();
            $table->string('customer_id', 36)->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('additional_charges', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->char('payment_method', 255)->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            $table->index('order_id', 'invoices_order_id_index');
            $table->index('customer_id', 'invoices_customer_id_index');
            $table->index('payment_status', 'invoices_payment_status_index');
            $table->index('issued_at', 'invoices_issued_at_index');
            $table->index('created_at', 'invoices_created_at_index');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('invoice_id', 36)->nullable();
            $table->string('order_item_id', 36)->nullable();
            $table->char('product_name', 255);
            $table->char('sku', 255);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
            $table->index('invoice_id', 'invoice_items_invoice_id_index');
            $table->index('order_item_id', 'invoice_items_order_item_id_index');
            $table->index('sku', 'invoice_items_sku_index');
        });

        Schema::create('returns', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('return_number', 255)->unique();
            $table->string('order_id', 36)->nullable();
            $table->string('customer_id', 36)->nullable();
            $table->enum('status', ['requested', 'under_review', 'approved', 'rejected', 'returned', 'completed'])->default('requested');
            $table->text('description')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('order_id', 'returns_order_id_index');
            $table->index('customer_id', 'returns_customer_id_index');
            $table->index('status', 'returns_status_index');
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('return_id', 36)->nullable();
            $table->string('order_item_id', 36)->nullable();
            $table->string('return_reason_id', 36)->nullable();
            $table->integer('quantity');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('return_reason_id', 'return_items_return_reason_id_index');
            $table->index('return_id', 'return_items_return_id_index');
            $table->index('order_item_id', 'return_items_order_item_id_index');
        });

        Schema::create('return_reasons', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('reason', 255);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('is_active', 'return_reasons_is_active_index');
            $table->index('sort_order', 'return_reasons_sort_order_index');
        });

        Schema::create('return_status_histories', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('return_id', 36)->nullable();
            $table->string('from_status', 255)->nullable();
            $table->string('to_status', 255);
            $table->text('note')->nullable();
            $table->string('changed_by', 255)->nullable();
            $table->timestamps();
            $table->index('return_id', 'return_status_histories_return_id_index');
            $table->index('changed_by', 'return_status_histories_changed_by_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_status_histories');
        Schema::dropIfExists('return_reasons');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_charges');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
    }
};
