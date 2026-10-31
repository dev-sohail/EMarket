<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the admin payment methods screen.
 *
 * The listing shipped a search box and a submit button, but
 * PaymentMethodController::index() took no Request, so submitting the form
 * reloaded the identical list and the filter looked broken. The filters now
 * reach the query, which means they also reach where clauses and must stay
 * validated — these tests pin that down so an unrecognised value cannot
 * silently render an empty table again.
 *
 * The secret Stripe key is the other trap here: the column is an `encrypted`
 * cast, so echoing `$paymentMethod->stripe_secret_key` back into the form
 * would decrypt it into the page source. The form must keep sending it blank
 * and the controller must retain the stored value.
 */
class AdminPaymentMethodPageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'payments-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);

        // By name: passing the model silently fails to attach, and the
        // middleware then bounces the user to the storefront with
        // "You do not have admin access."
        $admin->assignRole($role->name);

        foreach (['payment_methods.view', 'payment_methods.update'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'payment_methods',
            ])->getKey());
        }

        return $admin;
    }

    protected function page(User $admin, string $query = ''): string
    {
        return $this->actingAs($admin, 'admin')
            ->get('/admin/payment-methods'.$query)
            ->assertOk()
            ->getContent();
    }

    /** Table body only, so a match in the filter inputs cannot satisfy an assertion. */
    protected function table(string $html): string
    {
        $start = strpos($html, '<tbody');
        $end = strpos($html, '</tbody>');

        return substr($html, $start, $end - $start);
    }

    public function test_index_lists_the_seeded_methods(): void
    {
        $admin = $this->admin();

        PaymentMethod::create(['name' => 'Cash on Delivery', 'code' => 'cod', 'is_cod' => true, 'is_active' => true, 'sort_order' => 0]);
        PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1]);

        $html = $this->page($admin);

        $this->assertStringContainsString('Cash on Delivery', $this->table($html));
        $this->assertStringContainsString('Online', $this->table($html));
    }

    public function test_search_actually_filters_the_listing(): void
    {
        $admin = $this->admin();

        PaymentMethod::create(['name' => 'Cash on Delivery', 'code' => 'cod', 'is_cod' => true, 'is_active' => true, 'sort_order' => 0]);
        PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1]);

        $body = $this->table($this->page($admin, '?search=Online'));

        $this->assertStringContainsString('Online', $body);
        $this->assertStringNotContainsString('Cash on Delivery', $body);
    }

    public function test_search_also_matches_the_instructions(): void
    {
        $admin = $this->admin();

        PaymentMethod::create([
            'name' => 'Bank Transfer', 'code' => 'bank', 'is_cod' => false, 'is_active' => true,
            'sort_order' => 0, 'description' => 'Quote your order number in the reference.',
        ]);
        PaymentMethod::create(['name' => 'Cash on Delivery', 'code' => 'cod', 'is_cod' => true, 'is_active' => true, 'sort_order' => 1]);

        $body = $this->table($this->page($admin, '?search='.urlencode('order number')));

        $this->assertStringContainsString('Bank Transfer', $body);
        $this->assertStringNotContainsString('Cash on Delivery', $body);
    }

    public function test_status_filter_splits_active_from_inactive(): void
    {
        $admin = $this->admin();

        PaymentMethod::create(['name' => 'Cash on Delivery', 'code' => 'cod', 'is_cod' => true, 'is_active' => true, 'sort_order' => 0]);
        PaymentMethod::create(['name' => 'Retired Wallet', 'code' => 'wallet', 'is_cod' => false, 'is_active' => false, 'sort_order' => 1]);

        $active = $this->table($this->page($admin, '?status=active'));
        $this->assertStringContainsString('Cash on Delivery', $active);
        $this->assertStringNotContainsString('Retired Wallet', $active);

        $inactive = $this->table($this->page($admin, '?status=inactive'));
        $this->assertStringContainsString('Retired Wallet', $inactive);
        $this->assertStringNotContainsString('Cash on Delivery', $inactive);
    }

    public function test_type_filter_splits_cod_from_online(): void
    {
        $admin = $this->admin();

        PaymentMethod::create(['name' => 'Cash on Delivery', 'code' => 'cod', 'is_cod' => true, 'is_active' => true, 'sort_order' => 0]);
        PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1]);

        $cod = $this->table($this->page($admin, '?type=cod'));
        $this->assertStringContainsString('Cash on Delivery', $cod);
        $this->assertStringNotContainsString('Online', $cod);

        $online = $this->table($this->page($admin, '?type=online'));
        $this->assertStringContainsString('Online', $online);
        $this->assertStringNotContainsString('Cash on Delivery', $online);
    }

    public function test_sorting_is_applied(): void
    {
        $admin = $this->admin();

        PaymentMethod::create(['name' => 'Zebra', 'code' => 'z', 'is_cod' => true, 'is_active' => true, 'sort_order' => 0]);
        PaymentMethod::create(['name' => 'Alpha', 'code' => 'a', 'is_cod' => true, 'is_active' => true, 'sort_order' => 1]);

        $ascending = $this->table($this->page($admin, '?sort_by=name&sort_dir=asc'));
        $this->assertLessThan(strpos($ascending, 'Zebra'), strpos($ascending, 'Alpha'));

        $descending = $this->table($this->page($admin, '?sort_by=name&sort_dir=desc'));
        $this->assertLessThan(strpos($descending, 'Alpha'), strpos($descending, 'Zebra'));
    }

    public function test_default_order_is_storefront_position(): void
    {
        $admin = $this->admin();

        PaymentMethod::create(['name' => 'Second', 'code' => 'b', 'is_cod' => true, 'is_active' => true, 'sort_order' => 5]);
        PaymentMethod::create(['name' => 'First', 'code' => 'a', 'is_cod' => true, 'is_active' => true, 'sort_order' => 1]);

        $body = $this->table($this->page($admin));

        $this->assertLessThan(strpos($body, 'Second'), strpos($body, 'First'));
    }

    public function test_sort_links_carry_the_active_filters_forward(): void
    {
        $admin = $this->admin();
        PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 0]);

        $html = $this->page($admin, '?search=Online&type=online');

        // Choosing a sort column must not discard the search or the type filter,
        // and every sort href must carry both forward.
        preg_match_all('/href="([^"]*sort_by=[^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $href) {
            $href = html_entity_decode($href);

            $this->assertStringContainsString('search=Online', $href);
            $this->assertStringContainsString('type=online', $href);
        }

        // Re-clicking the active column flips the direction instead of pinning it.
        $this->assertMatchesRegularExpression('/sort_by=sort_order&sort_dir=desc/', html_entity_decode($html));
    }

    public function test_unknown_filter_values_are_rejected(): void
    {
        $admin = $this->admin();
        PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 0]);

        // Reaching a where clause unchecked would render a silently empty table
        // instead of telling the admin the query is wrong.
        $this->actingAs($admin, 'admin')
            ->get('/admin/payment-methods?status=maybe')
            ->assertSessionHasErrors('status');

        $this->actingAs($admin, 'admin')
            ->get('/admin/payment-methods?type=cheque')
            ->assertSessionHasErrors('type');

        $this->actingAs($admin, 'admin')
            ->get('/admin/payment-methods?sort_by=stripe_secret_key')
            ->assertSessionHasErrors('sort_by');

        $this->actingAs($admin, 'admin')
            ->get('/admin/payment-methods?sort_dir=sideways')
            ->assertSessionHasErrors('sort_dir');
    }

    public function test_summary_cards_count_every_method_not_just_the_filtered_page(): void
    {
        $admin = $this->admin();

        PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 0, 'stripe_enabled' => true]);
        PaymentMethod::create(['name' => 'Retired Wallet', 'code' => 'wallet', 'is_cod' => false, 'is_active' => false, 'sort_order' => 1]);

        $html = $this->page($admin, '?search=Online');

        // The table narrows to one row, but the cards still describe the whole
        // book: 2 methods, 1 active, 1 inactive, 1 with Stripe on.
        preg_match_all('/<p class="text-2xl font-bold[^>]*">(\d+)<\/p>/', $html, $cards);
        $this->assertSame(['2', '1', '1', '1'], $cards[1]);

        // ...while the filtered table drops the inactive row.
        $this->assertStringNotContainsString('Retired Wallet', $this->table($html));
    }

    public function test_gateway_column_reports_stripe_state(): void
    {
        $admin = $this->admin();

        $online = PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 0, 'stripe_enabled' => true, 'stripe_sandbox' => true]);
        PaymentMethod::create(['name' => 'Cash on Delivery', 'code' => 'cod', 'is_cod' => true, 'is_active' => true, 'sort_order' => 1]);

        $body = $this->table($this->page($admin));

        // Stripe enabled in sandbox mode.
        $this->assertStringContainsString('Stripe · Test', $body);

        // A COD method has no gateway at all: it must not claim to be manual.
        $codRow = substr($body, strpos($body, 'Cash on Delivery'));
        $this->assertStringNotContainsString('Manual only', $codRow);
        $this->assertStringNotContainsString('Stripe', $codRow);

        // Turning Stripe off downgrades the online method to instructions only.
        $online->update(['stripe_enabled' => false]);
        $this->assertStringContainsString('Manual only', $this->table($this->page($admin)));
    }

    public function test_empty_state_appears_when_nothing_matches(): void
    {
        $admin = $this->admin();
        PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 0]);

        $html = $this->page($admin, '?search=nothing-matches-this');

        $this->assertStringContainsString('No payment methods match these filters', $html);
        $this->assertStringContainsString('Clear all filters', $html);
    }

    public function test_edit_page_renders(): void
    {
        $admin = $this->admin();
        $method = PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/payment-methods/'.$method->getKey().'/edit')
            ->assertOk()
            ->assertSee('Online')
            ->assertSee('Stripe Gateway');
    }

    public function test_edit_page_never_echoes_the_secret_key(): void
    {
        $admin = $this->admin();
        $method = PaymentMethod::create([
            'name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1,
            'stripe_enabled' => true,
            'stripe_secret_key' => 'sk_live_super_secret_value',
        ]);

        $html = $this->actingAs($admin, 'admin')
            ->get('/admin/payment-methods/'.$method->getKey().'/edit')
            ->assertOk()
            ->getContent();

        // The column is an encrypted cast: rendering it would decrypt the key
        // straight into the page source, and any markup-logging middleware.
        $this->assertStringNotContainsString('sk_live_super_secret_value', $html);
        $this->assertStringContainsString('saved — type to replace', $html);
    }

    public function test_cod_method_hides_the_stripe_panel(): void
    {
        $admin = $this->admin();
        $method = PaymentMethod::create(['name' => 'Cash on Delivery', 'code' => 'cod', 'is_cod' => true, 'is_active' => true, 'sort_order' => 0]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/payment-methods/'.$method->getKey().'/edit')
            ->assertOk()
            ->assertDontSee('Stripe Gateway');
    }

    public function test_update_saves_the_basic_fields(): void
    {
        $admin = $this->admin();
        $method = PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($admin, 'admin')
            ->put('/admin/payment-methods/'.$method->getKey(), [
                'name' => 'Online Payment',
                'description' => 'Pay by transfer.',
                'sort_order' => 4,
                'is_active' => 1,
                'stripe_enabled' => 0,
                'stripe_sandbox' => 0,
            ])
            ->assertRedirect(route('admin.payment-methods.index'));

        $method->refresh();
        $this->assertSame('Online Payment', $method->name);
        $this->assertSame(4, $method->sort_order);
        $this->assertSame('Pay by transfer.', $method->description);
        $this->assertTrue($method->is_active);
    }

    public function test_blank_secret_key_keeps_the_stored_one(): void
    {
        $admin = $this->admin();
        $method = PaymentMethod::create([
            'name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1,
            'stripe_enabled' => true, 'stripe_secret_key' => 'sk_live_original',
        ]);

        $this->actingAs($admin, 'admin')
            ->put('/admin/payment-methods/'.$method->getKey(), [
                'name' => 'Online',
                'sort_order' => 1,
                'is_active' => 1,
                'stripe_enabled' => 1,
                'stripe_sandbox' => 0,
                'stripe_publishable_key' => 'pk_live_original',
                'stripe_secret_key' => '',
            ])
            ->assertRedirect(route('admin.payment-methods.index'));

        $this->assertSame('sk_live_original', $method->refresh()->stripe_secret_key);
    }

    public function test_stripe_keys_must_match_the_selected_environment(): void
    {
        $admin = $this->admin();
        $method = PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1]);

        // Live keys submitted while Sandbox is checked.
        $this->actingAs($admin, 'admin')
            ->from('/admin/payment-methods/'.$method->getKey().'/edit')
            ->put('/admin/payment-methods/'.$method->getKey(), [
                'name' => 'Online',
                'sort_order' => 1,
                'is_active' => 1,
                'stripe_enabled' => 1,
                'stripe_sandbox' => 1,
                'stripe_publishable_key' => 'pk_live_abc',
                'stripe_secret_key' => 'sk_live_abc',
            ])
            ->assertSessionHasErrors('stripe_keys');

        $this->assertFalse((bool) $method->refresh()->stripe_enabled);
    }

    public function test_enabling_stripe_requires_both_keys(): void
    {
        $admin = $this->admin();
        $method = PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($admin, 'admin')
            ->from('/admin/payment-methods/'.$method->getKey().'/edit')
            ->put('/admin/payment-methods/'.$method->getKey(), [
                'name' => 'Online',
                'sort_order' => 1,
                'is_active' => 1,
                'stripe_enabled' => 1,
                'stripe_sandbox' => 0,
                'stripe_publishable_key' => '',
                'stripe_secret_key' => '',
            ])
            ->assertSessionHasErrors('stripe_keys');
    }

    public function test_matching_test_mode_keys_are_accepted(): void
    {
        $admin = $this->admin();
        $method = PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($admin, 'admin')
            ->put('/admin/payment-methods/'.$method->getKey(), [
                'name' => 'Online',
                'sort_order' => 1,
                'is_active' => 1,
                'stripe_enabled' => 1,
                'stripe_sandbox' => 1,
                'stripe_publishable_key' => 'pk_test_abc',
                'stripe_secret_key' => 'sk_test_abc',
            ])
            ->assertSessionHasNoErrors();

        $method->refresh();
        $this->assertTrue($method->stripe_enabled);
        $this->assertTrue($method->stripe_sandbox);
        $this->assertSame('sk_test_abc', $method->stripe_secret_key);
    }

    public function test_name_is_required(): void
    {
        $admin = $this->admin();
        $method = PaymentMethod::create(['name' => 'Online', 'code' => 'online', 'is_cod' => false, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($admin, 'admin')
            ->from('/admin/payment-methods/'.$method->getKey().'/edit')
            ->put('/admin/payment-methods/'.$method->getKey(), ['name' => '', 'sort_order' => 1])
            ->assertSessionHasErrors('name');
    }
}
