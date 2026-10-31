<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collapse duplicate inventory rows that predate the unique index, moving
     * their movements onto the surviving row so the index can be created.
     */
    protected function mergeDuplicates(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }

        $rows = DB::table('inventories')->get();
        $groups = [];

        foreach ($rows as $row) {
            $key = (string) $row->product_id."\x1f".(string) ($row->variant_id ?? '');
            $groups[$key][] = $row;
        }

        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            usort($group, static function ($a, $b) {
                return $a->_id <=> $b->_id;
            });

            $keeper = $group[0];
            $duplicateIds = array_map(static fn ($row) => $row->_id, array_slice($group, 1));

            $totalQuantity = 0;
            $totalReserved = 0;
            foreach ($group as $row) {
                $totalQuantity += (int) $row->quantity;
                $totalReserved += (int) $row->reserved_quantity;
            }

            DB::table('inventories')->where('_id', $keeper->_id)->update([
                'quantity' => $totalQuantity,
                'reserved_quantity' => $totalReserved,
            ]);

            if (Schema::hasTable('inventory_movements')) {
                DB::table('inventory_movements')
                    ->whereIn('inventory_id', $duplicateIds)
                    ->update(['inventory_id' => $keeper->_id]);
            }

            DB::table('inventories')->whereIn('_id', $duplicateIds)->delete();
        }
    }

    public function up(): void
    {
        $this->mergeDuplicates();

        $driver = DB::getDriverName();
        $index = 'inventories_product_variant_unique';

        if ($driver === 'mysql' || $driver === 'mariadb') {
            if (! Schema::hasColumn('inventories', 'variant_key')) {
                DB::statement("ALTER TABLE inventories ADD COLUMN variant_key VARCHAR(36) GENERATED ALWAYS AS (COALESCE(variant_id, '')) STORED");
            }

            if (! Schema::hasIndex('inventories', $index)) {
                DB::statement("CREATE UNIQUE INDEX {$index} ON inventories (product_id, variant_key)");
            }

            return;
        }

        if ($driver === 'sqlite') {
            if (! Schema::hasIndex('inventories', $index)) {
                DB::statement("CREATE UNIQUE INDEX {$index} ON inventories (product_id, COALESCE(variant_id, ''))");
            }

            return;
        }

        // Fallback for other drivers.
        try {
            if (! Schema::hasIndex('inventories', $index)) {
                DB::statement("CREATE UNIQUE INDEX {$index} ON inventories (product_id, COALESCE(variant_id, ''))");
            }
        } catch (Throwable $e) {
            // ignore unsupported drivers
        }
    }

    public function down(): void
    {
        $index = 'inventories_product_variant_unique';

        if (Schema::hasIndex('inventories', $index)) {
            try {
                Schema::table('inventories', function ($table) use ($index) {
                    $table->dropIndex($index);
                });
            } catch (Throwable $e) {
                // Best-effort; the index may already be absent.
            }
        }

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            if (Schema::hasColumn('inventories', 'variant_key')) {
                try {
                    Schema::table('inventories', function ($table) {
                        $table->dropColumn('variant_key');
                    });
                } catch (Throwable $e) {
                    // Best-effort cleanup.
                }
            }
        }
    }
};
