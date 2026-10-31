<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * inventory_movements.type was created as enum('in','out','adjustment'), but
 * InventoryService logs every movement as an InventoryMovementType: stock_in,
 * sale, return, adjustment, damage, correction, reserved or released. Under
 * STRICT_TRANS_TABLES (the production sql_mode) inserting 'sale' fails with
 * "Data truncated for column 'type'", so every stock adjustment — and with it
 * every order that moves stock — raised a 500. Under a permissive mode it
 * wrote '' instead, so the movements screen filtered on values nothing could
 * ever hold.
 *
 * The column is widened, not narrowed, so no existing row is affected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->enum('type', [
                'stock_in',
                'sale',
                'return',
                'adjustment',
                'damage',
                'correction',
                'reserved',
                'released',
            ])->default('adjustment')->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->enum('type', ['in', 'out', 'adjustment'])->default('in')->change();
        });
    }
};
