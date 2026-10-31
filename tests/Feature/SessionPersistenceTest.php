<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression guard for silent session loss.
 *
 * `sessions.id` was created with `$table->id()` (auto-incrementing BIGINT)
 * while Laravel's database session driver keys sessions by a 40 character
 * random string. Every session INSERT failed, so no session ever persisted,
 * a new CSRF token was minted on every request, and every POST returned
 * 419 Page Expired - admin login included.
 *
 * Nothing threw and every page still rendered 200, so the only symptom was
 * "login does nothing". These tests fail loudly if the column type regresses
 * or if a POST stops being able to carry a valid token.
 */
class SessionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_table_uses_a_string_primary_key(): void
    {
        $this->assertTrue(
            Schema::hasTable('sessions'),
            'The sessions table is missing while SESSION_DRIVER=database.'
        );

        // sqlite reports "string" and MySQL reports "varchar" for the same column.
        $this->assertContains(
            Schema::getColumnType('sessions', 'id'),
            ['string', 'varchar'],
            'sessions.id must be a string column. Laravel writes the 40 character '
            .'session id into it; an integer/auto-increment column makes every '
            .'session write fail silently and breaks all POST requests.'
        );
    }

    public function test_a_session_row_is_actually_written(): void
    {
        DB::table('sessions')->insert([
            'id' => 'test-session-id-abcdefghij',
            'payload' => 'a:1:{s:5:"_token";s:5:"dummy";}',
            'last_activity' => time(),
        ]);

        $this->assertSame(
            1,
            DB::table('sessions')->where('id', 'test-session-id-abcdefghij')->count(),
            'A string session id could not be stored. The sessions table still '
            .'rejects the values the session driver writes.'
        );
    }

    /**
     * The behavioural half of the guard: a POST with a token minted by a prior
     * request must be accepted. This is the exact shape of the admin login
     * form and it is what silently stopped working.
     */
    public function test_posted_csrf_token_from_a_previous_request_is_accepted(): void
    {
        $this->get('/admin/login')->assertOk();

        $response = $this->post('/admin/login', [
            '_token' => session()->token(),
            'email' => 'nobody@example.test',
            'password' => 'wrong-password',
        ]);

        $this->assertNotSame(
            419,
            $response->getStatusCode(),
            'The POST was rejected as a CSRF failure (419), which means the '
            .'session did not survive between the form render and the submit.'
        );
    }

    public function test_admin_can_log_in_and_reach_the_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-login-test@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        // AdminMiddleware requires at least one role before it will let anyone
        // past the back office, so a role is part of a realistic admin.
        Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        $this->get('/admin/login')->assertOk();

        $response = $this->post('/admin/login', [
            '_token' => session()->token(),
            'email' => $admin->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');

        $this->get('/admin')->assertOk();
    }

    public function test_inactive_admin_is_rejected(): void
    {
        $inactive = User::factory()->create([
            'email' => 'inactive-admin@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => false,
        ]);

        $this->get('/admin/login')->assertOk();

        $this->post('/admin/login', [
            '_token' => session()->token(),
            'email' => $inactive->email,
            'password' => 'secret-password',
        ]);

        $this->assertGuest('admin');
    }
}
