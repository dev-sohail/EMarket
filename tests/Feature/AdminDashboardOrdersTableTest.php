<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the admin dashboard orders table.
 *
 * DataTables derives its column count from <thead> but reads row data cell by
 * cell, so it does not honour colspan. The dashboard's empty state is a single
 * <td colspan="5">, which made DataTables ask for cell 1 of a one-cell row and
 * log "Requested unknown parameter '1' for row 0, column 1" in the console.
 *
 * These tests assert the invariant that causes it: every rendered body row
 * carries one <td> per <th>, or the table is left un-enhanced.
 */
class AdminDashboardOrdersTableTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'dashboard-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        return $admin;
    }

    /**
     * @return array{0:int,1:int,2:array<int,int>,3:bool} header count, cell
     *                                                    counts per body row, cell counts, and whether any cell spans columns
     */
    protected function inspectOrdersTable(string $html): array
    {
        $this->assertMatchesRegularExpression(
            '/<table[^>]*id="ordersTable"/',
            $html,
            'The dashboard no longer renders the orders table.'
        );

        $table = preg_match('/<table[^>]*id="ordersTable".*?<\/table>/s', $html, $m) === 1
            ? $m[0]
            : '';

        preg_match('/<thead.*?<\/thead>/s', $table, $head);
        $columnCount = preg_match_all('/<th\b/', $head[0] ?? '');

        preg_match('/<tbody.*?<\/tbody>/s', $table, $bodyMatch);
        $body = $bodyMatch[0] ?? '';

        $cellCounts = [];
        preg_match_all('/<tr\b.*?<\/tr>/s', $body, $rows);

        foreach ($rows[0] as $row) {
            $cellCounts[] = preg_match_all('/<td\b/', $row);
        }

        $hasSpanningCell = preg_match('/<td[^>]*colspan/i', $body) === 1;

        return [$columnCount, count($cellCounts), $cellCounts, $hasSpanningCell];
    }

    public function test_dashboard_renders_with_the_empty_state_without_a_datatable_error(): void
    {
        $this->assertSame(0, Order::count(), 'Fixture expects an empty orders table.');

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

        $response->assertOk();

        [$columnCount, $rowCount, $cellCounts, $hasSpanningCell] = $this->inspectOrdersTable($response->getContent());

        $this->assertSame(5, $columnCount, 'The orders table header should still declare five columns.');
        $this->assertTrue($hasSpanningCell, 'Expected the colspan empty-state row.');
        $this->assertSame([1], $cellCounts, 'The empty state is a single spanning cell.');

        // The fix: a table whose only row spans columns must not be enhanced,
        // otherwise DataTables logs the unknown-parameter warning.
        $content = $response->getContent();

        $this->assertStringContainsString(
            "find('tbody td[colspan]')",
            $content,
            'The DataTable initialisation must skip tables with a spanning empty-state cell.'
        );
    }

    public function test_dashboard_renders_one_cell_per_column_when_orders_exist(): void
    {
        Order::factory()->count(3)->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

        $response->assertOk();

        [$columnCount, $rowCount, $cellCounts, $hasSpanningCell] = $this->inspectOrdersTable($response->getContent());

        $this->assertSame(5, $columnCount);
        $this->assertSame(3, $rowCount, 'Expected one body row per recent order.');
        $this->assertFalse($hasSpanningCell, 'Real data rows must not use colspan.');

        foreach ($cellCounts as $index => $count) {
            $this->assertSame(
                $columnCount,
                $count,
                "Body row {$index} has {$count} cells but the header declares {$columnCount}. "
                .'DataTables reads cells rather than column spans and will request an unknown parameter.'
            );
        }

        // With data present the table must still be enhanced.
        $this->assertStringContainsString(
            "find('tbody td[colspan]')",
            $response->getContent(),
            'The colspan guard should still be present so the table is enhanced when data is present.'
        );
    }

    public function test_cancelled_orders_are_excluded_from_the_table(): void
    {
        Order::factory()->create(['status' => 'pending']);
        Order::factory()->create(['status' => 'cancelled']);

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

        $response->assertOk();

        [, $rowCount] = $this->inspectOrdersTable($response->getContent());

        $this->assertSame(1, $rowCount, 'Cancelled orders must not reach the recent orders table.');
    }
}
