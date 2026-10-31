<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Content Security Policy regression guard.
 *
 * The storefront and admin render a lot of behaviour through inline <script>
 * blocks and element attributes. Both are silently blocked by the script-src
 * policy in SecurityHeadersMiddleware, so the failure mode is "the page loads
 * and the button does nothing" — no exception, no failed test, just dead UI.
 *
 * These tests make that failure loud. Every inline script must carry the
 * per-request nonce from the response header, and no element may carry an
 * inline on* handler, because the policy allows neither without 'unsafe-inline'.
 *
 * Behaviour that used to live in an on* attribute is expected to be expressed
 * as data attributes handled by the delegated listeners in resources/js/app.js.
 */
class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Inline event handler attributes. `onload`/`onerror` on a <body>/<img> is
     * covered too; none are permitted under this policy.
     */
    private const INLINE_HANDLER_PATTERN = '/\son[a-z]+\s*=\s*["\']/i';

    private const SCRIPT_TAG_PATTERN = '/<script\b[^>]*>/i';

    /**
     * Pages that historically carried the most inline behaviour. Each is
     * requested as a guest, which is the state where unauthenticated visitors
     * actually meet these screens.
     */
    public static function publicPageProvider(): array
    {
        return [
            'home' => ['/'],
            'shop' => ['/shop'],
            'cart' => ['/cart'],
            'login' => ['/login'],
            'register' => ['/register'],
        ];
    }

    #[DataProvider('publicPageProvider')]
    public function test_pages_emit_no_inline_event_handlers(string $uri): void
    {
        $response = $this->get($uri);

        $response->assertOk();

        $matches = [];
        preg_match_all(self::INLINE_HANDLER_PATTERN, $response->getContent(), $matches);

        $this->assertSame(
            [],
            array_values(array_unique($matches[0])),
            "Inline event handlers are blocked by script-src without 'unsafe-inline'. "
            ."Move the behaviour to a data-* attribute handled by resources/js/app.js. Found on {$uri}."
        );
    }

    #[DataProvider('publicPageProvider')]
    public function test_every_inline_script_carries_the_response_nonce(string $uri): void
    {
        $response = $this->get($uri);

        $response->assertOk();

        $nonce = $this->nonceFromResponse($response);

        $this->assertNotNull($nonce, "No script-src nonce found in the CSP header for {$uri}.");

        preg_match_all(self::SCRIPT_TAG_PATTERN, $response->getContent(), $matches);

        $offenders = [];

        foreach ($matches[0] as $tag) {
            // Scripts with a src are fetched under 'self'/allow-listed CDNs and
            // are not subject to the nonce requirement.
            if (stripos($tag, ' src=') !== false) {
                continue;
            }

            if (strpos($tag, 'nonce=') === false) {
                $offenders[] = $tag;

                continue;
            }

            // The nonce in the body must equal the one in the header, otherwise
            // the browser rejects the block even though the attribute is present.
            if (strpos($tag, 'nonce="'.$nonce.'"') === false) {
                $offenders[] = $tag;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Inline <script> blocks must use exactly the nonce from the CSP header. Offending tags on {$uri}."
        );
    }

    public function test_script_src_policy_never_permits_unsafe_inline_or_unsafe_eval(): void
    {
        $response = $this->get('/shop');

        $response->assertOk();

        $directive = $this->scriptSrcDirective($response->headers->get('Content-Security-Policy'));

        $this->assertNotNull($directive, 'Response is missing a script-src directive.');

        $this->assertStringNotContainsString(
            "'unsafe-inline'",
            $directive,
            "script-src must not allow 'unsafe-inline': it would silently re-enable every inline script this suite forbids."
        );

        $this->assertStringNotContainsString(
            "'unsafe-eval'",
            $directive,
            "script-src must not allow 'unsafe-eval': CSP-safe Alpine evaluates expressions with an interpreter, so nothing should need it."
        );
    }

    /**
     * A stale cached view is the most likely way a fixed template silently
     * returns to its old form in development.
     */
    public function test_cached_views_do_not_reintroduce_inline_handlers(): void
    {
        $this->artisan('view:clear')->assertExitCode(0);

        $response = $this->get('/shop');

        $response->assertOk();

        $this->assertDoesNotMatchRegularExpression(
            self::INLINE_HANDLER_PATTERN,
            $response->getContent(),
            'Inline handlers reappeared after clearing the view cache.'
        );
    }

    /**
     * The cart quantity controls were the densest cluster of argument-passing
     * inline handlers. They are now data-attribute driven, which is only correct
     * if the surrounding page still renders them and the page functions the
     * dispatcher calls are actually defined.
     */
    public function test_cart_quantity_controls_are_dispatchable(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->getKey()]);
        $product = Product::factory()->create(['is_active' => true]);

        // CartController resolves the cart via auth()->user()->customer, so the
        // cart must hang off a Customer record rather than the User directly.
        $cart = Cart::create(['customer_id' => $customer->getKey()]);

        CartItem::create([
            'cart_id' => $cart->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 2,
            'unit_price' => $product->base_price,
        ]);

        $this->assertSame(
            1,
            CartItem::where('cart_id', $cart->getKey())->count(),
            'Fixture cart item did not persist; the assertions below would be vacuous.'
        );

        $response = $this->actingAs($user)->get('/cart');

        $response->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString(
            '2 items in your cart',
            $content,
            'Cart page did not render the fixture item, so the dispatch assertions below would be vacuous.'
        );

        $this->assertMatchesRegularExpression(
            '/data-action="updateQuantity"\s+data-args="\[/', $content,
            'Cart quantity buttons must dispatch updateQuantity through data-args.'
        );

        $this->assertMatchesRegularExpression(
            '/data-action="removeFromCart"/', $content,
            'Cart remove control must dispatch removeFromCart through a data attribute.'
        );

        foreach (['updateQuantity', 'removeFromCart'] as $function) {
            $this->assertStringContainsString(
                "function {$function}(",
                $content,
                "The dispatcher calls {$function}(), but the page never defines it."
            );
        }

        // The attributes above must survive HTML entity decoding as valid JSON.
        // dispatchAction() in resources/js/app.js JSON.parses data-args and
        // returns silently on a parse failure, so a malformed value produces a
        // control that looks wired up and does nothing at all. This is the
        // single most likely way the data-attribute migration goes wrong, and
        // it is invisible without an assertion like this one.
        preg_match_all('/data-args="([^"]*)"/', $content, $matches);

        $this->assertNotEmpty($matches[1], 'Expected at least one data-args attribute on the cart page.');

        foreach (array_unique($matches[1]) as $raw) {
            // The browser resolves &quot; to " before the value reaches
            // JSON.parse, so decode the way the DOM would.
            $decoded = html_entity_decode($raw, ENT_QUOTES);

            json_decode($decoded);

            $this->assertSame(
                JSON_ERROR_NONE,
                json_last_error(),
                "data-args=\"{$raw}\" is not valid JSON once the browser decodes it "
                .'(decoded: '.$decoded.'): '.json_last_error_msg()
                .'. Single-quoted strings are the usual cause; JSON requires double quotes.'
            );
        }
    }

    private function nonceFromResponse($response): ?string
    {
        $header = $response->headers->get('Content-Security-Policy');

        if (! $header) {
            return null;
        }

        return preg_match("/'nonce-([^']+)'/", $header, $matches) === 1 ? $matches[1] : null;
    }

    private function scriptSrcDirective(?string $header): ?string
    {
        if (! $header) {
            return null;
        }

        foreach (explode(';', $header) as $part) {
            if (str_starts_with(trim($part), 'script-src')) {
                return trim($part);
            }
        }

        return null;
    }
}
