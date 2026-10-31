<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('order_number')->unique();
            $table->string('tracking_token')->nullable()->unique();
            $table->string('cart_id', 36)->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('customer_id', 36)->nullable();
            $table->string('user_id', 36)->nullable();
            $table->enum('status', [
                'pending',
                'confirmed',
                'processing',
                'ready_to_ship',
                'shipped',
                'out_for_delivery',
                'delivered',
                'completed',
                'returned',
                'cancelled',
            ])->default('pending');
            $table->string('payment_method')->default('cod');
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->enum('fulfillment_status', ['pending', 'in_warehouse', 'picked', 'packed', 'shipped', 'delivered'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->char('currency', 3)->nullable();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_client_secret')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('additional_charges', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->text('notes')->nullable();
            $table->string('coupon_code')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'orders_status_index');
            $table->index('payment_status', 'orders_payment_status_index');
            $table->index('created_at', 'orders_created_at_index');
            $table->index('tracking_number', 'orders_tracking_number_index');
            $table->index('fulfillment_status', 'orders_fulfillment_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
