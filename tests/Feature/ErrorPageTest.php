<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Guards for the branded 404 / 405 pages.
 *
 * There was no resources/views/errors/ directory at all, so a bad address fell
 * through to Laravel's plain built-in view — and with APP_DEBUG=true on this
 * box, to the raw Symfony exception page, which shows a stack trace to the
 * person who mistyped a URL and still tells them nothing actionable.
 *
 * The registration in bootstrap/app.php deliberately takes precedence over the
 * debug renderer for these two statuses only. The behaviour these tests pin:
 *
 *  1. 405 names the verb and lists what the route accepts, taken from Symfony's
 *     Allow header.
 *  2. GET /admin/payment-methods/create explains that the catalog is fixed. The
 *     `create` segment falls through to the {paymentMethod} update route, which
 *     is why this is a 405 rather than a 404.
 *  3. JSON and /api/* clients fall through to Laravel's own machine-readable
 *     response instead of receiving an HTML page.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'errors-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $admin->assignRole(Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Test role',
        ])->name);

        return $admin;
    }

    public function test_payment_methods_create_returns_the_branded_405(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->get('/admin/payment-methods/create');

        $response->assertStatus(405);
        $response->assertSee('That action is not available here');
        $response->assertSee('GET');
        $response->assertSee('Nothing was changed.');
    }

    public function test_405_lists_the_verbs_the_route_accepts(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->get('/admin/payment-methods/create');

        // The Allow header must survive onto the response as well as the page.
        $response->assertHeader('Allow', 'PUT');
        $response->assertSee('PUT');
    }

    public function test_405_explains_that_payment_methods_are_a_fixed_set(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/payment-methods/create')
            ->assertSee('fixed set')
            ->assertSee('Cash on Delivery');
    }

    public function test_405_offers_a_working_route_back_to_the_listing(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/payment-methods/create')
            ->assertSee(route('admin.payment-methods.index'))
            ->assertSee(route('admin.dashboard'));
    }

    public function test_unknown_admin_page_returns_the_branded_404(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->get('/admin/this-does-not-exist');

        $response->assertStatus(404);
        $response->assertSee('We could not find that page');
        $response->assertSee('admin/this-does-not-exist');
    }

    public function test_unknown_storefront_page_returns_the_branded_404(): void
    {
        $response = $this->get('/no-such-storefront-page');

        $response->assertStatus(404);
        $response->assertSee('We could not find that page');
        // Storefront visitors get the shop, not the back office.
        $response->assertSee(route('home'));
        $response->assertDontSee(route('admin.dashboard'));
    }

    public function test_404_page_is_branded_and_carries_no_inline_script(): void
    {
        $response = $this->get('/no-such-storefront-page');

        $response->assertSee('fa-solid fa-house', escape: false);
        $this->assertNoInlineScript($response->getContent());
    }

    public function test_json_clients_still_receive_a_machine_readable_error(): void
    {
        // The branded pages are HTML only; an XHR must not be handed markup.
        $this->getJson('/no-such-storefront-page')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_api_routes_are_not_converted_to_html(): void
    {
        $this->get('/api/no-such-endpoint')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    /**
     * Registers a throwaway route that aborts, so the branded pages can be
     * exercised without inventing a permanent route for each status.
     */
    protected function abortRoute(string $uri, int $status, string $message = '', array $headers = []): void
    {
        Route::get($uri, fn () => abort($status, $message, $headers))->middleware('web');
    }

    protected function throwingRoute(string $uri, string $exceptionClass = RuntimeException::class): void
    {
        Route::get($uri, fn () => throw new $exceptionClass('SQLSTATE[42S02]: table `secret_table` missing'))->middleware('web');
    }

    /**
     * Asserts no inline <script> and no on* handler.
     *
     * An external <script src> is fine: CSP's script-src allows 'self'. The
     *
     * @vite and @fonts directives do emit those, so the check has to look at
     * the tag rather than the substring "<script".
     */
    protected function assertNoInlineScript(string $html): void
    {
        preg_match_all('/<script\b[^>]*>/i', $html, $tags);

        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('src=', $tag, "Inline script found: {$tag}");
            $this->assertStringNotContainsString('nonce=', $tag);
        }

        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('onload=', $html);
    }

    // ---------------------------------------------------------------- 403

    public function test_403_is_branded(): void
    {
        $this->abortRoute('/__err/403', 403);

        $response = $this->get('/__err/403');

        $response->assertStatus(403);
        $response->assertSee('You do not have access to this page');
        $response->assertSee('Nothing was changed.');
    }

    public function test_403_surfaces_the_missing_permission(): void
    {
        // The shape Controller::authorizeAdmin() aborts with.
        $this->abortRoute('/__err/403-perm', 403, "You do not have the 'orders.view' permission.");

        $this->get('/__err/403-perm')
            ->assertStatus(403)
            ->assertSee('orders.view')
            ->assertSee('Nothing was changed.');
    }

    public function test_403_ignores_an_unrecognised_message(): void
    {
        // An arbitrary message must never be echoed into the page, so a value
        // that is not our permission shape is dropped rather than surfaced.
        $this->abortRoute('/__err/403-odd', 403, 'Postmark <token> rejected by upstream relay 10.4.2.9');

        $this->get('/__err/403-odd')
            ->assertStatus(403)
            ->assertDontSee('Postmark <token>')
            ->assertDontSee('10.4.2.9')
            ->assertSee('Nothing was changed.');
    }

    public function test_real_authorization_failure_reaches_the_branded_403(): void
    {
        // orders.view is not granted here, so OrderController::index aborts(403).
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/orders')
            ->assertStatus(403)
            ->assertSee('You do not have access to this page')
            ->assertSee('orders.view');
    }

    // ---------------------------------------------------------------- 419

    public function test_419_is_branded_and_offers_a_reload(): void
    {
        $this->abortRoute('/__err/419', 419);

        $this->get('/__err/419')
            ->assertStatus(419)
            ->assertSee('Your session expired')
            ->assertSee('Nothing was changed.')
            ->assertSee('data-action="reload"', escape: false);
    }

    public function test_419_reload_handler_is_in_its_own_bundle(): void
    {
        // The button only works if the bundle behind it exists and does not
        // drag in the app bundle (which boots cart/admin handlers in vain).
        $this->abortRoute('/__err/419-bundle', 419);

        $html = $this->get('/__err/419-bundle')->assertStatus(419)->getContent();

        $this->assertMatchesRegularExpression('/error-page-[^"]+\.js/', $html);
        // The app bundle boots cart and admin handlers; loading it on an error
        // page would be waste. Note the CSS *is* app-*.css, so match the JS.
        $this->assertDoesNotMatchRegularExpression('/app-[^"]+\.js/', $html);
        $this->assertNoInlineScript($html);
    }

    // ---------------------------------------------------------------- 429

    public function test_429_is_branded_and_shows_the_retry_delay(): void
    {
        $this->abortRoute('/__err/429', 429, 'Too Many Attempts.', ['Retry-After' => 30]);

        $response = $this->get('/__err/429');

        $response->assertStatus(429);
        $response->assertHeader('Retry-After', '30');
        $response->assertSee('Too many requests');
        $response->assertSee('30 seconds');
        $response->assertSee('Wait about 30 seconds');
    }

    public function test_429_json_carries_the_retry_delay(): void
    {
        $this->abortRoute('/__err/429-json', 429, 'Too Many Attempts.', ['Retry-After' => 30]);

        $this->getJson('/__err/429-json')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('retry_after', 30);
    }

    // ---------------------------------------------------------------- 401 / 503 / generic

    public function test_401_is_branded_and_links_to_the_admin_login(): void
    {
        $this->abortRoute('/admin/__err/401', 401);

        $this->get('/admin/__err/401')
            ->assertStatus(401)
            ->assertSee('Please sign in')
            ->assertSee(route('admin.login'));
    }

    public function test_503_is_branded(): void
    {
        $this->abortRoute('/__err/503', 503);

        $this->get('/__err/503')
            ->assertStatus(503)
            ->assertSee('Temporarily unavailable')
            ->assertSee('Nothing was lost.');
    }

    public function test_a_status_with_no_dedicated_page_falls_back_to_the_generic_view(): void
    {
        $this->abortRoute('/__err/409', 409, 'Conflict.');

        $this->get('/__err/409')
            ->assertStatus(409)
            ->assertSee('That request could not be completed')
            ->assertSee('Nothing was changed.');
    }

    // ---------------------------------------------------------------- 500

    public function test_500_is_branded_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        $this->throwingRoute('/__err/boom');

        $response = $this->get('/__err/boom');

        $response->assertStatus(500);
        $response->assertSee('Something went wrong on our side');
        $response->assertSee('nothing was saved');
    }

    public function test_500_never_leaks_the_exception_message(): void
    {
        config(['app.debug' => false]);
        $this->throwingRoute('/__err/boom-leak');

        $response = $this->get('/__err/boom-leak');

        $response->assertStatus(500);
        $response->assertDontSee('secret_table');
        $response->assertDontSee('SQLSTATE');
    }

    public function test_500_keeps_the_debug_trace_when_debug_is_on(): void
    {
        // The important one: branding 500 must not cost developers the trace.
        config(['app.debug' => true]);
        $this->throwingRoute('/__err/boom-debug');

        $response = $this->get('/__err/boom-debug');

        $response->assertStatus(500);

        // The trace, the exception class and the message all have to survive.
        // Note Laravel embeds the branded card *inside* its debug page rather
        // than replacing it, so the assertion is "the trace is there", not
        // "the branded card is gone".
        $response->assertSee('secret_table');
        $response->assertSee('RuntimeException');
    }

    // ---------------------------------------------------------------- shared

    public function test_branded_pages_never_carry_an_inline_script(): void
    {
        // CSP: script-src has a nonce and no 'unsafe-inline'.
        foreach ([401, 403, 419, 429, 503] as $status) {
            $this->abortRoute('/__err/nojs-'.$status, $status);

            $html = $this->get('/__err/nojs-'.$status)->assertStatus($status)->getContent();

            $this->assertNoInlineScript($html);
        }
    }

    public function test_branded_json_errors_keep_the_status_and_path(): void
    {
        $this->abortRoute('/__err/json', 403, 'Forbidden.');

        $this->getJson('/__err/json')
            ->assertStatus(403)
            ->assertJsonPath('path', '/__err/json');
    }
}
