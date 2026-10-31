<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Popup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards for the admin popups screens
 * (resources/views/admin/popups/{index,create,edit}.blade.php).
 *
 * Defects these screens shipped with:
 *
 *  1. All three opened `@section('content')` and never closed it — each file
 *     ended on `@endpush`. Without `@endsection` the section never stops
 *     capturing, so the whole page (card grid / form) was flushed ahead of
 *     `<!DOCTYPE html>` while `@yield('content')` inside the admin layout came
 *     out empty. Index rendered as a bare card above an empty shell; create and
 *     edit did the same with their `<form>`.
 *
 *  2. index.blade.php pushed its inline script *before* the SortableJS CDN tag
 *     that defines `Sortable`. Both are parsed in order, so the IIFE ran with
 *     `typeof Sortable === 'undefined'`, returned early, and drag-and-drop
 *     reordering was silently dead even though the CDN was allowed by CSP.
 */
class AdminPopupPageTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private const PAGES = ['index', 'create', 'edit'];

    private ?User $actingAdmin = null;

    private ?Popup $popup = null;

    protected function admin(): User
    {
        if ($this->actingAdmin !== null) {
            return $this->actingAdmin;
        }

        $admin = User::factory()->create([
            'email' => 'popups-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole($role->name);
        $role->permissions()->attach(Permission::create([
            'name' => 'popups.view',
            'slug' => 'popups-view',
            'group_name' => 'popups',
        ])->getKey());

        return $this->actingAdmin = $admin;
    }

    private function popup(): Popup
    {
        if ($this->popup !== null) {
            return $this->popup;
        }

        return $this->popup = Popup::create([
            'name' => 'Summer Sale',
            'title' => 'Big Sale',
            'type' => 'image',
            'position' => 'homepage',
            'size' => 'medium',
            'cta_text' => 'Shop Now',
            'url' => 'https://example.test',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, string> page name => rendered body
     */
    private function renderAll(User $actor): array
    {
        $popup = $this->popup();
        $urls = [
            'index' => route('admin.popups.index'),
            'create' => route('admin.popups.create'),
            'edit' => route('admin.popups.edit', $popup),
        ];

        $bodies = [];
        foreach (self::PAGES as $name) {
            $bodies[$name] = $this->actingAs($actor, 'admin')
                ->get($urls[$name])
                ->assertOk()
                ->getContent();
        }

        return $bodies;
    }

    /**
     * The unclosed-`@section('content')` bug: everything renders before the
     * document, and `@yield('content')` in the layout stays empty.
     */
    public function test_every_popup_page_renders_inside_the_admin_layout(): void
    {
        foreach ($this->renderAll($this->admin()) as $name => $html) {
            $this->assertStringStartsWith(
                '<!DOCTYPE html>',
                ltrim($html),
                "{$name}.blade.php is rendering its @section('content') before the document — the section is never closed with @endsection."
            );

            $this->assertMatchesRegularExpression(
                '/<main[^>]*>.*<\/main>/s',
                $html,
                "{$name}.blade.php must yield its content into the admin layout <main> element."
            );
        }
    }

    public function test_the_index_card_grid_is_yielded_into_main(): void
    {
        $html = $this->renderAll($this->admin())['index'];

        $main = Str::between($html, '<main', '</main>');

        $this->assertStringContainsString('popup-grid', $main, 'The card grid must render inside <main>, not above <!DOCTYPE html>.');
        $this->assertStringContainsString('Add Popup', $main);
    }

    public function test_the_forms_are_yielded_into_main(): void
    {
        foreach (['create', 'edit'] as $name) {
            $html = $this->renderAll($this->admin())[$name];
            $main = Str::between($html, '<main', '</main>');

            $this->assertStringContainsString(
                '<form',
                $main,
                "The {$name} form must render inside <main>, not above <!DOCTYPE html>."
            );
        }
    }

    /**
     * SortableJS has to be parsed before the inline script that reads it,
     * otherwise the reorder IIFE bails on `typeof Sortable === 'undefined'`.
     */
    public function test_sortable_is_loaded_before_the_script_that_uses_it(): void
    {
        $html = $this->renderAll($this->admin())['index'];

        $sortableAt = strpos($html, 'Sortable.min.js');
        $consumerAt = strpos($html, 'typeof Sortable');

        $this->assertNotFalse($sortableAt, 'Expected the SortableJS CDN tag on the index page.');
        $this->assertNotFalse($consumerAt, 'Expected the reorder script on the index page.');
        $this->assertLessThan(
            $consumerAt,
            $sortableAt,
            'SortableJS must load before the script that uses it, or drag-and-drop reordering never initialises.'
        );

        $this->assertSame(
            1,
            substr_count($html, 'Sortable.min.js'),
            'SortableJS should be loaded exactly once.'
        );
    }

    public function test_popup_scripts_satisfy_the_csp(): void
    {
        foreach ($this->renderAll($this->admin()) as $name => $html) {
            preg_match_all('/\son[a-z]+\s*=\s*["\']/i', $html, $matches);
            $this->assertSame(
                [],
                array_values(array_unique($matches[0])),
                "{$name}.blade.php emits an inline event handler, which script-src without 'unsafe-inline' blocks."
            );
        }

        $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.popups.index'));
        $response->assertOk();
        $nonce = preg_match(
            "/'nonce-([^']+)'/",
            (string) $response->headers->get('Content-Security-Policy'),
            $m
        ) ? $m[1] : null;

        $this->assertNotNull($nonce, 'Expected a nonce in the CSP header.');
        $this->assertStringContainsString(
            '<script nonce="'.$nonce.'">',
            $response->getContent(),
            'The pushed script must carry the response nonce or it never executes.'
        );
    }
}
