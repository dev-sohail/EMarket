<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression guard for the width of an `encrypted` cast column.
 *
 * `PaymentMethod::$casts` declares `stripe_secret_key` as `encrypted`, but the
 * column was `char(255)` — sized for the plaintext key rather than for the
 * ciphertext Laravel actually writes. The cast base64-encodes a JSON envelope of
 * {iv, value, mac, tag}, so a 107-character Stripe test key produced 372
 * characters of ciphertext and every attempt to enable Stripe in the admin
 * failed with:
 *
 *   SQLSTATE[22001]: String data, right truncated: 1406 Data too long for
 *   column 'stripe_secret_key'
 *
 * Under STRICT_TRANS_TABLES that is a hard 500 on
 * PUT /admin/payment-methods/{id}.
 *
 * MySQL-specific column assertions inspect the relevant migration source rather
 * than SQLite's schema (which does not preserve MySQL's char width) or a live
 * database connection. The repository's MariaDB tooling cannot safely generate
 * the schema dump, so the migration is the maintainable source of truth here.
 *
 * @see database/migrations/2026_10_03_000001_widen_payment_methods_stripe_secret_key.php
 */
class EncryptedColumnWidthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A synthetic stand-in for a long Stripe key, built rather than pasted.
     *
     * It used to be a real-looking `sk_test_51S2X6...` string of exactly the
     * modern restricted-key length. That format is indistinguishable from a
     * credential: a committed key of that shape has to be treated as leaked and
     * rotated even when it came from a test fixture, and it puts a secret-shaped
     * string through every secret scanner that ever touches this repo.
     *
     * The length is all this test actually depends on — the assertion is that
     * the AES-256-CBC envelope pushes the stored value past char(255). Building
     * the string keeps that guarantee while removing anything that could be
     * mistaken for, or reused as, a real key.
     */
    private const KEY_LENGTH = 129;

    /**
     * The synthetic long key. A class constant cannot call str_repeat(), so this
     * is a static helper rather than a const; every test that needs the value
     * calls longTestKey().
     */
    protected static function longTestKey(): string
    {
        return 'sk_test_'.str_repeat('a', self::KEY_LENGTH - 8);
    }

    public function test_a_realistic_stripe_key_still_overflows_char_255(): void
    {
        // Guards the guard: if a future Stripe key format ever fits in 255
        // characters, the assertion below stops being meaningful and this fails
        // so the test is revisited rather than silently passing for the wrong
        // reason.
        $this->assertGreaterThan(
            255,
            $this->ciphertextLengthFor(self::longTestKey()),
            'This Stripe key no longer overflows char(255); re-check whether the column still needs text.'
        );
    }

    public function test_the_mysql_migration_widens_only_the_encrypted_column(): void
    {
        $migration = (string) file_get_contents(
            __DIR__.'/../../database/migrations/2026_10_03_000001_widen_payment_methods_stripe_secret_key.php'
        );

        $this->assertStringContainsString(
            '$table->text(\'stripe_secret_key\')->nullable()->change()',
            $migration,
            'The encrypted secret must use a text column to fit Laravel ciphertext.'
        );
        $this->assertStringNotContainsString('text(\'stripe_publishable_key\')', $migration);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function keyProvider(): array
    {
        return [
            'test key' => [self::longTestKey()],
            'longer live-style key' => ['sk_live_'.str_repeat('a', 120)],
        ];
    }

    #[DataProvider('keyProvider')]
    public function test_no_realistic_key_overflows_the_column(string $key): void
    {
        $length = $this->ciphertextLengthFor($key);

        $this->assertLessThan(
            65535,
            $length,
            'text holds 65,535 bytes; the encryption envelope can never come close.'
        );
    }

    public function test_a_stripe_secret_key_round_trips_through_the_model(): void
    {
        $method = PaymentMethod::create([
            'name' => 'Online',
            'code' => 'online',
            'description' => 'Pay securely online with Stripe.',
            'is_active' => true,
            'sort_order' => 1,
            'stripe_secret_key' => self::longTestKey(),
        ]);

        $stored = PaymentMethod::find($method->getKey());

        $this->assertSame(self::longTestKey(), $stored->stripe_secret_key);
        $this->assertNotSame(
            self::longTestKey(),
            $stored->getRawOriginal('stripe_secret_key'),
            'The secret must be stored encrypted, never in plaintext.'
        );
    }

    private function ciphertextLengthFor(string $plaintext): int
    {
        return strlen((new Encrypter(
            hash('sha256', 'encrypted-column-width-test', true),
            'AES-256-CBC',
        ))->encryptString($plaintext));
    }
}
