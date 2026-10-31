<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_methods.stripe_secret_key is declared with the model's `encrypted`
 * cast, but the column is char(255) — sized for the plaintext key, not for what
 * Laravel actually writes.
 *
 * The cast base64-encodes a JSON envelope of {iv, value, mac, tag}. A 107-char
 * Stripe test key produced a 372-char payload, so saving the key in the admin
 * raised:
 *
 *   SQLSTATE[22001]: String data, right truncated: 1406 Data too long for
 *   column 'stripe_secret_key'
 *
 * under STRICT_TRANS_TABLES, i.e. a 500 on every attempt to enable Stripe.
 * The gap widens with the secret: the envelope is roughly 1.4x the plaintext
 * plus ~150 bytes, so even the 255-char input cap in PaymentMethodController's
 * validation could never fit. Validating the plaintext against the column's
 * length is the mistake — the two measure different things.
 *
 * text is the column type Laravel's encrypted casts are documented for, and it
 * leaves room for live keys, which are longer than test keys. The column is
 * widened, not narrowed, so no existing row is affected.
 *
 * stripe_publishable_key stays char(255): it is stored in plaintext, so the
 * validated input length and the column length do agree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->text('stripe_secret_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Truncating ciphertext cannot be reversed, so this is only safe while
        // no secret is stored. The controller refuses to enable Stripe without
        // both keys, so an in-use row cannot be silently shortened.
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->char('stripe_secret_key', 255)->nullable()->change();
        });
    }
};
