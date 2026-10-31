<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression guard for the settings identity rule: `settings.key` is globally
 * unique, `settings.group` is not part of any unique index.
 *
 * The migration says so explicitly — `setting()` resolves a value with
 * `pluck('value', 'key')`, which keeps only the last row for a duplicated key —
 * so `key` alone is a setting's identity and `group` is a display label.
 *
 * The trap is that the obvious write, `updateOrCreate(['group' => …, 'key' =>
 * …], …)`, searches on both. Whenever the stored group differs from the group
 * the caller expects, that search misses, Eloquent falls back to an INSERT, and
 * the unique index rejects it. Saving the admin settings screen 500'd with
 * `Duplicate entry 'timezone' for key 'settings_key_unique'` because the seeder
 * stored `timezone` under group `timezone` while SettingController looked for
 * group `general`. `returns_enabled` was seeded under group `return` and looked
 * up as `review` — the next row to have blown up.
 *
 * @see Setting::put()
 */
class SettingKeyIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create([
            'email' => 'settings-identity@example.test',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin', 'description' => 'Test role']);
        $admin->assignRole('Admin');

        $role->permissions()->attach(
            Permission::create([
                'name' => 'settings.update',
                'slug' => 'settings-update',
                'group_name' => 'settings',
            ])->getKey()
        );

        return $admin;
    }

    public function test_key_is_the_only_unique_column(): void
    {
        $indexes = collect(Schema::getIndexes('settings'))->keyBy('name');

        $this->assertSame(
            ['key'],
            $indexes['settings_key_unique']['columns'],
            '`key` alone is a setting\'s identity, so that unique index must not gain a `group` column.'
        );

        $this->assertTrue(
            $indexes['settings_key_unique']['unique'],
            'settings_key_unique must stay unique.'
        );

        $this->assertFalse(
            $indexes['settings_group_index']['unique'],
            '`group` is a display label, not part of the identity. If this ever becomes unique, '
                .'re-check whether (group, key) is the intended identity and fix every write path at once.'
        );
    }

    public function test_put_updates_a_row_whose_group_disagrees_instead_of_inserting(): void
    {
        Setting::create(['group' => 'timezone', 'key' => 'timezone', 'value' => 'UTC']);

        Setting::put('timezone', 'Europe/Paris', 'general');

        $this->assertSame(1, Setting::where('key', 'timezone')->count());
        $this->assertSame('Europe/Paris', Setting::where('key', 'timezone')->value('value'));
        $this->assertSame(
            'general',
            Setting::where('key', 'timezone')->value('group'),
            'Saving must re-normalise a group left behind by an older seeder.'
        );
    }

    public function test_saving_settings_survives_a_group_mismatch(): void
    {
        $this->seedSetting(['group' => 'timezone', 'key' => 'timezone', 'value' => 'UTC']);
        $this->seedSetting(['group' => 'return', 'key' => 'returns_enabled', 'value' => '0']);

        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/settings', [
                'store_name' => 'Regression Shop',
                'timezone' => 'Europe/Paris',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Europe/Paris', Setting::where('key', 'timezone')->value('value'));
        $this->assertSame('Regression Shop', Setting::where('key', 'store_name')->value('value'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function mismatchedGroupProvider(): array
    {
        // [key, the stale group an older seeder left behind, value the form posts]
        return [
            'timezone' => ['timezone', 'timezone', 'Europe/Paris'],
            'returns_enabled' => ['returns_enabled', 'return', '1'],
            'guest_checkout' => ['guest_checkout', 'checkout-old', '1'],
            'affiliate_enabled' => ['affiliate_enabled', 'affiliate-old', '0'],
            'low_stock_threshold' => ['low_stock_threshold', 'inventory-old', '7'],
        ];
    }

    /**
     * Every key the settings screen writes, seeded under a deliberately wrong
     * group. Any of these used to be a 500 the moment an operator hit Save.
     */
    #[DataProvider('mismatchedGroupProvider')]
    public function test_every_managed_key_saves_despite_a_stale_group(
        string $key,
        string $staleGroup,
        string $postedValue,
    ): void {
        $this->seedSetting(['group' => $staleGroup, 'key' => $key, 'value' => 'stale']);

        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/settings', [$key => $postedValue])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Setting::where('key', $key)->count(), "{$key} must not have been duplicated.");
        $this->assertSame($postedValue, Setting::where('key', $key)->value('value'));
    }

    public function test_repeated_saves_never_duplicate_a_row(): void
    {
        $admin = $this->actingAs($this->admin(), 'admin');

        $admin->put('/admin/settings', ['timezone' => 'Europe/Paris'])->assertRedirect();

        $after = Setting::count();

        foreach (range(1, 3) as $attempt) {
            $admin->put('/admin/settings', ['timezone' => 'Europe/Paris'])->assertRedirect();
        }

        $this->assertSame(
            $after,
            Setting::count(),
            'Saving the settings screen repeatedly must not add rows.'
        );
        $this->assertSame(1, Setting::where('key', 'timezone')->count());
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function seedSetting(array $attributes): Setting
    {
        return Setting::create($attributes + ['value' => '']);
    }
}
