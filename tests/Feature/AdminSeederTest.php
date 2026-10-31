<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_seeder_creates_a_super_admin_on_an_empty_database(): void
    {
        config(['admin.seed_password' => 'a-long-secret-pass']);

        (new AdminSeeder)->run();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('a-long-secret-pass', $admin->password));
        $this->assertTrue($admin->hasRole('Super Admin'));
    }

    public function test_the_admin_seeder_is_re_runnable(): void
    {
        config(['admin.seed_password' => 'first-secret-pass']);
        (new AdminSeeder)->run();

        config(['admin.seed_password' => 'second-secret-pass']);
        (new AdminSeeder)->run();

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
        $this->assertTrue(Hash::check('second-secret-pass', User::where('email', 'admin@example.com')->first()->password));
    }

    public function test_the_admin_seeder_refuses_a_blank_password(): void
    {
        config(['admin.seed_password' => '']);

        $this->expectException(\RuntimeException::class);

        (new AdminSeeder)->run();
    }
}
