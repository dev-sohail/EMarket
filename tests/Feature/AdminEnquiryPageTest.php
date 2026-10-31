<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the admin enquiries screens
 * (resources/views/admin/enquiries/{index,show}.blade.php).
 *
 * Defects these screens shipped with:
 *
 *  1. index.blade.php opened `@section('content')` and never closed it — the
 *     file ended on `@endpush`. Without an `@endsection` the section never
 *     stops capturing, so the whole card (stats, filters, table) was flushed
 *     ahead of `<!DOCTYPE html>` while `@yield('content')` inside the admin
 *     layout came out empty. The page rendered as a bare fragment above an
 *     empty shell.
 *  2. The status filter carried `onchange="this.form.submit()"`, which
 *     `script-src` without 'unsafe-inline' blocks, so the dropdown was dead.
 *     `data-auto-submit` is how every other admin filter submits.
 *  3. The pushed script had no nonce, so row navigation and debounced search
 *     were blocked by the same policy — a page that looks fine and does
 *     nothing when clicked.
 *  4. The "Contact" header omitted `hidden md:table-cell` while its cell had
 *     it, so below `md` the header row and body row disagreed on the column
 *     count.
 *  5. Rows and the View link carried Stimulus-style
 *     `data-action="click->table-row#..."` attributes, but there is no
 *     Stimulus in this project; `dispatchAction()` looks the value up as a
 *     global function and silently returns false.
 */
class AdminEnquiryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'enquiries-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);
        $role->permissions()->attach(Permission::create([
            'name' => 'enquiries.view',
            'slug' => 'enquiries-view',
            'group_name' => 'enquiries',
        ])->getKey());

        return $admin;
    }

    protected function fixture(User $actor, array $attributes = []): Enquiry
    {
        return Enquiry::create(array_merge([
            'name' => 'Alice Example',
            'email' => 'alice@example.test',
            'subject' => 'Order question',
            'message' => 'Has my order shipped yet?',
            'status' => 'new',
        ], $attributes));
    }

    public function test_the_index_renders_inside_the_admin_layout_not_above_it(): void
    {
        $admin = $this->admin();
        $this->fixture($admin);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.enquiries.index'))
            ->assertOk()
            ->getContent();

        $trimmed = ltrim($html);

        // The unclosed section pushed the card to the top of the response.
        $this->assertStringStartsWith(
            '<!DOCTYPE html>',
            $trimmed,
            "The enquiries card is rendering before the document, which means @section('content') is never closed."
        );

        // ...and therefore never reached @yield('content') in the layout.
        $this->assertMatchesRegularExpression(
            '/<main[^>]*>.*Stats Summary.*<\/main>/s',
            $html,
            'The enquiries card must be yielded inside the admin layout <main> element.'
        );
    }

    public function test_the_filter_controls_are_reachable_through_the_csp(): void
    {
        $admin = $this->admin();
        $this->fixture($admin);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.enquiries.index'));
        $response->assertOk();
        $html = $response->getContent();

        $nonce = preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $m)
            ? $m[1]
            : null;

        $this->assertNotNull($nonce, 'Expected a nonce in the CSP header.');

        // No inline on* handlers: script-src has no 'unsafe-inline'.
        preg_match_all('/\son[a-z]+\s*=\s*["\']/i', $html, $matches);
        $this->assertSame([], array_values(array_unique($matches[0])), 'Inline event handlers are blocked by script-src.');

        // The pushed script must carry the response nonce or it never runs.
        $this->assertStringContainsString('<script nonce="'.$nonce.'">', $html);

        // The repository-wide replacement for onchange="this.form.submit()".
        $this->assertMatchesRegularExpression('/<select[^>]*data-auto-submit/', $html);
    }

    public function test_no_stimulus_attributes_are_emitted(): void
    {
        $admin = $this->admin();
        $this->fixture($admin);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.enquiries.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'table-row#',
            $html,
            'There is no Stimulus in this project; dispatchAction() would resolve this to a missing global and do nothing.'
        );

        // Row navigation is expressed as data-href for the delegated handler.
        $this->assertStringContainsString('data-href="', $html);
    }

    public function test_the_responsive_column_headers_match_their_cells(): void
    {
        $admin = $this->admin();
        $this->fixture($admin);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.enquiries.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<th[^>]*hidden md:table-cell[^>]*>Contact<\/th>/',
            $html,
            'The Contact header must hide below md exactly as its cell does, or the two rows lose alignment.'
        );
    }

    public function test_pagination_links_keep_the_active_search_and_status(): void
    {
        $admin = $this->admin();

        // 25 rows is two pages at the 20-per-page default.
        foreach (range(1, 25) as $index) {
            $this->fixture($admin, [
                'name' => 'Alice Example '.$index,
                'email' => 'alice+'.$index.'@example.test',
                'status' => 'new',
            ]);
        }

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.enquiries.index', ['search' => 'Alice', 'status' => 'new']))
            ->assertOk()
            ->getContent();

        // Page 2 used to drop both filters and silently show every enquiry.
        $this->assertStringContainsString('search=Alice', $html);
        $this->assertStringContainsString('status=new', $html);
    }

    public function test_the_show_page_renders_its_sections(): void
    {
        $admin = $this->admin();
        $enquiry = $this->fixture($admin, ['admin_notes' => 'Followed up by phone.']);

        $html = $this->actingAs($admin, 'admin')
            ->get(route('admin.enquiries.show', $enquiry))
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('<!DOCTYPE html>', ltrim($html));
        $this->assertMatchesRegularExpression('/<main[^>]*>.*Has my order shipped yet\?.*<\/main>/s', $html);
        $this->assertStringContainsString('Followed up by phone.', $html);
    }
}
