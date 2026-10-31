<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends PrefixedModel
{
    use HasFactory;

    protected $table = 'permissions';

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'group_name',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Permission $permission) {
            $permission->roles()->detach();
        });
    }

    protected function getIdPrefix(): string
    {
        return 'per';
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission', 'permission_id', 'role_id');
    }
}
