<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `cache_locks` shipped with a stray `owner_id` column (NOT NULL, no default)
 * left over from the pre-prefixed-key schema, while Illuminate\Cache\DatabaseLock
 * writes `owner`. Under STRICT_TRANS_TABLES every lock insert was rejected, the
 * fallback update matched no row, and Cache::lock() never acquired — which made
 * `block(30)` in CheckoutService time out after 30 seconds and every checkout
 * fail with the generic "We encountered an error while processing your order".
 *
 * The base migration now creates the table correctly and the `owner` column was
 * added by 2026_10_04_093700, so this only has to remove the leftover column on
 * databases that already existed. Guarded, so it is a no-op on a fresh install.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cache_locks', 'owner_id')) {
            Schema::table('cache_locks', function (Blueprint $table) {
                $table->dropColumn('owner_id');
            });
        }

        if (! Schema::hasColumn('cache_locks', 'owner')) {
            Schema::table('cache_locks', function (Blueprint $table) {
                $table->string('owner')->after('key');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cache_locks', 'owner_id')) {
            Schema::table('cache_locks', function (Blueprint $table) {
                $table->string('owner_id')->nullable()->after('owner');
            });
        }
    }
};
