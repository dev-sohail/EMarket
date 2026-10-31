<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Inventory\InventoryService;
use App\Services\Report\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression coverage for the model/schema drift found by ModelSchemaAlignmentTest.
 *
 * Every test here failed before the fix, in a way that produced no error:
 * Eloquent drops an unknown attribute on insert and returns null when it is
 * read, so each of these was quietly losing data or returning an empty result
 * rather than crashing. They are written so that removing the corresponding fix
 * makes them fail again.
 */
class SchemaDriftRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- inventory notes

    public function test_an_inventory_movement_persists_its_note(): void
    {
        $product = Product::factory()->create();
        $inventory = Inventory::factory()->create([
            'product_id' => $product->getKey(),
            'quantity' => 10,
        ]);

        app(InventoryService::class)->adjustStock(
            $product->getKey(),
            null,
            -3,
            'Damaged in transit',
        );

        $movement = $inventory->movements()->latest('created_at')->first();

        $this->assertNotNull($movement, 'An adjustment must be logged.');
        $this->assertSame(
            'Damaged in transit',
            $movement->notes,
            'The note is written to the `notes` column; the model used to name `note`, '
            .'which does not exist, so every note was discarded on insert.',
        );
    }

    public function test_the_default_note_is_persisted_when_none_is_given(): void
    {
        $product = Product::factory()->create();
        $inventory = Inventory::factory()->create([
            'product_id' => $product->getKey(),
            'quantity' => 10,
        ]);

        app(InventoryService::class)->adjustStock($product->getKey(), null, -1, null);

        $this->assertSame(
            'Manual stock adjustment',
            $inventory->movements()->latest('created_at')->first()->notes,
        );
    }

    // ------------------------------------------------------- low stock report

    public function test_the_low_stock_report_lists_items_at_or_below_their_reorder_level(): void
    {
        $low = Product::factory()->create();
        $healthy = Product::factory()->create();

        Inventory::factory()->create([
            'product_id' => $low->getKey(),
            'quantity' => 2,
            'reorder_level' => 5,
        ]);

        Inventory::factory()->create([
            'product_id' => $healthy->getKey(),
            'quantity' => 40,
            'reorder_level' => 5,
        ]);

        $report = app(ReportService::class)->inventoryReport();

        $ids = collect($report['low_stock_items'])->pluck('product_id')->all();

        $this->assertContains($low->getKey(), $ids);
        $this->assertNotContains($healthy->getKey(), $ids);
    }

    public function test_the_report_respects_each_rows_own_reorder_level(): void
    {
        // `reorder_level` is NOT NULL, so the threshold is per row rather than a
        // single global default: an item is low only against its own level.
        $generous = Product::factory()->create();
        $strict = Product::factory()->create();

        Inventory::factory()->create([
            'product_id' => $generous->getKey(),
            'quantity' => 8,
            'reorder_level' => 10,
        ]);

        Inventory::factory()->create([
            'product_id' => $strict->getKey(),
            'quantity' => 8,
            'reorder_level' => 2,
        ]);

        $ids = collect(app(ReportService::class)->inventoryReport()['low_stock_items'])
            ->pluck('product_id')
            ->all();

        $this->assertContains($generous->getKey(), $ids);
        $this->assertNotContains($strict->getKey(), $ids);
    }

    public function test_out_of_stock_items_are_not_reported_as_low_stock(): void
    {
        $product = Product::factory()->create();
        Inventory::factory()->create([
            'product_id' => $product->getKey(),
            'quantity' => 0,
            'reorder_level' => 5,
        ]);

        $report = app(ReportService::class)->inventoryReport();

        $this->assertNotContains(
            $product->getKey(),
            collect($report['low_stock_items'])->pluck('product_id')->all(),
        );
    }

    // ---------------------------------------------------- customer addresses

    public function test_an_address_can_be_created_with_the_columns_the_table_has(): void
    {
        $customer = Customer::factory()->create();

        $address = $customer->addresses()->create([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+41 44 000 00 00',
            'address_line_1' => '1 Computing Way',
            'address_line_2' => null,
            'city' => 'Zurich',
            'state' => 'Zurich',
            'postal_code' => '8001',
            'country' => 'CH',
            'is_default' => true,
        ]);

        $this->assertNotNull($address->fresh());
        $this->assertSame('Ada', $address->fresh()->first_name);
        $this->assertSame('CH', $address->fresh()->country);
    }

    public function test_a_factory_built_address_is_insertable(): void
    {
        // The factory used to set `label` and `full_name`, which the table has
        // no column for, so every address it produced failed to insert.
        $address = CustomerAddress::factory()->create();

        $this->assertNotNull($address->fresh());
        $this->assertNotEmpty($address->first_name);
        $this->assertNotEmpty($address->last_name);
    }

    public function test_the_customer_name_accessor_composes_first_and_last_name(): void
    {
        $customer = Customer::factory()->create([
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
        ]);

        $this->assertSame('Grace Hopper', $customer->full_name);
    }

    // ------------------------------------------------------------- settings

    public function test_a_setting_round_trips_through_the_helper(): void
    {
        Setting::create(['group' => 'general', 'key' => 'store_name', 'value' => 'Demo Store']);
        Cache::forget('settings.all');

        // The table did not exist, so the helper caught "no such table" and
        // returned the default no matter what was stored.
        $this->assertSame('Demo Store', setting('store_name'));
    }

    public function test_the_settings_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('settings'),
            'The Setting model, seeder, admin controller and setting() helper all '
            .'require this table, but no migration created it.',
        );
    }

    public function test_an_unknown_setting_still_returns_its_default(): void
    {
        Cache::forget('settings.all');

        $this->assertSame('fallback', setting('does_not_exist', 'fallback'));
    }
}
