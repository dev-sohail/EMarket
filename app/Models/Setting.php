<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends PrefixedModel
{
    use HasFactory;

    protected $table = 'settings';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'group',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            // `json`, not `mixed`. There is no `mixed` primitive cast in this
            // framework version, so declaring one made Eloquent throw
            // "Call to undefined cast [mixed]" on every read — which the
            // `setting()` helper swallowed, hiding it behind the missing table.
            'value' => 'json',
        ];
    }

    protected function getIdPrefix(): string
    {
        return 'set';
    }

    public static function getGroup(string $group): array
    {
        return self::query()
            ->where('group', $group)
            ->get()
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * Upsert a setting.
     *
     * The lookup matches on `key` alone, because that is the only column the
     * `settings_key_unique` index actually covers. Passing `group` in the
     * search attributes — the obvious `updateOrCreate(['group' => …, 'key' =>
     * …], …)` — is a latent 500: whenever the stored group differs from the
     * group the caller expects, the search misses and Eloquent falls back to an
     * INSERT that violates the unique index. That is how saving the admin
     * settings screen died on `Duplicate entry 'timezone'`: the seeder stored
     * `timezone` under group `timezone` while the controller looked for group
     * `general`.
     *
     * `group` is written as a value instead, so saving a setting also
     * re-normalises a row whose group was set by an older seeder.
     */
    public static function put(string $key, mixed $value, string $group = 'general'): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['group' => $group, 'value' => $value]
        );
    }
}
