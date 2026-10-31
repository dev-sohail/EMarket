<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Regression guard for back office error messaging.
 *
 * The admin login view wrapped every message in a single `@if($errors->any())`.
 * The login controller reports bad credentials with ->with('error', ...) and no
 * validation errors, so $errors was empty, the whole block was skipped, and
 * "Invalid email or password" was never rendered. A failed login re-rendered a
 * blank form with no explanation at all.
 *
 * The three message sources are now rendered independently by a single shared
 * partial, components/admin/alerts.blade.php, used by both admin layouts.
 */
class AdminErrorMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(array $attributes = []): User
    {
        $admin = User::factory()->create(array_merge([
            'email' => 'error-msg-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ], $attributes));

        Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        return $admin;
    }

    /**
     * A wrong password produces no validation errors, only session('error').
     * This is the case that was invisible.
     */
    public function test_failed_login_shows_the_error_message(): void
    {
        $this->admin();

        $this->get('/admin/login')->assertOk();

        $response = $this->from('/admin/login')
            ->post('/admin/login', [
                '_token' => session()->token(),
                'email' => 'error-msg-admin@example.test',
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHas('error', 'Invalid email or password.');

        $html = $this->get('/admin/login')->getContent();

        $this->assertStringContainsString(
            'Invalid email or password.',
            $html,
            'The credential failure message is not visible. session(\'error\') must render '
            .'independently of $errors - there are no validation errors in this case.'
        );

        $this->assertStringContainsString(
            'role="alert"',
            $html,
            'The message must be announced to assistive technology.'
        );

        $this->assertGuest('admin');
    }

    public function test_validation_errors_are_listed_with_a_count_and_the_email_is_retained(): void
    {
        $this->get('/admin/login')->assertOk();

        $this->from('/admin/login')->post('/admin/login', [
            '_token' => session()->token(),
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $html = $this->get('/admin/login')->getContent();

        $this->assertStringContainsString('The email field must be a valid email address.', $html);
        $this->assertStringContainsString('The password field is required.', $html);

        // The count line makes the scope of the failure obvious at a glance.
        $this->assertMatchesRegularExpression(
            '/There (?:is 1 problem|are 2 problems) with this form/',
            $html,
            'Expected a pluralised summary line above the error list.'
        );

        // Repopulating the field saves the user retyping after a typo.
        $this->assertStringContainsString('value="not-an-email"', $html);
    }

    public function test_deactivated_account_message_is_visible(): void
    {
        $this->admin([
            'email' => 'deactivated-admin@example.test',
            'is_active' => false,
        ]);

        $this->get('/admin/login')->assertOk();

        $this->from('/admin/login')->post('/admin/login', [
            '_token' => session()->token(),
            'email' => 'deactivated-admin@example.test',
            'password' => 'secret-password',
        ]);

        $this->assertGuest('admin');

        $this->assertStringContainsString(
            'Your account has been deactivated.',
            $this->get('/admin/login')->getContent()
        );
    }

    public function test_successful_login_shows_the_welcome_message(): void
    {
        $this->admin();

        $this->get('/admin/login')->assertOk();

        $this->post('/admin/login', [
            '_token' => session()->token(),
            'email' => 'error-msg-admin@example.test',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertStringContainsString(
            'Welcome back, Admin!',
            $this->get('/admin')->getContent(),
            'The success flash must render on the dashboard through the admin layout.'
        );
    }

    /**
     * The authenticated admin layout must keep rendering messages too; the
     * shared partial replaced a working block there and must not regress it.
     */
    public function test_authenticated_admin_layout_renders_flash_messages(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin')->assertOk();

        $this->withSession(['error' => 'Could not save the order.']);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Could not save the order.');
    }

    public function test_alerts_partial_renders_all_three_sources_together(): void
    {
        // The old nested block could not produce this combination: it required
        // $errors to be non-empty before it would render session('error').
        $this->withSession([
            'success' => 'Saved successfully.',
            'error' => 'Something went wrong.',
        ]);

        $html = view('components.admin.alerts', [
            'errors' => (new ViewErrorBag)->put(
                'default',
                new MessageBag(['email' => ['The email field is required.']])
            ),
        ])->render();

        $this->assertStringContainsString('Saved successfully.', $html);
        $this->assertStringContainsString('Something went wrong.', $html);
        $this->assertStringContainsString('The email field is required.', $html);
        $this->assertStringContainsString('There is 1 problem with this form', $html);

        // Two independent alerts plus the validation list, never nested.
        $this->assertSame(3, substr_count($html, 'role="alert"'));
    }

    public function test_alerts_partial_renders_nothing_when_there_is_nothing_to_say(): void
    {
        $html = view('components.admin.alerts', ['errors' => new ViewErrorBag])->render();

        $this->assertStringNotContainsString('role="alert"', $html);
    }
}
