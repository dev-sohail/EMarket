<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the admin settings screen
 * (resources/views/admin/setting/index.blade.php).
 *
 * Three defects this page has shipped before:
 *
 *  1. It hand-rolled its own session('success') / $errors->any() banners on top
 *     of components/admin/alerts.blade.php (included by layouts.admin), so
 *     every flash and every validation message was rendered twice.
 *  2. The theme colour grid drifted out of nesting depth: a stray </div> closed
 *     each cell twice, which closed the grid and the card early and pushed the
 *     remaining swatches out of the section.
 *  3. The colour pickers fell out of step with the script that drives them:
 *     setupColorPickers() needs a `.color-hex` label and a swatch div next to
 *     each input[type=color].
 *  4. The colour labels showed "Ink --color-ink" only: a raw token with no clue
 *     what it paints, so the only safe-looking edit was to leave it alone.
 */
class AdminSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    /** Section ids the sticky nav and the page body agree on, in order. */
    private const SECTIONS = [
        'general',
        'regional',
        'contact',
        'seo',
        'orders',
        'shipping',
        'social',
        'display',
        'theme',
        'account',
    ];

    /** Theme colour keys, one picker + hex label + swatch each. */
    private const COLOURS = [
        'color_ink',
        'color_ink_soft',
        'color_paper',
        'color_paper_dim',
        'color_line',
        'color_steel',
        'color_red',
        'color_red_dark',
        'color_sale',
    ];

    /**
     * The wording shown between each colour name and its --color-* token. An
     * admin picking here is choosing a token, not a hue, so the label has to
     * say what the token paints. Values are decoded text; the template holds
     * the escaped form (&amp;).
     */
    private const PURPOSES = [
        'color_ink' => 'Primary text',
        'color_ink_soft' => 'Secondary text',
        'color_paper' => 'Page background',
        'color_paper_dim' => 'Subtle surfaces',
        'color_line' => 'Borders & dividers',
        'color_steel' => 'Muted text',
        'color_red' => 'Brand accent',
        'color_red_dark' => 'Accent hover',
        'color_sale' => 'Sale & success',
    ];

    /** Every setting the form must post, so a redesign cannot silently drop one. */
    private const FIELDS = [
        'affiliate_enabled', 'blogs_enabled', 'business_hours', 'city',
        'color_ink', 'color_ink_soft', 'color_line', 'color_paper',
        'color_paper_dim', 'color_red', 'color_red_dark', 'color_sale',
        'color_steel', 'currency', 'default_country', 'default_shipping_cost',
        'default_tax_rate', 'email_verification', 'facebook_url', 'favicon',
        'guest_checkout', 'instagram_url', 'invoice_prefix',
        'low_stock_threshold', 'meta_description', 'meta_keywords',
        'meta_title', 'order_prefix', 'products_per_page', 'registration',
        'returns_enabled', 'reviews_enabled', 'store_address',
        'store_description', 'store_email', 'store_logo', 'store_name',
        'store_phone', 'store_tagline', 'store_top_bar_message', 'tiktok_url',
        'timezone', 'twitter_url', 'whatsapp', 'youtube_url',
    ];

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'settings-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        // config('admin.route_permissions') gates admin.settings.index on
        // settings.view and admin.settings.update on settings.update.
        foreach (['settings.view', 'settings.update'] as $name) {
            $role->permissions()->attach(
                Permission::create([
                    'name' => $name,
                    'slug' => str_replace('.', '-', $name),
                    'group_name' => 'settings',
                ])->getKey()
            );
        }

        return $admin;
    }

    protected function page(User $admin): string
    {
        return $this->actingAs($admin, 'admin')
            ->get('/admin/settings')
            ->assertOk()
            ->getContent();
    }

    /**
     * Return the markup of the `<div id="$id">` element, matched by nesting
     * depth rather than by "the next </div>".
     *
     * A stray </div> closes a section early and lets the following section
     * bleed into it, so anything that only counts closing tags still reports a
     * balanced document. Walking the depth is what actually catches it.
     */
    protected function section(string $html, string $id): string
    {
        // Script bodies and comments must not feed the depth counter.
        $html = preg_replace('/<script\b.*?<\/script>/s', '', $html) ?? $html;
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;

        $at = strpos($html, 'id="'.$id.'"');

        if ($at === false) {
            return '';
        }

        $start = strrpos(substr($html, 0, $at), '<div');

        if ($start === false) {
            return '';
        }

        $depth = 0;
        $cursor = $start;

        while (preg_match('/<div\b|<\/div>/', $html, $matches, PREG_OFFSET_CAPTURE, $cursor) === 1) {
            [$token, $position] = $matches[0];

            $depth += $token === '</div>' ? -1 : 1;
            $cursor = $position + strlen($token);

            if ($depth === 0) {
                return substr($html, $start, $cursor - $start);
            }
        }

        // Never closed: return the tail so callers can see what it swallowed.
        return substr($html, $start);
    }

    public function test_the_page_renders_every_section_exactly_once(): void
    {
        $html = $this->page($this->admin());

        foreach (self::SECTIONS as $id) {
            $this->assertSame(
                1,
                substr_count($html, 'id="'.$id.'"'),
                "The #{$id} section must be rendered exactly once."
            );
        }
    }

    public function test_a_misplaced_closing_tag_cannot_nest_two_sections(): void
    {
        $html = $this->page($this->admin());

        $this->assertSame(
            substr_count($html, '<div'),
            substr_count($html, '</div>'),
            'Every opened <div> must be closed: a stray </div> swallows the markup that follows it.'
        );

        foreach (self::SECTIONS as $index => $id) {
            $next = self::SECTIONS[$index + 1] ?? null;

            if ($next === null) {
                continue;
            }

            $this->assertStringNotContainsString(
                'id="'.$next.'"',
                $this->section($html, $id),
                "The #{$id} card must close before #{$next} opens; a missing or stray </div> nests them."
            );
        }
    }

    public function test_the_page_posts_every_setting(): void
    {
        $html = $this->page($this->admin());

        foreach (self::FIELDS as $field) {
            $this->assertSame(
                1,
                substr_count($html, 'name="'.$field.'"'),
                "The form must post exactly one {$field} field."
            );
        }
    }

    public function test_each_theme_colour_has_a_hex_label_and_a_swatch(): void
    {
        $theme = $this->section($this->page($this->admin()), 'theme');

        $this->assertNotSame('', $theme, 'The #theme section is missing.');

        foreach (self::COLOURS as $key) {
            $this->assertMatchesRegularExpression(
                '/name="'.$key.'"[^>]*>.*?<span class="color-hex[^"]*">/s',
                $theme,
                "The {$key} picker must keep a .color-hex label beneath it; setupColorPickers() reads that element."
            );
        }

        $this->assertSame(
            count(self::COLOURS),
            substr_count($theme, 'class="color-hex'),
            'Every swatch must have exactly one hex label.'
        );
    }

    public function test_every_theme_colour_label_states_its_purpose_before_its_token(): void
    {
        $theme = $this->section($this->page($this->admin()), 'theme');

        foreach (self::COLOURS as $key) {
            // color_ink_soft -> --color-ink-soft
            $token = '--'.str_replace('_', '-', $key);

            $this->assertSame(
                1,
                preg_match(
                    '/<label\b[^>]*>\s*[^<]+<span\b[^>]*>([^<]+)<\/span>\s*'
                        .'<span\b[^>]*>'.preg_quote($token, '/').'<\/span>\s*<\/label>/',
                    $theme,
                    $matches
                ),
                "The {$key} label must read \"Name <purpose> {$token}\", "
                    .'so the admin knows which token they are repainting.'
            );

            $this->assertSame(
                self::PURPOSES[$key],
                html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                "The {$key} label must state its real purpose, not a copy-pasted one."
            );
        }
    }

    public function test_a_successful_save_flashes_the_message_once_and_persists_the_value(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->put('/admin/settings', ['store_name' => 'Regression Shop'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Regression Shop', Setting::where('key', 'store_name')->value('value'));

        $html = $this->page($admin);

        $this->assertSame(
            1,
            substr_count($html, 'Settings saved successfully.'),
            'layouts.admin already renders the flash through components/admin/alerts; the page must not repeat it.'
        );
    }

    public function test_validation_failures_are_summarised_once(): void
    {
        $html = $this->from('/admin/settings')
            ->actingAs($this->admin(), 'admin')
            ->followingRedirects()
            ->put('/admin/settings', [
                'store_email' => 'not-an-email',
                'products_per_page' => 500,
            ])
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'problems with this form'),
            'The validation summary must be rendered once, by components/admin/alerts.'
        );

        // The summary lists each failure; the field repeats it inline.
        $this->assertSame(
            2,
            substr_count($html, 'must be a valid email address'),
            'Expected the summary entry plus the inline @error message.'
        );
    }
}
