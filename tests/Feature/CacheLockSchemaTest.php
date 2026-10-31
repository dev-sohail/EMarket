<?php

namespace Tests\Feature;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The database cache store doubles as the checkout mutex (CheckoutService) and
 * the Stripe webhook mutex (StripeController), so a broken `cache_locks` table
 * does not fail loudly: `Cache::lock()` simply never acquires, `block(30)` burns
 * its timeout and the customer gets the generic "We encountered an error while
 * processing your order" page.
 *
 * That is exactly what happened. The table shipped with a stray `owner_id`
 * column (NOT NULL, no default) from the pre-prefixed-key shape, while
 * `Illuminate\Cache\DatabaseLock` inserts `owner`. The insert was rejected under
 * STRICT_TRANS_TABLES, the fallback update matched no row, and the lock was
 * never handed out. Nothing asserted that the table matched what the framework
 * writes, so the hole stayed open for every order on every free cart.
 */
class CacheLockSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cache_locks_table_has_the_columns_the_framework_writes(): void
    {
        $this->assertTrue(Schema::hasTable('cache_locks'));

        $this->assertTrue(
            Schema::hasColumn('cache_locks', 'owner'),
            'Illuminate\Cache\DatabaseLock writes the `owner` column on every acquire(); '
            .'without it no lock can ever be taken.',
        );

        $this->assertFalse(
            Schema::hasColumn('cache_locks', 'owner_id'),
            '`owner_id` is NOT NULL without a default, so it rejects the framework insert '
            .'and strands the lock as permanently unavailable.',
        );
    }

    /**
     * The suite runs on CACHE_STORE=array, which has working in-process locks and
     * therefore hides a broken `cache_locks` table — the production store is
     * `database`. Pin the database store explicitly, so this test exercises the
     * same code path (DatabaseLock) that checkout and the Stripe webhook use.
     */
    public function test_a_cache_lock_can_be_acquired_and_released(): void
    {
        $store = Cache::store('database');

        $this->assertInstanceOf(
            DatabaseStore::class,
            $store->getStore(),
            'This test must run against the database store, not the in-process array store.',
        );

        $lock = $store->lock('checkout:cart:lock-schema-test', 60);

        $this->assertTrue(
            $lock->get(),
            'The checkout mutex must be acquirable on a free cart, otherwise every order fails.',
        );

        $this->assertFalse(
            $store->lock('checkout:cart:lock-schema-test', 60)->get(),
            'A held lock must not be handed to a second caller.',
        );

        $this->assertTrue($lock->release());
    }
}
