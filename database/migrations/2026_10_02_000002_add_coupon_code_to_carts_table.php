<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CheckoutController reads $cart->coupon_code on GET and clears it with
 * $cart->update(['coupon_code' => null]) when a code no longer validates, but
 * `carts` had no such column and Cart::$fillable never listed it. Both were
 * silent no-ops: re-opening the checkout page always showed a zero discount, so
 * a customer who had already applied a coupon had to retype it. The column makes
 * the intent work — the cart remembers the code it last accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->string('coupon_code', 50)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('coupon_code');
        });
    }
};
