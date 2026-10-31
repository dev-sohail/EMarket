<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an operator decide which categories appear in the storefront home page
 * grid ("Shop by Category") instead of every active top-level category being
 * listed there unconditionally.
 *
 * Defaults to true so that the column matches today's behaviour on every
 * existing row: the home page currently lists all active top-level categories,
 * and defaulting to false would silently empty that grid on deploy. Operators
 * opt categories out from the edit screen, one at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || Schema::hasColumn('categories', 'show_on_home')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('show_on_home')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('show_on_home');
        });
    }
};
