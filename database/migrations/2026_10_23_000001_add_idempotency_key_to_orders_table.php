<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'idempotency_key')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->unique()->after('_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'idempotency_key')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
