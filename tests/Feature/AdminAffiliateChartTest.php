<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the affiliate chart on the admin screen
 * (resources/views/admin/affiliate/index.blade.php).
 *
 * Defects this chart has shipped:
 *
 *  1. The Chart.js block was an inline <script> inside @section('content')
 *     instead of @push('scripts'). It happened to work, but anything pushed by
 *     a layout or partial could then run before Chart had initialised.
 *  2. The service grouped commissions in PHP after ->get(), so the cost of the
 *     chart grew with the whole table rather than with the visible window.
 *  3. With no data the page still drew an empty canvas, so a fresh install
 *     looked broken instead of showing an empty state.
 *  4. Every day rendered as "M j", so a 90 day window showed three identical
 *     "Oct 2" labels with nothing to tell them apart.
 */
class AdminAffiliateChartTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'affiliate-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        foreach (['affiliate.view', 'affiliate.update'] as $name) {
            $role->permissions()->attach(Permission::create([
                'name' => $name,
                'slug' => str_replace('.', '-', $name),
                'group_name' => 'affiliates',
            ])->getKey());
        }

        return $admin;
    }

    protected function page(User $admin, string $query = ''): string
    {
        return $this->actingAs($admin, 'admin')
            ->get('/admin/affiliate'.$query)
            ->assertOk()
            ->getContent();
    }

    protected function commission(string $status, float $amount, int $daysAgo): AffiliateCommission
    {
        $customer = Customer::factory()->create();

        $affiliate = Affiliate::create([
            'customer_id' => $customer->getKey(),
            'code' => 'AFF'.strtoupper(substr(md5($customer->getKey().$daysAgo.$status), 0, 8)),
            'status' => 'active',
        ]);

        $commission = new AffiliateCommission([
            'affiliate_id' => $affiliate->getKey(),
            'amount' => $amount,
            'rate' => 10,
            'status' => $status,
        ]);

        $commission->created_at = now()->subDays($daysAgo);
        $commission->updated_at = now()->subDays($daysAgo);
        $commission->save();

        return $commission->fresh();
    }

    public function test_the_page_renders_with_no_commissions(): void
    {
        $html = $this->page($this->admin());

        $this->assertStringContainsString('No commissions in this period', $html);
        $this->assertStringNotContainsString('id="affiliateCommissionsChart"', $html);
    }

    public function test_the_chart_renders_once_commissions_exist(): void
    {
        $this->commission('pending', 12.50, 0);
        $this->commission('paid', 40.00, 2);
        $this->commission('approved', 7.25, 5);

        $html = $this->page($this->admin());

        $this->assertStringContainsString('id="affiliateCommissionsChart"', $html);
        $this->assertStringContainsString('Commission Activity', $html);
        $this->assertStringNotContainsString('No commissions in this period', $html);
    }

    public function test_the_chart_script_is_pushed_not_inlined_in_the_content_section(): void
    {
        $this->commission('paid', 25.00, 1);

        $html = $this->page($this->admin());

        $this->assertStringContainsString('affiliateCommissionsChart', $html);

        // The <canvas> markup and its script must not sit inside the content
        // section: the layout pushes scripts at the end of <body>.
        $contentStart = strpos($html, '@section') === false ? strpos($html, '<main') : 0;
        $main = substr($html, (int) $contentStart);
        $canvasAt = strpos($html, 'id="affiliateCommissionsChart"');
        $mainEnd = strpos($html, '</main>');
        $scriptAt = strpos($html, "getElementById('affiliateCommissionsChart')");

        $this->assertIsString($main);
        $this->assertIsInt($canvasAt);
        $this->assertIsInt($mainEnd);
        $this->assertIsInt($scriptAt);
        $this->assertLessThan($mainEnd, $canvasAt, 'The canvas must render inside the content section.');
        $this->assertGreaterThan($mainEnd, $scriptAt, 'The chart script must run after the content section, via @push(\'scripts\').');
    }

    public function test_the_range_switcher_is_offered_and_honours_the_query_string(): void
    {
        $admin = $this->admin();

        $this->commission('pending', 9.00, 0);

        $html = $this->page($admin);

        foreach ([7, 30, 90] as $option) {
            $this->assertMatchesRegularExpression('/>\s*'.$option.'d\s*</', $html);
        }

        $sevenDay = $this->page($admin, '?range=7');
        $this->assertStringContainsString('7 days', $sevenDay);

        $ninetyDay = $this->page($admin, '?range=90');
        $this->assertStringContainsString('90 days', $ninetyDay);

        // An out-of-range value must fall back to the default, not blow up.
        $bogus = $this->page($admin, '?range=9999');
        $this->assertStringContainsString('30 days', $bogus);
    }

    public function test_commissions_outside_the_window_are_excluded(): void
    {
        $this->commission('paid', 15.00, 0);
        $this->commission('paid', 500.00, 40);

        $admin = $this->admin();

        $sevenDay = $this->chartTotals($this->page($admin, '?range=7'));
        $this->assertCount(7, $sevenDay);
        $this->assertEqualsWithDelta(15.0, array_sum($sevenDay), 0.001);

        $ninetyDay = $this->chartTotals($this->page($admin, '?range=90'));
        $this->assertCount(90, $ninetyDay);
        $this->assertEqualsWithDelta(515.0, array_sum($ninetyDay), 0.001);
    }

    /**
     * The per-day totals the chart script is handed, read back out of the
     * rendered `const totals = [...]` payload.
     *
     * @return array<int, float>
     */
    protected function chartTotals(string $html): array
    {
        $this->assertSame(
            1,
            preg_match('/const totals = (\[.*?\]);/s', $html, $matches),
            'The chart script must publish a per-day totals array.'
        );

        return array_map('floatval', json_decode($matches[1], true) ?? []);
    }

    public function test_every_inline_script_carries_the_response_nonce(): void
    {
        $this->commission('approved', 30.00, 1);

        $response = $this->actingAs($this->admin(), 'admin')->get('/admin/affiliate');

        $response->assertOk();

        $nonce = $response->headers->get('Content-Security-Policy');
        preg_match("/script-src[^;]*'nonce-([^']+)'/", (string) $nonce, $matches);
        $this->assertNotEmpty($matches, 'The response must carry a script-src nonce.');

        preg_match_all('/<script\b(?![^>]*\bsrc=)[^>]*>/i', $response->getContent(), $tags);
        $this->assertNotEmpty($tags[0]);

        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('nonce=', $tag);
        }
    }
}
