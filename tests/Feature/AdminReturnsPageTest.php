<?php

namespace Tests\Feature;

use App\Enums\ReturnStatus;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the admin returns screen
 * (resources/views/admin/returns/index.blade.php).
 *
 * Defects this page has shipped before:
 *
 *  1. The status badge map was keyed on 'pending', a status ReturnStatus does
 *     not define. Every row fell through to the fallback, so Requested, Under
 *     Review and Returned all rendered identically and the status column
 *     carried no information.
 *  2. The status filter existed in the controller but had no UI: the tab strip
 *     did not exist, so `?status=` was only reachable by hand-editing the URL.
 *  3. An unrecognised `?status=` value reached `where status = <garbage>` and
 *     returned an empty page that read as "no returns exist".
 */
class AdminReturnsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ReturnController@index aborts 404 unless returns_enabled is truthy.
        Setting::create(['group' => 'return', 'key' => 'returns_enabled', 'value' => 'true']);
        setting_forget();
    }

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'returns-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);
        foreach (['returns.view', 'returns.update'] as $name) {
            $role->permissions()->attach(
                Permission::create([
                    'name' => $name,
                    'slug' => str_replace('.', '-', $name),
                    'group_name' => 'returns',
                ])->getKey()
            );
        }

        return $admin;
    }

    protected function page(User $actor, string $query = ''): string
    {
        return $this->actingAs($actor, 'admin')
            ->get('/admin/returns'.$query)
            ->assertOk()
            ->getContent();
    }

    public function test_the_page_lists_returns_with_customer_and_order(): void
    {
        $admin = $this->admin();
        $return = ProductReturn::factory()->create(['status' => ReturnStatus::Requested]);

        $html = $this->page($admin);

        $this->assertStringContainsString($return->return_number, $html);
        $this->assertStringContainsString($return->order->order_number, $html);
        $this->assertStringContainsString($return->customer->email, $html);
    }

    public function test_every_status_label_from_the_enum_is_rendered_on_the_tab_strip(): void
    {
        $html = $this->page($this->admin());

        foreach (ReturnStatus::cases() as $case) {
            $this->assertStringContainsString($case->label(), $html, "missing tab for {$case->value}");
        }
    }

    public function test_a_requested_return_shows_its_status_label(): void
    {
        $admin = $this->admin();
        ProductReturn::factory()->create(['status' => ReturnStatus::Requested]);

        $html = $this->page($admin);

        $this->assertStringContainsString('Requested', $html);
    }

    public function test_a_return_stuck_in_under_review_shows_its_own_label(): void
    {
        $admin = $this->admin();
        ProductReturn::factory()->create(['status' => ReturnStatus::UnderReview]);

        $html = $this->page($admin);

        $this->assertStringContainsString('Under Review', $html);
    }

    public function test_the_status_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        $requested = ProductReturn::factory()->create(['status' => ReturnStatus::Requested]);
        $completed = ProductReturn::factory()->create(['status' => ReturnStatus::Completed]);

        $html = $this->page($admin, '?status=completed');

        $this->assertStringContainsString($completed->return_number, $html);
        $this->assertStringNotContainsString($requested->return_number, $html);
    }

    public function test_an_unknown_status_is_ignored_rather_than_blanking_the_page(): void
    {
        $admin = $this->admin();
        $return = ProductReturn::factory()->create(['status' => ReturnStatus::Requested]);

        $html = $this->page($admin, '?status=not-a-status');

        $this->assertStringContainsString($return->return_number, $html);
        $this->assertStringNotContainsString('No returns match this view', $html);
    }

    public function test_the_search_filter_matches_return_number(): void
    {
        $admin = $this->admin();
        $match = ProductReturn::factory()->create([
            'return_number' => 'RET-20260101-ABCDEF',
            'status' => ReturnStatus::Requested,
        ]);
        ProductReturn::factory()->create([
            'return_number' => 'RET-20260101-ZZZZZZ',
            'status' => ReturnStatus::Requested,
        ]);

        $html = $this->page($admin, '?q=ABCDEF');

        $this->assertStringContainsString($match->return_number, $html);
        $this->assertStringNotContainsString('RET-20260101-ZZZZZZ', $html);
    }

    public function test_the_search_filter_matches_customer_email(): void
    {
        $admin = $this->admin();
        $match = ProductReturn::factory()->create(['status' => ReturnStatus::Requested]);
        $match->customer->update(['email' => 'returns-pro@example.test']);
        ProductReturn::factory()->create(['status' => ReturnStatus::Requested]);

        $html = $this->page($admin, '?q=returns-pro');

        $this->assertStringContainsString($match->return_number, $html);
    }

    public function test_pending_rows_offer_an_approve_action(): void
    {
        $admin = $this->admin();
        $pending = ProductReturn::factory()->create(['status' => ReturnStatus::Requested]);
        $done = ProductReturn::factory()->create(['status' => ReturnStatus::Completed]);

        $html = $this->page($admin);

        $this->assertStringContainsString(route('admin.returns.approve', $pending->getKey()), $html);
        $this->assertStringNotContainsString(route('admin.returns.approve', $done->getKey()), $html);
    }

    public function test_the_item_count_is_shown_per_return(): void
    {
        $admin = $this->admin();
        $return = ProductReturn::factory()->create(['status' => ReturnStatus::Requested]);

        // The factory ships no return items, so count 3 would silently read 0.
        ReturnItem::create([
            'return_id' => $return->getKey(),
            'order_item_id' => OrderItem::factory()->create(),
            'quantity' => 1,
        ]);
        ReturnItem::create([
            'return_id' => $return->getKey(),
            'order_item_id' => OrderItem::factory()->create(),
            'quantity' => 2,
        ]);

        $html = $this->page($admin);

        $this->assertMatchesRegularExpression('/<td class="px-6 py-4 text-sm text-\[var\(--color-steel\)\]">2<\/td>/', $html);
    }

    public function test_the_empty_state_distinguishes_no_returns_from_no_matches(): void
    {
        $admin = $this->admin();

        $empty = $this->page($admin);
        $this->assertStringContainsString('No returns yet', $empty);

        ProductReturn::factory()->create(['status' => ReturnStatus::Requested]);

        $filtered = $this->page($admin, '?status=completed');
        $this->assertStringContainsString('No returns match this view', $filtered);
    }
}
