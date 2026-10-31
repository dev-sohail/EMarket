<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The base schema now creates `owner` itself, so this only has to do
        // anything on a database that predates the fix. It must stay guarded:
        // re-adding the column on a fresh install would fail with a duplicate
        // column, and this migration is skipped once it has run.
        if (Schema::hasColumn('cache_locks', 'owner')) {
            return;
        }

        Schema::table('cache_locks', function (Blueprint $table) {
            $table->string('owner')->after('key');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cache_locks', 'owner')) {
            return;
        }

        Schema::table('cache_locks', function (Blueprint $table) {
            $table->dropColumn('owner');
        });
    }
};
