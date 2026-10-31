<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Regression guard for the product create form.
 *
 * Two defects were reported together on /admin/products/create:
 *
 *  1. "Product description is required." appeared even though a description had
 *     been typed. Nothing was wrong server-side: the same POST body creates the
 *     product, and `old('description')` round-trips the text back into the
 *     textarea. CKEditor 4 replaces the <textarea name="description"> with its
 *     own widget and holds the typed content in memory, so if it does not copy
 *     that content back before the browser serialises the form, the field is
 *     simply absent from the POST and `required` rejects it. Six admin screens
 *     (product, category, page, blog post, brand) all called CKEDITOR.replace()
 *     with no sync; layouts/admin.blade.php now owns one delegated capture-phase
 *     submit listener for all of them.
 *
 *  2. The error was rendered twice — once by components/admin/alerts and again
 *     by a hand-rolled banner at the top of product/partials/form.blade.php,
 *     which is why the report showed "There is 1 problem with this form." twice.
 *     The same local banner existed in category/partials/form.blade.php.
 *
 * @see resources/views/layouts/admin.blade.php
 * @see resources/views/admin/product/partials/form.blade.php
 */
class AdminProductDescriptionWiringTest extends TestCase
{
    use RefreshDatabase;

    private const DESCRIPTION = '<p>Reconstituted CJC-1295, 2mg/vial, lyophilised.</p>';

    private function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'product-wiring@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        foreach (['products.view', 'products.create', 'products.update'] as $permission) {
            $role->permissions()->attach(
                Permission::create([
                    'name' => $permission,
                    'slug' => str_replace('.', '-', $permission),
                    'group_name' => 'products',
                ])->getKey()
            );
        }

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        $category = Category::create(['name' => 'Peptides', 'slug' => 'peptides-wiring']);

        return array_merge([
            'name' => 'CJC-1295 2mg',
            'slug' => 'cjc-1295-2mg',
            'sku' => 'CJC-2MG',
            'description' => self::DESCRIPTION,
            'base_price' => '49.99',
            'stock_status' => 'in_stock',
            'stock_quantity' => '10',
            'category_ids' => [$category->getKey()],
            // images is required|array|min:1; a fixture keeps these tests about
            // the description wiring rather than about file uploads.
            'images' => [UploadedFile::fake()->image('product.jpg')],
        ], $overrides);
    }

    public function test_a_submitted_description_is_accepted(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/products', $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Product::where('slug', 'cjc-1295-2mg')->count());
    }

    public function test_a_genuinely_absent_description_is_still_rejected(): void
    {
        $payload = $this->validPayload();
        unset($payload['description']);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/products', $payload)
            ->assertSessionHasErrors('description');

        $this->assertSame(0, Product::count());
    }

    public function test_a_blank_description_is_still_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/products', $this->validPayload(['description' => '']))
            ->assertSessionHasErrors('description');
    }

    public function test_the_submitted_description_is_persisted_through_the_safehtml_sanitiser(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/products', $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString(
            'CJC-1295',
            (string) Product::where('slug', 'cjc-1295-2mg')->value('description'),
            'The text CKEditor collected must reach the product, not just pass validation.'
        );
    }

    public function test_the_create_page_wires_ckeditor_up_with_a_description_field(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')
            ->get('/admin/products/create')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="description"', $html);
        $this->assertStringContainsString('ckeditor.js', $html);
    }

    public function test_every_admin_screen_syncs_ckeditor_back_into_its_textarea_on_submit(): void
    {
        $html = $this->actingAs($this->admin(), 'admin')
            ->get('/admin/products/create')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'updateElement',
            $html,
            'layouts.admin must sync every CKEditor instance into its textarea on submit, '
                .'otherwise a typed description is never POSTed.'
        );
        $this->assertStringContainsString(
            "addEventListener('submit'",
            $html,
            'The sync has to be bound to a submit event.'
        );
    }

    public function test_the_product_form_does_not_render_a_second_error_summary(): void
    {
        $payload = $this->validPayload();
        unset($payload['description']);

        $html = $this->actingAs($this->admin(), 'admin')
            ->from('/admin/products/create')
            ->followingRedirects()
            ->post('/admin/products', $payload)
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'with this form.'),
            'layouts.admin already renders the summary through components/admin/alerts; '
                .'product/partials/form.blade.php must not repeat it.'
        );
    }

    public function test_the_category_form_does_not_render_a_second_error_summary(): void
    {
        $html = $this->get('/admin/category/create')->getContent();

        $this->assertStringNotContainsString(
            'There is :count problem with this form.|There are :count problems with this form.',
            $html,
            'A translated placeholder leaking into the page means the local banner is still there.'
        );
    }

    public function test_the_error_summary_is_rendered_exactly_once_per_error(): void
    {
        $payload = $this->validPayload(['description' => '', 'base_price' => 'not-a-number']);
        unset($payload['slug']);

        $html = $this->actingAs($this->admin(), 'admin')
            ->from('/admin/products/create')
            ->followingRedirects()
            ->post('/admin/products', $payload)
            ->assertOk()
            ->getContent();

        // "problem"/"problems" is chosen by trans_choice, so count the stable
        // part of the sentence rather than one grammatical form of it.
        $this->assertSame(
            1,
            substr_count($html, 'with this form.'),
            'One summary block, no matter how many fields failed.'
        );
    }
}
