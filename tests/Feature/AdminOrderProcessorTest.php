<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the order processor
 * (resources/views/admin/order-processor/index.blade.php and
 * OrderProcessorController::index).
 *
 * Defects this screen shipped with:
 *
 *  1. The status pill was built from an interpolated class name,
 *     `bg-{{ $color }}-100 text-{{ $color }}-800`. Tailwind compiles classes by
 *     scanning source text, so it cannot see a name it has to assemble at
 *     runtime. `indigo` (Processing) and `cyan` (Out for Delivery) appear
 *     nowhere else in the codebase, so those two pills were emitted with no
 *     colour or background at all while the other six rendered fine.
 *  2. The Verify button used an inline `@click="openVerifyModal(...)"`
 *     handler. The app ships a nonce CSP with no 'unsafe-inline', so the
 *     handler is blocked and the button does nothing.
 *  3. The status tabs were built from `status` alone, discarding the search and
 *     page size.
 *  4. `status` was never validated, so an unknown value returned a silently
 *     empty list, and the empty state dereferenced an undefined array key.
 */
class AdminOrderProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'processor-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);

        foreach (['orders.view', 'orders.update'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'orders',
            ])->getKey());
        }

        return $admin;
    }

    protected function page(User $actor, array $query = []): string
    {
        return $this->actingAs($actor, 'admin')
            ->get(route('admin.order-processor.index', $query))
            ->assertOk()
            ->getContent();
    }

    public function test_status_pills_use_literal_classes_rather_than_interpolated_ones(): void
    {
        $admin = $this->admin();

        Order::factory()->create(['order_number' => 'OP-1', 'status' => 'processing', 'grand_total' => 10]);

        $html = $this->page($admin, ['status' => 'processing']);

        // An interpolated class name is invisible to Tailwind's scanner, so the
        // utility never reaches the stylesheet and the pill renders unstyled.
        $this->assertStringNotContainsString('bg-{{', $html);
        $this->assertStringNotContainsString('$currentStatus', $html);
        $this->assertMatchesRegularExpression(
            '/data-status="processing"\s+class="[^"]*bg-\[[^\]]+\]/',
            $html,
            'the Processing pill has no literal background class'
        );
    }

    public function test_every_pipeline_status_renders_a_toned_pill(): void
    {
        $admin = $this->admin();

        foreach (['pending', 'confirmed', 'processing', 'ready_to_ship', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'] as $status) {
            Order::factory()->create([
                'order_number' => 'OP-'.$status,
                'status' => $status,
                'grand_total' => 10,
            ]);
        }

        $html = $this->page($admin, ['status' => 'processing']);
        $html .= $this->page($admin, ['status' => 'out_for_delivery']);

        foreach (['processing', 'out_for_delivery'] as $status) {
            $this->assertMatchesRegularExpression(
                '/data-status="'.$status.'"\s+class="[^"]*bg-/',
                $html,
                "the {$status} pill has no background"
            );
        }
    }

    public function test_no_inline_event_handler_drives_the_row_actions(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['order_number' => 'OP-1', 'status' => 'pending', 'grand_total' => 10]);

        $html = $this->page($admin);

        // Scoped to the row actions: the shared admin layout legitimately uses
        // @click for its Alpine dropdowns. This handler was the one the CSP
        // blocked, leaving the Verify button inert.
        $this->assertStringNotContainsString('@click="openVerifyModal', $html);
    }

    public function test_the_verify_button_dispatches_through_the_shared_dispatcher(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['order_number' => 'OP-1', 'status' => 'pending', 'grand_total' => 10]);

        $html = $this->page($admin);

        $this->assertStringContainsString('data-action="openVerifyModal"', $html);
        $this->assertStringContainsString($order->getKey(), $html);
        // The arguments must be real JSON: dispatchAction() JSON.parses them, and
        // Js::from() on a bare string emits PHP-style 'quotes' that would make
        // the parse throw and the button do nothing.
        preg_match('/data-args=\'([^\']*)\'/', $html, $args);
        $decoded = html_entity_decode($args[1]);

        $this->assertJson($decoded);
        $this->assertSame('OP-1', json_decode($decoded, true)[1]);
    }

    public function test_switching_status_keeps_the_search_and_page_size(): void
    {
        $admin = $this->admin();

        $html = $this->page($admin, ['search' => 'Zed', 'per_page' => 50]);

        // Only the tab row is in scope: the "Clear" link beside the search box
        // deliberately drops the search it is clearing.
        preg_match('/<nav[^>]*data-tabs[^>]*>(.*?)<\/nav>/s', $html, $bar);
        preg_match_all('/href="([^"]*order-processor[^"]*)"/', $bar[1] ?? '', $links);

        $this->assertNotEmpty($links[1]);

        foreach ($links[1] as $link) {
            $this->assertStringContainsString('search=Zed', $link);
            $this->assertStringContainsString('per_page=50', $link);
            $this->assertStringContainsString('status=', $link);
        }
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.order-processor.index'))
            ->get(route('admin.order-processor.index', ['status' => 'bogus']))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_the_empty_state_names_the_status_without_erroring(): void
    {
        $admin = $this->admin();

        $html = $this->page($admin, ['status' => 'delivered']);

        $this->assertStringContainsString('No Delivered orders', $html);
    }

    public function test_the_bulk_form_requires_a_target_status(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['order_number' => 'OP-1', 'status' => 'pending', 'grand_total' => 10]);

        // Applying with no target selected previously sent `status=""` and
        // bounced off the `required` rule with a generic message.
        $this->actingAs($admin, 'admin')
            ->from(route('admin.order-processor.index'))
            ->post(route('admin.order-processor.bulk'), [
                'order_ids' => [$order->getKey()],
                'status' => '',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $order->fresh()->status->value);
    }

    public function test_the_bulk_form_posts_order_ids_as_an_array(): void
    {
        $admin = $this->admin();

        Order::factory()->create(['order_number' => 'OP-1', 'status' => 'pending', 'grand_total' => 10]);

        $html = $this->page($admin);

        $this->assertStringContainsString('name="order_ids[]"', $html);
    }

    public function test_guest_checkout_orders_render_without_erroring(): void
    {
        $admin = $this->admin();

        Order::factory()->create([
            'order_number' => 'OP-GUEST',
            'status' => 'pending',
            'grand_total' => 10,
            'customer_id' => null,
            'user_id' => null,
        ]);

        $html = $this->page($admin);

        $this->assertStringContainsString('OP-GUEST', $html);
    }

    public function test_the_page_renders_with_no_orders_in_the_selected_status(): void
    {
        $admin = $this->admin();

        $this->page($admin, ['status' => 'cancelled']);
    }
}
