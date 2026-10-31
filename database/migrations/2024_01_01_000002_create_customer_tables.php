<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('user_id', 36)->nullable();
            $table->char('first_name', 255);
            $table->char('last_name', 255);
            $table->char('email', 255)->unique();
            $table->char('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('user_id', 'customers_user_id_index');
            $table->index('phone', 'customers_phone_index');
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('customer_id', 36)->nullable();
            $table->char('first_name', 255);
            $table->char('last_name', 255);
            $table->char('email', 255);
            $table->char('phone', 20);
            $table->char('company', 255)->nullable();
            $table->char('address_line_1', 255);
            $table->char('address_line_2', 255)->nullable();
            $table->char('city', 255);
            $table->char('state', 255);
            $table->char('postal_code', 20);
            $table->char('country', 2);
            $table->boolean('is_default')->default(false);
            $table->char('type', 20)->default('shipping');
            $table->timestamps();
            $table->softDeletes();
            $table->index('customer_id', 'customer_addresses_customer_id_index');
            $table->index('is_default', 'customer_addresses_is_default_index');
        });

        Schema::create('customer_verifications', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('customer_id', 36)->nullable();
            $table->char('type', 20);
            $table->char('code', 255);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index('customer_id', 'customer_verifications_customer_id_index');
            $table->index(['type', 'code'], 'customer_verifications_type_code_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_verifications');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
