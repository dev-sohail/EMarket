<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\TaxRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the admin tax rules screen
 * (resources/views/admin/tax/index.blade.php) and its modal form.
 *
 * Two defects this page has shipped before:
 *
 *  1. Add and Edit were not reachable at all — the form hard-coded
 *     action="admin.tax.store" with no `id` field and the table offered only
 *     Delete, so an existing rule could never be corrected.
 *  2. The Edit affordance lives in `data-args` as JSON. @json() inside a
 *     single-quoted HTML attribute truncates at the first `)` inside
 *     `$taxRule->getKey()`, which produced a compiled view that would not
 *     parse at all ("Unclosed '[' does not match ')'"). The page 500'd on
 *     every render, so these tests assert the page renders at all.
 */
class AdminTaxPageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'tax-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        foreach (['tax.view', 'tax.create', 'tax.update', 'tax.delete'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'tax',
            ])->getKey());
        }

        return $admin;
    }

    protected function page(User $admin, string $query = ''): string
    {
        return $this->actingAs($admin, 'admin')
            ->get('/admin/tax'.$query)
            ->assertOk()
            ->getContent();
    }

    public function test_the_page_renders_with_a_rule_present(): void
    {
        TaxRule::create(['name' => 'VAT', 'rate' => 8.1, 'type' => 'percentage', 'is_active' => true]);

        $html = $this->page($this->admin());

        $this->assertStringContainsString('VAT', $html);
        $this->assertStringContainsString('8.1%', $html);
    }

    public function test_the_page_renders_when_empty(): void
    {
        $html = $this->page($this->admin());

        $this->assertStringContainsString('No tax rules found', $html);
    }

    public function test_the_rate_label_is_driven_by_the_rule_type(): void
    {
        TaxRule::create(['name' => 'Swiss VAT', 'rate' => 8.1, 'type' => 'percentage', 'is_active' => true]);
        TaxRule::create(['name' => 'Handling', 'rate' => 2.5, 'type' => 'fixed', 'is_active' => false]);

        $html = $this->page($this->admin());

        $this->assertStringContainsString('8.1%', $html);
        // Fixed rules render a currency amount, never a percentage.
        $this->assertStringContainsString(format_currency(2.5), $html);
        $this->assertStringNotContainsString('2.5%', $html);
    }

    public function test_each_row_carries_json_edit_arguments(): void
    {
        $rule = TaxRule::create(['name' => 'VAT', 'rate' => 8.1, 'type' => 'percentage', 'is_active' => true]);

        $html = $this->page($this->admin());

        $this->assertMatchesRegularExpression('/data-args=\'(\[.*?\])\'/', $html, 'no data-args attribute found');

        preg_match('/data-args=\'(\[.*?\])\'/', $html, $matches);
        $args = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);

        $this->assertIsArray($args, 'data-args is not valid JSON');
        $this->assertSame([$rule->getKey(), 'VAT', '8.1', 'percentage', 1], $args);
    }

    public function test_the_modal_posts_to_store_and_carries_an_id_field(): void
    {
        $html = $this->page($this->admin());

        $this->assertStringContainsString('id="tax-rule-form"', $html);
        $this->assertStringContainsString('name="id"', $html);
        $this->assertStringContainsString('openTaxRuleModal', $html);
        $this->assertStringContainsString('closeTaxRuleModal', $html);
    }

    public function test_every_row_exposes_toggle_and_delete_actions(): void
    {
        $rule = TaxRule::create(['name' => 'VAT', 'rate' => 8.1, 'type' => 'percentage', 'is_active' => true]);

        $html = $this->page($this->admin());

        $this->assertStringContainsString(route('admin.tax.toggleStatus', $rule->getKey()), $html);
        $this->assertStringContainsString(route('admin.tax.destroy', $rule->getKey()), $html);
    }

    public function test_the_list_paginates_past_the_page_size(): void
    {
        foreach (range(1, 20) as $i) {
            TaxRule::create(['name' => 'Rule '.$i, 'rate' => $i, 'type' => 'percentage', 'is_active' => true]);
        }

        $admin = $this->admin();
        $first = $this->page($admin);

        $this->assertStringContainsString('page=2', $first, 'no second page link rendered');
        // 15 per page: the header button is the 16th openTaxRuleModal trigger.
        $this->assertSame(16, substr_count($first, 'data-action="openTaxRuleModal"'));

        $second = $this->page($admin, '?page=2');

        $this->assertSame(6, substr_count($second, 'data-action="openTaxRuleModal"'));
    }

    public function test_filters_narrow_the_list(): void
    {
        TaxRule::create(['name' => 'VAT', 'rate' => 8.1, 'type' => 'percentage', 'is_active' => true]);
        TaxRule::create(['name' => 'Handling fee', 'rate' => 2.5, 'type' => 'fixed', 'is_active' => false]);

        $filtered = $this->page($this->admin(), '?q=VAT&type=percentage&status=active');

        $this->assertStringContainsString('VAT', $filtered);
        $this->assertStringNotContainsString('Handling fee', $filtered);
    }

    public function test_a_failed_validation_reopens_the_modal_with_the_submitted_values(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->from('/admin/tax')
            ->post('/admin/tax', ['name' => '', 'rate' => 'not-a-number', 'type' => 'percentage'])
            ->assertRedirect('/admin/tax')
            ->assertSessionHasErrors(['name', 'rate']);

        // The redirect target must still render, and the modal reopen script
        // must be present for the fields the admin typed.
        $html = $this->page($admin);

        $this->assertStringContainsString('openTaxRuleModal', $html);
    }

    public function test_an_edit_updates_the_existing_rule(): void
    {
        $rule = TaxRule::create(['name' => 'VAT', 'rate' => 8.1, 'type' => 'percentage', 'is_active' => true]);

        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/tax/'.$rule->getKey(), [
                'name' => 'VAT 2026',
                'rate' => 8.2,
                'type' => 'percentage',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tax_rules', ['_id' => $rule->getKey(), 'name' => 'VAT 2026']);
    }

    public function test_toggling_status_flips_the_rule(): void
    {
        $rule = TaxRule::create(['name' => 'VAT', 'rate' => 8.1, 'type' => 'percentage', 'is_active' => true]);

        $this->actingAs($this->admin(), 'admin')
            ->patch('/admin/tax/'.$rule->getKey().'/toggle-status')
            ->assertRedirect();

        $this->assertFalse($rule->fresh()->is_active);
    }
}
